import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Only boot Echo when a Pusher key is configured. Without it the app still works —
// the wheel falls back to its 3s polling to detect spins.
if (import.meta.env.VITE_PUSHER_APP_KEY) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
        forceTLS: true,
    });

    // Trigger the wheel spin straight from the Pusher payload — no server
    // round-trip — so every client starts within fan-out jitter of each other.
    // Subscribed once here (survives wire:navigate); the Alpine component
    // dedups by drawId. Falls back to wire:poll when Pusher is unavailable.
    window.Echo.channel('wheel').listen('.WheelSpun', (e) => {
        window.dispatchEvent(new CustomEvent('wheel-spin', {
            detail: { targetAngle: e.targetAngle, drawId: e.drawId },
        }));
    });

    // An admin reset the shared Neko room — everyone was disconnected, so every
    // open Sala remounts its iframe to log back in. Subscribed here, alongside
    // the wheel, so it survives wire:navigate.
    window.Echo.channel('neko').listen('.NekoSessionReloaded', () => {
        window.dispatchEvent(new CustomEvent('neko-reload'));
    });
}
