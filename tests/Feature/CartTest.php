<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function seedProduct(): string
    {
        $sku = 'TEST-SKU-001';
        DB::table('source_categories')->insertOrIgnore([
            'id'      => 'test-cat-001',
            'name'    => 'Test Category',
            'path'    => 'Test Category',
            'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                   => $sku,
            'source_offer_id'       => 'offer-001',
            'name'                  => 'Тестовый товар',
            'brand'                 => 'TestBrand',
            'price_kzt'             => '9990.00',
            'quantity'              => 5,
            'available'             => 1,
            'source_category_id'    => 'test-cat-001',
            'source_category_path'  => 'Test Category',
            'source_description'    => '',
            'source_photos_json'    => '[]',
            'present'               => 1,
        ]);
        return $sku;
    }

    public function test_cart_is_empty_by_default(): void
    {
        $response = $this->get(route('cart.index'));
        $response->assertStatus(200);
        $response->assertSee('Ваша корзина пуста');
    }

    public function test_add_product_to_cart(): void
    {
        $sku = $this->seedProduct();

        $response = $this->post(route('cart.items.store'), [
            'sku' => $sku,
            'qty' => 2,
        ]);

        $response->assertRedirect();
        $this->assertEquals(2, session('cart')[$sku]['qty']);
    }

    public function test_adding_same_sku_increments_qty(): void
    {
        $sku = $this->seedProduct();

        $this->post(route('cart.items.store'), ['sku' => $sku, 'qty' => 1]);
        $this->post(route('cart.items.store'), ['sku' => $sku, 'qty' => 2]);

        $this->assertEquals(3, session('cart')[$sku]['qty']);
    }

    public function test_cannot_add_nonexistent_product(): void
    {
        $response = $this->post(route('cart.items.store'), ['sku' => 'NOPE-SKU', 'qty' => 1]);
        $response->assertRedirect();
        $this->assertEmpty(session('cart', []));
    }

    public function test_update_cart_item_qty(): void
    {
        $sku = $this->seedProduct();
        $this->post(route('cart.items.store'), ['sku' => $sku, 'qty' => 1]);

        $this->patch(route('cart.items.update', $sku), ['qty' => 5]);

        $this->assertEquals(5, session('cart')[$sku]['qty']);
    }

    public function test_delete_cart_item(): void
    {
        $sku = $this->seedProduct();
        $this->post(route('cart.items.store'), ['sku' => $sku, 'qty' => 1]);

        $this->delete(route('cart.items.destroy', $sku));

        $this->assertArrayNotHasKey($sku, session('cart', []));
    }

    public function test_clear_cart(): void
    {
        $sku = $this->seedProduct();
        $this->post(route('cart.items.store'), ['sku' => $sku, 'qty' => 1]);

        $this->delete(route('cart.clear'));

        $this->assertEmpty(session('cart', []));
    }

    public function test_cart_badge_count_is_sum_of_qtys(): void
    {
        $sku1 = $this->seedProduct();

        // Add second test product
        $sku2 = 'TEST-SKU-002';
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku2,
            'source_offer_id'      => 'offer-002',
            'name'                 => 'Второй товар',
            'brand'                => 'Brand2',
            'price_kzt'            => '5000.00',
            'quantity'             => 3,
            'available'            => 1,
            'source_category_id'   => 'test-cat-001',
            'source_category_path' => 'Test Category',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
        ]);

        $this->post(route('cart.items.store'), ['sku' => $sku1, 'qty' => 3]);
        $this->post(route('cart.items.store'), ['sku' => $sku2, 'qty' => 2]);

        $cart      = session('cart', []);
        $cartCount = array_sum(array_column($cart, 'qty'));
        $this->assertEquals(5, $cartCount);
    }
}
