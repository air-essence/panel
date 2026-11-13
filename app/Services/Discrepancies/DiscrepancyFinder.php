<?php

namespace App\Services\Discrepancies;

use App\Enums\RefundStatus;
use App\Enums\Severity;
use App\Enums\ShipmentHold;
use App\Enums\StatusGroup;
use App\Models\DiscrepancyDismissal;
use App\Models\Order;
use App\Models\OrderCase;
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
    ];

    /** Grupy statusów oznaczające, że zamówienie jest dalej realizowane. */
    private const ACTIVE = [StatusGroup::CzekaNaPlatnosc, StatusGroup::WRealizacji, StatusGroup::Wyslane, StatusGroup::Zakonczone];

    /**
     * @return Collection<int, Discrepancy> posortowane: krytyczne najpierw, potem od najnowszych zamówień
     */
    public function find(bool $includeDismissed = false): Collection
    {
        $days = (int) config('panel.discrepancy_days', 90);

        $orders = Order::with(['status', 'salesChannel', 'cases' => fn ($q) => $q->cancellations()])
            ->where(fn ($q) => $q->where('ordered_at', '>=', now()->subDays($days))->orWhereHas('cases'))
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
