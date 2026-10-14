<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function seedProductAndCart(): string
    {
        $sku = 'CHECKOUT-SKU-001';
        DB::table('source_categories')->insertOrIgnore([
            'id'      => 'checkout-cat-001',
            'name'    => 'Checkout Category',
            'path'    => 'Checkout Category',
            'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => 'offer-checkout-001',
            'name'                 => 'Checkout товар',
            'brand'                => 'CheckBrand',
            'price_kzt'            => '12500.00',
            'quantity'             => 10,
            'available'            => 1,
            'source_category_id'   => 'checkout-cat-001',
            'source_category_path' => 'Checkout Category',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
        ]);

        $this->withSession(['cart' => [$sku => ['sku' => $sku, 'qty' => 2]]]);

        return $sku;
    }

    public function test_checkout_redirects_when_cart_empty(): void
    {
        $response = $this->get(route('checkout.index'));
        $response->assertRedirect(route('cart.index'));
    }

    public function test_checkout_page_renders_with_cart(): void
    {
        $sku = $this->seedProductAndCart();

        $response = $this->withSession(['cart' => [$sku => ['sku' => $sku, 'qty' => 2]]])
            ->get(route('checkout.index'));

        $response->assertStatus(200);
        $response->assertSee('Оформление заказа');
    }

    public function test_checkout_creates_order_with_correct_snapshot(): void
    {
        $sku = $this->seedProductAndCart();

        $response = $this->withSession(['cart' => [$sku => ['sku' => $sku, 'qty' => 2]]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Иван Иванов',
                'phone'         => '+7 777 123 45 67',
                'city'          => 'Алматы',
                'comment'       => 'Тестовый заказ',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Иван Иванов',
            'phone'         => '+7 777 123 45 67',
            'city'          => 'Алматы',
            'status'        => 'new',
        ]);

        $order = Order::first();
        $this->assertNotNull($order->order_number);
        $this->assertStringStartsWith('NB-', $order->order_number);

        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals($sku, $item->sku);
        $this->assertEquals('Checkout товар', $item->name);
        $this->assertEquals(2, $item->qty);
        $this->assertEquals('12500.00', $item->price_kzt);
        $this->assertEquals('25000.00', $item->subtotal_kzt);
    }

    public function test_cart_cleared_after_successful_checkout(): void
    {
        $sku = $this->seedProductAndCart();

        $this->withSession(['cart' => [$sku => ['sku' => $sku, 'qty' => 1]]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Тест',
                'phone'         => '+77001234567',
                'city'          => 'Астана',
            ]);

        $this->assertEmpty(session('cart', []));
    }

    public function test_checkout_validation_requires_fields(): void
    {
        $sku = $this->seedProductAndCart();

        $response = $this->withSession(['cart' => [$sku => ['sku' => $sku, 'qty' => 1]]])
            ->post(route('checkout.store'), []);

        $response->assertSessionHasErrors(['customer_name', 'phone', 'city']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_status_defaults_to_new(): void
    {
        $sku = $this->seedProductAndCart();

        $this->withSession(['cart' => [$sku => ['sku' => $sku, 'qty' => 1]]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Test',
                'phone'         => '+77771234567',
                'city'          => 'Тест',
            ]);

        $order = Order::first();
        $this->assertEquals(OrderStatus::New, $order->status);
    }

    public function test_order_number_format(): void
    {
        $sku = $this->seedProductAndCart();

        $this->withSession(['cart' => [$sku => ['sku' => $sku, 'qty' => 1]]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Format Test',
                'phone'         => '+77771111111',
                'city'          => 'Тест',
            ]);

        $order = Order::first();
        $this->assertMatchesRegularExpression('/^NB-\d{8}-\d{6}$/', $order->order_number);
    }

    public function test_done_page_shows_order(): void
    {
        $sku = $this->seedProductAndCart();

        $this->withSession(['cart' => [$sku => ['sku' => $sku, 'qty' => 1]]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Покупатель',
                'phone'         => '+77772222222',
                'city'          => 'Алматы',
            ]);

        $order    = Order::first();
        $response = $this->get(route('checkout.done', ['id' => $order->id]));

        $response->assertStatus(200);
        $response->assertSee($order->order_number);
        $response->assertSee('Покупатель');
    }

    // ── P0 stock gate tests ────────────────────────────────────────────────────

    public function test_checkout_blocked_when_product_goes_out_of_stock(): void
    {
        // Seed product as available, put it in the cart, then zero it (simulates
        // an import run that archives the SKU while it sits in the customer's cart).
        $sku = 'CHECKOUT-OOS-SKU';
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'checkout-cat-001', 'name' => 'Checkout Category',
            'path' => 'Checkout Category', 'present' => 1,
        ]);
        DB::table('products')->insert([
            'sku'                  => $sku,
            'source_offer_id'      => 'offer-oos-checkout',
            'name'                 => 'Архивный в корзине',
            'brand'                => 'TestBrand',
            'price_kzt'            => '9990.00',
            'quantity'             => 5,
            'available'            => 1,
            'source_category_id'   => 'checkout-cat-001',
            'source_category_path' => 'Checkout Category',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
        ]);
        DB::table('stock_pp3')->insert([
            'sku'            => $sku,
            'export_sku'     => $sku,
            'match_method'   => 'exact',
            'raw_stock'      => 5,
            'preorder_days'  => 0,
            'source_available' => 1,
            'quantity'       => 5,
            'state'          => 'in_stock',
            'present_export' => 1,
            'updated_at'     => now()->toISOString(),
        ]);

        // Simulate import zeroing the SKU between cart add and checkout submit
        DB::table('products')->where('sku', $sku)
            ->update(['available' => 0, 'quantity' => 0]);
        DB::table('stock_pp3')->where('sku', $sku)
            ->update(['state' => 'out_of_stock', 'quantity' => 0, 'raw_stock' => 0,
                      'source_available' => 0, 'present_export' => 0]);

        $response = $this->withSession(['cart' => [$sku => ['sku' => $sku, 'qty' => 1]]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Тест Клиент',
                'phone'         => '+77770000001',
                'city'          => 'Алматы',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_proceeds_with_preorder_product(): void
    {
        $sku = 'CHECKOUT-PREORDER-SKU';
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'checkout-cat-001', 'name' => 'Checkout Category',
            'path' => 'Checkout Category', 'present' => 1,
        ]);
        DB::table('products')->insert([
            'sku'                  => $sku,
            'source_offer_id'      => 'offer-preorder-checkout',
            'name'                 => 'Предзаказ в корзине',
            'brand'                => 'TestBrand',
            'price_kzt'            => '18000.00',
            'quantity'             => 0,
            'available'            => 0,
            'source_category_id'   => 'checkout-cat-001',
            'source_category_path' => 'Checkout Category',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
        ]);
        DB::table('stock_pp3')->insert([
            'sku'            => $sku,
            'export_sku'     => $sku,
            'match_method'   => 'exact',
            'raw_stock'      => 0,
            'preorder_days'  => 14,
            'source_available' => 1,
            'quantity'       => 0,
            'state'          => 'preorder',
            'present_export' => 1,
            'updated_at'     => now()->toISOString(),
        ]);

        $response = $this->withSession(['cart' => [$sku => ['sku' => $sku, 'qty' => 1]]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Предзаказчик',
                'phone'         => '+77770000002',
                'city'          => 'Алматы',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('order_items', ['sku' => $sku]);
    }
}
