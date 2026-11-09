<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a paginated listing of products with variants and KPI stats.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 10), 5), 100);
        $search = trim($request->input('search', ''));
        $categoryId = $request->input('category_id');
        $subCategoryId = $request->input('sub_category_id');
        $status = $request->input('status', 'all'); // all, in_stock, low_stock, out_of_stock

        $query = Product::with([
            'category:id,name,slug,default_uom',
            'subCategory:id,name,slug,default_uom',
            'variants'
        ]);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model_no', 'like', "%{$search}%")
                  ->orWhereHas('variants', function ($vq) use ($search) {
                      $vq->where('sku', 'like', "%{$search}%")
                         ->orWhere('size_label', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($categoryId) && $categoryId !== 'all') {
            $query->where('category_id', $categoryId);
        }

        if (!empty($subCategoryId) && $subCategoryId !== 'all') {
            $query->where('sub_category_id', $subCategoryId);
        }

        if ($status === 'low_stock') {
            $query->whereHas('variants', function ($q) {
                $q->where('stock', '>', 0)
                  ->whereColumn('stock', '<=', 'min_threshold');
            });
        } elseif ($status === 'out_of_stock') {
            $query->whereHas('variants', function ($q) {
                $q->where('stock', '<=', 0);
            });
        } elseif ($status === 'in_stock') {
            $query->whereDoesntHave('variants', function ($q) {
                $q->where('stock', '<=', 0);
            })->whereHas('variants', function ($q) {
                $q->whereColumn('stock', '>', 'min_threshold');
            });
        }

        $query->orderBy('id', 'desc');

        $paginated = $query->paginate($perPage);

        // Overall KPIs across inventory
        $kpis = [
            'total_items' => Product::count(),
            'total_variants' => ProductVariant::count(),
            'low_stock_alerts' => ProductVariant::where('stock', '>', 0)
                ->whereColumn('stock', '<=', 'min_threshold')
                ->count(),
            'out_of_stock' => ProductVariant::where('stock', '<=', 0)->count(),
        ];

        return response()->json([
            'status' => true,
            'kpis' => $kpis,
            'data' => $paginated,
        ]);
    }

    /**
     * Store a newly created product along with its size variants.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'model_no' => 'nullable|string|max:255',
            'base_uom' => 'required|string|max:50',
            'package_uom' => 'nullable|string|max:50',
            'package_ratio' => 'nullable|numeric|min:0',
            'has_variants' => 'nullable|boolean',
            'description' => 'nullable|string',
            'variants' => 'nullable|array',
            'variants.*.size_label' => 'required|string|max:100',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.stock' => 'nullable|numeric|min:0',
            'variants.*.min_threshold' => 'nullable|numeric|min:0',
            'variants.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        $product = DB::transaction(function () use ($validated) {
            $product = Product::create([
                'name' => $validated['name'],
                'category_id' => $validated['category_id'],
                'sub_category_id' => $validated['sub_category_id'] ?? null,
                'brand' => $validated['brand'] ?? null,
                'model_no' => $validated['model_no'] ?? null,
                'base_uom' => $validated['base_uom'],
                'package_uom' => $validated['package_uom'] ?? null,
                'package_ratio' => $validated['package_ratio'] ?? null,
                'has_variants' => $validated['has_variants'] ?? true,
                'description' => $validated['description'] ?? null,
            ]);

            $variants = $validated['variants'] ?? [];

            // If no variants passed, create a default "Standard" variant
            if (empty($variants)) {
                $skuPrefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $validated['name']), 0, 3));
                $variants = [[
                    'size_label' => 'Standard',
                    'sku' => $skuPrefix . '-001',
                    'stock' => 0.00,
                    'min_threshold' => 5.00,
                    'unit_cost' => 0.00,
                ]];
            }

            foreach ($variants as $v) {
                $sku = !empty($v['sku']) ? $v['sku'] : strtoupper(Str::random(6));
                $product->variants()->create([
                    'size_label' => $v['size_label'],
                    'sku' => $sku,
                    'stock' => isset($v['stock']) ? (float) $v['stock'] : 0.00,
                    'min_threshold' => isset($v['min_threshold']) ? (float) $v['min_threshold'] : 5.00,
                    'unit_cost' => isset($v['unit_cost']) ? (float) $v['unit_cost'] : 0.00,
                    'is_active' => true,
                ]);
            }

            return $product->load(['category', 'subCategory', 'variants']);
        });

        return response()->json([
            'status' => true,
            'message' => 'Product and size variants created successfully.',
            'data' => $product,
        ], 201);
    }

    /**
     * Display the specified product with variants and calculations.
     */
    public function show($id): JsonResponse
    {
        $product = Product::with(['category', 'subCategory', 'variants'])->findOrFail($id);

        $totalStock = $product->variants->sum('stock');
        $hasLowStock = $product->variants->contains(function ($v) {
            return $v->stock > 0 && $v->stock <= $v->min_threshold;
        });
        $hasOutOfStock = $product->variants->contains(function ($v) {
            return $v->stock <= 0;
        });

        $status = 'In Stock';
        if ($hasOutOfStock && $product->variants->count() === 1) {
            $status = 'Out of Stock';
        } elseif ($hasOutOfStock || $hasLowStock) {
            $status = 'Low Stock Alert';
        }

        return response()->json([
            'status' => true,
            'data' => [
                'product' => $product,
                'total_stock' => $totalStock,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Update the specified product and sync size variants.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'model_no' => 'nullable|string|max:255',
            'base_uom' => 'required|string|max:50',
            'package_uom' => 'nullable|string|max:50',
            'package_ratio' => 'nullable|numeric|min:0',
            'has_variants' => 'nullable|boolean',
            'description' => 'nullable|string',
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|exists:product_variants,id',
            'variants.*.size_label' => 'required|string|max:100',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.stock' => 'nullable|numeric|min:0',
            'variants.*.min_threshold' => 'nullable|numeric|min:0',
            'variants.*.unit_cost' => 'nullable|numeric|min:0',
            'variants.*.is_active' => 'nullable|boolean',
        ]);

        $updatedProduct = DB::transaction(function () use ($product, $validated) {
            $product->update([
                'name' => $validated['name'],
                'category_id' => $validated['category_id'],
                'sub_category_id' => $validated['sub_category_id'] ?? null,
                'brand' => $validated['brand'] ?? null,
                'model_no' => $validated['model_no'] ?? null,
                'base_uom' => $validated['base_uom'],
                'package_uom' => $validated['package_uom'] ?? null,
                'package_ratio' => $validated['package_ratio'] ?? null,
                'has_variants' => $validated['has_variants'] ?? true,
                'description' => $validated['description'] ?? null,
            ]);

            if (isset($validated['variants'])) {
                $incomingVariantIds = [];

                foreach ($validated['variants'] as $v) {
                    if (!empty($v['id'])) {
                        $variant = ProductVariant::where('product_id', $product->id)->find($v['id']);
                        if ($variant) {
                            $variant->update([
                                'size_label' => $v['size_label'],
                                'sku' => $v['sku'] ?? $variant->sku,
                                'stock' => isset($v['stock']) ? (float) $v['stock'] : $variant->stock,
                                'min_threshold' => isset($v['min_threshold']) ? (float) $v['min_threshold'] : $variant->min_threshold,
                                'unit_cost' => isset($v['unit_cost']) ? (float) $v['unit_cost'] : $variant->unit_cost,
                                'is_active' => $v['is_active'] ?? $variant->is_active,
                            ]);
                            $incomingVariantIds[] = $variant->id;
                        }
                    } else {
                        $sku = !empty($v['sku']) ? $v['sku'] : strtoupper(Str::random(6));
                        $newVariant = $product->variants()->create([
                            'size_label' => $v['size_label'],
                            'sku' => $sku,
                            'stock' => isset($v['stock']) ? (float) $v['stock'] : 0.00,
                            'min_threshold' => isset($v['min_threshold']) ? (float) $v['min_threshold'] : 5.00,
                            'unit_cost' => isset($v['unit_cost']) ? (float) $v['unit_cost'] : 0.00,
                            'is_active' => true,
                        ]);
                        $incomingVariantIds[] = $newVariant->id;
                    }
                }

                // Delete variants removed by the user
                if (!empty($incomingVariantIds)) {
                    $product->variants()->whereNotIn('id', $incomingVariantIds)->delete();
                }
            }

            return $product->load(['category', 'subCategory', 'variants']);
        });

        return response()->json([
            'status' => true,
            'message' => 'Product updated successfully.',
            'data' => $updatedProduct,
        ]);
    }

    /**
     * Remove the specified product and its variants.
     */
    public function destroy($id): JsonResponse
    {
        $product = Product::with('variants')->findOrFail($id);

        $productName = $product->name;
        $product->variants()->delete();
        $product->delete();

        return response()->json([
            'status' => true,
            'message' => "Product '{$productName}' and all associated variants deleted successfully.",
        ]);
    }
}
