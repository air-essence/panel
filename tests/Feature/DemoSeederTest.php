<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Discrepancies\DiscrepancyFinder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_triggers_every_discrepancy_rule(): void
    {
        $this->seed(DemoSeeder::class);

        $rules = app(DiscrepancyFinder::class)->find()->pluck('rule')->unique()->sort()->values()->all();

        $this->assertSame(collect(array_keys(DiscrepancyFinder::RULES))->sort()->values()->all(), $rules);
    }

    public function test_demo_accounts_can_log_in_and_open_main_pages(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['admin@demo.pl', 'kierownik@demo.pl', 'pracownik@demo.pl'] as $email) {
            $this->post(route('login'), ['email' => $email, 'password' => DemoSeeder::PASSWORD])->assertRedirect();
            $this->assertAuthenticatedAs(User::where('email', $email)->first());

            foreach (['/', '/zamowienia', '/anulacje', '/zwroty', '/reklamacje', '/do-wyjasnienia'] as $page) {
                $this->get($page)->assertOk();
            }

            $this->post(route('logout'));
        }
    }

    public function test_demo_data_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectExceptionMessage('produkcyjnego');
        app(DemoSeeder::class)->run();
    }
}
