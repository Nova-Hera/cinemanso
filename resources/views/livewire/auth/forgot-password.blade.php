<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('Um link de redefinição de senha será enviado se a conta existir.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Forgot password')" :description="__('Insira seu email para receber um link de redefinição de senha')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <!-- Email Address -->
        <flux:input
            wire:model="email"
            :label="__('Endereço de e-mail')"
            type="email"
            required
            autofocus
            placeholder="email@exemplo.com"
        />

        <flux:button variant="primary" type="submit" class="w-full">{{ __('Enviar link de redefinição de senha') }}</flux:button>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
        <span>{{ __('Ou, volte para') }}</span>
        <flux:link :href="route('login')" wire:navigate>{{ __('fazer login') }}</flux:link>
    </div>
</div>
