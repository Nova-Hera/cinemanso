<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $baseUsername = Str::slug($validated['name']);
        $username = $baseUsername;
        
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        }
        
        $validated['username'] = $username;
        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        $this->redirectIntended(route('home', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Crie uma conta')" :description="__('Insira seus dados abaixo para criar sua conta')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <!-- Name -->
        <flux:input
            wire:model="name"
            :label="'Nome'"
            type="text"
            required
            autofocus
            autocomplete="name"
            :placeholder="'Nome de usuário'"
        />

        <!-- Email Address -->
        <flux:input
            wire:model="email"
            :label="'Endereço de e-mail'"
            type="email"
            required
            autocomplete="email"
            placeholder="email@exemplo.com"
        />

        <!-- Password -->
        <flux:input
            wire:model="password"
            :label="'Senha'"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="'Senha'"
            viewable
        />

        <!-- Confirm Password -->
        <flux:input
            wire:model="password_confirmation"
            :label="'Confirmar senha'"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="'Senha'"
            viewable
        />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                Criar conta
            </flux:button>
        </div>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
        <span>Já tem uma conta?</span>
        <flux:link :href="route('login')" wire:navigate>Logar</flux:link>
    </div>
</div>
