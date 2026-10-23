<?php

namespace Tests\Feature;

use App\Events\WheelSpun;
use App\Events\WheelStateChanged;
use App\Livewire\Wheel;
use App\Models\Movie;
use App\Models\User;
use App\Models\WheelDraw;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class WheelTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;
    private User $bob;
    private Testable $aliceWheel;
    private Testable $bobWheel;

    /** Two users with the wheel open and a movie each on the watchlist. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create();
        $this->bob   = User::factory()->create();

        foreach ([$this->alice, $this->bob] as $owner) {
            Movie::create([
                'title'    => 'Filme '.$owner->id,
                'slug'     => 'filme-'.$owner->id,
                'poster'   => 'poster.jpg',
                'status'   => 'watchlist',
                'added_by' => $owner->id,
            ]);
        }
    }

    private function openWheels(): void
    {
        $this->aliceWheel = Livewire::actingAs($this->alice)->test(Wheel::class);
        $this->bobWheel   = Livewire::actingAs($this->bob)->test(Wheel::class);
    }

    /** actingAs is global, so switch to the clicker before every call. */
    private function click(User $user, Testable $wheel): void
    {
        $this->actingAs($user);
        $wheel->call('clickCenter');
    }

    public function test_joining_announces_the_new_presence(): void
    {
        Event::fake([WheelSpun::class, WheelStateChanged::class]);

        $this->openWheels();

        Event::assertDispatchedTimes(WheelStateChanged::class, 2);
    }

    public function test_a_non_final_ready_click_pushes_the_new_count_without_spinning(): void
    {
        $this->openWheels();

        Event::fake([WheelSpun::class, WheelStateChanged::class]);

        $this->click($this->alice, $this->aliceWheel);

        Event::assertDispatchedTimes(WheelStateChanged::class, 1);
        Event::assertNotDispatched(WheelSpun::class);
        $this->assertSame(0, WheelDraw::count());
        $this->assertSame(1, DB::table('wheel_votes')->where('ready', true)->count());
    }

    public function test_the_final_ready_click_draws_a_movie_and_pushes_the_spin(): void
    {
        $this->openWheels();
        $this->click($this->bob, $this->bobWheel);

        Event::fake([WheelSpun::class, WheelStateChanged::class]);

        $this->click($this->alice, $this->aliceWheel);

        $draw = WheelDraw::sole();
        Event::assertDispatched(WheelSpun::class, fn (WheelSpun $e) => $e->drawId === $draw->id);
        Event::assertNotDispatched(WheelStateChanged::class);
        $this->assertSame('watching', $draw->movie->status);
        $this->assertSame(0, DB::table('wheel_votes')->where('ready', true)->count());
    }

    public function test_a_failing_broadcaster_neither_breaks_the_click_nor_goes_unnoticed(): void
    {
        $this->openWheels();
        $this->click($this->bob, $this->bobWheel);

        Exceptions::fake();
        $this->mock(BroadcastFactory::class)
            ->shouldReceive('event')
            ->andThrow(new \RuntimeException('push is down'));

        $this->click($this->alice, $this->aliceWheel);

        $this->assertSame(1, WheelDraw::count());
        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'push is down');
    }
}
