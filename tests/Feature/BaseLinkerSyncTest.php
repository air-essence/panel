<?php

namespace Tests\Feature;

use App\Enums\StatusGroup;
use App\Models\BaselinkerSource;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\SalesChannel;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\BaseLinker\BaseLinkerException;
use App\Services\BaseLinker\Client;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BaseLinkerSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DictionarySeeder::class);
        config(['services.baselinker.token' => 'token-testowy', 'services.baselinker.url' => 'https://bl.test/connector.php']);
    }

    private function order(array $overrides = []): array
    {
        return array_merge([
            'order_id' => 1001,
            'shop_order_id' => 5521,
            'external_order_id' => '',
            'order_source' => 'shop',
            'order_source_id' => 2444,
            'order_status_id' => 7001,
            'date_add' => 1_763_020_800,
            'date_confirmed' => 1_763_020_900,
            'date_in_status' => 1_763_021_000,
            'email' => 'jan@example.com',
            'phone' => '500600700',
            'delivery_fullname' => 'Jan Kowalski',
            'delivery_method' => 'InPost Paczkomaty',
            'delivery_price' => 12.99,
            'delivery_package_module' => 'inpost',
            'delivery_package_nr' => '6800000000000000000001',
            'payment_method' => 'PayU',
            'payment_method_cod' => '0',
            'payment_done' => 412.99,
            'currency' => 'PLN',
            'products' => [
                ['name' => 'Oczyszczacz powietrza', 'sku' => 'AE-100', 'ean' => '5901234123457', 'quantity' => 2, 'price_brutto' => 200],
            ],
        ], $overrides);
    }

    private function fakeBaseLinker(array $orderPages = []): void
    {
        $pages = $orderPages ?: [[$this->order()]];

        Http::fake(function (Request $request) use (&$pages) {
            return match ($request['method']) {
                'getOrderStatusList' => Http::response(['status' => 'SUCCESS', 'statuses' => [
                    ['id' => 7001, 'name' => 'wysyłka z biura'],
                    ['id' => 7002, 'name' => 'Nowy status z BL'],
                ]]),
                'getOrderSources' => Http::response(['status' => 'SUCCESS', 'sources' => [
                    'shop' => ['2444' => 'air-essence.store'],
                    'allegro' => ['11' => 'icosplay'],
                ]]),
                'getOrders' => Http::response(['status' => 'SUCCESS', 'orders' => array_shift($pages) ?? []]),
            };
        });
    }

    public function test_sync_imports_orders_statuses_and_sources(): void
    {
        $this->fakeBaseLinker();

        $this->artisan('panel:synchronizuj-baselinker')
            ->expectsOutputToContain('Nowe zamówienia: 1, zaktualizowane: 0')
            ->assertSuccessful();

        // Istniejący status powiązany po nazwie, nowy dodany jako „Do ustalenia”.
        $this->assertSame(7001, OrderStatus::where('name', 'wysyłka z biura')->value('baselinker_status_id'));
        $this->assertSame(StatusGroup::DoUstalenia, OrderStatus::where('baselinker_status_id', 7002)->first()->group);

        $this->assertEqualsCanonicalizing(['shop:2444', 'allegro:11'], BaselinkerSource::pluck('key')->all());

        $order = Order::with('items', 'status')->sole();
        $this->assertSame('wysyłka z biura', $order->status->name);
        $this->assertSame('shop:2444', $order->baselinker_source);
        $this->assertNull($order->sales_channel_id);
        $this->assertSame('412.99', $order->total);
        $this->assertTrue($order->isPaid());
        $this->assertSame('6800000000000000000001', $order->tracking_number);
        $this->assertNull($order->external_order_id);
        $this->assertSame('5901234123457', $order->items->sole()->ean);
        $this->assertTrue(SyncRun::sole()->succeeded());
    }

    public function test_sync_is_read_only_and_sends_token_in_header(): void
    {
        $this->fakeBaseLinker();

        $this->artisan('panel:synchronizuj-baselinker')->assertSuccessful();

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->hasHeader('X-BLToken', 'token-testowy') && str_starts_with($r['method'], 'get'));
    }

    public function test_client_refuses_write_methods(): void
    {
        Http::fake();

        $this->expectException(BaseLinkerException::class);

        try {
            Client::fromConfig()->call('setOrderStatus', ['order_id' => 1, 'status_id' => 2]);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_second_sync_updates_instead_of_duplicating(): void
    {
        $this->fakeBaseLinker([[$this->order()], [$this->order(['order_status_id' => 7002, 'products' => []])]]);

        $this->artisan('panel:synchronizuj-baselinker')->assertSuccessful();
        $this->artisan('panel:synchronizuj-baselinker')
            ->expectsOutputToContain('Nowe zamówienia: 0, zaktualizowane: 1')
            ->assertSuccessful();

        $order = Order::sole();
        $this->assertSame(7002, (int) $order->baselinker_status_id);
        $this->assertCount(0, $order->items);
    }

    public function test_sync_follows_pages_of_100_orders(): void
    {
        $base = now()->subDays(5)->getTimestamp();
        $first = array_map(fn ($i) => $this->order(['order_id' => 2000 + $i, 'date_confirmed' => $base + $i]), range(1, 100));
        $this->fakeBaseLinker([$first, [$this->order(['order_id' => 3000])]]);

        $this->artisan('panel:synchronizuj-baselinker')->assertSuccessful();

        $this->assertSame(101, Order::count());
        Http::assertSent(fn (Request $r) => $r['method'] === 'getOrders'
            && json_decode($r['parameters'], true)['date_confirmed_from'] === $base + 101);
    }

    public function test_assigning_source_to_channel_links_existing_orders(): void
    {
        $this->fakeBaseLinker();
        $this->artisan('panel:synchronizuj-baselinker')->assertSuccessful();

        $channel = SalesChannel::where('name', 'air-essence.store')->firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('channels.update', $channel), [
            'name' => $channel->name,
            'type' => $channel->type->value,
            'baselinker_source' => 'shop:2444',
            'is_active' => '1',
        ])->assertRedirect(route('channels.index'));

        $this->assertSame($channel->id, Order::sole()->sales_channel_id);

        $channel->refresh()->update(['baselinker_source' => null]);
        $this->assertNull(Order::sole()->sales_channel_id);
    }

    public function test_api_error_is_recorded_and_shown_on_dashboard(): void
    {
        Http::fake(['*' => Http::response(['status' => 'ERROR', 'error_code' => 'ERROR_BAD_TOKEN', 'error_message' => 'Invalid user token'])]);

        $this->artisan('panel:synchronizuj-baselinker')
            ->expectsOutputToContain('ERROR_BAD_TOKEN')
            ->assertFailed();

        $this->assertStringContainsString('ERROR_BAD_TOKEN', SyncRun::sole()->error);

        $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()
            ->assertSee('błąd')
            ->assertSee('ERROR_BAD_TOKEN');
    }

    public function test_command_without_token_does_nothing(): void
    {
        config(['services.baselinker.token' => null]);
        Http::fake();

        $this->artisan('panel:synchronizuj-baselinker')
            ->expectsOutputToContain('Brak tokenu')
            ->assertFailed();

        Http::assertNothingSent();
        $this->assertSame(0, SyncRun::count());
    }

    public function test_orders_list_and_details_are_visible_and_filterable(): void
    {
        $this->fakeBaseLinker([[$this->order(), $this->order(['order_id' => 1002, 'email' => 'anna@example.com', 'delivery_fullname' => 'Anna Nowak', 'order_status_id' => 7002])]]);
        $this->artisan('panel:synchronizuj-baselinker')->assertSuccessful();

        $user = User::factory()->create();

        $this->actingAs($user)->get('/zamowienia')->assertOk()->assertSee('Jan Kowalski')->assertSee('Anna Nowak');
        $this->actingAs($user)->get('/zamowienia?q=anna')->assertOk()->assertSee('Anna Nowak')->assertDontSee('Jan Kowalski');
        $this->actingAs($user)->get('/zamowienia?grupa=do_ustalenia')->assertOk()->assertSee('Anna Nowak')->assertDontSee('Jan Kowalski');

        $this->actingAs($user)->get(route('orders.show', Order::where('baselinker_order_id', 1001)->sole()))
            ->assertOk()
            ->assertSee('Oczyszczacz powietrza')
            ->assertSee('5901234123457');
    }
}
