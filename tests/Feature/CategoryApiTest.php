<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_categories_tree_and_flat(): void
    {
        $parent = Category::create([
            'name' => 'HVAC Equipment',
            'slug' => 'hvac-equipment',
            'default_uom' => 'Pieces',
        ]);

        $child = Category::create([
            'name' => 'VRF Units',
            'slug' => 'vrf-units',
            'parent_id' => $parent->id,
            'default_uom' => 'Pieces',
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
            ]);

        $this->assertCount(1, $response->json('data.tree'));
        $this->assertCount(2, $response->json('data.flat'));
        $this->assertEquals($child->id, $response->json('data.tree.0.children.0.id'));
    }

    public function test_can_create_parent_and_subcategory(): void
    {
        $parentRes = $this->postJson('/api/categories', [
            'name' => 'Piping Materials',
            'slug' => 'piping-materials',
            'default_uom' => 'Meters',
            'description' => 'Tubes and fittings',
        ]);

        $parentRes->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Piping Materials',
                    'slug' => 'piping-materials',
                ],
            ]);

        $parentId = $parentRes->json('data.id');

        $childRes = $this->postJson('/api/categories', [
            'name' => 'Copper Pipes',
            'parent_id' => $parentId,
            'default_uom' => 'Meters',
        ]);

        $childRes->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Copper Pipes',
                    'parent_id' => $parentId,
                ],
            ]);
    }

    public function test_slug_auto_generation_and_uniqueness(): void
    {
        $res1 = $this->postJson('/api/categories', [
            'name' => 'Refrigerant Gases',
            'default_uom' => 'Kg',
        ]);
        $res1->assertStatus(201);
        $this->assertEquals('refrigerant-gases', $res1->json('data.slug'));

        // Post identical name without slug - should auto-append counter
        $res2 = $this->postJson('/api/categories', [
            'name' => 'Refrigerant Gases',
            'default_uom' => 'Kg',
        ]);
        $res2->assertStatus(201);
        $this->assertEquals('refrigerant-gases-1', $res2->json('data.slug'));
    }

    public function test_cannot_set_self_or_circular_parent(): void
    {
        $parent = Category::create([
            'name' => 'Thermal Insulation',
            'slug' => 'thermal-insulation',
            'default_uom' => 'Meters',
        ]);

        $child = Category::create([
            'name' => 'Nitrile Rubber',
            'slug' => 'nitrile-rubber',
            'parent_id' => $parent->id,
            'default_uom' => 'Meters',
        ]);

        // Attempt 1: Self-parenting
        $selfRes = $this->putJson("/api/categories/{$parent->id}", [
            'name' => 'Thermal Insulation',
            'parent_id' => $parent->id,
        ]);
        $selfRes->assertStatus(422)
            ->assertJson(['status' => false]);

        // Attempt 2: Circular parenting (setting child as parent of its parent)
        $circularRes = $this->putJson("/api/categories/{$parent->id}", [
            'name' => 'Thermal Insulation',
            'parent_id' => $child->id,
        ]);
        $circularRes->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    public function test_cannot_delete_category_with_subcategories_or_products(): void
    {
        $parent = Category::create([
            'name' => 'Cables & Wiring',
            'slug' => 'cables-wiring',
            'default_uom' => 'Meters',
        ]);

        $child = Category::create([
            'name' => 'Shielded Cables',
            'slug' => 'shielded-cables',
            'parent_id' => $parent->id,
            'default_uom' => 'Meters',
        ]);

        // Guard against deleting parent with children
        $delParentRes = $this->deleteJson("/api/categories/{$parent->id}");
        $delParentRes->assertStatus(422)
            ->assertJson(['status' => false]);

        // Add a product to child
        Product::create([
            'category_id' => $parent->id,
            'sub_category_id' => $child->id,
            'name' => '0.75 sq mm Shielded Bus Cable',
            'base_uom' => 'Meters',
        ]);

        // Guard against deleting child with products
        $delChildRes = $this->deleteJson("/api/categories/{$child->id}");
        $delChildRes->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    public function test_can_delete_empty_category(): void
    {
        $category = Category::create([
            'name' => 'Temporary Category',
            'slug' => 'temporary-category',
            'default_uom' => 'Pieces',
        ]);

        $response = $this->deleteJson("/api/categories/{$category->id}");
        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
