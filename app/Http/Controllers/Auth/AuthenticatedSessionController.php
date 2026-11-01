<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        $emailKey = $request->session()->get('login_lockout_email_key');
        $keys = $emailKey ? [$emailKey, $emailKey.':ip:'.$request->ip()] : [];
        $seconds = 0;
        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, (int) config('auth.lockout.max_attempts', 5))) {
                $seconds = max($seconds, RateLimiter::availableIn($key));
            }
        }

        if ($seconds === 0) {
            $request->session()->forget(['login_lockout_email_key', 'login_lockout_email']);
        }

        return view('auth.login', [
            'lockoutSeconds' => $seconds,
            'lockoutEmail' => $request->session()->get('login_lockout_email', ''),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended($user->homePath());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
