<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Hyperbeam REST API (https://docs.hyperbeam.com).
 *
 * The app exposes a single shared "Sala" (watch-party room): one virtual browser
 * that every member joins via the same embed_url. The active session is cached so
 * visitors reuse it instead of each spawning (and paying for) their own VM.
 */
class Hyperbeam
{
    private const CACHE_KEY = 'hyperbeam:room';

    private string $key;
    private string $base;

    public function __construct()
    {
        $this->key  = (string) config('services.hyperbeam.key', '');
        $this->base = rtrim((string) config('services.hyperbeam.base_url', 'https://engine.hyperbeam.com/v0'), '/');
    }

    public function configured(): bool
    {
        return $this->key !== '';
    }

    /**
     * Return the single shared room, creating one if none is currently alive.
     * A lock prevents two simultaneous first-visitors from each spawning a VM.
     *
     * @return array{session_id:string,embed_url:string,admin_token:string}|null
     */
    public function sharedSession(): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        return Cache::lock('hyperbeam:room:lock', 10)->block(6, function () {
            $stored = Cache::get(self::CACHE_KEY);

            if (is_array($stored) && ! empty($stored['session_id']) && $this->alive($stored['session_id'])) {
                return $stored;
            }

            return $this->startAndStore();
        });
    }

    /**
     * Create a fresh VM and cache it as the shared room.
     *
     * @return array{session_id:string,embed_url:string,admin_token:string}|null
     */
    public function startAndStore(?int $fps = null, ?int $width = null, ?int $height = null): ?array
    {
        $session = $this->createSession($fps, $width, $height);

        if ($session !== null) {
            $ttl = max((int) config('services.hyperbeam.offline_timeout', 300), 60) + 120;
            Cache::put(self::CACHE_KEY, $session, now()->addSeconds($ttl));
        }

        return $session;
    }

    /**
     * Terminate a VM and drop it from the cache if it is the current room.
     */
    public function terminate(string $sessionId): void
    {
        Http::withToken($this->key)
            ->timeout(6)
            ->delete("{$this->base}/vm/{$sessionId}");

        $stored = Cache::get(self::CACHE_KEY);
        if (is_array($stored) && ($stored['session_id'] ?? null) === $sessionId) {
            Cache::forget(self::CACHE_KEY);
        }
    }

    /**
     * @return array{session_id:string,embed_url:string,admin_token:string}|null
     */
    private function createSession(?int $fps = null, ?int $width = null, ?int $height = null): ?array
    {
        try {
            $response = Http::withToken($this->key)
                ->timeout(20)
                ->post("{$this->base}/vm", [
                    'start_url'       => config('services.hyperbeam.start_url', 'about:blank'),
                    'region'          => config('services.hyperbeam.region', 'NA'),
                    'offline_timeout' => (int) config('services.hyperbeam.offline_timeout', 300),
                    'width'           => $width  ?? (int) config('services.hyperbeam.width', 1280),
                    'height'          => $height ?? (int) config('services.hyperbeam.height', 720),
                    'fps'             => $fps    ?? (int) config('services.hyperbeam.fps', 30),
                    // Everyone can control by default; cursor_data powers peer presence for control mgmt.
                    'default_roles'   => ['control', 'clipboard_copy', 'cursor_data'],
                ]);
        } catch (ConnectionException $e) {
            report($e);

            return null;
        }

        if (! $response->successful()) {
            report(new \RuntimeException("Hyperbeam create failed [{$response->status()}]: {$response->body()}"));

            return null;
        }

        return [
            'session_id'  => (string) $response->json('session_id'),
            'embed_url'   => (string) $response->json('embed_url'),
            'admin_token' => (string) $response->json('admin_token'),
        ];
    }

    private function alive(string $sessionId): bool
    {
        try {
            return Http::withToken($this->key)
                ->timeout(6)
                ->get("{$this->base}/vm/{$sessionId}")
                ->successful();
        } catch (ConnectionException $e) {
            return false;
        }
    }
}
