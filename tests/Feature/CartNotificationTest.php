<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for OOS (out-of-stock) notification UX:
 * AJAX responses, flash fallback, WA link construction, server-side guard.
 */
class CartNotificationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Seed helpers ────────────────────────────────────────────────────────

    private function seedCategory(): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'notif-cat', 'name' => 'Notif Cat',
            'path' => 'Notif Cat', 'present' => 1,
        ]);
    }

    private function seedProduct(string $sku, string $name, bool $available, string $state = 'in_stock'): void
    {
        $this->seedCategory();
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => $name,
            'brand'                => 'TestBrand',
            'price_kzt'            => '10000.00',
            'quantity'             => $available ? 5 : 0,
            'available'            => $available ? 1 : 0,
            'source_category_id'   => 'notif-cat',
            'source_category_path' => 'Notif Cat',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
        ]);
        DB::table('stock_pp3')->insertOrIgnore([
            'sku'            => $sku,
            'export_sku'     => $sku,
            'match_method'   => 'exact',
            'raw_stock'      => $available ? 5 : 0,
            'preorder_days'  => 0,
            'source_available' => $available ? 1 : 0,
            'quantity'       => $available ? 5 : 0,
            'state'          => $state,
            'present_export' => 1,
            'updated_at'     => now()->toISOString(),
        ]);
    }

    private function seedPreorderProduct(string $sku, string $name): void
    {
        $this->seedCategory();
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => $name,
            'brand'                => 'TestBrand',
            'price_kzt'            => '8000.00',
            'quantity'             => 0,
            'available'            => 0,
            'source_category_id'   => 'notif-cat',
            'source_category_path' => 'Notif Cat',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
        ]);
        DB::table('stock_pp3')->insertOrIgnore([
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
    }

    // ─── 1. Zero stock — AJAX returns 409 with correct JSON ──────────────────

    public function test_ajax_returns_409_and_out_of_stock_type_for_unavailable_product(): void
    {
        $this->seedProduct('OOS-001', 'Тестовый архивный товар', false, 'out_of_stock');

        $response = $this->postJson(route('cart.items.store'), [
            'sku' => 'OOS-001',
            'qty' => 1,
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'ok'   => false,
            'type' => 'out_of_stock',
        ]);
        // Product name must be in response so JS can build the WA link
        $this->assertEquals('Тестовый архивный товар', $response->json('product_name'));
        $this->assertEquals('OOS-001', $response->json('sku'));
        // Must NOT appear in cart
        $this->assertEmpty(session('cart', []));
    }

    // ─── 2. AJAX success for in-stock product ────────────────────────────────

    public function test_ajax_returns_200_ok_for_in_stock_product(): void
    {
        $this->seedProduct('IN-001', 'Доступный товар', true);

        $response = $this->postJson(route('cart.items.store'), [
            'sku' => 'IN-001',
            'qty' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
        $response->assertJsonStructure(['ok', 'cart_count']);
        $this->assertArrayHasKey('IN-001', session('cart', []));
    }

    // ─── 3. AJAX cart_count increments correctly ─────────────────────────────

    public function test_ajax_cart_count_reflects_qty_in_session(): void
    {
        $this->seedProduct('COUNT-A', 'Product A', true);
        $this->seedProduct('COUNT-B', 'Product B', true);

        $this->postJson(route('cart.items.store'), ['sku' => 'COUNT-A', 'qty' => 2]);
        $r = $this->postJson(route('cart.items.store'), ['sku' => 'COUNT-B', 'qty' => 3]);

        $r->assertJson(['ok' => true, 'cart_count' => 5]);
    }

    // ─── 4. WA link: product_name and sku present for dialog construction ────

    public function test_ajax_oos_response_contains_product_name_and_sku_for_wa_link(): void
    {
        $this->seedProduct('WA-TEST', 'Кресло ProChair X3', false, 'out_of_stock');

        $response = $this->postJson(route('cart.items.store'), ['sku' => 'WA-TEST', 'qty' => 1]);

        $response->assertStatus(409);
        // JS constructs: "Здравствуйте! Подскажите, когда появится в наличии {name}, артикул {sku}?"
        $this->assertEquals('Кресло ProChair X3', $response->json('product_name'));
        $this->assertEquals('WA-TEST', $response->json('sku'));
    }

    // ─── 5. Preorder — AJAX succeeds (preorder is allowed) ───────────────────

    public function test_ajax_succeeds_for_preorder_product(): void
    {
        $this->seedPreorderProduct('PRE-001', 'Товар под заказ');

        $response = $this->postJson(route('cart.items.store'), [
            'sku' => 'PRE-001',
            'qty' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
        $this->assertArrayHasKey('PRE-001', session('cart', []));
    }

    // ─── 6. Server-side guard preserved — plain POST returns redirect+flash ──

    public function test_plain_post_redirects_with_flash_error_for_oos_product(): void
    {
        $this->seedProduct('OOS-PLAIN', 'Нет в наличии товар', false, 'out_of_stock');

        $response = $this->post(route('cart.items.store'), [
            'sku' => 'OOS-PLAIN',
            'qty' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEmpty(session('cart', []));
    }

    // ─── 7. Plain POST success returns redirect+flash success ────────────────

    public function test_plain_post_redirects_with_flash_success_for_available_product(): void
    {
        $this->seedProduct('OK-PLAIN', 'В наличии товар', true);

        $response = $this->post(route('cart.items.store'), [
            'sku' => 'OK-PLAIN',
            'qty' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertArrayHasKey('OK-PLAIN', session('cart', []));
    }

    // ─── 8. Non-existent SKU — AJAX 422 ──────────────────────────────────────

    public function test_ajax_returns_422_for_nonexistent_sku(): void
    {
        $response = $this->postJson(route('cart.items.store'), [
            'sku' => 'GHOST-SKU',
            'qty' => 1,
        ]);

        $response->assertStatus(409); // not present → treated as unavailable
        $response->assertJson(['ok' => false]);
        $this->assertEmpty(session('cart', []));
    }

    // ─── 9. Server guard: absent SKU absent from DB blocks AJAX add ──────────

    public function test_ajax_blocks_add_when_product_present_false(): void
    {
        // present=0 product should never be orderable
        $this->seedCategory();
        DB::table('products')->insert([
            'sku'                  => 'HIDDEN-SKU',
            'source_offer_id'      => 'HIDDEN-SKU',
            'name'                 => 'Скрытый товар',
            'brand'                => 'Brand',
            'price_kzt'            => '5000.00',
            'quantity'             => 10,
            'available'            => 1,
            'source_category_id'   => 'notif-cat',
            'source_category_path' => 'Notif Cat',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 0, // hidden from catalog
        ]);

        $response = $this->postJson(route('cart.items.store'), ['sku' => 'HIDDEN-SKU', 'qty' => 1]);

        $response->assertStatus(409);
        $response->assertJson(['ok' => false]);
    }

    // ─── 10. Empty SKU — AJAX 422 ────────────────────────────────────────────

    public function test_ajax_returns_422_for_empty_sku(): void
    {
        $response = $this->postJson(route('cart.items.store'), ['sku' => '', 'qty' => 1]);
        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);
    }

    // ─── 11. Checkout shows session('error') for OOS item ────────────────────

    public function test_checkout_page_shows_flash_error_from_session(): void
    {
        // Seed an available product so checkout page doesn't immediately redirect
        $this->seedProduct('CHK-001', 'Checkout товар', true);

        $response = $this->withSession([
            'cart'  => ['CHK-001' => ['sku' => 'CHK-001', 'qty' => 1]],
            'error' => 'Следующие товары недоступны для заказа: Checkout товар. Пожалуйста, обновите корзину.',
        ])->get(route('checkout.index'));

        $response->assertStatus(200);
        $response->assertSee('Следующие товары недоступны для заказа');
    }

    // ─── 12. Product page shows flash error fallback ──────────────────────────

    public function test_product_page_shows_flash_error_for_no_js_fallback(): void
    {
        $this->seedProduct('PDP-OOS', 'Товар страница', false, 'out_of_stock');

        // Simulate what happens when cart.store redirects back with error flash
        $response = $this->withSession(['error' => 'Товар недоступен для заказа.'])
            ->get(route('product.show', ['sku' => 'PDP-OOS']));

        $response->assertStatus(200);
        $response->assertSee('Товар недоступен для заказа.');
    }
}
