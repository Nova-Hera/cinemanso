<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // When mail.smtp.connect_host is set, connect to that address instead of
        // resolving "host" via DNS, while still presenting "host" for TLS SNI/EHLO.
        // Needed here because MAIL_HOST (cinemanso.newhera.net) is Cloudflare-proxied
        // for the website and no longer resolves to the real mail server.
        Mail::extend('smtp', function (array $config) {
            $scheme = $config['scheme'] ?? (($config['port'] ?? null) == 465 ? 'smtps' : 'smtp');

            $dsnOptions = $config;

            if (isset($config['connect_host'])) {
                $dsnOptions['local_domain'] = $config['host'];
            }

            $transport = (new EsmtpTransportFactory)->create(new Dsn(
                $scheme,
                $config['connect_host'] ?? $config['host'],
                $config['username'] ?? null,
                $config['password'] ?? null,
                $config['port'] ?? null,
                $dsnOptions
            ));

            $stream = $transport->getStream();

            if ($stream instanceof SocketStream) {
                if (isset($config['connect_host'])) {
                    $stream->setStreamOptions(array_replace_recursive(
                        $stream->getStreamOptions(),
                        ['ssl' => ['peer_name' => $config['host']]]
                    ));
                }

                if (isset($config['source_ip'])) {
                    $stream->setSourceIp($config['source_ip']);
                }

                if (isset($config['timeout'])) {
                    $stream->setTimeout($config['timeout']);
                }
            }

            return $transport;
        });
    }
}
