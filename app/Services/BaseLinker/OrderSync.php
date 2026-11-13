<?php

namespace App\Services\BaseLinker;

use App\Enums\StatusGroup;
use App\Models\BaselinkerSource;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\SalesChannel;
use App\Models\SyncRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pobiera z BaseLinkera statusy, źródła i zamówienia z ostatnich dni i zapisuje je w panelu.
 * Niczego nie wysyła do BaseLinkera.
 */
class OrderSync
{
    public const SOURCE = 'baselinker';

    /** BaseLinker zwraca maksymalnie 100 zamówień na stronę. */
    private const PAGE_SIZE = 100;

    /** Bezpiecznik na wypadek błędnej paginacji. */
    private const MAX_PAGES = 200;

    public function __construct(private readonly Client $client) {}

    public function run(?Carbon $from = null): SyncRun
    {
        $run = SyncRun::create(['source' => self::SOURCE, 'started_at' => now()]);

        try {
            $this->syncStatuses();
            $this->syncSources();
            [$created, $updated] = $this->syncOrders($from ?? now()->subDays(config('services.baselinker.sync_days')));

            $run->update(['finished_at' => now(), 'orders_created' => $created, 'orders_updated' => $updated]);
        } catch (Throwable $e) {
            $run->update(['finished_at' => now(), 'error' => Str::limit($e->getMessage(), 1000)]);

            throw $e;
        }

        return $run;
    }

    /**
     * Statusy z BaseLinkera łączymy z istniejącymi po nazwie. Nowe dostają grupę „Do ustalenia”.
     */
    public function syncStatuses(): void
    {
        foreach ($this->client->call('getOrderStatusList')['statuses'] ?? [] as $remote) {
            $id = (int) $remote['id'];
            $name = trim((string) $remote['name']);

            if (OrderStatus::where('baselinker_status_id', $id)->exists()) {
                continue;
            }

            $status = OrderStatus::whereNull('baselinker_status_id')
                ->whereRaw('lower(name) = ?', [Str::lower($name)])
                ->first();

            if ($status) {
                $status->update(['baselinker_status_id' => $id]);

                continue;
            }

            OrderStatus::create([
                'name' => OrderStatus::where('name', $name)->exists() ? "{$name} (BaseLinker {$id})" : $name,
                'group' => StatusGroup::DoUstalenia,
                'baselinker_status_id' => $id,
                'notes' => 'Dodany automatycznie z BaseLinkera — przypisz grupę.',
            ]);
        }
    }

    /**
     * Źródła zamówień (sklepy, konta Allegro itd.) zapisujemy jako listę do przypisania kanałom.
     */
    public function syncSources(): void
    {
        foreach ($this->client->call('getOrderSources')['sources'] ?? [] as $type => $sources) {
            foreach ((array) $sources as $id => $name) {
                BaselinkerSource::updateOrCreate(
                    ['key' => "{$type}:{$id}"],
                    ['type' => (string) $type, 'name' => (string) $name],
                );
            }
        }
    }

    /**
     * @return array{0: int, 1: int} liczba utworzonych i zaktualizowanych zamówień
     */
    public function syncOrders(Carbon $from): array
    {
        $channels = SalesChannel::whereNotNull('baselinker_source')->pluck('id', 'baselinker_source');
        $statuses = OrderStatus::whereNotNull('baselinker_status_id')->pluck('id', 'baselinker_status_id');

        $created = $updated = 0;
        $cursor = $from->getTimestamp();

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $orders = $this->client->call('getOrders', [
                'date_confirmed_from' => $cursor,
                'get_unconfirmed_orders' => false,
            ])['orders'] ?? [];

            foreach ($orders as $remote) {
                $this->store($remote, $channels->all(), $statuses->all()) ? $created++ : $updated++;
                $cursor = max($cursor, (int) ($remote['date_confirmed'] ?? 0) + 1);
            }

            if (count($orders) < self::PAGE_SIZE) {
                break;
            }
        }

        return [$created, $updated];
    }

    /**
     * @return bool true, jeśli zamówienie jest nowe
     */
    private function store(array $remote, array $channels, array $statuses): bool
    {
        $sourceKey = ($remote['order_source'] ?? 'unknown').':'.($remote['order_source_id'] ?? 0);
        $statusId = isset($remote['order_status_id']) ? (int) $remote['order_status_id'] : null;
        $products = $remote['products'] ?? [];

        $productsTotal = collect($products)->sum(fn ($p) => (float) ($p['price_brutto'] ?? 0) * (int) ($p['quantity'] ?? 1));

        return DB::transaction(function () use ($remote, $sourceKey, $statusId, $products, $productsTotal, $channels, $statuses) {
            $order = Order::updateOrCreate(
                ['baselinker_order_id' => (int) $remote['order_id']],
                [
                    'baselinker_source' => $sourceKey,
                    'sales_channel_id' => $channels[$sourceKey] ?? null,
                    'baselinker_status_id' => $statusId,
                    'order_status_id' => $statuses[$statusId] ?? null,
                    'shop_order_id' => $this->nullable($remote['shop_order_id'] ?? null),
                    'external_order_id' => $this->nullable($remote['external_order_id'] ?? null),
                    'customer_name' => $this->nullable($remote['delivery_fullname'] ?? null) ?? $this->nullable($remote['invoice_fullname'] ?? null),
                    'email' => $this->nullable($remote['email'] ?? null),
                    'phone' => $this->nullable($remote['phone'] ?? null),
                    'delivery_method' => $this->nullable($remote['delivery_method'] ?? null),
                    'carrier' => $this->nullable($remote['delivery_package_module'] ?? null),
                    'tracking_number' => $this->nullable($remote['delivery_package_nr'] ?? null),
                    'payment_method' => $this->nullable($remote['payment_method'] ?? null),
                    'is_cod' => (bool) (int) ($remote['payment_method_cod'] ?? 0),
                    'total' => round($productsTotal + (float) ($remote['delivery_price'] ?? 0), 2),
                    'paid' => round((float) ($remote['payment_done'] ?? 0), 2),
                    'currency' => $remote['currency'] ?? 'PLN',
                    'ordered_at' => $this->time($remote['date_add'] ?? null),
                    'confirmed_at' => $this->time($remote['date_confirmed'] ?? null),
                    'status_changed_at' => $this->time($remote['date_in_status'] ?? null),
                    'synced_at' => now(),
                    'raw' => $remote,
                ],
            );

            $order->items()->delete();
            $order->items()->createMany(collect($products)->map(fn ($p) => [
                'name' => Str::limit((string) ($p['name'] ?? 'bez nazwy'), 250),
                'sku' => $this->nullable($p['sku'] ?? null),
                'ean' => $this->nullable($p['ean'] ?? null),
                'quantity' => max(1, (int) ($p['quantity'] ?? 1)),
                'price' => round((float) ($p['price_brutto'] ?? 0), 2),
            ])->all());

            return $order->wasRecentlyCreated;
        });
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function time(mixed $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp, config('app.timezone')) : null;
    }
}
