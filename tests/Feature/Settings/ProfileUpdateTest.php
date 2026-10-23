<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/settings/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Volt::test('settings.profile')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Volt::test('settings.profile')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_banner_can_be_uploaded_and_replaced(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('settings.profile')
            ->set('banner', UploadedFile::fake()->image('first.jpg', 1920, 400))
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $first = $user->refresh()->banner;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        Volt::test('settings.profile')
            ->set('banner', UploadedFile::fake()->image('second.jpg', 1920, 400))
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $second = $user->refresh()->banner;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_banner_must_be_an_image_up_to_2mb(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Volt::test('settings.profile')
            ->set('banner', UploadedFile::fake()->create('banner.pdf', 10, 'application/pdf'))
            ->call('updateProfileInformation')
            ->assertHasErrors(['banner']);

        Volt::test('settings.profile')
            ->set('banner', UploadedFile::fake()->image('big.jpg')->size(3000))
            ->call('updateProfileInformation')
            ->assertHasErrors(['banner']);
    }

    public function test_banner_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['banner' => 'banners/old.jpg']);
        Storage::disk('public')->put('banners/old.jpg', 'x');

        $this->actingAs($user);

        Volt::test('settings.profile')->call('removeBanner');

        $this->assertNull($user->refresh()->banner);
        Storage::disk('public')->assertMissing('banners/old.jpg');
    }

    public function test_user_page_shows_banner(): void
    {
        $user = User::factory()->create(['banner' => 'banners/mine.jpg']);

        $this->actingAs($user)
            ->get(route('users.show', $user->username))
            ->assertOk()
            ->assertSee('storage/banners/mine.jpg', false);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Volt::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $response
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertFalse(auth()->check());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Volt::test('settings.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }
}
