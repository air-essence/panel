<?php

namespace Tests\Feature;

use App\Enums\ChannelType;
use App\Enums\Role;
use App\Enums\StatusGroup;
use App\Models\Company;
use App\Models\OrderStatus;
use App\Models\SalesChannel;
use App\Models\User;
use Database\Seeders\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DictionaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_companies_channels_and_statuses_and_is_idempotent(): void
    {
        $this->seed(DictionarySeeder::class);
        $this->seed(DictionarySeeder::class);

        $this->assertSame(3, Company::count());
        $this->assertSame(10, SalesChannel::count());
        $this->assertSame(18, OrderStatus::count());
        $this->assertSame(StatusGroup::Reklamacja, OrderStatus::where('name', 'reklamacja')->first()->group);
    }

    public function test_seeder_keeps_changes_made_in_panel(): void
    {
        $this->seed(DictionarySeeder::class);
        OrderStatus::where('name', 'ręczne allegro')->update(['group' => StatusGroup::WRealizacji->value]);

        $this->seed(DictionarySeeder::class);

        $this->assertSame(StatusGroup::WRealizacji, OrderStatus::where('name', 'ręczne allegro')->first()->group);
    }

    public function test_every_role_can_view_dictionaries(): void
    {
        $this->seed(DictionarySeeder::class);

        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/spolki')->assertOk()->assertSee('Trusted Solutions');
            $this->actingAs($user)->get('/kanaly')->assertOk()->assertSee('medivon.pl');
            $this->actingAs($user)->get('/statusy')->assertOk()->assertSee('przeniesione do trusted');
            $this->actingAs($user)->get('/')->assertOk()->assertSee('Do wyjaśnienia');
        }
    }

    public function test_employee_cannot_change_dictionaries(): void
    {
        $user = User::factory()->create(['role' => Role::Kierownik]);

        $this->actingAs($user)->get('/kanaly/create')->assertForbidden();
        $this->actingAs($user)->post('/kanaly', ['name' => 'X', 'type' => 'inny'])->assertForbidden();
        $this->assertSame(0, SalesChannel::count());
    }

    public function test_admin_can_create_update_and_delete_channel(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::create(['name' => 'Swing 2 Harmony', 'short_name' => 'Swing']);

        $this->actingAs($admin)->post('/kanaly', [
            'name' => 'Sklep testowy', 'type' => 'woocommerce', 'company_id' => $company->id, 'is_active' => '1',
        ])->assertRedirect('/kanaly');

        $channel = SalesChannel::firstWhere('name', 'Sklep testowy');
        $this->assertSame(ChannelType::WooCommerce, $channel->type);
        $this->assertTrue($channel->company->is($company));

        $this->actingAs($admin)->put("/kanaly/{$channel->id}", [
            'name' => 'Sklep testowy', 'type' => 'woocommerce', 'baselinker_source' => 'shop_123',
        ])->assertRedirect('/kanaly');
        $channel->refresh();
        $this->assertSame('shop_123', $channel->baselinker_source);
        $this->assertNull($channel->company_id);
        $this->assertFalse($channel->is_active);

        $this->actingAs($admin)->delete("/kanaly/{$channel->id}")->assertRedirect('/kanaly');
        $this->assertSame(0, SalesChannel::count());
    }

    public function test_required_fields_are_enforced_with_polish_messages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from('/statusy/create')->post('/statusy', ['name' => '', 'group' => 'zly'])
            ->assertRedirect('/statusy/create')
            ->assertSessionHasErrors([
                'name' => 'Pole nazwa jest wymagane.',
                'group' => 'Wybrana wartość pola grupa jest nieprawidłowa.',
            ]);
        $this->assertSame(0, OrderStatus::count());
    }

    public function test_baselinker_status_id_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        OrderStatus::create(['name' => 'A', 'group' => StatusGroup::Wyslane, 'baselinker_status_id' => 5]);

        $this->actingAs($admin)->post('/statusy', ['name' => 'B', 'group' => 'wyslane', 'baselinker_status_id' => 5])
            ->assertSessionHasErrors('baselinker_status_id');
    }
}
