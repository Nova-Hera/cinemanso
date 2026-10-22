<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Neko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NekoMemberTest extends TestCase
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

    private function fakeNeko(array $sessions = [], array $overrides = []): void
    {
        Http::fake($overrides + [
            'neko.test/api/login'    => Http::response(['id' => 'cinemanso-bot', 'token' => 'tok']),
            'neko.test/api/sessions' => Http::response($sessions),
            '*'                      => Http::response(null, 204),
        ]);
    }

    public function test_opening_the_room_registers_the_user_by_username_with_avatar(): void
    {
        $this->fakeNeko();

        $user = User::factory()->create(['profile_picture' => 'profile-pictures/me.jpg']);

        $response = $this->actingAs($user)->get('/neko')->assertOk();

        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/members'
            && $r['username'] === $user->username
            && $r['password'] === 'guest-secret'
            && $r['profile']['name'] === $user->username
            && str_ends_with($r['profile']['avatar'], '/storage/profile-pictures/me.jpg')
            && $r['profile']['is_admin'] === false);

        $response->assertSee('usr='.$user->username.'&', false);
    }

    public function test_users_without_a_picture_get_the_default_avatar_and_admins_the_admin_password(): void
    {
        $this->fakeNeko();

        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $this->actingAs($admin)->get('/neko')->assertOk();

        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/members'
            && $r['password'] === 'admin-secret'
            && $r['profile']['is_admin'] === true
            && str_ends_with($r['profile']['avatar'], '/storage/default-profile.png'));
    }

    public function test_an_existing_member_is_updated_instead(): void
    {
        $this->fakeNeko([], [
            'neko.test/api/members' => Http::response(['code' => 422, 'message' => 'member already exists'], 422),
        ]);

        $user = User::factory()->create();

        $this->assertSame($user->username, app(Neko::class)->syncMember($user));

        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/members/'.$user->username);
        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/members/'.$user->username.'/password');
        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/logout');
    }

    public function test_a_still_connected_session_of_the_user_is_disconnected_so_the_login_works(): void
    {
        $user = User::factory()->create();

        $this->fakeNeko([
            ['id' => $user->username, 'state' => ['is_connected' => true]],
            ['id' => 'someone-else', 'state' => ['is_connected' => true]],
        ]);

        app(Neko::class)->syncMember($user);

        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/sessions/'.$user->username.'/disconnect');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'someone-else'));
    }

    public function test_names_neko_rejects_are_sanitized_for_the_member_id(): void
    {
        $user = User::factory()->create(['username' => 'ana maria/silva']);

        $this->assertSame('ana-maria-silva', app(Neko::class)->memberId($user));
    }

    public function test_the_room_still_opens_when_neko_is_down(): void
    {
        Http::fake(['neko.test/*' => Http::response(null, 500)]);

        $this->actingAs(User::factory()->create())->get('/neko')->assertOk();
    }
}
