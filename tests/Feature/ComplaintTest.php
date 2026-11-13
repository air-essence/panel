<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\StatusGroup;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\OrderStatus;
use App\Models\User;
use App\Services\Discrepancies\DiscrepancyFinder;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DictionarySeeder::class);
        $this->user = User::factory()->create(['name' => 'Kasia Serwis']);
        $this->order = Order::create([
            'baselinker_order_id' => 48211,
            'customer_name' => 'Jan Kowalski',
            'total' => 599,
            'paid' => 599,
            'ordered_at' => now()->subDays(40),
            'order_status_id' => OrderStatus::where('group', StatusGroup::Zakonczone)->value('id'),
        ]);
        $this->order->items()->create(['name' => 'Oczyszczacz AE-100', 'ean' => '5901234123457', 'quantity' => 1, 'price' => 599]);
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'order_number' => '48211',
            'product_name' => 'Oczyszczacz AE-100',
            'reported_on' => now()->format('Y-m-d'),
            'defect_description' => 'Nie włącza się po tygodniu',
            'product_location' => 'w_drodze',
        ], $overrides);
    }

    private function rules(): array
    {
        return app(DiscrepancyFinder::class)->find()->pluck('rule')->all();
    }

    public function test_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('complaints.store'), ['order_number' => '48211'])
            ->assertSessionHasErrors(['product_name', 'reported_on', 'defect_description', 'product_location']);

        $this->actingAs($this->user)
            ->post(route('complaints.store'), $this->valid(['product_location' => 'w_serwisie', 'resolution' => 'naprawa', 'drive_link' => 'dysk']))
            ->assertSessionHasErrors(['service_name', 'service_sent_on', 'resolved_on', 'drive_link']);
    }

    public function test_product_can_be_picked_from_order_items(): void
    {
        $item = $this->order->items()->sole();

        $this->actingAs($this->user)
            ->post(route('complaints.store'), $this->valid(['product_name' => '', 'order_item_id' => $item->id]))
            ->assertSessionHasNoErrors();

        $complaint = OrderComplaint::sole();
        $this->assertSame('Oczyszczacz AE-100', $complaint->product_name);
        $this->assertSame('5901234123457', $complaint->ean);

        $this->actingAs($this->user)->get(route('complaints.create', ['zamowienie' => $this->order->id]))
            ->assertOk()->assertSee('Oczyszczacz AE-100 · EAN 5901234123457');
    }

    public function test_item_from_another_order_is_rejected(): void
    {
        $other = Order::create(['baselinker_order_id' => 1]);
        $foreign = $other->items()->create(['name' => 'Obcy', 'quantity' => 1, 'price' => 1]);

        $this->actingAs($this->user)
            ->post(route('complaints.store'), $this->valid(['order_item_id' => $foreign->id]))
            ->assertSessionHasErrors('order_item_id');
    }

    public function test_closing_requires_response_resolution_and_wz(): void
    {
        $this->actingAs($this->user)->post(route('complaints.store'), $this->valid());
        $complaint = OrderComplaint::sole();

        $this->actingAs($this->user)
            ->put(route('complaints.update', $complaint), $this->valid(['status' => 'zakonczona', 'resolution' => 'nowy_produkt', 'resolved_on' => now()->format('Y-m-d')]))
            ->assertSessionHasErrors(['status' => 'Nie można zakończyć reklamacji. Uzupełnij: datę odpowiedzi dla klienta, numer WZ wysłanego produktu.']);

        $this->actingAs($this->user)
            ->put(route('complaints.update', $complaint), $this->valid([
                'status' => 'zakonczona',
                'product_location' => 'zutylizowany',
                'responded_on' => now()->format('Y-m-d'),
                'resolution' => 'nowy_produkt',
                'resolved_on' => now()->format('Y-m-d'),
                'wz_number' => 'WZ/55/2026',
            ]))
            ->assertSessionHasNoErrors();

        $complaint->refresh();
        $this->assertSame(CaseStatus::Zakonczona, $complaint->status);
        $this->assertSame([null, 'Nowy produkt'], $complaint->events()->where('action', 'zmieniono')->sole()->changes['resolution']);
    }

    public function test_response_deadline_is_flagged(): void
    {
        $this->actingAs($this->user)->post(route('complaints.store'), $this->valid(['reported_on' => now()->subDays(15)->format('Y-m-d')]));

        $this->assertSame(['reklamacja_bez_odpowiedzi'], $this->rules());
        $this->actingAs($this->user)->get('/reklamacje?status=bez_odpowiedzi')->assertOk()->assertSee('po terminie');

        OrderComplaint::sole()->update(['responded_on' => now()]);
        $this->assertSame([], $this->rules());
    }

    public function test_long_service_and_refund_without_return(): void
    {
        $this->actingAs($this->user)->post(route('complaints.store'), $this->valid([
            'product_location' => 'w_serwisie',
            'service_name' => 'RG Media',
            'service_sent_on' => now()->subDays(31)->format('Y-m-d'),
            'responded_on' => now()->subDays(30)->format('Y-m-d'),
            'resolution' => 'zwrot_pieniedzy',
            'resolved_on' => now()->format('Y-m-d'),
        ]));

        $this->assertEqualsCanonicalizing(['reklamacja_dlugo_w_serwisie', 'reklamacja_zwrot_bez_zwrotu'], $this->rules());
        $this->actingAs($this->user)->get('/do-wyjasnienia')->assertOk()->assertSee('RG Media')->assertSee('Dodaj zwrot');
    }

    public function test_complaint_status_in_baselinker_without_case(): void
    {
        $this->order->update(['order_status_id' => OrderStatus::where('group', StatusGroup::Reklamacja)->value('id')]);
        $this->assertSame(['reklamacja_tylko_w_baselinkerze'], $this->rules());

        $this->actingAs($this->user)->post(route('complaints.store'), $this->valid());
        $this->assertSame([], $this->rules());
    }

    public function test_only_admin_can_delete(): void
    {
        $this->actingAs($this->user)->post(route('complaints.store'), $this->valid());
        $complaint = OrderComplaint::sole();

        $this->actingAs($this->user)->delete(route('complaints.destroy', $complaint))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete(route('complaints.destroy', $complaint))->assertRedirect(route('complaints.index'));
    }
}
