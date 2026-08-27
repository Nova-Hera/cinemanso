<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Talks to the self-hosted Neko (m1k1o/neko v3) admin REST API.
 *
 * The room has no "restart" endpoint — Neko cannot reload its own browser — so a
 * "reload the session" is the closest equivalent: free whoever is holding the
 * mouse/keyboard, then kick every connected client so they re-handshake WebRTC.
 */
class Neko
{
    private string $url;
    private string $adminPassword;

    public function __construct()
    {
        $this->url           = config('services.neko.url', '');
        $this->adminPassword = config('services.neko.admin_password', '');
    }

    public function configured(): bool
    {
        return $this->url !== '' && $this->adminPassword !== '';
    }

    /**
     * Reset the shared room. Returns [ok, message] — the message is shown to the
     * admin who pressed the button, so it is written in Portuguese like the rest
     * of the UI. Never throws: Neko being unreachable must not break the page.
     *
     * @return array{0: bool, 1: string}
     */
    public function resetSession(): array
    {
        if (!$this->configured()) {
            return [false, 'Neko não está configurado (NEKO_URL / NEKO_ADMIN_PASSWORD).'];
        }

        // One jar shared by every request below carries the NEKO_SESSION cookie.
        $jar = new CookieJar();

        try {
            // Neko refuses a login whose username is already connected, so a fixed
            // name would break whenever a previous run left a session dangling.
            $login = $this->request($jar)->post('/api/login', [
                'username' => 'cinemanso-bot-'.Str::random(6),
                'password' => $this->adminPassword,
            ]);

            if (!$login->successful()) {
                return [false, 'Não foi possível autenticar no Neko (HTTP '.$login->status().').'];
            }

            // `token` only comes back when Neko has cookie auth disabled; otherwise
            // the jar holds the session. Support both so a Neko config change
            // doesn't silently break this.
            $token = $login->json('token');
            $ownId = $login->json('id');

            $this->request($jar, $token)->post('/api/room/control/reset');

            $disconnected = 0;
            $sessions     = $this->request($jar, $token)->get('/api/sessions');

            if ($sessions->successful()) {
                foreach ($sessions->json() ?? [] as $session) {
                    $id = $session['id'] ?? null;

                    // Skip our own session — disconnecting it kills the auth we
                    // still need for the rest of the loop.
                    if (!$id || $id === $ownId) {
                        continue;
                    }

                    if ($this->request($jar, $token)->post("/api/sessions/{$id}/disconnect")->successful()) {
                        $disconnected++;
                    }
                }
            }

            // Don't leave the bot parked in the member list.
            $this->request($jar, $token)->post('/api/logout');

            return [true, "Sessão reiniciada — {$disconnected} pessoa(s) reconectando."];
        } catch (\Throwable $e) {
            // The raw cURL text is too noisy for a flash message — send it to the
            // log and tell the admin the useful part.
            report($e);

            return [false, 'Não foi possível falar com o Neko — o servidor está fora do ar?'];
        }
    }

    private function request(CookieJar $jar, ?string $token = null): PendingRequest
    {
        $request = Http::baseUrl($this->url)
            ->timeout(8)
            ->withOptions(['cookies' => $jar]);

        return $token ? $request->withToken($token) : $request;
    }
}
