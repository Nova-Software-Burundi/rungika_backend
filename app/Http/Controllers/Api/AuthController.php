<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;

class AuthController extends Controller
{
    public function portalLogin(Request $request)
    {
        $credentials = $request->validate([
            'identifier' => 'required|email',
            'password'   => 'required|string',
        ]);

        $attemptCredentials = [
            'email'    => $credentials['identifier'],
            'password' => $credentials['password'],
        ];

        if (!Auth::guard('web')->attempt($attemptCredentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'identifier' => [__('auth.failed')],
            ]);
        }

        $user = Auth::user();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $tempToken = Crypt::encrypt([
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ]);

            return response()->json([
                'requires_2fa' => true,
                'temp_token' => $tempToken,
            ]);
        }

        $tempToken = Crypt::encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return response()->json([
            'requires_2fa_setup' => true,
            'temp_token' => $tempToken,
        ]);
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'temp_token' => 'required|string',
            'code' => 'required|string',
        ]);

        $payload = $this->decryptTempToken($request->temp_token);
        if (!$payload) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $user = \App\Models\User::find($payload['user_id']);
        if (!$user || !$user->hasEnabledTwoFactorAuthentication()) {
            return response()->json(['message' => 'Two-factor authentication is not enabled.'], 422);
        }

        $google2fa = app('pragmarx.google2fa');
        $secret = decrypt($user->two_factor_secret);

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        Auth::guard('web')->loginUsingId($user->id);
        $request->session()->regenerate();

        $userData = $user->toArray();
        $userData['roles_list'] = $user->getRoleNames();

        return response()->json(['user' => $userData]);
    }

    public function verifyRecoveryCode(Request $request)
    {
        $request->validate([
            'temp_token' => 'required|string',
            'code' => 'required|string',
        ]);

        $payload = $this->decryptTempToken($request->temp_token);
        if (!$payload) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $user = \App\Models\User::find($payload['user_id']);
        if (!$user || !$user->hasEnabledTwoFactorAuthentication()) {
            return response()->json(['message' => 'Two-factor authentication is not enabled.'], 422);
        }

        if (!$user->two_factor_recovery_codes) {
            return response()->json(['message' => 'No recovery codes available.'], 422);
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        $foundIndex = false;

        foreach ($codes as $i => $rc) {
            if (hash_equals($rc, $request->code)) {
                $foundIndex = $i;
                break;
            }
        }

        if ($foundIndex === false) {
            return response()->json(['message' => 'Invalid recovery code.'], 422);
        }

        unset($codes[$foundIndex]);
        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes))),
        ])->save();

        Auth::guard('web')->loginUsingId($user->id);
        $request->session()->regenerate();

        $userData = $user->toArray();
        $userData['roles_list'] = $user->getRoleNames();

        return response()->json(['user' => $userData]);
    }

    public function initSetup(Request $request)
    {
        $request->validate(['temp_token' => 'required|string']);

        $payload = $this->decryptTempToken($request->temp_token);
        if (!$payload) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $user = \App\Models\User::find($payload['user_id']);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 401);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            Auth::guard('web')->loginUsingId($user->id);
            $request->session()->regenerate();

            $userData = $user->toArray();
            $userData['roles_list'] = $user->getRoleNames();

            return response()->json(['user' => $userData, 'message' => '2FA already active.']);
        }

        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
        ])->save();

        $recoveryCodes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        return response()->json([
            'secret' => $secret,
            'qr_code' => $user->twoFactorQrCodeSvg(),
            'qr_code_url' => $user->twoFactorQrCodeUrl(),
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function confirmSetup(Request $request)
    {
        $request->validate([
            'temp_token' => 'required|string',
            'code' => 'required|string',
        ]);

        $payload = $this->decryptTempToken($request->temp_token);
        if (!$payload) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $user = \App\Models\User::find($payload['user_id']);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 401);
        }

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'No 2FA setup in progress. Call setup-init first.'], 422);
        }

        $google2fa = app('pragmarx.google2fa');
        $secret = decrypt($user->two_factor_secret);

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        Auth::guard('web')->loginUsingId($user->id);
        $request->session()->regenerate();

        $userData = $user->toArray();
        $userData['roles_list'] = $user->getRoleNames();

        return response()->json(['user' => $userData, 'message' => '2FA setup complete.']);
    }

    public function enableTwoFactor(Request $request)
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json(['message' => 'Two-factor authentication is already enabled.'], 422);
        }

        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
        ])->save();

        $recoveryCodes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function confirmTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'No 2FA setup in progress. Call enable first.'], 422);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json(['message' => 'Two-factor authentication is already enabled.'], 422);
        }

        $google2fa = app('pragmarx.google2fa');
        $secret = decrypt($user->two_factor_secret);

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return response()->json(['message' => 'Two-factor authentication enabled successfully.']);
    }

    public function disableTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();

        if (!$user->hasEnabledTwoFactorAuthentication()) {
            return response()->json(['message' => 'Two-factor authentication is not enabled.'], 422);
        }

        $google2fa = app('pragmarx.google2fa');
        $secret = decrypt($user->two_factor_secret);

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    public function getTwoFactorQrCode(Request $request)
    {
        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'Two-factor authentication is not set up.'], 422);
        }

        $google2fa = new \PragmaRX\Google2FAQRCode\Google2FA();
        $secret = decrypt($user->two_factor_secret);

        return response()->json([
            'secret' => decrypt($user->two_factor_secret),
            'qr_code' => $user->twoFactorQrCodeSvg(),
            'qr_code_url' => $user->twoFactorQrCodeUrl(),
        ]);
    }

    public function getRecoveryCodes(Request $request)
    {
        $user = $request->user();

        if (!$user->two_factor_recovery_codes) {
            return response()->json(['message' => 'No recovery codes available.'], 422);
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        return response()->json(['recovery_codes' => $codes]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user();

        if (!$user->hasEnabledTwoFactorAuthentication()) {
            return response()->json(['message' => 'Two-factor authentication is not enabled.'], 422);
        }

        $recoveryCodes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        return response()->json(['recovery_codes' => $recoveryCodes]);
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

    public function login(Request $request)
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
        if ($request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }

    private function decryptTempToken(string $token): ?array
    {
        try {
            $payload = Crypt::decrypt($token);

            if (!isset($payload['user_id'], $payload['expires_at'])) {
                return null;
            }

            if (now()->timestamp > $payload['expires_at']) {
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            return null;
        }
    }
}
