<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KaspiEnrichmentApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-token-that-is-at-least-32-characters-long';

    protected function setUp(): void
    {
        parent::setUp();
        config(['kaspi.api_token' => $this->token]);
    }

    // ─── Auth ────────────────────────────────────────────────────────────────

    public function test_candidates_requires_token(): void
    {
        $this->getJson('/api/kaspi/candidates')
            ->assertStatus(403);
    }

    public function test_candidates_rejects_wrong_token(): void
    {
        $this->withHeader('Authorization', 'Bearer wrong-token-value')
            ->getJson('/api/kaspi/candidates')
            ->assertStatus(403);
    }

    public function test_candidates_accepts_valid_token(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/kaspi/candidates')
            ->assertOk()
            ->assertJsonStructure(['candidates', 'not_found']);
    }

    // ─── GET /api/kaspi/candidates ───────────────────────────────────────────

    public function test_candidates_returns_resolved_skus(): void
    {
        $this->seedProduct('SKU-001');
        DB::table('kaspi_lookup')->insert([
            'sku'        => 'SKU-001',
            'partner_id' => 'Avtoximiya',
            'city_id'    => '750000000',
            'status'     => 'resolved',
            'url'        => 'https://kaspi.kz/shop/p/some-product-12345678/',
            'detail'     => '',
            'frame_url'  => 'https://kaspi.kz/shop/kaspibutton/frame/?merchantSku=SKU-001&merchantCode=Avtoximiya&city=750000000&template=button&style=desktop',
            'checked_at' => now(),
            'attempts'   => 1,
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/kaspi/candidates')
            ->assertOk();

        $this->assertCount(1, $resp->json('candidates'));
        $this->assertSame('SKU-001', $resp->json('candidates.0.sku'));
        $this->assertNull($resp->json('candidates.0.content_status'));
    }

    public function test_candidates_not_found_count(): void
    {
        $this->seedProduct('SKU-002');
        DB::table('kaspi_lookup')->insert([
            'sku'        => 'SKU-002',
            'partner_id' => 'Avtoximiya',
            'city_id'    => '750000000',
            'status'     => 'not_found',
            'url'        => null,
            'detail'     => 'Product not found',
            'frame_url'  => '',
            'checked_at' => now(),
            'attempts'   => 1,
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/kaspi/candidates')
            ->assertOk();

        $this->assertSame(1, $resp->json('not_found'));
        $this->assertCount(0, $resp->json('candidates')); // not_found SKU is excluded
    }

    // ─── POST /api/kaspi/content ─────────────────────────────────────────────

    public function test_store_content_saves_new_record(): void
    {
        $this->seedProduct('SKU-001');
        $this->seedLookup('SKU-001', 'https://kaspi.kz/shop/p/some-product-12345678/');

        $payload = $this->validContentPayload('SKU-001', 'https://kaspi.kz/shop/p/some-product-12345678/');

        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $payload)
            ->assertOk();

        $this->assertSame('SKU-001', $resp->json('sku'));
        $this->assertContains($resp->json('status'), ['ready', 'pending_category']);

        $row = DB::table('kaspi_content')->where('sku', 'SKU-001')->first();
        $this->assertNotNull($row);
        $this->assertSame('Test description', $row->description);
    }

    public function test_store_content_does_not_overwrite_manual_fields(): void
    {
        $this->seedProduct('SKU-001');
        $this->seedLookup('SKU-001', 'https://kaspi.kz/shop/p/some-product-12345678/');

        // Pre-seed content with manual fields set
        DB::table('kaspi_content')->insert([
            'sku'                   => 'SKU-001',
            'url'                   => 'https://kaspi.kz/shop/p/some-product-12345678/',
            'description'           => 'Old description',
            'attributes_json'       => '[]',
            'photos_json'           => '[]',
            'category_path_json'    => '[]',
            'breadcrumbs_json'      => '[]',
            'status'                => 'pending_category',
            'detail'                => '',
            'source_sha256'         => '',
            'received_at'           => now(),
            'manual_description'    => 'Editor override',
            'manual_attributes_json'=> '["custom"]',
            'manual_photos_json'    => '["https://example.com/photo.jpg"]',
        ]);

        $payload = $this->validContentPayload('SKU-001', 'https://kaspi.kz/shop/p/some-product-12345678/');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $payload)
            ->assertOk();

        $row = DB::table('kaspi_content')->where('sku', 'SKU-001')->first();
        $this->assertSame('Editor override', $row->manual_description);
        $this->assertSame('["custom"]', $row->manual_attributes_json);
        $this->assertSame('["https://example.com/photo.jpg"]', $row->manual_photos_json);
    }

    public function test_store_content_does_not_modify_price_or_stock(): void
    {
        $this->seedProduct('SKU-001', price: '12999.00', quantity: 5);
        $this->seedLookup('SKU-001', 'https://kaspi.kz/shop/p/some-product-12345678/');

        $payload = $this->validContentPayload('SKU-001', 'https://kaspi.kz/shop/p/some-product-12345678/');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $payload)
            ->assertOk();

        $product = DB::table('products')->where('sku', 'SKU-001')->first();
        $this->assertSame('12999.00', $product->price_kzt);
        $this->assertSame(5, $product->quantity);
    }

    public function test_store_content_error_does_not_overwrite_existing_content(): void
    {
        $this->seedProduct('SKU-001');
        $this->seedLookup('SKU-001', 'https://kaspi.kz/shop/p/some-product-12345678/');

        DB::table('kaspi_content')->insert([
            'sku'                => 'SKU-001',
            'url'                => 'https://kaspi.kz/shop/p/some-product-12345678/',
            'description'        => 'Existing good description',
            'attributes_json'    => '[]',
            'photos_json'        => '[]',
            'category_path_json' => '[]',
            'breadcrumbs_json'   => '[]',
            'status'             => 'ready',
            'detail'             => '',
            'source_sha256'      => 'abc123',
            'received_at'        => now(),
        ]);

        $errorPayload = [
            'sku'    => 'SKU-001',
            'url'    => 'https://kaspi.kz/shop/p/some-product-12345678/',
            'status' => 'error',
            'detail' => 'Parser failed',
            'description'    => '',
            'attributes'     => [],
            'images'         => [],
            'breadcrumbs'    => [],
            'category_path'  => [],
            'source_sha256'  => '',
        ];

        $resp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $errorPayload)
            ->assertOk();

        $this->assertSame('error', $resp->json('status'));

        // Existing content must be unchanged
        $row = DB::table('kaspi_content')->where('sku', 'SKU-001')->first();
        $this->assertSame('Existing good description', $row->description);
        $this->assertSame('ready', $row->status);
    }

    public function test_store_content_rejects_unknown_fields(): void
    {
        $this->seedProduct('SKU-001');
        $this->seedLookup('SKU-001', 'https://kaspi.kz/shop/p/some-product-12345678/');

        $payload = array_merge(
            $this->validContentPayload('SKU-001', 'https://kaspi.kz/shop/p/some-product-12345678/'),
            ['extra_field' => 'value']
        );

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $payload)
            ->assertStatus(422);
    }

    public function test_store_content_rejects_unresolved_sku(): void
    {
        $this->seedProduct('SKU-001'); // No kaspi_lookup row

        $payload = $this->validContentPayload('SKU-001', 'https://kaspi.kz/shop/p/some-product-12345678/');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $payload)
            ->assertStatus(422);
    }

    // ─── POST /api/kaspi/widget-result ───────────────────────────────────────

    public function test_widget_result_returns_404_for_unknown_sku(): void
    {
        $payload = [
            'sku'        => 'NONEXISTENT-SKU',
            'partner_id' => 'Avtoximiya',
            'city_id'    => '750000000',
            'status'     => 'resolved',
            'url'        => 'https://kaspi.kz/shop/p/product-12345678/',
            'detail'     => '',
        ];

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/widget-result', $payload)
            ->assertStatus(404);
    }

    public function test_widget_result_upserts_lookup(): void
    {
        $this->seedProduct('SKU-001');

        $payload = [
            'sku'        => 'SKU-001',
            'partner_id' => 'Avtoximiya',
            'city_id'    => '750000000',
            'status'     => 'resolved',
            'url'        => 'https://kaspi.kz/shop/p/product-12345678/',
            'detail'     => '',
        ];

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/widget-result', $payload)
            ->assertOk()
            ->assertJson(['sku' => 'SKU-001', 'status' => 'resolved']);

        $this->assertDatabaseHas('kaspi_lookup', [
            'sku'    => 'SKU-001',
            'status' => 'resolved',
            'url'    => 'https://kaspi.kz/shop/p/product-12345678/',
        ]);
    }

    // ─── Category ID Generation (Python-compatible SHA-256) ──────────────────

    public function test_rebuild_tree_generates_python_compatible_id_for_exhaust(): void
    {
        $sku = 'GS-BLOC-GS-900-WHITE';
        $url = 'https://kaspi.kz/shop/p/gs-bloc-gs-900-white-12345678/';
        $this->seedProduct($sku);
        $this->seedLookup($sku, $url);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $this->contentPayloadWithPath($sku, $url, [
                'Бытовая техника', 'Крупная техника для дома', 'Вытяжки',
            ]))
            ->assertOk()
            ->assertJson(['status' => 'ready']);

        $row = DB::table('product_kaspi_categories')->where('sku', $sku)->first();
        $this->assertNotNull($row, 'product_kaspi_categories row missing');
        $this->assertSame('b03186f32cc9f323b8ce1589', $row->category_id);
    }

    public function test_rebuild_tree_generates_python_compatible_id_for_cooler(): void
    {
        $sku = 'COOLER-SKU-671143194';
        $url = 'https://kaspi.kz/shop/p/cooler-671143194-99999999/';
        $this->seedProduct($sku);
        $this->seedLookup($sku, $url);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $this->contentPayloadWithPath($sku, $url, [
                'Бытовая техника', 'Крупная техника для дома', 'Кулеры для воды',
            ]))
            ->assertOk()
            ->assertJson(['status' => 'ready']);

        $row = DB::table('product_kaspi_categories')->where('sku', $sku)->first();
        $this->assertNotNull($row, 'product_kaspi_categories row missing');
        $this->assertSame('3b50c632c5fcf97505984a51', $row->category_id);
    }

    public function test_rebuild_tree_generates_python_compatible_id_for_microwave(): void
    {
        $sku = 'BIMO-15-01N-WH';
        $url = 'https://kaspi.kz/shop/p/bimo-15-01n-wh-12345678/';
        $this->seedProduct($sku);
        $this->seedLookup($sku, $url);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $this->contentPayloadWithPath($sku, $url, [
                'Бытовая техника', 'Кухонные приборы для готовки', 'Плиты и гриль', 'Микроволновые печи',
            ]))
            ->assertOk()
            ->assertJson(['status' => 'ready']);

        $row = DB::table('product_kaspi_categories')->where('sku', $sku)->first();
        $this->assertNotNull($row, 'product_kaspi_categories row missing');
        // sha256('["Бытовая техника", "Кухонные приборы для готовки", "Плиты и гриль", "Микроволновые печи"]')[:24]
        $this->assertSame('c64e107d7e4e805b6a488992', $row->category_id);
    }

    public function test_rebuild_tree_preserves_category_overrides(): void
    {
        $sku = 'OVERRIDE-TEST-SKU-001';
        $url = 'https://kaspi.kz/shop/p/override-test-12345678/';
        $this->seedProduct($sku);
        $this->seedLookup($sku, $url);

        $payload = $this->contentPayloadWithPath($sku, $url, ['Бытовая техника', 'Вытяжки']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $payload)
            ->assertOk();

        $categoryId = DB::table('product_kaspi_categories')->where('sku', $sku)->value('category_id');
        $this->assertNotNull($categoryId);

        DB::table('kaspi_category_overrides')->insert([
            'category_id'  => $categoryId,
            'display_name' => 'Custom Display Name',
        ]);

        // Second push triggers a full rebuild
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/content', $payload)
            ->assertOk();

        $override = DB::table('kaspi_category_overrides')->where('category_id', $categoryId)->first();
        $this->assertNotNull($override, 'Override was deleted during rebuild');
        $this->assertSame('Custom Display Name', $override->display_name);
    }

    public function test_catalog_tree_excludes_products_without_resolved_lookup(): void
    {
        $this->seedProduct('EXCLUDED-SKU-001');

        DB::table('kaspi_content')->insert([
            'sku'                => 'EXCLUDED-SKU-001',
            'url'                => 'https://kaspi.kz/shop/p/excluded-sku-99999999/',
            'description'        => 'Test',
            'attributes_json'    => '[]',
            'photos_json'        => '[]',
            'category_path_json' => '[]',
            'breadcrumbs_json'   => '[]',
            'status'             => 'ready',
            'detail'             => '',
            'source_sha256'      => str_repeat('b', 64),
            'received_at'        => now(),
        ]);

        // Manually insert category and mapping to bypass rebuildTree (which already requires resolved lookup)
        DB::table('kaspi_categories')->insert([
            'id'        => 'excl-cat-test-id-000001',
            'parent_id' => null,
            'name'      => 'Should Not Appear',
            'path'      => 'Should Not Appear',
            'source_id' => 'excl1',
        ]);
        DB::table('product_kaspi_categories')->insert([
            'sku'         => 'EXCLUDED-SKU-001',
            'category_id' => 'excl-cat-test-id-000001',
        ]);

        $service = new \App\Services\CategoryTreeService();
        $ids     = array_map(fn ($c) => $c->id, $service->getPublicCategories());

        $this->assertNotContains('excl-cat-test-id-000001', $ids);
    }

    public function test_widget_result_rejects_wrong_partner_id(): void
    {
        $this->seedProduct('SKU-001');

        $payload = [
            'sku'        => 'SKU-001',
            'partner_id' => 'WrongPartner',
            'city_id'    => '750000000',
            'status'     => 'not_found',
            'url'        => null,
            'detail'     => '',
        ];

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/kaspi/widget-result', $payload)
            ->assertStatus(422);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function seedProduct(string $sku, string $price = '1000.00', int $quantity = 1): void
    {
        DB::table('source_categories')->insertOrIgnore([
            'id' => 'test-cat', 'name' => 'Test', 'path' => 'Test', 'present' => 1,
        ]);
        DB::table('products')->insertOrIgnore([
            'sku'                  => $sku,
            'source_offer_id'      => $sku,
            'name'                 => "Product $sku",
            'brand'                => 'TestBrand',
            'price_kzt'            => $price,
            'quantity'             => $quantity,
            'available'            => 1,
            'source_category_id'   => 'test-cat',
            'source_category_path' => 'Test',
            'source_description'   => '',
            'source_photos_json'   => '[]',
            'present'              => 1,
            'last_imported_at'     => now(),
        ]);
    }

    private function seedLookup(string $sku, string $url): void
    {
        DB::table('kaspi_lookup')->insert([
            'sku'        => $sku,
            'partner_id' => 'Avtoximiya',
            'city_id'    => '750000000',
            'status'     => 'resolved',
            'url'        => $url,
            'detail'     => '',
            'frame_url'  => 'https://kaspi.kz/shop/kaspibutton/frame/?merchantSku=' . urlencode($sku),
            'checked_at' => now(),
            'attempts'   => 1,
        ]);
    }

    private function validContentPayload(string $sku, string $url): array
    {
        return [
            'sku'           => $sku,
            'url'           => $url,
            'status'        => 'pending_category',
            'description'   => 'Test description',
            'attributes'    => [['name' => 'Color', 'value' => 'Red']],
            'images'        => ['https://resources.cdn-kaspi.kz/img/product/photo.jpg'],
            'breadcrumbs'   => [],
            'category_path' => [],
            'detail'        => 'Full category path unavailable',
            'source_sha256' => 'abc123def456abc123def456abc123def456abc123def456abc123def456abc1',
        ];
    }

    private function contentPayloadWithPath(string $sku, string $url, array $pathNames): array
    {
        $breadcrumbs = [['name' => 'Kaspi Магазин', 'url' => '/shop/c/categories/']];
        foreach ($pathNames as $i => $name) {
            $breadcrumbs[] = ['id' => 'src-' . $i, 'name' => $name, 'url' => '/shop/c/level' . $i . '/'];
        }
        $category_path = array_slice($breadcrumbs, 1);
        return [
            'sku'           => $sku,
            'url'           => $url,
            'status'        => 'ready',
            'description'   => 'Test',
            'attributes'    => [],
            'images'        => ['https://resources.cdn-kaspi.kz/img/test.jpg'],
            'breadcrumbs'   => $breadcrumbs,
            'category_path' => $category_path,
            'detail'        => '',
            'source_sha256' => hash('sha256', implode(',', $pathNames)),
        ];
    }
}
