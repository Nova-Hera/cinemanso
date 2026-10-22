<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the self-hosted Neko (m1k1o/neko v3) admin REST API.
 *
 * Neko runs with the `object` member provider: the only member it knows at boot is a
 * bot admin (BOT). Every real user is registered here (syncMember) right before the
 * room is opened, so each one gets their own name and avatar in Neko's member list.
 *
 * The room has no "restart" endpoint — Neko cannot reload its own browser — so a
 * "reload the session" is the closest equivalent: free whoever is holding the
 * mouse/keyboard, then kick every connected client so they re-handshake WebRTC.
 */
class Neko
{
    /** Member that exists at Neko boot (NEKO_MEMBER_OBJECT_USERS); never connects. */
    private const BOT = 'cinemanso-bot';

    private string $url;
    private string $password;
    private string $adminPassword;

    public function __construct()
    {
        $this->url           = config('services.neko.url', '');
        $this->password      = config('services.neko.password', '');
        $this->adminPassword = config('services.neko.admin_password', '');
    }

    public function configured(): bool
    {
        return $this->url !== '' && $this->adminPassword !== '';
    }

    /**
     * Neko member id of a user: their username, with anything Neko rejects (spaces,
     * slashes…) replaced.
     */
    public function memberId(User $user): string
    {
        $id = trim(preg_replace('/[^\p{L}\p{N}_.-]/u', '-', $user->username ?: $user->name), '-');

        return $id !== '' ? $id : 'u'.$user->id;
    }

    /**
     * Password the user's iframe logs in with. Admin rights live in the profile, but
     * giving admins their own password keeps a guest (who sees the guest password in
     * the page) from logging in as an admin's member.
     */
    public function memberPassword(User $user): string
    {
        return $user->is_admin ? $this->adminPassword : $this->password;
    }

    /**
     * Create or update the user's Neko member (name, avatar, admin flag, password) and
     * return its id, or null when Neko can't be reached. Never throws.
     *
     * Neko refuses to log in a member whose previous session is still connected (page
     * reload, second tab), so that old session is disconnected first.
     */
    public function syncMember(User $user): ?string
    {
        if (!$this->configured()) {
            return null;
        }

        try {
            return $this->withBot(function (CookieJar $jar, ?string $token) use ($user) {
                $id       = $this->memberId($user);
                $password = $this->memberPassword($user);
                $profile  = [
                    'name'                     => $user->username ?: $user->name,
                    'avatar'                   => asset('storage/'.($user->profile_picture ?: 'default-profile.png')),
                    'is_admin'                 => (bool) $user->is_admin,
                    'can_login'                => true,
                    'can_connect'              => true,
                    'can_watch'                => true,
                    'can_host'                 => true,
                    'can_share_media'          => true,
                    'can_access_clipboard'     => true,
                    'sends_inactive_cursor'    => true,
                    'can_see_inactive_cursors' => true,
                ];

                $create = $this->request($jar, $token)->post('/api/members', [
                    'username' => $id,
                    'password' => $password,
                    'profile'  => $profile,
                ]);

                // 422 = already exists: refresh its profile and password instead.
                if (!$create->successful()) {
                    if ($create->status() !== 422) {
                        return null;
                    }

                    $this->request($jar, $token)->post('/api/members/'.rawurlencode($id), $profile);
                    $this->request($jar, $token)->post('/api/members/'.rawurlencode($id).'/password', [
                        'password' => $password,
                    ]);
                }

                $sessions = $this->request($jar, $token)->get('/api/sessions');

                foreach ($sessions->successful() ? ($sessions->json() ?? []) : [] as $session) {
                    if (($session['id'] ?? null) === $id && ($session['state']['is_connected'] ?? false)) {
                        $this->request($jar, $token)->post('/api/sessions/'.rawurlencode($id).'/disconnect');
                    }
                }

                return $id;
            });
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Reset the shared room. Returns [ok, message] — the message is shown to the
     * admin who pressed the button, so it is written in Portuguese like the rest of
     * the UI. Never throws: Neko being unreachable must not break the page.
     *
     * @return array{0: bool, 1: string}
     */
    public function resetSession(): array
    {
        if (!$this->configured()) {
            return [false, 'Neko não está configurado (NEKO_URL / NEKO_ADMIN_PASSWORD).'];
        }

        try {
            $result = $this->withBot(function (CookieJar $jar, ?string $token, string $ownId) {
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

                return [true, "Sessão reiniciada — {$disconnected} pessoa(s) reconectando."];
            });

            return $result ?? [false, 'Não foi possível autenticar no Neko.'];
        } catch (\Throwable $e) {
            // The raw cURL text is too noisy for a flash message — send it to the
            // log and tell the admin the useful part.
            report($e);

            return [false, 'Não foi possível falar com o Neko — o servidor está fora do ar?'];
        }
    }

    /**
     * Run $callback logged in as the bot admin and always log out afterwards. A lock
     * serialises callers because every login shares the bot's single Neko session.
     * Returns null when the bot can't authenticate.
     */
    private function withBot(callable $callback): mixed
    {
        return Cache::lock('neko-bot', 20)->block(10, function () use ($callback) {
            // One jar shared by every request below carries the NEKO_SESSION cookie.
            $jar = new CookieJar();

            $login = $this->request($jar)->post('/api/login', [
                'username' => self::BOT,
                'password' => $this->adminPassword,
            ]);

            if (!$login->successful()) {
                return null;
            }

            // `token` only comes back when Neko has cookie auth disabled; otherwise
            // the jar holds the session. Support both so a Neko config change
            // doesn't silently break this.
            $token = $login->json('token');
            $ownId = $login->json('id') ?? self::BOT;

            try {
                return $callback($jar, $token, $ownId);
            } finally {
                // Don't leave the bot parked in the session list.
                try {
                    $this->request($jar, $token)->post('/api/logout');
                } catch (\Throwable) {}
            }
        });
    }

    private function request(CookieJar $jar, ?string $token = null): PendingRequest
    {
        $request = Http::baseUrl($this->url)
            ->timeout(8)
            ->withOptions(['cookies' => $jar]);

        return $token ? $request->withToken($token) : $request;
    }
}
