<?php

namespace Tests\Feature;

use App\Enums\StatusGroup;
use App\Models\DiscrepancyDismissal;
use App\Models\Order;
use App\Models\OrderCase;
use App\Models\OrderStatus;
use App\Models\User;
use App\Services\Discrepancies\DiscrepancyFinder;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscrepancyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DictionarySeeder::class);
        $this->user = User::factory()->create();
    }

    private function order(StatusGroup $group, array $attributes = []): Order
    {
        static $number = 50000;

        return Order::create($attributes + [
            'baselinker_order_id' => ++$number,
            'customer_name' => 'Klient '.$number,
            'total' => 100,
            'paid' => 100,
            'ordered_at' => now()->subDay(),
            'order_status_id' => OrderStatus::where('group', $group)->value('id'),
        ]);
    }

    private function cancellation(Order $order, array $attributes = []): OrderCase
    {
        return OrderCase::create($attributes + [
            'order_id' => $order->id,
            'reason' => 'rezygnacja_klienta',
            'requested_on' => now(),
            'refund_status' => 'do_zwrotu',
            'refund_amount' => 100,
            'shipment_hold' => 'nie_nadano',
            'created_by' => $this->user->id,
        ]);
    }

    private function rules(): array
    {
        return app(DiscrepancyFinder::class)->find()->map(fn ($d) => $d->rule.':'.$d->order->baselinker_order_id)->all();
    }

    public function test_consistent_orders_have_no_discrepancies(): void
    {
        $this->order(StatusGroup::WRealizacji);
        $this->order(StatusGroup::Wyslane, ['tracking_number' => '123']);
        $cancelled = $this->order(StatusGroup::AnulowaneZwrocone);
        $this->cancellation($cancelled, ['refund_status' => 'zwrocono', 'refunded_on' => now()]);
        $this->order(StatusGroup::AnulowaneZwrocone, ['paid' => 0]);

        $this->assertSame([], $this->rules());
    }

    public function test_cancelled_order_with_tracking_number_is_critical(): void
    {
        $order = $this->order(StatusGroup::AnulowaneZwrocone, ['tracking_number' => '680001']);
        $this->cancellation($order);

        $this->assertSame(["anulowane_z_przesylka:{$order->baselinker_order_id}"], $this->rules());

        // Po zapisaniu w anulacji, że paczkę zatrzymano, rozbieżność znika.
        $order->cases()->update(['shipment_hold' => 'wstrzymano']);
        $this->assertSame([], $this->rules());
    }

    public function test_refund_while_order_is_still_processed(): void
    {
        $refunded = $this->order(StatusGroup::WRealizacji);
        $this->cancellation($refunded, ['refund_status' => 'zwrocono', 'refunded_on' => now()]);
        $pending = $this->order(StatusGroup::Wyslane);
        $this->cancellation($pending);

        $this->assertEqualsCanonicalizing([
            "zwrot_a_realizacja:{$refunded->baselinker_order_id}",
            "anulacja_tylko_w_panelu:{$pending->baselinker_order_id}",
        ], $this->rules());
    }

    public function test_paid_order_cancelled_only_in_baselinker(): void
    {
        $order = $this->order(StatusGroup::AnulowaneZwrocone);

        $this->assertSame(["anulacja_tylko_w_baselinkerze:{$order->baselinker_order_id}"], $this->rules());
    }

    public function test_unknown_status_and_old_orders_are_skipped(): void
    {
        $this->order(StatusGroup::DoUstalenia);
        $this->order(StatusGroup::AnulowaneZwrocone, ['ordered_at' => now()->subDays(200)]);
        Order::create(['baselinker_order_id' => 1, 'paid' => 10, 'ordered_at' => now()]);

        $this->assertSame([], $this->rules());
    }

    public function test_dismissal_requires_note_and_hides_discrepancy(): void
    {
        $order = $this->order(StatusGroup::AnulowaneZwrocone);

        $this->actingAs($this->user)->get('/do-wyjasnienia')->assertOk()->assertSee('Anulacja tylko w BaseLinkerze');

        $this->actingAs($this->user)
            ->post(route('discrepancies.dismiss'), ['order_id' => $order->id, 'rule' => 'anulacja_tylko_w_baselinkerze', 'note' => ''])
            ->assertSessionHasErrors(['note' => 'Pole wyjaśnienie jest wymagane.']);

        $this->actingAs($this->user)
            ->post(route('discrepancies.dismiss'), ['order_id' => $order->id, 'rule' => 'anulacja_tylko_w_baselinkerze', 'note' => 'Zwrot zrobiony w PayU'])
            ->assertSessionHasNoErrors();

        $this->assertSame([], $this->rules());
        $this->actingAs($this->user)->get('/do-wyjasnienia')->assertOk()->assertSee('Zwrot zrobiony w PayU');

        $this->actingAs($this->user)->delete(route('discrepancies.restore', DiscrepancyDismissal::sole()));
        $this->assertCount(1, $this->rules());
    }

    public function test_dashboard_and_order_page_show_discrepancies(): void
    {
        $order = $this->order(StatusGroup::AnulowaneZwrocone, ['tracking_number' => '680001']);

        $this->actingAs($this->user)->get('/')->assertOk()->assertSee('Anulowane, a paczka nadana')->assertSee((string) $order->baselinker_order_id);
        $this->actingAs($this->user)->get(route('orders.show', $order))->assertOk()->assertSee('Sprawdź, czy paczka nie wyszła');
        $this->actingAs($this->user)->get('/do-wyjasnienia?waga=ostrzezenie')->assertOk()->assertSee('Anulacja tylko w BaseLinkerze');
    }
}
