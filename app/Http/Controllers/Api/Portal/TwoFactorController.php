<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'enabled' => !is_null($user->two_factor_confirmed_at),
            'confirmed_at' => $user->two_factor_confirmed_at,
            'pending_setup' => $request->session()->get('2fa_pending_setup', false),
            'pending_challenge' => $request->session()->get('2fa_pending_challenge', false),
        ]);
    }

    public function setup(Request $request)
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return response()->json(['message' => 'Two-factor authentication is already enabled.'], 422);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
        ])->save();

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $recoveryCodes = collect(range(1, 8))->map(fn () => strtoupper(\Illuminate\Support\Str::random(10)))->all();

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        return response()->json([
            'secret' => $secret,
            'qr_url' => $qrUrl,
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'No 2FA setup in progress. Call setup first.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new Google2FA();

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->put('two_factor_passed', true);
        $request->session()->forget('2fa_pending_setup');
        $request->session()->forget('2fa_pending_challenge');

        return response()->json(['message' => 'Two-factor authentication enabled successfully.']);
    }

    public function challenge(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();

        if (!$user->two_factor_confirmed_at || !$user->two_factor_secret) {
            return response()->json(['message' => 'Two-factor authentication is not enabled.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new Google2FA();

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $request->session()->put('two_factor_passed', true);
        $request->session()->forget('2fa_pending_setup');
        $request->session()->forget('2fa_pending_challenge');

        return response()->json(['message' => 'Two-factor authentication verified.']);
    }

    public function disable(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();

        if (!$user->two_factor_confirmed_at || !$user->two_factor_secret) {
            return response()->json(['message' => 'Two-factor authentication is not enabled.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new Google2FA();

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Invalid code. Please try again.'], 422);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('two_factor_passed');

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }
}
