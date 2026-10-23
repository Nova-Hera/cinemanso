<?php

namespace App\Http\Controllers;

use App\Events\NekoSessionReloaded;
use App\Services\Neko;

class NekoController extends Controller
{
    /**
     * "Sala" — embed the self-hosted Neko virtual browser, auto-logging in the
     * signed-in user. Neko decides admin (host) rights purely from which password
     * it receives, so that choice is made here, server-side, and never exposed to
     * the client: only `is_admin` users are ever sent the admin password.
     *
     * Neko handles control, presence and its own UI.
     */
    public function index(Neko $neko)
    {
        $url  = config('services.neko.url');
        $user = auth()->user();
        $src  = null;

        if ($url) {
            // Register this visit in Neko first (name + profile picture for its member list).
            $memberId = $neko->syncMember($user);

            $src = $url.'/?'.http_build_query([
                'usr'       => $memberId ?? $user->username,      // the Neko member created above
                'pwd'       => $neko->memberPassword($user),      // admin password -> host rights
                'show_side' => 1,                                 // open Neko's member/chat sidebar
            ]);
        }

        return view('neko.neko', ['src' => $src]);
    }

    /**
     * Admin-only: reset the shared room when it wedges — frees stuck control and
     * kicks every client so they re-handshake. The broadcast makes every open
     * Sala remount its iframe; the redirect covers the admin who pressed it.
     */
    public function reload(Neko $neko)
    {
        abort_unless(auth()->user()?->is_admin, 403);

        [$ok, $message] = $neko->resetSession();

        if ($ok) {
            // A broadcast failure must not undo the reset — the room was still reset,
            // and Neko's own client retries on its side — but it is reported.
            try {
                broadcast(new NekoSessionReloaded());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()
            ->with('neko_status', $message)
            ->with('neko_ok', $ok);
    }
}
