<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Ga4Test extends TestCase
{
    use RefreshDatabase;

    private const MEASUREMENT_ID = 'G-TEST1234567';

    private function enableGa4(): void
    {
        config(['analytics.ga4_measurement_id' => self::MEASUREMENT_ID]);
    }

    private function disableGa4(): void
    {
        config(['analytics.ga4_measurement_id' => '']);
    }

    private function seedProduct(array $overrides = []): object
    {
        $sku = $overrides['sku'] ?? 'SKU-GA4-TEST';
        DB::table('source_categories')->insertOrIgnore([
            'id' => '__manual__', 'name' => 'Ручной товар', 'path' => '__manual__', 'present' => 0,
        ]);
        DB::table('products')->insertOrIgnore(array_merge([
            'sku'                    => $sku,
            'source_offer_id'        => $sku,
            'name'                   => 'Тест GA4',
            'brand'                  => 'Бренд',
            'price_kzt'              => 9900,
            'quantity'               => 5,
            'available'              => 1,
            'source_category_id'     => '__manual__',
            'source_category_path'   => '',
            'source_description'     => '',
            'source_photos_json'     => '[]',
            'present'                => 1,
            'url_slug'               => $sku,
            'source'                 => 'manual',
            'exclude_from_feed_sync' => 1,
            'last_imported_at'       => now(),
        ], $overrides));
        DB::table('stock_pp3')->insertOrIgnore([
            'sku'              => $sku,
            'export_sku'       => $sku,
            'match_method'     => 'manual',
            'raw_stock'        => 5,
            'preorder_days'    => 0,
            'source_available' => 1,
            'quantity'         => 5,
            'state'            => 'in_stock',
            'present_export'   => 1,
            'updated_at'       => now()->toISOString(),
        ]);
        return DB::table('products')->where('sku', $sku)->first();
    }

    // ─── 1. GA4 script rendered when ID set ──────────────────────────────────

    public function test_ga4_script_rendered_when_id_set(): void
    {
        $this->enableGa4();

        $this->get(route('home'))
            ->assertStatus(200)
            ->assertSee('googletagmanager.com/gtag/js', false)
            ->assertSee(self::MEASUREMENT_ID, false);
    }

    // ─── 2. GA4 script not rendered when ID empty ─────────────────────────────

    public function test_ga4_script_not_rendered_when_id_empty(): void
    {
        $this->disableGa4();

        $this->get(route('home'))
            ->assertStatus(200)
            ->assertDontSee('googletagmanager.com/gtag/js', false);
    }

    // ─── 3. Product page contains view_item when GA4 enabled ─────────────────

    public function test_product_page_contains_view_item_event(): void
    {
        $this->enableGa4();
        $this->seedProduct();

        $this->get(route('product.show', ['sku' => 'SKU-GA4-TEST']))
            ->assertStatus(200)
            ->assertSee('view_item', false);
    }

    // ─── 4. Product page WA button has ga4-placement attribute ───────────────

    public function test_product_wa_button_has_ga4_placement(): void
    {
        $this->enableGa4();
        $this->seedProduct();

        $this->get(route('product.show', ['sku' => 'SKU-GA4-TEST']))
            ->assertStatus(200)
            ->assertSee('data-ga4-placement="product_page"', false)
            ->assertSee('data-ga4-item-id="SKU-GA4-TEST"', false);
    }

    // ─── 5. Cart page fires view_cart when cart is non-empty ─────────────────

    public function test_cart_page_contains_view_cart_event_with_items(): void
    {
        $this->enableGa4();
        $this->seedProduct();

        $this->withSession(['cart' => ['SKU-GA4-TEST' => ['sku' => 'SKU-GA4-TEST', 'qty' => 1]]])
            ->get(route('cart.index'))
            ->assertStatus(200)
            ->assertSee('view_cart', false);
    }

    // ─── 6. Empty cart does not fire view_cart ────────────────────────────────

    public function test_cart_page_no_view_cart_event_when_empty(): void
    {
        $this->enableGa4();

        $this->get(route('cart.index'))
            ->assertStatus(200)
            ->assertDontSee('view_cart', false);
    }

    // ─── 7. Checkout page fires begin_checkout ────────────────────────────────

    public function test_checkout_page_contains_begin_checkout_event(): void
    {
        $this->enableGa4();
        $this->seedProduct();

        $this->withSession(['cart' => ['SKU-GA4-TEST' => ['sku' => 'SKU-GA4-TEST', 'qty' => 1]]])
            ->get(route('checkout.index'))
            ->assertStatus(200)
            ->assertSee('begin_checkout', false);
    }

    // ─── 8. Checkout done fires purchase exactly once ─────────────────────────

    public function test_checkout_done_fires_purchase_on_first_visit(): void
    {
        $this->enableGa4();
        $order = $this->createTestOrder();

        $this->withSession(['ga4_purchase_order_id' => $order->id])
            ->get(route('checkout.done', ['id' => $order->id]))
            ->assertStatus(200)
            ->assertSee("'purchase'", false);
    }

    // ─── 9. Checkout done does NOT fire purchase on second visit ─────────────

    public function test_checkout_done_does_not_fire_purchase_twice(): void
    {
        $this->enableGa4();
        $order = $this->createTestOrder();

        // First visit — clears the session flag
        $this->withSession(['ga4_purchase_order_id' => $order->id])
            ->get(route('checkout.done', ['id' => $order->id]));

        // Second visit — flag is gone, no purchase event
        $this->get(route('checkout.done', ['id' => $order->id]))
            ->assertStatus(200)
            ->assertDontSee("'purchase'", false);
    }

    // ─── 10. Catalog search fires search event ────────────────────────────────

    public function test_catalog_search_fires_search_event(): void
    {
        $this->enableGa4();

        $this->get(route('catalog.index', ['q' => 'кресло']))
            ->assertStatus(200)
            ->assertSee('search', false)
            ->assertSee('кресло', false);
    }

    // ─── 11. Catalog without query does not fire search event ────────────────

    public function test_catalog_without_search_no_search_event(): void
    {
        $this->enableGa4();

        $content = $this->get(route('catalog.index'))->assertStatus(200)->getContent();
        // The page may contain "search" in breadcrumb text, but not as a gtag call
        $this->assertStringNotContainsString("gtag('event', 'search'", $content);
    }

    // ─── 12. CartController JSON response includes ga4_item ──────────────────

    public function test_cart_ajax_response_includes_ga4_item(): void
    {
        $this->seedProduct();

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('cart.items.store'), ['sku' => 'SKU-GA4-TEST', 'qty' => 1]);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ga4_item.item_id', 'SKU-GA4-TEST')
            ->assertJsonPath('ga4_item.item_name', 'Тест GA4')
            ->assertJsonPath('ga4_item.price', 9900);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createTestOrder(): Order
    {
        $order = Order::create([
            'status'        => 'new',
            'customer_name' => 'Тест',
            'phone'         => '+77001112233',
            'city'          => 'Алматы',
            'subtotal_kzt'  => 9900,
            'total_kzt'     => 9900,
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'sku'          => 'SKU-GA4-TEST',
            'name'         => 'Тест GA4',
            'brand'        => 'Бренд',
            'price_kzt'    => 9900,
            'qty'          => 1,
            'subtotal_kzt' => 9900,
        ]);

        return $order->fresh(['items']);
    }
}
