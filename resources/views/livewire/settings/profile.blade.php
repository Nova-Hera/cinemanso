<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $profile_picture = '';
    public $photo = null;
    public ?string $banner_path = null;
    public $banner = null;

    /**
     * The default avatar filename — never deleted when replaced.
     */
    private const DEFAULT_PICTURE = 'default-profile.png';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->profile_picture = Auth::user()->profile_picture ?: self::DEFAULT_PICTURE;
        $this->banner_path = Auth::user()->banner;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id)
            ],

            'photo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->fill([
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($this->photo) {
            $path = $this->photo->store('profile-pictures', 'public');

            $old = $user->profile_picture;
            if ($old && $old !== self::DEFAULT_PICTURE) {
                Storage::disk('public')->delete($old);
            }

            $user->profile_picture = $path;
            $this->profile_picture = $path;
            $this->photo = null;
        }

        if ($this->banner) {
            $path = $this->banner->store('banners', 'public');

            if ($user->banner) {
                Storage::disk('public')->delete($user->banner);
            }

            $user->banner = $path;
            $this->banner_path = $path;
            $this->banner = null;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Remove the banner of the currently authenticated user.
     */
    public function removeBanner(): void
    {
        $user = Auth::user();

        if ($user->banner) {
            Storage::disk('public')->delete($user->banner);
        }

        $user->banner = null;
        $user->save();

        $this->banner_path = null;
        $this->banner = null;
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('home', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Perfil')" :subheading="__('Atualiza tuas informações de perfil abaixo')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <div class="space-y-4">
                <flux:label>{{ __('Imagens do perfil') }}</flux:label>

                <div>
                    <div class="relative h-28 w-full overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-gradient-to-br from-green-100 to-zinc-100 dark:from-green-950 dark:to-zinc-900">
                        @php $bannerPreview = $banner?->isPreviewable() ? $banner->temporaryUrl() : ($banner_path ? asset('storage/' . $banner_path) : null); @endphp
                        @if ($bannerPreview)
                            <img
                                src="{{ $bannerPreview }}"
                                alt=""
                                class="absolute inset-0 h-full w-full object-cover"
                            />
                        @endif
                    </div>
                    <div class="-mt-7 flex px-3">
                        <span class="relative flex h-16 w-16 shrink-0 overflow-hidden rounded-full border-[3px] border-white dark:border-zinc-800">
                            <img
                                src="{{ $photo ? $photo->temporaryUrl() : asset('storage/' . $profile_picture) }}"
                                alt="{{ $name }}"
                                class="h-full w-full object-cover"
                            />
                        </span>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex min-w-0 flex-col gap-1">
                        <flux:label>{{ __('Foto de perfil') }}</flux:label>
                        <x-image-picker-button property="photo">{{ __('Escolher foto') }}</x-image-picker-button>
                        <div wire:loading wire:target="photo" class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Enviando…') }}</div>
                        @error('photo')
                            <span class="text-xs text-red-500">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex min-w-0 flex-col gap-1">
                        <flux:label>{{ __('Banner') }}</flux:label>
                        <x-image-picker-button property="banner">{{ __('Escolher banner') }}</x-image-picker-button>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Proporção 4,8:1 (1920×400), até 2 MB. O centro da imagem é o que sempre aparece.') }}</span>
                        <div wire:loading wire:target="banner" class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Enviando…') }}</div>
                        @error('banner')
                            <span class="text-xs text-red-500">{{ $message }}</span>
                        @enderror
                        @if ($banner_path && ! $banner)
                            <button type="button" wire:click="removeBanner" class="self-start cursor-pointer text-xs text-zinc-500 underline hover:text-red-500 dark:text-zinc-400">
                                {{ __('Remover banner') }}
                            </button>
                        @endif
                    </div>
                </div>

                @if ($photo || $banner)
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Imagem nova escolhida. Clique em Salvar para aplicar.') }}</p>
                @endif
            </div>

            <flux:input wire:model="name" :label="__('Nome')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&! auth()->user()->hasVerifiedEmail())
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Seu endereço de email não está verificado.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Clique aqui para reenviar o email de verificação.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('Um novo link de verificação foi enviado para o seu endereço de email.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Salvar') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Salvo.') }}
                </x-action-message>
            </div>
        </form>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
