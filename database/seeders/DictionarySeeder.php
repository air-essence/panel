<?php

namespace Database\Seeders;

use App\Enums\ChannelType;
use App\Enums\StatusGroup;
use App\Models\Company;
use App\Models\OrderStatus;
use App\Models\SalesChannel;
use Illuminate\Database\Seeder;

/**
 * Dane startowe słowników na podstawie obecnych arkuszy i listy statusów w air-essence.store (13.11.2026).
 * Seeder można uruchamiać wielokrotnie: nie nadpisuje zmian wprowadzonych w panelu.
 */
class DictionarySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['short_name' => 'Swing', 'name' => 'Swing 2 Harmony'],
            ['short_name' => 'Trusted', 'name' => 'Trusted Solutions'],
            ['short_name' => 'Roynal', 'name' => 'Roynal'],
        ] as $company) {
            Company::firstOrCreate(['short_name' => $company['short_name']], $company);
        }

        // Spółki przypisze administrator w panelu: arkusze nie mówią jednoznacznie, który kanał należy do której spółki.
        foreach ([
            ['air-essence.store', ChannelType::WooCommerce],
            ['air-essence.de', ChannelType::WooCommerce],
            ['medivon.pl', ChannelType::WooCommerce],
            ['medivon.net', ChannelType::WooCommerce],
            ['beautifly.eu', ChannelType::WooCommerce],
            ['beautifly.de', ChannelType::WooCommerce],
            ['Allegro icosplay', ChannelType::Allegro],
            ['Allegro Roynal', ChannelType::Allegro],
            ['Kaufland', ChannelType::Kaufland],
            ['Amazon', ChannelType::Amazon],
        ] as [$name, $type]) {
            SalesChannel::firstOrCreate(['name' => $name], ['type' => $type, 'is_active' => true]);
        }

        foreach ([
            ['Oczekujące na płatność', StatusGroup::CzekaNaPlatnosc, null],
            ['Wstrzymane (oczekujące na płatność)', StatusGroup::CzekaNaPlatnosc, null],
            ['Faktura z odroczeniem', StatusGroup::CzekaNaPlatnosc, null],
            ['W trakcie realizacji', StatusGroup::WRealizacji, null],
            ['ręczne allegro', StatusGroup::DoUstalenia, 'Znaczenie do potwierdzenia z zespołem'],
            ['Nadane', StatusGroup::Wyslane, null],
            ['wysyłka z biura', StatusGroup::Wyslane, null],
            ['wysyłka z biura allegro', StatusGroup::Wyslane, null],
            ['wysyłka z zielonej góry', StatusGroup::Wyslane, null],
            ['inpostwysłane', StatusGroup::Wyslane, null],
            ['inpostimported', StatusGroup::DoUstalenia, 'Czym różni się od „inpostwysłane”? Do potwierdzenia'],
            ['Zrealizowane', StatusGroup::Zakonczone, null],
            ['zrealizowane allegro', StatusGroup::Zakonczone, null],
            ['reklamacja', StatusGroup::Reklamacja, null],
            ['Anulowano', StatusGroup::AnulowaneZwrocone, null],
            ['Zwrócone', StatusGroup::AnulowaneZwrocone, null],
            ['Nieudane', StatusGroup::AnulowaneZwrocone, null],
            ['przeniesione do trusted', StatusGroup::PrzeniesioneDoSpolki, 'Zamówienie przejęte przez Trusted Solutions'],
        ] as [$name, $group, $notes]) {
            OrderStatus::firstOrCreate(['name' => $name], ['group' => $group, 'notes' => $notes]);
        }
    }
}
