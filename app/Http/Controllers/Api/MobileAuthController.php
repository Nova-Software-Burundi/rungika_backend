<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Illuminate\Support\Facades\App;

class MobileAuthController extends Controller
{
    /**
     * Request an OTP for login/verification via Twilio (WhatsApp).
     */
    public function requestWhatsAppOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
        ]);

        $contact = Contact::where('value', $request->identifier)
            ->where('type', 'whatsapp')
            ->first();

        if (!$contact || !$contact->user) {
            return response()->json(['message' => 'Contact not found or invalid.'], 404);
        }

        $cacheKey = 'otp_lock_' . $contact->id;

        // Dev cost control: If lock exists, just log it.
        if (Cache::has($cacheKey) && !App::environment('production')) {
            $code = Cache::get('otp_code_' . $contact->id);
            Log::info("[DEV/TEST] WhatsApp OTP for {$contact->value}: {$code}");
            return response()->json(['message' => 'Verification code sent (mocked).']);
        }

        $plainCode = (string) random_int(100000, 999999);

        // Store hash in DB
        $contact->update([
            'verification_code' => Hash::make($plainCode),
            'code_expires_at'   => now()->addMinutes(10),
        ]);

        // Lock for 5 mins to prevent spam/costs
        Cache::put($cacheKey, true, now()->addMinutes(5));
        Cache::put('otp_code_' . $contact->id, $plainCode, now()->addMinutes(10)); // Just for dev logging

        try {
            $client = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $client->messages->create(
                "whatsapp:{$contact->value}",
                [
                    'from' => config('services.twilio.whatsapp_from'),
                    'body' => "Your Martin Logistics verification code is {$plainCode}. It expires in 10 minutes.",
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Twilio WhatsApp send failed: ' . $e->getMessage());
            // Clear cache if failed so they can retry
            Cache::forget($cacheKey);
            return response()->json(['message' => 'Failed to send code via WhatsApp.'], 500);
        }

        return response()->json(['message' => 'Verification code sent via WhatsApp.']);
    }

    /**
     * Verify WhatsApp OTP and Login.
     */
    public function verifyWhatsAppOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        $contact = Contact::where('value', $request->identifier)
            ->where('type', 'whatsapp')
            ->first();

        if (!$contact || !$contact->user) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        if (!$contact->verification_code || !$contact->code_expires_at) {
            return response()->json(['message' => 'No code has been requested.'], 422);
        }

        if (now()->greaterThan($contact->code_expires_at)) {
            return response()->json(['message' => 'Verification code expired.'], 422);
        }

        if (!Hash::check($request->code, $contact->verification_code)) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        // Verification success
        $contact->update([
            'verified_at' => now(),
            'verification_code' => null,
            'code_expires_at' => null,
        ]);

        // Issue token
        $token = $contact->user->createToken('mobile_api_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $contact->user,
        ]);
    }

    /**
     * Verify Phone via Firebase ID Token and Login.
     * Note: Firebase SMS is usually initiated client-side. The client sends the verified idToken here.
     */
    public function verifyFirebasePhone(Request $request, FirebaseAuth $firebaseAuth)
    {
        $request->validate([
            'idToken' => 'required|string', // Firebase ID Token
            'identifier' => 'required|string', // The expected phone number to match
        ]);

        $contact = Contact::where('value', $request->identifier)
            ->where('type', 'phone')
            ->first();

        if (!$contact || !$contact->user) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        // For local dev/testing cost control, we can bypass actual Firebase check if a secret token is used
        if (!App::environment('production') && $request->idToken === 'TEST_BYPASS_TOKEN_123') {
            Log::info("[DEV/TEST] Bypassed Firebase verification for {$contact->value}");
            $contact->update(['verified_at' => now()]);
            return response()->json([
                'message' => 'Login successful (bypass).',
                'token' => $contact->user->createToken('mobile_api_token')->plainTextToken,
                'user' => $contact->user,
            ]);
        }

        try {
            $verifiedToken = $firebaseAuth->verifyIdToken($request->idToken);
            $phoneNumber   = $verifiedToken->claims()->get('phone_number');

            if (!$phoneNumber || $phoneNumber !== $contact->value) {
                return response()->json(['message' => 'Phone number mismatch or token invalid.'], 422);
            }

            // Verification success
            $contact->update(['verified_at' => now()]);

            // Issue token
            $token = $contact->user->createToken('mobile_api_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
                'user' => $contact->user,
            ]);

        } catch (\Throwable $e) {
            Log::warning('Firebase verify failed: '.$e->getMessage());
            return response()->json(['message' => 'Invalid or expired Firebase token.'], 422);
        }
    }

    /**
     * Register a new mobile user.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? $data['phone'] . '@mobile.user',
                'password' => Hash::make(str()->random(32)),
                'kyc_status' => 'pending',
            ]);

            $user->assignRole('Customer');

            Contact::create([
                'user_id' => $user->id,
                'type' => 'whatsapp',
                'value' => $data['phone'],
                'is_primary' => true,
            ]);

            if ($data['email'] ?? null) {
                Contact::create([
                    'user_id' => $user->id,
                    'type' => 'email',
                    'value' => $data['email'],
                    'is_primary' => false,
                ]);
            }

            return $user;
        });

        return response()->json([
            'message' => 'Registration successful. Please verify your phone via OTP to login.',
            'user' => $user->load('roles'),
        ], 201);
    }

    /**
     * Logout Mobile User
     */
    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
