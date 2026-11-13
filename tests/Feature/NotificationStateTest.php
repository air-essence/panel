<?php

namespace Tests\Feature;

use App\Enums\StatusGroup;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\SyncRun;
use App\Models\User;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DictionarySeeder::class);
    }

    private function cancelledOrder(array $attributes): Order
    {
        return Order::create($attributes + [
            'paid' => 100,
            'total' => 100,
            'ordered_at' => now(),
            'order_status_id' => OrderStatus::where('group', StatusGroup::AnulowaneZwrocone)->value('id'),
        ]);
    }

    public function test_guests_cannot_read_state(): void
    {
        $this->getJson('/powiadomienia/stan')->assertUnauthorized();
    }

    public function test_state_lists_only_critical_discrepancies(): void
    {
        $critical = $this->cancelledOrder(['baselinker_order_id' => 1, 'tracking_number' => '680001']);
        $this->cancelledOrder(['baselinker_order_id' => 2]);

        $this->actingAs(User::factory()->create())
            ->getJson('/powiadomienia/stan')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(1, 'critical')
            ->assertJsonPath('critical.0.key', $critical->id.'|anulowane_z_przesylka')
            ->assertJsonPath('critical.0.url', route('orders.show', $critical))
            ->assertJsonPath('sync_error', null);
    }

    public function test_state_reports_failed_sync(): void
    {
        $run = SyncRun::create(['source' => 'baselinker', 'started_at' => now(), 'finished_at' => now(), 'error' => 'BaseLinker zwrócił błąd ERROR_BAD_TOKEN']);

        $this->actingAs(User::factory()->create())
            ->getJson('/powiadomienia/stan')
            ->assertOk()
            ->assertJsonPath('sync_error.key', 'sync|'.$run->id)
            ->assertJsonPath('sync_error.message', 'BaseLinker zwrócił błąd ERROR_BAD_TOKEN');
    }

    public function test_layout_contains_polling_script(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('nav-discrepancies', false)
            ->assertSee('"\/powiadomienia\/stan"', false);
    }
}
