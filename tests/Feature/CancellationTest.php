<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\StatusGroup;
use App\Models\Order;
use App\Models\OrderCase;
use App\Models\OrderStatus;
use App\Models\User;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DictionarySeeder::class);
        $this->user = User::factory()->create(['name' => 'Ola Handlowiec']);
        $this->order = Order::create([
            'baselinker_order_id' => 48211,
            'shop_order_id' => '10230',
            'customer_name' => 'Jan Kowalski',
            'total' => 412.99,
            'paid' => 412.99,
            'order_status_id' => OrderStatus::where('group', StatusGroup::WRealizacji)->value('id'),
        ]);
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'order_number' => '48211',
            'reason' => 'rezygnacja_klienta',
            'requested_on' => now()->format('Y-m-d'),
            'shipment_hold' => 'nie_nadano',
            'refund_status' => 'do_zwrotu',
            'refund_amount' => '412.99',
        ], $overrides);
    }

    public function test_required_fields_are_enforced_in_polish(): void
    {
        $this->actingAs($this->user)
            ->post(route('cancellations.store'), [])
            ->assertSessionHasErrors([
                'order_number' => 'Pole numer zamówienia jest wymagane.',
            ]);

        $this->actingAs($this->user)
            ->post(route('cancellations.store'), ['order_number' => '48211'])
            ->assertSessionHasErrors([
                'reason' => 'Pole powód jest wymagane.',
                'requested_on' => 'Pole data zgłoszenia jest wymagane.',
                'shipment_hold' => 'Pole przesyłka jest wymagane.',
                'refund_status' => 'Pole zwrot pieniędzy jest wymagane.',
            ]);

        $this->assertSame(0, OrderCase::count());
    }

    public function test_conditional_fields_are_required(): void
    {
        $this->actingAs($this->user)
            ->post(route('cancellations.store'), $this->valid([
                'reason' => 'inny',
                'refund_status' => 'zwrocono',
                'refund_amount' => '',
            ]))
            ->assertSessionHasErrors(['reason_details', 'refund_amount', 'refunded_on']);

        $this->actingAs($this->user)
            ->post(route('cancellations.store'), $this->valid(['requested_on' => now()->addDay()->format('Y-m-d')]))
            ->assertSessionHasErrors(['requested_on' => 'Pole data zgłoszenia nie może być datą z przyszłości.']);
    }

    public function test_unknown_order_number_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('cancellations.store'), $this->valid(['order_number' => '999']))
            ->assertSessionHasErrors('order_number');
    }

    public function test_cancellation_can_be_created_by_shop_number_and_is_logged(): void
    {
        $response = $this->actingAs($this->user)->post(route('cancellations.store'), $this->valid(['order_number' => '10230']));

        $case = OrderCase::sole();
        $response->assertRedirect(route('cancellations.show', $case));
        $this->assertSame($this->order->id, $case->order_id);
        $this->assertSame(CaseStatus::Nowa, $case->status);
        $this->assertSame($this->user->id, $case->created_by);
        $this->assertSame('utworzono', $case->events()->sole()->action);
    }

    public function test_second_open_cancellation_for_same_order_is_blocked(): void
    {
        $this->actingAs($this->user)->post(route('cancellations.store'), $this->valid());

        $this->actingAs($this->user)
            ->post(route('cancellations.store'), $this->valid())
            ->assertSessionHasErrors('order_number');

        $this->assertSame(1, OrderCase::count());
    }

    public function test_cannot_close_before_refund(): void
    {
        $this->actingAs($this->user)->post(route('cancellations.store'), $this->valid());
        $case = OrderCase::sole();

        $this->actingAs($this->user)
            ->put(route('cancellations.update', $case), $this->valid(['status' => 'zakonczona']))
            ->assertSessionHasErrors('status');

        $this->assertSame(CaseStatus::Nowa, $case->fresh()->status);
    }

    public function test_update_records_history_and_closes(): void
    {
        $this->actingAs($this->user)->post(route('cancellations.store'), $this->valid());
        $case = OrderCase::sole();

        $this->actingAs($this->user)
            ->put(route('cancellations.update', $case), $this->valid([
                'status' => 'zakonczona',
                'refund_status' => 'zwrocono',
                'refunded_on' => now()->format('Y-m-d'),
            ]))
            ->assertRedirect(route('cancellations.show', $case));

        $case->refresh();
        $this->assertSame(CaseStatus::Zakonczona, $case->status);
        $this->assertNotNull($case->closed_at);

        $event = $case->events()->where('action', 'zmieniono')->sole();
        $this->assertSame(['Nowa', 'Zakończona'], $event->changes['status']);
        $this->assertSame(['Do zwrotu', 'Zwrócono'], $event->changes['refund_status']);
        $this->assertArrayNotHasKey('reason', $event->changes);

        $this->actingAs($this->user)->get(route('cancellations.show', $case))
            ->assertOk()
            ->assertSee('Historia zmian')
            ->assertSee('Ola Handlowiec')
            ->assertSee('Zwrot pieniędzy');
    }

    public function test_warnings_compare_case_with_baselinker_data(): void
    {
        $this->order->update(['tracking_number' => '680000000000000001']);

        $this->actingAs($this->user)->post(route('cancellations.store'), $this->valid([
            'refund_status' => 'nie_dotyczy',
            'refund_amount' => '',
        ]));
        $warnings = OrderCase::sole()->warnings();

        $this->assertCount(3, $warnings);
        $this->assertStringContainsString('numer przesyłki', $warnings[0]);
        $this->assertStringContainsString('zwrot nie dotyczy', $warnings[1]);
        $this->assertStringContainsString('a nie anulowane', $warnings[2]);

        $this->actingAs($this->user)->get(route('cancellations.index'))->assertOk()->assertSee('3 ostrzeżenia');
    }

    public function test_no_warnings_when_everything_matches(): void
    {
        $this->order->update(['order_status_id' => OrderStatus::where('group', StatusGroup::AnulowaneZwrocone)->value('id')]);

        $this->actingAs($this->user)->post(route('cancellations.store'), $this->valid([
            'refund_status' => 'zwrocono',
            'refunded_on' => now()->format('Y-m-d'),
        ]));

        $this->assertSame([], OrderCase::sole()->warnings());
    }

    public function test_only_admin_can_delete(): void
    {
        $this->actingAs($this->user)->post(route('cancellations.store'), $this->valid());
        $case = OrderCase::sole();

        $this->actingAs($this->user)->delete(route('cancellations.destroy', $case))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete(route('cancellations.destroy', $case))->assertRedirect(route('cancellations.index'));

        $this->assertSame(0, OrderCase::count());
    }

    public function test_order_page_links_to_new_cancellation(): void
    {
        $this->actingAs($this->user)->get(route('orders.show', $this->order))
            ->assertOk()
            ->assertSee(route('cancellations.create', ['zamowienie' => $this->order->id]), false);

        $this->actingAs($this->user)->get(route('cancellations.create', ['zamowienie' => $this->order->id]))
            ->assertOk()
            ->assertSee('Jan Kowalski');
    }
}
