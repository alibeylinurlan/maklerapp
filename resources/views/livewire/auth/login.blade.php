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

<div class="flex min-h-screen items-center justify-center bg-zinc-100 dark:bg-zinc-900 px-4">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-zinc-800 dark:text-white">Makler</h1>
            <p class="mt-2 text-zinc-500">Emlakçı platformasına daxil olun</p>
        </div>

        <flux:card>
            <form wire:submit="login" class="space-y-4">
                <flux:input wire:model="email" label="Email" type="email" placeholder="email@example.com" />
                <flux:input wire:model="password" label="Şifrə" type="password" />
                <flux:checkbox wire:model="remember" label="Məni xatırla" />

                @error('email')
                    <div class="text-sm text-red-500">{{ $message }}</div>
                @enderror

                <flux:button type="submit" variant="primary" class="w-full">
                    Daxil ol
                </flux:button>
            </form>

            <div class="mt-4 text-center text-sm text-zinc-500">
                Hesabınız yoxdur? <a href="{{ route('register') }}" class="text-indigo-600 hover:underline">Qeydiyyat</a>
            </div>
        </flux:card>
    </div>
</div>
