<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $this->validate([
            'name' => 'required|min:2|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|max:20',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'password' => $this->password,
        ]);

        $user->assignRole('makler');

        Auth::login($user);
        session()->regenerate();
        $this->redirect(route('dashboard'));
    }
}; ?>

<div class="flex min-h-screen items-center justify-center bg-zinc-100 dark:bg-zinc-900 px-4">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-zinc-800 dark:text-white">Makler</h1>
            <p class="mt-2 text-zinc-500">Yeni hesab yaradın</p>
        </div>

        <flux:card>
            <form wire:submit="register" class="space-y-4">
                <flux:input wire:model="name" label="Ad Soyad" placeholder="Adınız" />
                <flux:input wire:model="email" label="Email" type="email" placeholder="email@example.com" />
                <flux:input wire:model="phone" label="Telefon" placeholder="+994 XX XXX XX XX" />
                <flux:input wire:model="password" label="Şifrə" type="password" />
                <flux:input wire:model="password_confirmation" label="Şifrə təkrar" type="password" />

                <flux:button type="submit" variant="primary" class="w-full">
                    Qeydiyyat
                </flux:button>
            </form>

            <div class="mt-4 text-center text-sm text-zinc-500">
                Artıq hesabınız var? <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Daxil olun</a>
            </div>
        </flux:card>
    </div>
</div>
