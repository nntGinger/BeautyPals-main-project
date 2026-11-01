@props(['id', 'name', 'autocomplete' => 'current-password', 'label' => 'mật khẩu'])

<div class="password-field">
    <input id="{{ $id }}" type="password" name="{{ $name }}" required autocomplete="{{ $autocomplete }}" {{ $attributes }}>
    <button type="button" class="password-toggle" data-password-toggle aria-controls="{{ $id }}"
        data-label="{{ $label }}" aria-label="Hiện {{ $label }}" aria-pressed="false" hidden>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
            <circle cx="12" cy="12" r="3" />
            <path class="password-eye-slash" d="m3 3 18 18" hidden />
        </svg>
    </button>
</div>
