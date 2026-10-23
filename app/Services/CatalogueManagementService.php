<?php

namespace App\Services;

use App\Models\CatalogueCategory;
use App\Models\CatalogueService;
use App\Models\Design;
use App\Models\DesignSpecification;
use App\Models\Niche;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CatalogueManagementService
{
    // ═══════════════════════════════════════════════════════════════════
    // 1. NICHES
    // ═══════════════════════════════════════════════════════════════════

    public function listNiches(array $filters = []): array
    {
        try {
            $query = Niche::withCount(['businesses'])->orderBy('niche_name');

            if (isset($filters['is_active']) && $filters['is_active'] !== '') {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            }

            if (!empty($filters['search'])) {
                $query->where('niche_name', 'like', '%' . trim($filters['search']) . '%');
            }

            $niches = $query->get();

            return ['status' => true, 'data' => $niches, 'code' => 200];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function createNiche(array $data): array
    {
        try {
            $validator = Validator::make($data, [
                'niche_name' => 'required|string|max:255|unique:niches,niche_name',
                'is_active'  => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $niche = Niche::create([
                'niche_name' => trim($data['niche_name']),
                'is_active'  => $data['is_active'] ?? true,
            ]);

            return [
                'status'  => true,
                'message' => 'Niche created successfully.',
                'data'    => $niche->loadCount('businesses'),
                'code'    => 201,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function updateNiche(int $id, array $data): array
    {
        try {
            $niche = Niche::find($id);
            if (!$niche) {
                return ['status' => false, 'message' => 'Niche not found.', 'code' => 404];
            }

            $validator = Validator::make($data, [
                'niche_name' => 'required|string|max:255|unique:niches,niche_name,' . $id,
                'is_active'  => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $niche->niche_name = trim($data['niche_name']);
            if (isset($data['is_active'])) {
                $niche->is_active = (bool) $data['is_active'];
            }
            $niche->save();

            return [
                'status'  => true,
                'message' => 'Niche updated successfully.',
                'data'    => $niche->loadCount('businesses'),
                'code'    => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function toggleNicheStatus(int $id): array
    {
        try {
            $niche = Niche::find($id);
            if (!$niche) {
                return ['status' => false, 'message' => 'Niche not found.', 'code' => 404];
            }

            $niche->is_active = !$niche->is_active;
            $niche->save();

            return [
                'status'  => true,
                'message' => 'Niche status updated successfully.',
                'data'    => $niche->loadCount('businesses'),
                'code'    => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function deleteNiche(int $id): array
    {
        try {
            $niche = Niche::find($id);
            if (!$niche) {
                return ['status' => false, 'message' => 'Niche not found.', 'code' => 404];
            }

            // Safely unlink any assigned businesses before deleting niche
            User::where('niche_id', $id)->update(['niche_id' => null]);
            $niche->delete();

            return ['status' => true, 'message' => 'Niche deleted successfully.', 'code' => 200];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. BUSINESSES (Reusing existing User records with role='business')
    // ═══════════════════════════════════════════════════════════════════

    public function listBusinessesByNiche(int $nicheId): array
    {
        try {
            $niche = Niche::find($nicheId);
            if (!$niche) {
                return ['status' => false, 'message' => 'Niche not found.', 'code' => 404];
            }

            $businesses = User::where('role', 'business')
                ->where('niche_id', $nicheId)
                ->withCount(['catalogueServices', 'catalogueCategories', 'designs'])
                ->orderBy('name')
                ->get();

            return [
                'status' => true,
                'niche'  => $niche,
                'data'   => $businesses,
                'code'   => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function listAllBusinesses(array $filters = []): array
    {
        try {
            $query = User::where('role', 'business')->with('niche:id,niche_name');

            if (isset($filters['unassigned_only']) && filter_var($filters['unassigned_only'], FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNull('niche_id');
            }

            if (!empty($filters['search'])) {
                $term = trim($filters['search']);
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('business_id', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%");
                });
            }

            $businesses = $query->withCount(['catalogueServices', 'catalogueCategories', 'designs'])
                ->orderBy('name')
                ->get();

            return ['status' => true, 'data' => $businesses, 'code' => 200];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function createBusinessUnderNiche(int $nicheId, array $data): array
    {
        try {
            $niche = Niche::find($nicheId);
            if (!$niche) {
                return ['status' => false, 'message' => 'Niche not found.', 'code' => 404];
            }

            $validator = Validator::make($data, [
                'business_name' => 'required|string|max:255',
                'email'         => 'required|email|unique:users,email',
                'phone'         => 'nullable|string|unique:users,phone',
                'bio'           => 'nullable|string',
                'password'      => 'nullable|string|min:6',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $businessUid = 'BUS-' . strtoupper(Str::random(8));
            while (User::where('business_id', $businessUid)->exists()) {
                $businessUid = 'BUS-' . strtoupper(Str::random(8));
            }

            $user = User::create([
                'name'        => trim($data['business_name']),
                'email'       => trim($data['email']),
                'phone'       => $data['phone'] ?? null,
                'bio'         => $data['bio'] ?? null,
                'business_id' => $businessUid,
                'niche_id'    => $nicheId,
                'role'        => 'business',
                'password'    => Hash::make($data['password'] ?? 'password123'),
            ]);

            return [
                'status'  => true,
                'message' => 'Business created and linked to niche successfully.',
                'data'    => $user->load('niche:id,niche_name'),
                'code'    => 201,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function assignBusinessToNiche(int $nicheId, int $userId): array
    {
        try {
            $niche = Niche::find($nicheId);
            if (!$niche) {
                return ['status' => false, 'message' => 'Niche not found.', 'code' => 404];
            }

            $business = User::where('id', $userId)->where('role', 'business')->first();
            if (!$business) {
                return ['status' => false, 'message' => 'Business not found.', 'code' => 404];
            }

            $business->niche_id = $nicheId;
            $business->save();

            return [
                'status'  => true,
                'message' => 'Business assigned to niche successfully.',
                'data'    => $business->load('niche:id,niche_name'),
                'code'    => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function unlinkBusinessFromNiche(int $nicheId, int $userId): array
    {
        try {
            $business = User::where('id', $userId)
                ->where('role', 'business')
                ->where('niche_id', $nicheId)
                ->first();

            if (!$business) {
                return ['status' => false, 'message' => 'Business not found under this niche.', 'code' => 404];
            }

            $business->niche_id = null;
            $business->save();

            return ['status' => true, 'message' => 'Business unlinked from niche.', 'code' => 200];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. SERVICES (Catalogue Services scoped to Business)
    // ═══════════════════════════════════════════════════════════════════

    public function listServicesByBusiness(int $businessId): array
    {
        try {
            $business = User::where('id', $businessId)->where('role', 'business')->first();
            if (!$business) {
                return ['status' => false, 'message' => 'Business not found.', 'code' => 404];
            }

            $services = CatalogueService::where('business_id', $businessId)
                ->withCount(['categories', 'designs'])
                ->orderBy('service_name')
                ->get();

            return [
                'status'   => true,
                'business' => $business,
                'data'     => $services,
                'code'     => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function createService(int $businessId, array $data): array
    {
        try {
            $business = User::where('id', $businessId)->where('role', 'business')->first();
            if (!$business) {
                return ['status' => false, 'message' => 'Business not found.', 'code' => 404];
            }

            $validator = Validator::make($data, [
                'service_name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $serviceName = trim($data['service_name']);

            // Validate service name unique for this business
            $exists = CatalogueService::where('business_id', $businessId)
                ->where('service_name', $serviceName)
                ->exists();

            if ($exists) {
                return ['status' => false, 'message' => 'Service already exists for this business.', 'code' => 409];
            }

            $service = CatalogueService::create([
                'business_id'  => $businessId,
                'service_name' => $serviceName,
            ]);

            return [
                'status'  => true,
                'message' => 'Service created successfully.',
                'data'    => $service->loadCount(['categories', 'designs']),
                'code'    => 201,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function updateService(int $id, array $data): array
    {
        try {
            $service = CatalogueService::find($id);
            if (!$service) {
                return ['status' => false, 'message' => 'Service not found.', 'code' => 404];
            }

            $validator = Validator::make($data, [
                'service_name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $serviceName = trim($data['service_name']);

            $exists = CatalogueService::where('business_id', $service->business_id)
                ->where('service_name', $serviceName)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return ['status' => false, 'message' => 'Service with this name already exists for this business.', 'code' => 409];
            }

            $service->service_name = $serviceName;
            $service->save();

            return [
                'status'  => true,
                'message' => 'Service updated successfully.',
                'data'    => $service->loadCount(['categories', 'designs']),
                'code'    => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function deleteService(int $id): array
    {
        try {
            $service = CatalogueService::find($id);
            if (!$service) {
                return ['status' => false, 'message' => 'Service not found.', 'code' => 404];
            }

            $service->delete();

            return ['status' => true, 'message' => 'Service and its related categories and designs deleted successfully.', 'code' => 200];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. CATEGORIES & SUBCATEGORIES
    // ═══════════════════════════════════════════════════════════════════

    public function listCategoriesByService(int $serviceId): array
    {
        try {
            $service = CatalogueService::with('business')->find($serviceId);
            if (!$service) {
                return ['status' => false, 'message' => 'Service not found.', 'code' => 404];
            }

            // Tree format
            $tree = CatalogueCategory::where('service_id', $serviceId)
                ->whereNull('parent_id')
                ->with(['allChildren.designs.specifications', 'designs.specifications'])
                ->withCount('designs')
                ->orderBy('category_name')
                ->get();

            // Flat format for dropdowns
            $allCats = CatalogueCategory::where('service_id', $serviceId)
                ->withCount('designs')
                ->orderBy('category_name')
                ->get();

            $flat = $this->buildFlatCategoryList($allCats);

            return [
                'status'  => true,
                'service' => $service,
                'tree'    => $tree,
                'flat'    => $flat,
                'data'    => $allCats,
                'code'    => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    private function buildFlatCategoryList($categories, $parentId = null, $depth = 0): array
    {
        $result = [];
        foreach ($categories->where('parent_id', $parentId) as $cat) {
            $result[] = [
                'id'            => $cat->id,
                'category_name' => $cat->category_name,
                'label'         => str_repeat('— ', $depth) . $cat->category_name,
                'depth'         => $depth,
                'parent_id'     => $cat->parent_id,
                'designs_count' => $cat->designs_count ?? 0,
            ];
            $children = $this->buildFlatCategoryList($categories, $cat->id, $depth + 1);
            $result = array_merge($result, $children);
        }
        return $result;
    }

    public function createCategory(int $serviceId, array $data): array
    {
        try {
            $service = CatalogueService::find($serviceId);
            if (!$service) {
                return ['status' => false, 'message' => 'Service not found.', 'code' => 404];
            }

            $validator = Validator::make($data, [
                'category_name' => 'required|string|max:255',
                'parent_id'     => 'nullable|integer|exists:catalogue_categories,id',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;

            // Integrity check: If parent_id is supplied, verify it belongs to the SAME service & business
            if ($parentId) {
                $parent = CatalogueCategory::find($parentId);
                if (!$parent) {
                    return ['status' => false, 'message' => 'Parent category not found.', 'code' => 404];
                }
                if ($parent->service_id !== $serviceId || $parent->business_id !== $service->business_id) {
                    return [
                        'status'  => false,
                        'message' => 'Parent category must belong to the exact same service and business.',
                        'code'    => 422,
                    ];
                }
            }

            $category = CatalogueCategory::create([
                'business_id'   => $service->business_id,
                'service_id'    => $serviceId,
                'category_name' => trim($data['category_name']),
                'parent_id'     => $parentId,
            ]);

            return [
                'status'  => true,
                'message' => 'Category created successfully.',
                'data'    => $category->load('parent:id,category_name'),
                'code'    => 201,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function updateCategory(int $id, array $data): array
    {
        try {
            $category = CatalogueCategory::find($id);
            if (!$category) {
                return ['status' => false, 'message' => 'Category not found.', 'code' => 404];
            }

            $validator = Validator::make($data, [
                'category_name' => 'required|string|max:255',
                'parent_id'     => 'nullable|integer|exists:catalogue_categories,id',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;

            if ($parentId === $id) {
                return ['status' => false, 'message' => 'A category cannot be its own parent.', 'code' => 422];
            }

            if ($parentId) {
                $parent = CatalogueCategory::find($parentId);
                if (!$parent || $parent->service_id !== $category->service_id || $parent->business_id !== $category->business_id) {
                    return [
                        'status'  => false,
                        'message' => 'Parent category must belong to the same service and business.',
                        'code'    => 422,
                    ];
                }
            }

            $category->category_name = trim($data['category_name']);
            $category->parent_id = $parentId;
            $category->save();

            return [
                'status'  => true,
                'message' => 'Category updated successfully.',
                'data'    => $category->load('parent:id,category_name'),
                'code'    => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function deleteCategory(int $id): array
    {
        try {
            $category = CatalogueCategory::find($id);
            if (!$category) {
                return ['status' => false, 'message' => 'Category not found.', 'code' => 404];
            }

            $category->delete();

            return ['status' => true, 'message' => 'Category and nested records deleted successfully.', 'code' => 200];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 5. DESIGNS
    // ═══════════════════════════════════════════════════════════════════

    public function listDesigns(array $filters = []): array
    {
        try {
            $query = Design::with([
                'business:id,name,business_id',
                'service:id,service_name',
                'category:id,category_name,parent_id',
                'specifications',
            ]);

            if (!empty($filters['business_id'])) {
                $query->where('business_id', $filters['business_id']);
            }
            if (!empty($filters['service_id'])) {
                $query->where('service_id', $filters['service_id']);
            }
            if (!empty($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }
            if (!empty($filters['search'])) {
                $term = trim($filters['search']);
                $query->where('design_name', 'like', "%{$term}%");
            }

            $designs = $query->latest()->get();

            return ['status' => true, 'data' => $designs, 'code' => 200];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function getDesign(int $id): array
    {
        try {
            $design = Design::with([
                'business.niche',
                'service',
                'category.parent',
                'specifications',
            ])->find($id);

            if (!$design) {
                return ['status' => false, 'message' => 'Design not found.', 'code' => 404];
            }

            return ['status' => true, 'data' => $design, 'code' => 200];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function createDesign(array $data): array
    {
        try {
            $validator = Validator::make($data, [
                'category_id'      => 'required|integer|exists:catalogue_categories,id',
                'design_name'      => 'required|string|max:255',
                'service_id'       => 'nullable|integer|exists:catalogue_services,id',
                'business_id'      => 'nullable|integer|exists:users,id',
                'specifications'   => 'nullable|array',
                'specifications.*.attribute_name'  => 'required|string|max:255',
                'specifications.*.attribute_value' => 'required',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $category = CatalogueCategory::with('service')->findOrFail($data['category_id']);

            // Validate consistency if caller passed service_id or business_id
            if (!empty($data['service_id']) && (int) $data['service_id'] !== $category->service_id) {
                return [
                    'status'  => false,
                    'message' => 'Data integrity violation: provided service_id does not match the category service.',
                    'code'    => 422,
                ];
            }

            if (!empty($data['business_id']) && (int) $data['business_id'] !== $category->business_id) {
                return [
                    'status'  => false,
                    'message' => 'Data integrity violation: provided business_id does not match category business.',
                    'code'    => 422,
                ];
            }

            $design = DB::transaction(function () use ($category, $data) {
                $newDesign = Design::create([
                    'business_id' => $category->business_id,
                    'service_id'  => $category->service_id,
                    'category_id' => $category->id,
                    'design_name' => trim($data['design_name']),
                ]);

                if (!empty($data['specifications']) && is_array($data['specifications'])) {
                    foreach ($data['specifications'] as $spec) {
                        if (!empty($spec['attribute_name']) && isset($spec['attribute_value'])) {
                            DesignSpecification::create([
                                'design_id'       => $newDesign->id,
                                'attribute_name'  => trim($spec['attribute_name']),
                                'attribute_value' => is_array($spec['attribute_value'])
                                    ? json_encode($spec['attribute_value'])
                                    : (string) $spec['attribute_value'],
                            ]);
                        }
                    }
                }

                return $newDesign;
            });

            return [
                'status'  => true,
                'message' => 'Design created successfully.',
                'data'    => $design->load(['business:id,name', 'service:id,service_name', 'category:id,category_name', 'specifications']),
                'code'    => 201,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function updateDesign(int $id, array $data): array
    {
        try {
            $design = Design::find($id);
            if (!$design) {
                return ['status' => false, 'message' => 'Design not found.', 'code' => 404];
            }

            $validator = Validator::make($data, [
                'design_name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $design->design_name = trim($data['design_name']);
            $design->save();

            return [
                'status'  => true,
                'message' => 'Design updated successfully.',
                'data'    => $design->load(['category', 'specifications']),
                'code'    => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function deleteDesign(int $id): array
    {
        try {
            $design = Design::find($id);
            if (!$design) {
                return ['status' => false, 'message' => 'Design not found.', 'code' => 404];
            }

            $design->delete();

            return ['status' => true, 'message' => 'Design deleted successfully.', 'code' => 200];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 6. SPECIFICATIONS (Flexible Attribute-Value structure)
    // ═══════════════════════════════════════════════════════════════════

    public function addSpecification(int $designId, array $data): array
    {
        try {
            $design = Design::find($designId);
            if (!$design) {
                return ['status' => false, 'message' => 'Design not found.', 'code' => 404];
            }

            // Support single specification or batch
            if (!empty($data['specifications']) && is_array($data['specifications'])) {
                $created = DB::transaction(function () use ($designId, $data) {
                    $items = [];
                    foreach ($data['specifications'] as $item) {
                        if (!empty($item['attribute_name']) && isset($item['attribute_value'])) {
                            $items[] = DesignSpecification::create([
                                'design_id'       => $designId,
                                'attribute_name'  => trim($item['attribute_name']),
                                'attribute_value' => is_array($item['attribute_value'])
                                    ? json_encode($item['attribute_value'])
                                    : (string) $item['attribute_value'],
                            ]);
                        }
                    }
                    return $items;
                });

                return [
                    'status'  => true,
                    'message' => count($created) . ' specifications added successfully.',
                    'data'    => $created,
                    'code'    => 201,
                ];
            }

            $validator = Validator::make($data, [
                'attribute_name'  => 'required|string|max:255',
                'attribute_value' => 'required',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $spec = DesignSpecification::create([
                'design_id'       => $designId,
                'attribute_name'  => trim($data['attribute_name']),
                'attribute_value' => is_array($data['attribute_value'])
                    ? json_encode($data['attribute_value'])
                    : (string) $data['attribute_value'],
            ]);

            return [
                'status'  => true,
                'message' => 'Specification added successfully.',
                'data'    => $spec,
                'code'    => 201,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function updateSpecification(int $id, array $data): array
    {
        try {
            $spec = DesignSpecification::find($id);
            if (!$spec) {
                return ['status' => false, 'message' => 'Specification not found.', 'code' => 404];
            }

            $validator = Validator::make($data, [
                'attribute_name'  => 'required|string|max:255',
                'attribute_value' => 'required',
            ]);

            if ($validator->fails()) {
                return ['status' => false, 'message' => $validator->errors()->first(), 'code' => 422];
            }

            $spec->attribute_name = trim($data['attribute_name']);
            $spec->attribute_value = is_array($data['attribute_value'])
                ? json_encode($data['attribute_value'])
                : (string) $data['attribute_value'];
            $spec->save();

            return [
                'status'  => true,
                'message' => 'Specification updated successfully.',
                'data'    => $spec,
                'code'    => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    public function deleteSpecification(int $id): array
    {
        try {
            $spec = DesignSpecification::find($id);
            if (!$spec) {
                return ['status' => false, 'message' => 'Specification not found.', 'code' => 404];
            }

            $spec->delete();

            return ['status' => true, 'message' => 'Specification deleted successfully.', 'code' => 200];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 7. STRUCTURED SPECIFICATION SEARCH & RETRIEVAL
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Search designs with multi-attribute filtering scoped strictly to database
     * Example: Show me L-shaped modular kitchen designs under ₹3 lakh
     * Supports:
     *   - business_id (int or string BUS-XXXX)
     *   - service_id / service_name
     *   - category_id
     *   - specifications key-values (e.g. Shape=L, Finish=Matte, Budget_max=300000)
     */
    public function searchDesigns(array $params): array
    {
        try {
            $query = Design::query()->with([
                'business:id,name,business_id',
                'service:id,service_name',
                'category:id,category_name',
                'specifications',
            ]);

            // Business scope: strictly filter by business in DB
            if (!empty($params['business_id'])) {
                $busIdentifier = $params['business_id'];
                if (is_numeric($busIdentifier)) {
                    $query->where('business_id', (int) $busIdentifier);
                } else {
                    $query->whereHas('business', function ($q) use ($busIdentifier) {
                        $q->where('business_id', $busIdentifier);
                    });
                }
            }

            // Service scope
            if (!empty($params['service_id'])) {
                $query->where('service_id', (int) $params['service_id']);
            } elseif (!empty($params['service_name'])) {
                $svcName = trim($params['service_name']);
                $query->whereHas('service', function ($q) use ($svcName) {
                    $q->where('service_name', 'like', "%{$svcName}%");
                });
            }

            // Category scope
            if (!empty($params['category_id'])) {
                $query->where('category_id', (int) $params['category_id']);
            }

            // Design name keyword
            if (!empty($params['q'])) {
                $term = trim($params['q']);
                $query->where('design_name', 'like', "%{$term}%");
            }

            // Specification key-value attributes filtering directly in database
            // E.g. $params['specifications'] = ['Shape' => 'L', 'Finish' => 'Matte']
            $specs = $params['specifications'] ?? [];
            if (!is_array($specs)) {
                $specs = json_decode($specs, true) ?? [];
            }

            // Also check individual query parameters prefixed with 'spec_'
            foreach ($params as $key => $val) {
                if (str_starts_with($key, 'spec_') && !empty($val)) {
                    $attr = substr($key, 5);
                    $specs[$attr] = $val;
                }
            }

            foreach ($specs as $attrName => $attrValue) {
                if ($attrValue === '' || $attrValue === null) {
                    continue;
                }

                $query->whereHas('specifications', function ($q) use ($attrName, $attrValue) {
                    $q->where('attribute_name', $attrName)
                      ->where('attribute_value', 'like', "%{$attrValue}%");
                });
            }

            $results = $query->latest()->get();

            return [
                'status'  => true,
                'count'   => $results->count(),
                'filters' => [
                    'business_id'    => $params['business_id'] ?? null,
                    'service_id'     => $params['service_id'] ?? null,
                    'category_id'    => $params['category_id'] ?? null,
                    'specifications' => $specs,
                ],
                'data'    => $results,
                'code'    => 200,
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'code' => 500];
        }
    }
}
