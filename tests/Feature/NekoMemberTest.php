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

    public function test_opening_the_room_registers_the_user_with_name_and_avatar(): void
    {
        Http::fake([
            'neko.test/api/login' => Http::response(['id' => 'cinemanso-bot', 'token' => 'tok']),
            '*'                   => Http::response(null, 204),
        ]);

        $user = User::factory()->create(['profile_picture' => 'profile-pictures/me.jpg']);

        $response = $this->actingAs($user)->get('/neko')->assertOk();

        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/members'
            && $r['username'] === 'u'.$user->id
            && $r['password'] === 'guest-secret'
            && $r['profile']['name'] === $user->username
            && str_ends_with($r['profile']['avatar'], '/storage/profile-pictures/me.jpg')
            && $r['profile']['is_admin'] === false);

        // The iframe logs in as that member, not as a name derived from the username.
        $response->assertSee('usr=u'.$user->id, false);
    }

    public function test_users_without_a_picture_get_the_default_avatar_and_admins_the_admin_password(): void
    {
        Http::fake([
            'neko.test/api/login' => Http::response(['id' => 'cinemanso-bot', 'token' => 'tok']),
            '*'                   => Http::response(null, 204),
        ]);

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
        Http::fake([
            'neko.test/api/login'   => Http::response(['id' => 'cinemanso-bot', 'token' => 'tok']),
            'neko.test/api/members' => Http::response(['code' => 422, 'message' => 'member already exists'], 422),
            '*'                     => Http::response(null, 204),
        ]);

        $user = User::factory()->create();

        $this->assertTrue(app(Neko::class)->syncMember($user));

        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/members/u'.$user->id);
        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/members/u'.$user->id.'/password');
        Http::assertSent(fn ($r) => $r->url() === 'https://neko.test/api/logout');
    }

    public function test_the_room_still_opens_when_neko_is_down(): void
    {
        Http::fake(['neko.test/*' => Http::response(null, 500)]);

        $this->actingAs(User::factory()->create())->get('/neko')->assertOk();
    }
}
