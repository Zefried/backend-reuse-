<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Category $subCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Piping & Tubing',
            'slug' => 'piping-and-tubing',
            'default_uom' => 'Meters',
        ]);

        $this->subCategory = Category::create([
            'name' => 'Copper Pipes',
            'slug' => 'copper-pipes',
            'parent_id' => $this->category->id,
            'default_uom' => 'Meters',
        ]);
    }

    public function test_can_create_product_with_size_variants(): void
    {
        $payload = [
            'name' => 'VRF Seamless Copper Pipe',
            'category_id' => $this->category->id,
            'sub_category_id' => $this->subCategory->id,
            'brand' => 'Mandev',
            'base_uom' => 'Meters',
            'package_uom' => 'Coils',
            'package_ratio' => 15.00,
            'has_variants' => true,
            'variants' => [
                [
                    'size_label' => '1/4" (6.35 mm)',
                    'sku' => 'CP-014',
                    'stock' => 45.00,
                    'min_threshold' => 5.00,
                    'unit_cost' => 780.00,
                ],
                [
                    'size_label' => '3/8" (9.52 mm)',
                    'sku' => 'CP-038',
                    'stock' => 4.00, // Low stock <= 5
                    'min_threshold' => 5.00,
                    'unit_cost' => 1050.00,
                ],
                [
                    'size_label' => '1/2" (12.7 mm)',
                    'sku' => 'CP-012',
                    'stock' => 0.00, // Out of stock
                    'min_threshold' => 5.00,
                    'unit_cost' => 1440.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'VRF Seamless Copper Pipe',
                    'base_uom' => 'Meters',
                ],
            ]);

        $this->assertDatabaseHas('products', ['name' => 'VRF Seamless Copper Pipe']);
        $this->assertDatabaseCount('product_variants', 3);
        $this->assertDatabaseHas('product_variants', ['sku' => 'CP-014', 'stock' => 45.00]);
    }

    public function test_can_paginate_products_and_returns_kpis(): void
    {
        // Seed 15 products
        for ($i = 1; $i <= 15; $i++) {
            $p = Product::create([
                'name' => "HVAC Item #{$i}",
                'category_id' => $this->category->id,
                'base_uom' => 'Pieces',
            ]);

            $p->variants()->create([
                'size_label' => 'Standard',
                'sku' => "SKU-{$i}",
                'stock' => $i % 3 === 0 ? 0.00 : ($i % 2 === 0 ? 3.00 : 25.00),
                'min_threshold' => 5.00,
                'unit_cost' => 100.00,
            ]);
        }

        // Fetch page 1 with 5 items per page
        $res = $this->getJson('/api/products?page=1&per_page=5');

        $res->assertStatus(200)
            ->assertJson([
                'status' => true,
            ]);

        $this->assertEquals(15, $res->json('kpis.total_items'));
        $this->assertEquals(15, $res->json('kpis.total_variants'));
        $this->assertEquals(5, count($res->json('data.data')));
        $this->assertEquals(1, $res->json('data.current_page'));
        $this->assertEquals(3, $res->json('data.last_page'));
        $this->assertEquals(15, $res->json('data.total'));
    }

    public function test_can_filter_by_status_and_search(): void
    {
        // Product A: In Stock
        $prodA = Product::create([
            'name' => 'Heavy Inverter AC',
            'category_id' => $this->category->id,
            'base_uom' => 'Pieces',
        ]);
        $prodA->variants()->create([
            'size_label' => '2.0 Ton',
            'sku' => 'AC-2TON',
            'stock' => 10.00,
            'min_threshold' => 2.00,
        ]);

        // Product B: Low Stock
        $prodB = Product::create([
            'name' => 'Drain Hose Pipe',
            'category_id' => $this->category->id,
            'base_uom' => 'Meters',
        ]);
        $prodB->variants()->create([
            'size_label' => '25mm',
            'sku' => 'HOSE-25',
            'stock' => 3.00,
            'min_threshold' => 5.00,
        ]);

        // Product C: Out of Stock
        $prodC = Product::create([
            'name' => 'R-410A Refrigerant',
            'category_id' => $this->category->id,
            'base_uom' => 'Kg',
        ]);
        $prodC->variants()->create([
            'size_label' => '11.3 Kg',
            'sku' => 'R410-11',
            'stock' => 0.00,
            'min_threshold' => 5.00,
        ]);

        // Filter low stock
        $lowRes = $this->getJson('/api/products?status=low_stock');
        $lowRes->assertStatus(200);
        $this->assertEquals(1, count($lowRes->json('data.data')));
        $this->assertEquals('Drain Hose Pipe', $lowRes->json('data.data.0.name'));

        // Filter out of stock
        $outRes = $this->getJson('/api/products?status=out_of_stock');
        $outRes->assertStatus(200);
        $this->assertEquals(1, count($outRes->json('data.data')));
        $this->assertEquals('R-410A Refrigerant', $outRes->json('data.data.0.name'));

        // Search query
        $searchRes = $this->getJson('/api/products?search=Inverter');
        $searchRes->assertStatus(200);
        $this->assertEquals(1, count($searchRes->json('data.data')));
        $this->assertEquals('Heavy Inverter AC', $searchRes->json('data.data.0.name'));
    }

    public function test_can_update_product_and_sync_variants(): void
    {
        $prod = Product::create([
            'name' => 'Original Product',
            'category_id' => $this->category->id,
            'base_uom' => 'Pieces',
        ]);
        $var1 = $prod->variants()->create([
            'size_label' => 'Size A',
            'sku' => 'VAR-A',
            'stock' => 10.00,
            'min_threshold' => 5.00,
        ]);
        $var2 = $prod->variants()->create([
            'size_label' => 'Size B',
            'sku' => 'VAR-B',
            'stock' => 20.00,
            'min_threshold' => 5.00,
        ]);

        // Update: modify var1, delete var2 (by omitting it), and add var3
        $updatePayload = [
            'name' => 'Renamed Product',
            'category_id' => $this->category->id,
            'base_uom' => 'Pieces',
            'variants' => [
                [
                    'id' => $var1->id,
                    'size_label' => 'Size A Modified',
                    'sku' => 'VAR-A',
                    'stock' => 15.00,
                    'min_threshold' => 5.00,
                ],
                [
                    'size_label' => 'Size C New',
                    'sku' => 'VAR-C',
                    'stock' => 30.00,
                    'min_threshold' => 5.00,
                ],
            ],
        ];

        $res = $this->putJson("/api/products/{$prod->id}", $updatePayload);

        $res->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Renamed Product',
                ],
            ]);

        $this->assertDatabaseHas('products', ['id' => $prod->id, 'name' => 'Renamed Product']);
        $this->assertDatabaseHas('product_variants', ['id' => $var1->id, 'size_label' => 'Size A Modified', 'stock' => 15.00]);
        $this->assertDatabaseMissing('product_variants', ['id' => $var2->id]); // var2 deleted
        $this->assertDatabaseHas('product_variants', ['sku' => 'VAR-C', 'size_label' => 'Size C New']);
    }

    public function test_can_delete_product_and_cascade_variants(): void
    {
        $prod = Product::create([
            'name' => 'To Be Deleted',
            'category_id' => $this->category->id,
            'base_uom' => 'Pieces',
        ]);
        $prod->variants()->create([
            'size_label' => '1/4"',
            'sku' => 'DEL-014',
            'stock' => 5.00,
        ]);

        $res = $this->deleteJson("/api/products/{$prod->id}");
        $res->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertDatabaseMissing('products', ['id' => $prod->id]);
        $this->assertDatabaseMissing('product_variants', ['sku' => 'DEL-014']);
    }
}
