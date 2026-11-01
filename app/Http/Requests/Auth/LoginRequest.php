<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email', '')))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()->where('email', $this->string('email')->toString())->first();

        if ($user?->isLocked() && Hash::check($this->string('password')->toString(), $user->password)) {
            $this->hitRateLimiters();
            throw ValidationException::withMessages([
                'email' => 'Tài khoản này đã bị khóa và không thể đăng nhập. Vui lòng liên hệ quản trị viên.',
            ]);
        }

        if (! Auth::attempt([...$this->only('email', 'password'), 'status' => 'active'], $this->boolean('remember'))) {
            $this->hitRateLimiters();
            $this->ensureIsNotRateLimited();

            $remaining = max(0, (int) config('auth.lockout.max_attempts', 5) - max(
                RateLimiter::attempts($this->emailThrottleKey()),
                RateLimiter::attempts($this->ipThrottleKey()),
            ));

            throw ValidationException::withMessages([
                'email' => "Email hoặc mật khẩu không đúng. Bạn còn {$remaining} lần thử trước khi bị chặn tạm thời.",
            ]);
        }

        $this->clearRateLimiters();
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $maxAttempts = (int) config('auth.lockout.max_attempts', 5);

        if (
            ! RateLimiter::tooManyAttempts($this->emailThrottleKey(), $maxAttempts)
            && ! RateLimiter::tooManyAttempts($this->ipThrottleKey(), $maxAttempts)
        ) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->emailThrottleKey()),
            RateLimiter::availableIn($this->ipThrottleKey()),
        );

        $this->session()->put('login_lockout_email_key', $this->emailThrottleKey());
        $this->session()->put('login_lockout_email', $this->string('email')->toString());
        $duration = sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);

        throw ValidationException::withMessages([
            'email' => "Đã đạt giới hạn {$maxAttempts} lần đăng nhập sai cho tài khoản này. Vui lòng thử lại sau {$duration}.",
        ]);
    }

    public function hitRateLimiters(): void
    {
        $decay = (int) config('auth.lockout.decay_seconds', 900);

        RateLimiter::hit($this->emailThrottleKey(), $decay);
        RateLimiter::hit($this->ipThrottleKey(), $decay);
    }

    public function clearRateLimiters(): void
    {
        RateLimiter::clear($this->emailThrottleKey());
        RateLimiter::clear($this->ipThrottleKey());
        $this->session()->forget(['login_lockout_email_key', 'login_lockout_email']);
    }

    public function emailThrottleKey(): string
    {
        return 'login:email:'.Str::transliterate(Str::lower($this->string('email')->toString()));
    }

    public function ipThrottleKey(): string
    {
        return $this->emailThrottleKey().':ip:'.$this->ip();
    }
}
