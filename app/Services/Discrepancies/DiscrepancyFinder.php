<?php

namespace App\Services\Discrepancies;

use App\Enums\ComplaintResolution;
use App\Enums\GoodsStatus;
use App\Enums\RefundStatus;
use App\Enums\Severity;
use App\Enums\ShipmentHold;
use App\Enums\StatusGroup;
use App\Models\DiscrepancyDismissal;
use App\Models\Order;
use App\Models\OrderCase;
use App\Models\OrderComplaint;
use App\Models\OrderReturn;
use Illuminate\Support\Collection;

/**
 * Porównuje sprawy prowadzone w panelu z danymi zamówień z BaseLinkera.
 * Liczone na bieżąco przy każdym wyświetleniu (przy ~500 zamówieniach miesięcznie to kilka milisekund).
 */
class DiscrepancyFinder
{
    public const RULES = [
        'anulowane_z_przesylka' => 'Anulowane, a paczka nadana',
        'zwrot_a_realizacja' => 'Pieniądze zwrócone, a zamówienie w realizacji',
        'anulacja_tylko_w_panelu' => 'Anulacja tylko w panelu',
        'anulacja_tylko_w_baselinkerze' => 'Anulacja tylko w BaseLinkerze',
        'zwrot_po_terminie' => 'Minął termin zwrotu pieniędzy',
        'zwrot_wiekszy_niz_wplata' => 'Zwroty większe niż wpłata',
        'pieniadze_bez_towaru' => 'Pieniądze zwrócone, towar nie dotarł',
        'reklamacja_bez_odpowiedzi' => 'Minął termin odpowiedzi na reklamację',
        'reklamacja_dlugo_w_serwisie' => 'Produkt długo w serwisie',
        'reklamacja_zwrot_bez_zwrotu' => 'Reklamacja: zwrot pieniędzy bez zwrotu w panelu',
        'reklamacja_tylko_w_baselinkerze' => 'Reklamacja tylko w BaseLinkerze',
    ];

    /** Grupy statusów oznaczające, że zamówienie jest dalej realizowane. */
    private const ACTIVE = [StatusGroup::CzekaNaPlatnosc, StatusGroup::WRealizacji, StatusGroup::Wyslane, StatusGroup::Zakonczone];

    /**
     * @return Collection<int, Discrepancy> posortowane: krytyczne najpierw, potem od najnowszych zamówień
     */
    public function find(bool $includeDismissed = false): Collection
    {
        $days = (int) config('panel.discrepancy_days', 90);

        $orders = Order::with(['status', 'salesChannel', 'cases' => fn ($q) => $q->cancellations(), 'returns', 'complaints'])
            ->where(fn ($q) => $q->where('ordered_at', '>=', now()->subDays($days))->orWhereHas('cases')->orWhereHas('returns')->orWhereHas('complaints'))
            ->get();

        $dismissed = $includeDismissed ? collect() : DiscrepancyDismissal::query()
            ->get(['order_id', 'rule'])
            ->map(fn ($d) => $d->order_id.'|'.$d->rule)
            ->flip();

        return $orders
            ->flatMap(fn (Order $order) => $this->check($order))
            ->reject(fn (Discrepancy $d) => $dismissed->has($d->order->id.'|'.$d->rule))
            ->sortBy([
                fn ($a, $b) => $a->severity === $b->severity ? 0 : ($a->severity === Severity::Krytyczna ? -1 : 1),
                fn ($a, $b) => ($b->order->ordered_at?->getTimestamp() ?? 0) <=> ($a->order->ordered_at?->getTimestamp() ?? 0),
            ])
            ->values();
    }

    /**
     * @return list<Discrepancy>
     */
    public function check(Order $order): array
    {
        return [...$this->checkReturns($order), ...$this->checkComplaints($order), ...$this->checkCancellations($order)];
    }

    /**
     * @return list<Discrepancy>
     */
    private function checkComplaints(Order $order): array
    {
        /** @var Collection<int, OrderComplaint> $complaints */
        $complaints = $order->complaints;
        $found = [];

        if ($complaints->isEmpty()) {
            if ($order->status?->group === StatusGroup::Reklamacja) {
                $found[] = $this->make('reklamacja_tylko_w_baselinkerze', Severity::Ostrzezenie, $order,
                    "W BaseLinkerze zamówienie ma status „{$order->status->name}”, a w panelu nie ma reklamacji. Dodaj ją, żeby pilnować terminu odpowiedzi.");
            }

            return $found;
        }

        $overdue = $complaints->first(fn (OrderComplaint $c) => $c->isResponseOverdue());
        if ($overdue) {
            $found[] = $this->make('reklamacja_bez_odpowiedzi', Severity::Krytyczna, $order,
                "Reklamacja „{$overdue->product_name}” zgłoszona {$overdue->reported_on->format('d.m.Y')} nie ma odpowiedzi. Termin minął {$overdue->responseDeadline()->format('d.m.Y')}. Brak odpowiedzi w 14 dni oznacza uznanie reklamacji.");
        }

        $inService = $complaints->first(fn (OrderComplaint $c) => $c->status->isOpen() && $c->isLongInService());
        if ($inService) {
            $found[] = $this->make('reklamacja_dlugo_w_serwisie', Severity::Ostrzezenie, $order,
                "„{$inService->product_name}” jest w serwisie {$inService->service_name} od {$inService->service_sent_on->format('d.m.Y')}. Zapytaj serwis o termin.");
        }

        if ($order->returns->isEmpty() && $complaints->contains(fn (OrderComplaint $c) => $c->resolution === ComplaintResolution::ZwrotPieniedzy)) {
            $found[] = $this->make('reklamacja_zwrot_bez_zwrotu', Severity::Ostrzezenie, $order,
                'Reklamacja zakończyła się zwrotem pieniędzy, a w panelu nie ma zwrotu. Dodaj zwrot, żeby było widać kwotę i dokumenty.');
        }

        return $found;
    }

