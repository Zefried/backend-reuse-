<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories with nested children and product counts.
     */
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')
            ->with(['children' => function ($query) {
                $query->withCount('products');
            }])
            ->whereNull('parent_id')
            ->get();

        // Also get all flat categories for selectors
        $flatList = Category::with('parent:id,name')->withCount('products')->get();

        return response()->json([
            'status' => true,
            'data' => [
                'tree' => $categories,
                'flat' => $flatList,
            ],
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'default_uom' => 'nullable|string|max:50',
        ]);

        $slug = !empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        // Ensure unique slug
        $originalSlug = $slug;
        $counter = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'parent_id' => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'default_uom' => $validated['default_uom'] ?? 'Pieces',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Category created successfully.',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show($id): JsonResponse
    {
        $category = Category::with(['parent', 'children', 'products.variants'])->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $category,
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => "nullable|string|max:255|unique:categories,slug,{$id}",
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'default_uom' => 'nullable|string|max:50',
        ]);

        // Prevent self-parenting and circular parenting
        if (!empty($validated['parent_id'])) {
            if ($validated['parent_id'] == $id) {
                return response()->json([
                    'status' => false,
                    'message' => 'A category cannot be its own parent.',
                ], 422);
            }

            // Check if parent is one of this category's children
            $isChild = Category::where('parent_id', $id)->where('id', $validated['parent_id'])->exists();
            if ($isChild) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot set a child subcategory as parent.',
                ], 422);
            }
        }

        $slug = !empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $category->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'parent_id' => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'default_uom' => $validated['default_uom'] ?? 'Pieces',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Category updated successfully.',
            'data' => $category,
        ]);
    }

    /**
     * Remove the specified category with safe guards.
     */
    public function destroy($id): JsonResponse
    {
        $category = Category::withCount(['children', 'products'])->findOrFail($id);

        if ($category->children_count > 0) {
            return response()->json([
                'status' => false,
                'message' => "Cannot delete '{$category->name}': it has {$category->children_count} active subcategories. Delete or move them first.",
            ], 422);
        }

        if ($category->products_count > 0) {
            return response()->json([
                'status' => false,
                'message' => "Cannot delete '{$category->name}': it has {$category->products_count} active products assigned. Reassign products first.",
            ], 422);
        }

        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}
