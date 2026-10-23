import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Reverb speaks the Pusher protocol, so Echo drives it through pusher-js.
window.Pusher = Pusher;

// Only boot Echo when a Reverb key is configured. Without it the app still works —
// the wheel falls back to its 3s polling to detect spins.
if (import.meta.env.VITE_REVERB_APP_KEY) {
    // In production Caddy serves Reverb on the app's own host (wss://<host>/app/...),
    // so host/port default to wherever the page was loaded from. Locally set
    // VITE_REVERB_HOST/PORT/SCHEME to point at `php artisan reverb:start`.
    const secure = (import.meta.env.VITE_REVERB_SCHEME ?? location.protocol.replace(':', '')) === 'https';
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? (secure ? 443 : 80));

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: secure,
        enabledTransports: ['ws', 'wss'],
    });

    // Trigger the wheel spin straight from the socket payload — no server
    // round-trip — so every client starts within fan-out jitter of each other.
    // Subscribed once here (survives wire:navigate); the Alpine component
    // dedups by drawId. Falls back to wire:poll when the socket is unavailable.
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
