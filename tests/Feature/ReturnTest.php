<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\StatusGroup;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderStatus;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\Discrepancies\DiscrepancyFinder;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DictionarySeeder::class);
        $this->user = User::factory()->create(['name' => 'Kasia Serwis']);

        $channel = SalesChannel::where('name', 'air-essence.store')->firstOrFail();
        $channel->update(['company_id' => Company::where('short_name', 'Swing')->value('id')]);

        $this->order = Order::create([
            'baselinker_order_id' => 48211,
            'shop_order_id' => '10230',
            'customer_name' => 'Jan Kowalski',
            'total' => 599,
            'paid' => 599,
            'sales_channel_id' => $channel->id,
            'ordered_at' => now()->subDays(10),
            'order_status_id' => OrderStatus::where('group', StatusGroup::Zakonczone)->value('id'),
        ]);
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'order_number' => '48211',
            'kind' => 'odstapienie',
            'requested_on' => now()->format('Y-m-d'),
            'goods_status' => 'w_drodze',
            'amount' => '599',
            'refund_method' => 'ta_sama_metoda',
            'sale_document' => 'paragon',
            'sale_document_number' => 'PAR/123/2026',
        ], $overrides);
    }

    private function rules(): array
    {
        return app(DiscrepancyFinder::class)->find()->pluck('rule')->all();
    }

    public function test_required_fields_are_enforced(): void
    {
        $this->actingAs($this->user)
            ->post(route('returns.store'), ['order_number' => '48211'])
            ->assertSessionHasErrors(['kind', 'requested_on', 'goods_status', 'amount', 'refund_method', 'sale_document', 'sale_document_number']);

        $this->assertSame(0, OrderReturn::count());
    }

    public function test_arrived_goods_need_date_and_location_and_transfer_needs_account(): void
    {
        $this->actingAs($this->user)
            ->post(route('returns.store'), $this->valid(['goods_status' => 'sprawny', 'refund_method' => 'przelew']))
            ->assertSessionHasErrors(['received_on', 'goods_location', 'refund_account']);

        $this->actingAs($this->user)
            ->post(route('returns.store'), $this->valid(['refund_method' => 'przelew', 'refund_account' => '12 3456']))
            ->assertSessionHasErrors(['refund_account' => 'Numer konta musi mieć 26 cyfr (można poprzedzić go „PL”).']);
    }

    public function test_discount_must_not_expect_goods(): void
    {
        $this->actingAs($this->user)
            ->post(route('returns.store'), $this->valid(['kind' => 'rabat', 'goods_status' => 'w_drodze']))
            ->assertSessionHasErrors('goods_status');

        $this->actingAs($this->user)
            ->post(route('returns.store'), $this->valid(['kind' => 'odstapienie', 'goods_status' => 'nie_dotyczy']))
            ->assertSessionHasErrors('goods_status');

        $this->actingAs($this->user)
            ->post(route('returns.store'), $this->valid(['kind' => 'rabat', 'goods_status' => 'nie_dotyczy', 'amount' => '50']))
            ->assertSessionHasNoErrors();
    }

    public function test_return_is_created_with_normalized_account_and_history(): void
    {
        $this->actingAs($this->user)->post(route('returns.store'), $this->valid([
            'order_number' => '10230',
            'refund_method' => 'przelew',
            'refund_account' => 'PL61 1090 1014 0000 0712 1981 2874',
        ]))->assertRedirect();

        $return = OrderReturn::sole();
        $this->assertSame('PL61109010140000071219812874', $return->refund_account);
        $this->assertSame('utworzono', $return->events()->sole()->action);

        $this->actingAs($this->user)->get(route('returns.show', $return))
            ->assertOk()
            ->assertSee('Swing 2 Harmony')
            ->assertSee('PAR/123/2026');
    }

    public function test_cannot_close_without_refund_correction_and_goods(): void
    {
        $this->actingAs($this->user)->post(route('returns.store'), $this->valid());
        $return = OrderReturn::sole();

        $this->actingAs($this->user)
            ->put(route('returns.update', $return), $this->valid(['status' => 'zakonczona']))
            ->assertSessionHasErrors(['status' => 'Nie można zakończyć zwrotu. Uzupełnij: datę zwrotu pieniędzy, numer korekty albo PZ, informację, że towar dotarł.']);

        $this->actingAs($this->user)
            ->put(route('returns.update', $return), $this->valid([
                'status' => 'zakonczona',
                'goods_status' => 'sprawny',
                'received_on' => now()->format('Y-m-d'),
                'goods_location' => 'Kalwaryjska',
                'refunded_on' => now()->format('Y-m-d'),
                'correction_document_number' => 'KOR/1/2026',
            ]))
            ->assertSessionHasNoErrors();

        $return->refresh();
        $this->assertSame(CaseStatus::Zakonczona, $return->status);
        $changes = $return->events()->where('action', 'zmieniono')->sole()->changes;
        $this->assertSame(['Czekamy na paczkę', 'Dotarł, sprawny (do odsprzedaży)'], $changes['goods_status']);
        $this->assertSame([null, 'Kalwaryjska'], $changes['goods_location']);
    }

    public function test_overdue_refund_is_flagged_and_filterable(): void
    {
        $this->actingAs($this->user)->post(route('returns.store'), $this->valid(['requested_on' => now()->subDays(15)->format('Y-m-d')]));

        $this->assertSame(['zwrot_po_terminie'], $this->rules());
        $this->actingAs($this->user)->get('/zwroty?status=po_terminie')->assertOk()->assertSee('po terminie')->assertSee('Jan Kowalski');

        OrderReturn::sole()->update(['refunded_on' => now()->subDay(), 'goods_status' => 'sprawny']);
        $this->assertSame([], $this->rules());
    }

    public function test_refunds_above_payment_and_money_without_goods(): void
    {
        $this->actingAs($this->user)->post(route('returns.store'), $this->valid(['amount' => '400', 'refunded_on' => now()->format('Y-m-d')]));
        $this->assertSame(['pieniadze_bez_towaru'], $this->rules());

        $this->actingAs($this->user)->post(route('returns.store'), $this->valid(['kind' => 'czesciowy', 'amount' => '300', 'goods_status' => 'sprawny', 'received_on' => now()->format('Y-m-d'), 'goods_location' => 'Kraków']));
        $this->assertEqualsCanonicalizing(['zwrot_wiekszy_niz_wplata', 'pieniadze_bez_towaru'], $this->rules());
    }

    public function test_list_filters_by_company(): void
    {
        $this->actingAs($this->user)->post(route('returns.store'), $this->valid());
        $swing = Company::where('short_name', 'Swing')->value('id');
        $trusted = Company::where('short_name', 'Trusted')->value('id');

        $this->actingAs($this->user)->get("/zwroty?spolka={$swing}")->assertOk()->assertSee('Jan Kowalski');
        $this->actingAs($this->user)->get("/zwroty?spolka={$trusted}")->assertOk()->assertDontSee('Jan Kowalski');
    }

    public function test_only_admin_can_delete(): void
    {
        $this->actingAs($this->user)->post(route('returns.store'), $this->valid());
        $return = OrderReturn::sole();

        $this->actingAs($this->user)->delete(route('returns.destroy', $return))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete(route('returns.destroy', $return))->assertRedirect(route('returns.index'));
        $this->assertSame(0, OrderReturn::count());
    }
}
