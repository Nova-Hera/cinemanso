<?php

namespace App\Http\Controllers;

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
    public function index()
    {
        $url  = config('services.neko.url');
        $user = auth()->user();
        $src  = null;

        if ($url) {
            $src = $url.'/?'.http_build_query([
                'usr'       => $user->username ?: $user->name,   // shown in Neko's member list
                'pwd'       => $user->is_admin
                    ? config('services.neko.admin_password')      // host  -> admin rights
                    : config('services.neko.password'),           // guest -> normal user
                'show_side' => 1,                                 // open Neko's member/chat sidebar
            ]);
        }

        return view('neko.neko', ['src' => $src]);
    }
}
