<?php

namespace Tests\Feature;

use App\Events\NekoSessionReloaded;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NekoReloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.neko.url'            => 'https://neko.test',
            'services.neko.admin_password' => 'admin-secret',
            'services.neko.password'       => 'guest-secret',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();

        return $user;
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->post('/neko/reload')->assertRedirect('/login');
    }

    public function test_non_admins_are_forbidden(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->post('/neko/reload')
            ->assertForbidden();

        // The guard must run before we ever touch the Neko host.
        Http::assertNothingSent();
    }

    public function test_admins_reset_the_room_and_the_reload_is_broadcast(): void
    {
        Event::fake([NekoSessionReloaded::class]);

        Http::fake([
            'neko.test/api/login' => Http::response([
                'id'      => 'bot-session',
                'token'   => 'tok',
                'profile' => ['is_admin' => true],
            ]),
            'neko.test/api/sessions' => Http::response([
                ['id' => 'viewer-1', 'profile' => ['name' => 'a'], 'state' => ['is_connected' => true]],
                ['id' => 'bot-session', 'profile' => ['name' => 'bot'], 'state' => ['is_connected' => false]],
            ]),
            '*' => Http::response(null, 204),
        ]);

        $this->actingAs($this->admin())
            ->from('/neko')
            ->post('/neko/reload')
            ->assertRedirect('/neko')
            ->assertSessionHas('neko_ok', true);

        Event::assertDispatched(NekoSessionReloaded::class);

        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/room/control/reset');
        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/sessions/viewer-1/disconnect');
        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/logout');

        // Disconnecting our own session would kill the auth the rest of the loop needs.
        Http::assertNotSent(fn ($r) => $r->url() === 'https://neko.test/api/sessions/bot-session/disconnect');
    }

    public function test_a_failing_neko_does_not_break_the_page(): void
    {
        Event::fake([NekoSessionReloaded::class]);

        Http::fake(['neko.test/api/login' => Http::response(null, 401)]);

        $this->actingAs($this->admin())
            ->from('/neko')
            ->post('/neko/reload')
            ->assertRedirect('/neko')
            ->assertSessionHas('neko_ok', false);

        Event::assertNotDispatched(NekoSessionReloaded::class);
    }
}
