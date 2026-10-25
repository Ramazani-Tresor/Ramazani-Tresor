<?php

namespace Tests;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashService;
use App\Services\ProductService;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Settings::flush();
    }

    protected function user(string $role = Role::CASHIER): User
    {
        return User::factory()->role($role)->create();
    }

    protected function product(array $attrs = [], ?string $initialStock = '10', ?User $by = null): Product
    {
        $category = Category::query()->firstOrCreate(['name' => 'Test']);

        return app(ProductService::class)->create(array_merge([
            'reference' => 'P-'.Str::upper(Str::random(6)),
            'name' => 'Article '.Str::random(4),
            'type' => 'product',
            'category_id' => $category->id,
            'unit_id' => Unit::query()->where('symbol', 'pce')->value('id'),
            'cost_price' => '600.00',
            'sale_price' => '1000.00',
            'alert_threshold' => '2',
            'is_active' => true,
        ], $attrs), $initialStock, $by ?? $this->user(Role::ADMIN));
    }

    protected function openSession(User $user, string $amount = '0'): CashSession
    {
        return app(CashService::class)->open($user, $amount);
    }

    protected function cash(): PaymentMethod
    {
        return PaymentMethod::query()->where('code', 'cash')->firstOrFail();
    }

    protected function mobile(): PaymentMethod
    {
        return PaymentMethod::query()->where('code', 'mobile_money')->firstOrFail();
    }

    protected function salePayload(array $items, array $payments = [], array $extra = []): array
    {
        return array_merge([
            'client_uuid' => (string) Str::uuid(),
            'items' => $items,
            'payments' => $payments,
        ], $extra);
    }
}
