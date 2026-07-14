<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function portalLogin(Request $request)
    {
        // 1. Validate the incoming request
        $credentials = $request->validate([
            'identifier' => 'required|email', // Assuming 'identifier' is the email
            'password'   => 'required|string',
        ]);

        // 2. Map 'identifier' to the 'email' column for Auth::attempt
        $attemptCredentials = [
            'email'    => $credentials['identifier'],
            'password' => $credentials['password'],
        ];

        // 3. Attempt to log the user in using the 'web' guard
        if (Auth::guard('web')->attempt($attemptCredentials, $request->boolean('remember'))) {

            // Regenerate session to prevent fixation attacks
            $request->session()->regenerate();

            $user = Auth::user();

            // If 2FA is not yet confirmed, store a pending flag and signal the frontend
            if (is_null($user->two_factor_confirmed_at)) {
                $request->session()->put('2fa_pending_setup', true);

                return response()->json([
                    'user'     => $user,
                    'message'  => 'Two-factor authentication setup required before accessing the portal.',
                    'requires_2fa_setup' => true,
                ]);
            }

            // 2FA is enabled but not yet passed for this session
            if (!$request->session()->get('two_factor_passed', false)) {
                $request->session()->put('2fa_pending_challenge', true);

                return response()->json([
                    'user'    => $user,
                    'message' => 'Two-factor authentication challenge required.',
                    'requires_2fa_challenge' => true,
                ]);
            }

            return response()->json([
                'user'    => $user,
                'message' => 'Authenticated via session',
            ]);
        }

        // 4. If authentication fails, throw the standard error
        throw ValidationException::withMessages([
            'identifier' => [__('auth.failed')],
        ]);
    }

    public function mobileLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid email or password'
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('mobile-auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ]
        ]);
    }

    public function login(Request $request) // Token-based for Mobile/API
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $contact = Contact::where('value', $request->identifier)->first();

        if (! $contact || ! $contact->user || ! Hash::check($request->password, $contact->user->password)) {
            throw ValidationException::withMessages(['identifier' => [__('auth.failed')]]);
        }

        if (! $contact->isVerified()) {
            return response()->json(['message' => 'Verify your contact method first.'], 403);
        }

        return response()->json([
            'token' => $contact->user->createToken('api_token')->plainTextToken,
            'user' => $contact->user,
        ]);
    }

    public function logout(Request $request)
    {
        // 1. Kill current mobile token if it exists
        if ($request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        // 2. Kill the web session
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
