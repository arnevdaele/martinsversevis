<?php

namespace Tests\Feature;

use App\Jobs\QueueHeartbeat;
use App\Support\BackgroundHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackgroundHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.health_checks' => true, 'queue.default' => 'database']);
    }

    private function queueMail(int $minutesAgo): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Mail\\OrderConfirmation']),
            'attempts' => 0,
            'available_at' => now()->subMinutes($minutesAgo)->getTimestamp(),
            'created_at' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);
    }

    public function test_all_quiet_while_both_heartbeats_are_fresh(): void
    {
        BackgroundHealth::beat(BackgroundHealth::SCHEDULER);
        (new QueueHeartbeat)->handle();

        $this->assertSame([], BackgroundHealth::problems());
        $this->actingAs($this->superAdmin())->get('/admin')->assertOk()->assertDontSee('Achtergrondtaken liggen stil');
    }

    public function test_a_stopped_worker_is_reported_with_the_mails_waiting(): void
    {
        BackgroundHealth::beat(BackgroundHealth::SCHEDULER);
        $this->travel(-20)->minutes();
        BackgroundHealth::beat(BackgroundHealth::QUEUE);
        $this->travelBack();
        $this->queueMail(3);

        $problems = BackgroundHealth::problems();

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('wachtrij', $problems[0]);
        $this->assertStringContainsString('1 e-mail(s)', $problems[0]);

        $this->actingAs($this->superAdmin())->get('/admin')->assertSee('Achtergrondtaken liggen stil');
        $this->actingAs($this->staffWith(['orders.view']))->get('/admin/orders')->assertDontSee('Achtergrondtaken liggen stil');
    }

    public function test_a_stopped_scheduler_is_reported_and_stuck_mails_still_flag_the_queue(): void
    {
        Cache::forget('health.'.BackgroundHealth::SCHEDULER);
        $this->assertCount(1, BackgroundHealth::problems());

        $this->queueMail(30);
        $this->assertCount(2, BackgroundHealth::problems());
    }

    public function test_the_heartbeat_job_is_not_counted_as_a_waiting_mail(): void
    {
        BackgroundHealth::beat(BackgroundHealth::SCHEDULER);
        QueueHeartbeat::dispatch();

        $this->assertStringContainsString('pas verstuurd', BackgroundHealth::problems()[0]);
    }

    public function test_off_unless_enabled(): void
    {
        config(['queue.health_checks' => false]);

        $this->assertSame([], BackgroundHealth::problems());
    }
}
