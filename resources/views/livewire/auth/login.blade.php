<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'Email və ya şifrə yanlışdır.');
            return;
        }

        session()->regenerate();
        $this->redirect(route('properties.index'));
    }
}; ?>

<div class="login-bg flex min-h-screen items-center justify-center px-4">

    {{-- Background color washes --}}
    <div class="wash wash-1"></div>
    <div class="wash wash-2"></div>
    <div class="wash wash-3"></div>

    <div class="login-card relative w-full max-w-sm">

        {{-- Logo --}}
        <div class="mb-10 text-center">
            <div class="inline-flex items-center justify-center gap-2.5 mb-3">
                <span class="logo-light login-logo-icon text-zinc-900 eye-logo"><span class="eye-char">◉</span><span class="eye-char">◉</span></span>
                <span class="logo-dark login-logo-icon text-white eye-logo"><span class="eye-char">◎</span><span class="eye-char">◎</span></span>
                <span class="login-logo-text text-zinc-800 dark:text-white">Binokl.az</span>
            </div>
            <p class="text-sm text-zinc-400 dark:text-zinc-500 tracking-wide">Emlakçı platforması</p>
        </div>

        {{-- Form card --}}
        <div class="login-form-card">
            <form wire:submit="login" class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest mb-1.5">Email</label>
                    <input wire:model="email" type="email" placeholder="email@example.com" autocomplete="email"
                           class="login-input" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest mb-1.5">Şifrə</label>
                    <input wire:model="password" type="password" placeholder="••••••••" autocomplete="current-password"
                           class="login-input" />
                </div>

                @error('email')
                    <div class="text-xs text-red-400 -mt-2">{{ $message }}</div>
                @enderror

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input wire:model="remember" type="checkbox"
                               class="size-3.5 rounded border-zinc-300 dark:border-zinc-600 accent-indigo-500" />
                        <span class="text-xs text-zinc-400">Məni xatırla</span>
                    </label>
                </div>

                <button type="submit"
                        class="login-btn w-full"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove>Daxil ol</span>
                    <span wire:loading class="flex items-center justify-center gap-2">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        Gözləyin...
                    </span>
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-zinc-100 dark:border-zinc-800 text-center text-xs text-zinc-400">
                Hesabınız yoxdur?
                <a href="{{ route('register') }}" class="text-indigo-500 hover:text-indigo-400 font-medium ml-1 transition-colors">Qeydiyyat</a>
            </div>
        </div>
    </div>
</div>

<style>
/* Background */
.login-bg {
    background-color: #f8f8f6;
    position: relative;
    overflow: hidden;
}
.dark .login-bg {
    background-color: #0d0d0f;
}

/* Color washes */
.wash {
    position: fixed;
    border-radius: 50%;
    pointer-events: none;
    animation-timing-function: ease-in-out;
    animation-iteration-count: infinite;
    animation-direction: alternate;
}
.wash-1 {
    width: 80vw; height: 80vw;
    background: radial-gradient(circle, rgba(99,102,241,0.28) 0%, transparent 65%);
    top: -20vw; right: -15vw;
    animation: drift1 9s ease-in-out infinite alternate;
}
.wash-2 {
    width: 70vw; height: 70vw;
    background: radial-gradient(circle, rgba(14,165,233,0.22) 0%, transparent 65%);
    bottom: -15vw; left: -10vw;
    animation: drift2 12s ease-in-out infinite alternate;
}
.wash-3 {
    width: 55vw; height: 55vw;
    background: radial-gradient(circle, rgba(139,92,246,0.15) 0%, transparent 65%);
    bottom: -10vw; right: 10vw;
    animation: drift3 15s ease-in-out infinite alternate;
}
.dark .wash-1 { background: radial-gradient(circle, rgba(99,102,241,0.18) 0%, transparent 65%); }
.dark .wash-2 { background: radial-gradient(circle, rgba(14,165,233,0.15) 0%, transparent 65%); }
.dark .wash-3 { background: radial-gradient(circle, rgba(139,92,246,0.10) 0%, transparent 65%); }

@keyframes drift1 {
    from { transform: translate(0, 0) scale(1); }
    to   { transform: translate(-4vw, 5vh) scale(1.08); }
}
@keyframes drift2 {
    from { transform: translate(0, 0) scale(1); }
    to   { transform: translate(5vw, -4vh) scale(1.06); }
}
@keyframes drift3 {
    from { transform: translate(0, 0) scale(1); }
    to   { transform: translate(-3vw, -5vh) scale(1.05); }
}

/* Logo */
.login-logo-icon {
    font-size: 2rem;
    line-height: 1;
    letter-spacing: -0.05em;
    user-select: none;
}
.login-logo-text {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1;
}

/* Card */
.login-form-card {
    background: rgba(255,255,255,0.8);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 1.25rem;
    padding: 2rem;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06), 0 1px 4px rgba(0,0,0,0.04);
}
.dark .login-form-card {
    background: rgba(22,22,26,0.85);
    border-color: rgba(255,255,255,0.06);
    box-shadow: 0 4px 32px rgba(0,0,0,0.4), 0 1px 4px rgba(0,0,0,0.3);
}

/* Input */
.login-input {
    width: 100%;
    background: rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 0.625rem;
    padding: 0.65rem 0.875rem;
    font-size: 0.875rem;
    color: #18181b;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.login-input::placeholder { color: #a1a1aa; }
.login-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.dark .login-input {
    background: rgba(255,255,255,0.04);
    border-color: rgba(255,255,255,0.08);
    color: #f4f4f5;
}
.dark .login-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
}

/* Button */
.login-btn {
    background: #18181b;
    color: #fff;
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    padding: 0.7rem 1rem;
    border-radius: 0.625rem;
    border: none;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.login-btn:hover {
    background: #27272a;
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    transform: translateY(-1px);
}
.login-btn:active { transform: translateY(0); }
.login-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.dark .login-btn {
    background: #f4f4f5;
    color: #18181b;
}
.dark .login-btn:hover {
    background: #ffffff;
}
.eye-char { display: inline-block; }
</style>
<script>
    function blinkEye(el) {
        if (el.dataset.blinking) return;
        el.dataset.blinking = '1';
        el.style.transition = 'transform 0.5s ease';
        el.style.transform = 'rotateX(150deg)';
        setTimeout(() => {
            el.style.transform = 'rotateX(0deg)';
            setTimeout(() => {
                el.style.transition = '';
                el.style.transform = '';
                delete el.dataset.blinking;
            }, 500);
        }, 500);
    }
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.eye-logo').forEach(logo => {
            const [left, right] = logo.querySelectorAll('.eye-char');
            if (!left || !right) return;
            left.addEventListener('mouseenter', () => blinkEye(right));
            right.addEventListener('mouseenter', () => blinkEye(left));
        });
    });
    function flipRandomEye() {
        const chars = document.querySelectorAll('.eye-char');
        if (chars.length) blinkEye(chars[Math.floor(Math.random() * chars.length)]);
    }
    setInterval(flipRandomEye, 60000);
</script>