    /**
     * Reguły zwrotów nie zależą od statusu w BaseLinkerze.
     *
     * @return list<Discrepancy>
     */
    private function checkReturns(Order $order): array
    {
        /** @var Collection<int, OrderReturn> $returns */
        $returns = $order->returns;
        $found = [];

        if ($returns->isEmpty()) {
            return $found;
        }

        $overdue = $returns->first(fn (OrderReturn $r) => $r->status->isOpen() && $r->isRefundOverdue());
        if ($overdue) {
            $found[] = $this->make('zwrot_po_terminie', Severity::Krytyczna, $order,
                "Klient zgłosił odstąpienie {$overdue->requested_on->format('d.m.Y')}, a pieniądze nie zostały zwrócone. Termin minął {$overdue->refundDeadline()->format('d.m.Y')}.");
        }

        $sum = (int) round($returns->sum(fn (OrderReturn $r) => (float) $r->amount) * 100);
        $paid = (int) round((float) $order->paid * 100);
        if ($paid > 0 && $sum > $paid) {
            $found[] = $this->make('zwrot_wiekszy_niz_wplata', Severity::Krytyczna, $order,
                'Suma zwrotów ('.number_format($sum / 100, 2, ',', ' ').') jest większa niż wpłata klienta ('.number_format($paid / 100, 2, ',', ' ')." {$order->currency}).");
        }

        if ($returns->contains(fn (OrderReturn $r) => $r->refunded_on && $r->goods_status === GoodsStatus::WDrodze)) {
            $found[] = $this->make('pieniadze_bez_towaru', Severity::Ostrzezenie, $order,
                'Pieniądze zostały zwrócone, a towar jeszcze nie dotarł. Sprawdź przesyłkę zwrotną.');
        }

        return $found;
    }

    /**
     * @return list<Discrepancy>
     */
    private function checkCancellations(Order $order): array
    {
        $group = $order->status?->group;

        // Bez znanego statusu nie porównujemy — takie zamówienia widać na pulpicie jako „do konfiguracji”.
        if ($group === null || $group === StatusGroup::DoUstalenia) {
            return [];
        }

        /** @var Collection<int, OrderCase> $cases */
        $cases = $order->cases;
        $cancelledInBl = $group === StatusGroup::AnulowaneZwrocone;
        $found = [];

        if ($cancelledInBl && $order->tracking_number
            && ! $cases->contains(fn (OrderCase $c) => in_array($c->shipment_hold, [ShipmentHold::Wstrzymano, ShipmentHold::Nadano], true))) {
            $found[] = $this->make('anulowane_z_przesylka', Severity::Krytyczna, $order,
                "Zamówienie jest anulowane, ale ma numer przesyłki {$order->tracking_number}. Sprawdź, czy paczka nie wyszła, i zapisz to w anulacji.");
        }

        if ($cases->isNotEmpty() && in_array($group, self::ACTIVE, true)) {
            if ($cases->contains(fn (OrderCase $c) => $c->refund_status === RefundStatus::Zwrocono)) {
                $found[] = $this->make('zwrot_a_realizacja', Severity::Krytyczna, $order,
                    "Pieniądze zostały zwrócone, a w BaseLinkerze zamówienie ma status „{$order->status->name}”. Zatrzymaj wysyłkę i zmień status.");
            } else {
                $found[] = $this->make('anulacja_tylko_w_panelu', Severity::Krytyczna, $order,
                    "W panelu jest anulacja, a w BaseLinkerze zamówienie ma status „{$order->status->name}”. Zmień status w BaseLinkerze.");
            }
        }

        if ($cancelledInBl && $cases->isEmpty() && (float) $order->paid > 0) {
            $found[] = $this->make('anulacja_tylko_w_baselinkerze', Severity::Ostrzezenie, $order,
                'Zamówienie jest anulowane w BaseLinkerze, klient wpłacił '.number_format((float) $order->paid, 2, ',', ' ')." {$order->currency}, a w panelu nie ma anulacji z informacją o zwrocie.");
        }

        return $found;
    }

    private function make(string $rule, Severity $severity, Order $order, string $message): Discrepancy
    {
        return new Discrepancy($rule, $severity, $order, $message);
    }
}
