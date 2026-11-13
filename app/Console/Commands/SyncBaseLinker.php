<?php

namespace App\Console\Commands;

use App\Services\BaseLinker\BaseLinkerException;
use App\Services\BaseLinker\Client;
use App\Services\BaseLinker\OrderSync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncBaseLinker extends Command
{
    protected $signature = 'panel:synchronizuj-baselinker {--dni= : Ile dni wstecz pobrać zamówienia (domyślnie BASELINKER_SYNC_DAYS)}';

    protected $description = 'Pobiera z BaseLinkera statusy, źródła i zamówienia (tylko odczyt)';

    public function handle(): int
    {
        $client = Client::fromConfig();

        if (! $client->isConfigured()) {
            $this->warn('Brak tokenu BaseLinkera. Ustaw BASELINKER_TOKEN w pliku .env.');

            return self::FAILURE;
        }

        $days = $this->option('dni');

        if ($days !== null && (! ctype_digit((string) $days) || (int) $days < 1)) {
            $this->error('Opcja --dni musi być liczbą dodatnią.');

            return self::INVALID;
        }

        try {
            $run = (new OrderSync($client))->run($days ? Carbon::now()->subDays((int) $days) : null);
        } catch (BaseLinkerException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Gotowe. Nowe zamówienia: {$run->orders_created}, zaktualizowane: {$run->orders_updated}.");

        return self::SUCCESS;
    }
}
