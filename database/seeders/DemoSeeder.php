<?php

namespace Database\Seeders;

use App\Enums\CancellationReason;
use App\Enums\CaseStatus;
use App\Enums\ComplaintResolution;
use App\Enums\GoodsStatus;
use App\Enums\ProductLocation;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Enums\ReturnKind;
use App\Enums\Role;
use App\Enums\SaleDocument;
use App\Enums\ShipmentHold;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\SalesChannel;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\BaseLinker\OrderSync;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fikcyjne dane do testów panelu na własnym komputerze: konta demo, zamówienia i sprawy,
 * które wywołują każdą regułę z „Do wyjaśnienia”. Nie uruchamiać na serwerze z prawdziwymi danymi.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo-panel';

    private int $number = 9100000;

    /** @var array<string, User> */
    private array $users = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Dane demonstracyjne nie są przeznaczone dla środowiska produkcyjnego.');
        }

        $this->call(DictionarySeeder::class);

        foreach ([
            'admin' => ['admin@demo.pl', 'Gabriel (demo)', Role::Admin],
            'kierownik' => ['kierownik@demo.pl', 'Kasia Kierownik', Role::Kierownik],
            'pracownik' => ['pracownik@demo.pl', 'Tomek Pracownik', Role::Pracownik],
        ] as $key => [$email, $name, $role]) {
            $this->users[$key] = User::updateOrCreate(['email' => $email], [
                'name' => $name, 'role' => $role, 'is_active' => true, 'password' => self::PASSWORD,
            ]);
        }

        // Kanały dostają źródło BaseLinkera i spółkę, jak po konfiguracji przez administratora.
        $companies = Company::pluck('id', 'short_name');
        foreach ([
            'air-essence.store' => 'Swing', 'air-essence.de' => 'Swing', 'medivon.pl' => 'Trusted',
            'medivon.net' => 'Trusted', 'beautifly.eu' => 'Roynal', 'beautifly.de' => 'Roynal',
            'Allegro icosplay' => 'Swing', 'Allegro Roynal' => 'Roynal', 'Kaufland' => 'Trusted', 'Amazon' => 'Swing',
        ] as $channel => $company) {
            SalesChannel::where('name', $channel)->first()?->update([
                'baselinker_source' => 'demo_'.str($channel)->slug('_'),
                'company_id' => $companies[$company] ?? null,
            ]);
        }

        $this->regularOrders();
        $this->cancellations();
        $this->returns();
        $this->complaints();

        SyncRun::create([
            'source' => OrderSync::SOURCE,
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(3)->addSeconds(4),
            'orders_created' => Order::count(),
            'orders_updated' => 0,
        ]);
    }

    private function regularOrders(): void
    {
        $this->order('air-essence.store', 'W trakcie realizacji', 'Anna Kowalska', 1, [['Oczyszczacz powietrza AE-300', 899.00]]);
        $this->order('air-essence.store', 'Nadane', 'Marek Nowak', 2, [['Filtr HEPA do AE-300', 149.00], ['Filtr węglowy', 59.00]], tracking: true);
        $this->order('medivon.pl', 'Zrealizowane', 'Ewa Wiśniewska', 9, [['Inhalator Medivon Pro', 249.00]], tracking: true);
        $this->order('beautifly.eu', 'Oczekujące na płatność', 'Julia Zielińska', 0, [['Szczoteczka soniczna B-Sonic', 199.00]], paid: false);
        $this->order('Allegro icosplay', 'wysyłka z biura allegro', 'Paweł Lewandowski', 3, [['Peruka cosplay, srebrna', 129.00]], tracking: true);
        $this->order('Kaufland', 'inpostwysłane', 'Katarzyna Dąbrowska', 4, [['Nawilżacz powietrza AE-H2', 349.00]], tracking: true);
        $this->order('Amazon', 'Zrealizowane', 'Hans Müller', 12, [['Air purifier AE-300', 219.00]], currency: 'EUR', tracking: true);
        $this->order('air-essence.de', 'W trakcie realizacji', 'Lena Schmidt', 1, [['Luftreiniger AE-500', 329.00]], currency: 'EUR');
        $this->order('medivon.net', 'ręczne allegro', 'Robert Wójcik', 2, [['Ciśnieniomierz Medivon BP', 179.00]]);
        $this->order('beautifly.de', 'Faktura z odroczeniem', 'Salon Piękności Aga sp. z o.o.', 1, [['Zestaw B-Sonic x10', 1490.00]], paid: false);
    }

    private function cancellations(): void
    {
        // Anulowane w BaseLinkerze z numerem przesyłki i bez anulacji w panelu: dwie rozbieżności.
        $this->order('air-essence.store', 'Anulowano', 'Michał Kamiński', 6, [['Oczyszczacz powietrza AE-500', 1299.00]], tracking: true);

        // Anulowane w BaseLinkerze, klient zapłacił, a w panelu brak anulacji.
        $this->order('beautifly.eu', 'Anulowano', 'Natalia Kaczmarek', 5, [['Prostownica B-Straight', 279.00]]);

        // Pieniądze zwrócone, a zamówienie dalej w realizacji.
        $order = $this->order('medivon.pl', 'W trakcie realizacji', 'Zofia Mazur', 3, [['Inhalator Medivon Kids', 199.00]]);
        $this->cancellation($order, CaseStatus::WTrakcie, CancellationReason::RezygnacjaKlienta, RefundStatus::Zwrocono, ShipmentHold::Wstrzymano, daysAgo: 2);

        // Anulacja w panelu, a w BaseLinkerze paczka nadana.
        $order = $this->order('Allegro Roynal', 'Nadane', 'Krzysztof Piotrowski', 2, [['Lokówka automatyczna', 159.00]], tracking: true);
        $this->cancellation($order, CaseStatus::Nowa, CancellationReason::BrakTowaru, RefundStatus::DoZwrotu, ShipmentHold::NieNadano, daysAgo: 1);

        // Prawidłowo zamknięta anulacja: brak rozbieżności.
        $order = $this->order('air-essence.store', 'Anulowano', 'Agnieszka Grabowska', 8, [['Filtr HEPA do AE-500', 189.00]]);
        $this->cancellation($order, CaseStatus::Zakonczona, CancellationReason::Duplikat, RefundStatus::Zwrocono, ShipmentHold::NieNadano, daysAgo: 7);
    }

    private function returns(): void
    {
        // Odstąpienie zgłoszone 20 dni temu, pieniędzy nadal nie zwrócono: termin 14 dni minął.
        $order = $this->order('air-essence.store', 'Zrealizowane', 'Tomasz Pawlak', 30, [['Oczyszczacz powietrza AE-300', 899.00]], tracking: true);
        $this->orderReturn($order, ReturnKind::Odstapienie, GoodsStatus::Sprawny, 899.00, daysAgo: 20, receivedDaysAgo: 12);

        // Suma zwrotów większa niż wpłata klienta.
        $order = $this->order('medivon.pl', 'Zrealizowane', 'Magdalena Król', 15, [['Ciśnieniomierz Medivon BP', 179.00]], tracking: true);
        $this->orderReturn($order, ReturnKind::Rabat, GoodsStatus::NieDotyczy, 50.00, daysAgo: 10, refundedDaysAgo: 9);
        $this->orderReturn($order, ReturnKind::Czesciowy, GoodsStatus::Sprawny, 179.00, daysAgo: 4, receivedDaysAgo: 2);

        // Pieniądze zwrócone, a towar jeszcze w drodze.
        $order = $this->order('beautifly.eu', 'Zrealizowane', 'Oliwia Jankowska', 11, [['Szczoteczka soniczna B-Sonic', 199.00]], tracking: true);
        $this->orderReturn($order, ReturnKind::Odstapienie, GoodsStatus::WDrodze, 199.00, daysAgo: 3, refundedDaysAgo: 1);

        // Otwarty zwrot w terminie: brak rozbieżności.
        $order = $this->order('Allegro icosplay', 'Zrealizowane', 'Dawid Szymański', 9, [['Strój cosplay, rozmiar M', 239.00]], tracking: true);
        $this->orderReturn($order, ReturnKind::Odstapienie, GoodsStatus::WDrodze, 239.00, daysAgo: 2);
    }

    private function complaints(): void
    {
        // Reklamacja bez odpowiedzi od 20 dni: termin 14 dni minął.
        $order = $this->order('medivon.pl', 'Zrealizowane', 'Barbara Wróbel', 40, [['Inhalator Medivon Pro', 249.00]], tracking: true);
        $this->complaint($order, ProductLocation::UNas, 'Urządzenie nie włącza się po naładowaniu.', daysAgo: 20);

        // Produkt od 40 dni w serwisie zewnętrznym.
        $order = $this->order('air-essence.store', 'Zrealizowane', 'Jan Kozłowski', 70, [['Oczyszczacz powietrza AE-500', 1299.00]], tracking: true);
        $this->complaint($order, ProductLocation::WSerwisie, 'Głośna praca wentylatora na najniższym biegu.', daysAgo: 45, respondedDaysAgo: 44, serviceDaysAgo: 40);

        // Rozpatrzona zwrotem pieniędzy, ale w panelu brak zwrotu.
        $order = $this->order('beautifly.eu', 'Zrealizowane', 'Monika Adamczyk', 25, [['Prostownica B-Straight', 279.00]], tracking: true);
        $this->complaint($order, ProductLocation::UNas, 'Pęknięta płytka grzewcza.', daysAgo: 12, respondedDaysAgo: 8, resolution: ComplaintResolution::ZwrotPieniedzy);

        // Status „reklamacja” w BaseLinkerze, a w panelu brak reklamacji.
        $this->order('Kaufland', 'reklamacja', 'Grzegorz Dudek', 18, [['Nawilżacz powietrza AE-H2', 349.00]], tracking: true);

        // Otwarta reklamacja w terminie: brak rozbieżności.
        $order = $this->order('air-essence.de', 'Zrealizowane', 'Klaus Weber', 14, [['Luftreiniger AE-300', 219.00]], currency: 'EUR', tracking: true);
        $this->complaint($order, ProductLocation::WDrodze, 'Pilot nie działa.', daysAgo: 3);
    }

    /**
     * @param  list<array{0: string, 1: float}>  $items
     */
    private function order(string $channel, string $status, string $customer, int $daysAgo, array $items, bool $paid = true, string $currency = 'PLN', bool $tracking = false): Order
    {
        $channel = SalesChannel::where('name', $channel)->firstOrFail();
        $number = ++$this->number;
        $total = array_sum(array_column($items, 1));

        $order = Order::create([
            'baselinker_order_id' => $number,
            'sales_channel_id' => $channel->id,
            'baselinker_source' => $channel->baselinker_source,
            'order_status_id' => OrderStatus::where('name', $status)->firstOrFail()->id,
            'shop_order_id' => (string) ($number - 9000000),
            'customer_name' => $customer,
            'email' => str($customer)->ascii()->slug('.').'@example.com',
            'phone' => '+48 600 '.str_pad((string) ($number % 1000), 3, '0', STR_PAD_LEFT).' 000',
            'delivery_method' => 'InPost Paczkomat',
            'carrier' => $tracking ? 'InPost' : null,
            'tracking_number' => $tracking ? '6800000000'.$number : null,
            'payment_method' => $paid ? 'Przelewy24' : 'Przelew tradycyjny',
            'total' => $total,
            'paid' => $paid ? $total : 0,
            'currency' => $currency,
            'ordered_at' => now()->subDays($daysAgo)->setTime(10, 15),
            'confirmed_at' => now()->subDays($daysAgo)->setTime(10, 20),
            'status_changed_at' => now()->subDays(max($daysAgo - 1, 0)),
            'synced_at' => now()->subMinutes(3),
        ]);

        foreach ($items as $i => [$name, $price]) {
            $order->items()->create([
                'name' => $name,
                'sku' => 'DEMO-'.($i + 1),
                'ean' => '590'.str_pad((string) (crc32($name) % 1000000000), 10, '0', STR_PAD_LEFT),
                'quantity' => 1,
                'price' => $price,
            ]);
        }

        return $order;
    }

    private function cancellation(Order $order, CaseStatus $status, CancellationReason $reason, RefundStatus $refund, ShipmentHold $hold, int $daysAgo): void
    {
        $case = $order->cases()->create([
            'status' => $status,
            'reason' => $reason,
            'requested_on' => today()->subDays($daysAgo),
            'refund_status' => $refund,
            'refund_amount' => $refund === RefundStatus::NieDotyczy ? null : $order->paid,
            'refunded_on' => $refund === RefundStatus::Zwrocono ? today()->subDays(max($daysAgo - 1, 0)) : null,
            'shipment_hold' => $hold,
            'created_by' => $this->users['pracownik']->id,
            'assigned_to' => $this->users['pracownik']->id,
            'closed_at' => $status === CaseStatus::Zakonczona ? now()->subDays(max($daysAgo - 1, 0)) : null,
        ]);
        $case->events()->create(['user_id' => $this->users['pracownik']->id, 'action' => 'utworzono', 'created_at' => now()->subDays($daysAgo)]);
    }

    private function orderReturn(Order $order, ReturnKind $kind, GoodsStatus $goods, float $amount, int $daysAgo, ?int $receivedDaysAgo = null, ?int $refundedDaysAgo = null): void
    {
        $return = $order->returns()->create([
            'status' => CaseStatus::WTrakcie,
            'kind' => $kind,
            'requested_on' => today()->subDays($daysAgo),
            'goods_status' => $goods,
            'received_on' => $receivedDaysAgo !== null ? today()->subDays($receivedDaysAgo) : null,
            'goods_location' => $receivedDaysAgo !== null ? 'Kraków, regał B' : null,
            'amount' => $amount,
            'refund_method' => RefundMethod::Przelew,
            'refund_account' => '12 1020 0000 0000 0000 0000 0000',
            'refunded_on' => $refundedDaysAgo !== null ? today()->subDays($refundedDaysAgo) : null,
            'sale_document' => SaleDocument::Paragon,
            'sale_document_number' => 'PAR/'.$order->shop_order_id,
            'created_by' => $this->users['pracownik']->id,
            'assigned_to' => $this->users['kierownik']->id,
        ]);
        $return->events()->create(['user_id' => $this->users['pracownik']->id, 'action' => 'utworzono', 'created_at' => now()->subDays($daysAgo)]);
    }

    private function complaint(Order $order, ProductLocation $location, string $defect, int $daysAgo, ?int $respondedDaysAgo = null, ?int $serviceDaysAgo = null, ?ComplaintResolution $resolution = null): void
    {
        $item = $order->items()->first();
        $complaint = $order->complaints()->create([
            'status' => CaseStatus::WTrakcie,
            'product_name' => $item->name,
            'ean' => $item->ean,
            'reported_on' => today()->subDays($daysAgo),
            'defect_description' => $defect,
            'product_location' => $location,
            'service_name' => $serviceDaysAgo !== null ? 'Serwis Elektro-Fix Kraków' : null,
            'service_sent_on' => $serviceDaysAgo !== null ? today()->subDays($serviceDaysAgo) : null,
            'responded_on' => $respondedDaysAgo !== null ? today()->subDays($respondedDaysAgo) : null,
            'resolution' => $resolution,
            'resolved_on' => $resolution ? today()->subDays(max(($respondedDaysAgo ?? 1) - 1, 0)) : null,
            'drive_link' => 'https://drive.google.com/drive/folders/demo',
            'created_by' => $this->users['pracownik']->id,
            'assigned_to' => $this->users['pracownik']->id,
        ]);
        $complaint->events()->create(['user_id' => $this->users['pracownik']->id, 'action' => 'utworzono', 'created_at' => now()->subDays($daysAgo)]);
    }
}
