<?php

namespace Tests\Feature;

use App\Filament\Resources\FailedJobs\Pages\ManageFailedJobs;
use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\FailedJob;
use App\Models\Order;
use App\Notifications\CustomerInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class FailedMailsTest extends TestCase
{
    private CustomerUser $chef;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = Customer::factory()->create();
        $this->chef = CustomerUser::factory()->for($customer)->create(['email' => 'chef@example.test']);
        $this->order = Order::create(['number' => '2026-000042', 'customer_id' => $customer->id, 'submitted_at' => now()]);
    }

    /** Queues for real on the database queue, then fails the jobs the way the worker would. */
    private function failEverythingQueued(): void
    {
        config(['queue.default' => 'database']);

        Mail::to($this->chef)->queue(new OrderConfirmation($this->order));
        $this->chef->notify(new CustomerInvitation('token'));

        foreach (DB::table('jobs')->get() as $job) {
            app('queue.failer')->log('database', $job->queue, $job->payload, new RuntimeException("Expected response code 250 but got 421\nstack trace…"));
        }
        DB::table('jobs')->delete();
    }

    public function test_failed_mails_are_listed_with_what_and_for_whom(): void
    {
        $this->failEverythingQueued();

        $failed = FailedJob::all()->keyBy(fn (FailedJob $job) => $job->type());
        $this->assertSame(['Orderbevestiging (klant)', 'Uitnodiging portaal'], $failed->keys()->sort()->values()->all());

        $confirmation = $failed->get('Orderbevestiging (klant)');
        $this->assertSame('chef@example.test', $confirmation->recipient());
        $this->assertTrue($confirmation->order()->is($this->order));
        $this->assertSame('Expected response code 250 but got 421', $confirmation->reason());
        $this->assertSame('chef@example.test', $failed->get('Uitnodiging portaal')->recipient());

        $this->actingAs($this->superAdmin())
            ->get('/admin/failed-jobs')
            ->assertOk()
            ->assertSee('chef@example.test')
            ->assertSee('2026-000042');
    }

    public function test_a_mail_whose_order_is_gone_still_shows_up(): void
    {
        $this->failEverythingQueued();
        $this->order->delete();

        $confirmation = FailedJob::all()->first(fn (FailedJob $job) => $job->type() === 'Orderbevestiging (klant)');
        $this->assertNull($confirmation->recipient());
        $this->assertNull($confirmation->order());

        $this->actingAs($this->superAdmin())->get('/admin/failed-jobs')->assertOk();
    }

    public function test_retry_puts_the_mail_back_on_the_queue(): void
    {
        $this->failEverythingQueued();
        $job = FailedJob::first();
        Queue::fake();

        $this->actingAs($this->superAdmin());
        Livewire::test(ManageFailedJobs::class)->callTableAction('retry', $job);

        $this->assertModelMissing($job);
        $this->assertCount(1, Queue::pushedRaw());
    }

    public function test_retry_all(): void
    {
        $this->failEverythingQueued();
        Queue::fake();

        $this->actingAs($this->superAdmin());
        Livewire::test(ManageFailedJobs::class)->callAction('retryAll');

        $this->assertSame(0, FailedJob::count());
        $this->assertCount(2, Queue::pushedRaw());
    }

    public function test_it_follows_permissions(): void
    {
        $this->failEverythingQueued();

        $this->actingAs($this->staffWith(['orders.view']))->get('/admin/failed-jobs')->assertForbidden();

        $this->actingAs($this->staffWith(['failed-mails.view']));
        Livewire::test(ManageFailedJobs::class)
            ->assertTableActionHidden('retry', FailedJob::first())
            ->assertActionHidden('retryAll');
    }
}
