<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CatalogueManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    protected CatalogueManagementService $service;

    public function __construct(CatalogueManagementService $service)
    {
        $this->service = $service;
    }

    // ── Niches ──────────────────────────────────────────────────────────

    public function listNiches(Request $request): JsonResponse
    {
        $res = $this->service->listNiches($request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function createNiche(Request $request): JsonResponse
    {
        $res = $this->service->createNiche($request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function updateNiche(Request $request, int $id): JsonResponse
    {
        $res = $this->service->updateNiche($id, $request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function toggleNiche(int $id): JsonResponse
    {
        $res = $this->service->toggleNicheStatus($id);
        return response()->json($res, $res['code'] ?? 200);
    }

    public function deleteNiche(int $id): JsonResponse
    {
        $res = $this->service->deleteNiche($id);
        return response()->json($res, $res['code'] ?? 200);
    }

    // ── Businesses under Niche ─────────────────────────────────────────

    public function listBusinessesByNiche(int $nicheId): JsonResponse
    {
        $res = $this->service->listBusinessesByNiche($nicheId);
        return response()->json($res, $res['code'] ?? 200);
    }

    public function listAllBusinesses(Request $request): JsonResponse
    {
        $res = $this->service->listAllBusinesses($request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function createBusinessUnderNiche(Request $request, int $nicheId): JsonResponse
    {
        $res = $this->service->createBusinessUnderNiche($nicheId, $request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function assignBusinessToNiche(Request $request, int $nicheId): JsonResponse
    {
        $userId = (int) $request->input('user_id');
        $res = $this->service->assignBusinessToNiche($nicheId, $userId);
        return response()->json($res, $res['code'] ?? 200);
    }

    public function unlinkBusinessFromNiche(int $nicheId, int $businessId): JsonResponse
    {
        $res = $this->service->unlinkBusinessFromNiche($nicheId, $businessId);
        return response()->json($res, $res['code'] ?? 200);
    }

    // ── Services ────────────────────────────────────────────────────────

    public function listServicesByBusiness(int $businessId): JsonResponse
    {
        $res = $this->service->listServicesByBusiness($businessId);
        return response()->json($res, $res['code'] ?? 200);
    }

    public function createService(Request $request, int $businessId): JsonResponse
    {
        $res = $this->service->createService($businessId, $request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function updateService(Request $request, int $id): JsonResponse
    {
        $res = $this->service->updateService($id, $request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function deleteService(int $id): JsonResponse
    {
        $res = $this->service->deleteService($id);
        return response()->json($res, $res['code'] ?? 200);
    }

    // ── Categories & Subcategories ──────────────────────────────────────

    public function listCategoriesByService(int $serviceId): JsonResponse
    {
        $res = $this->service->listCategoriesByService($serviceId);
        return response()->json($res, $res['code'] ?? 200);
    }

    public function createCategory(Request $request, int $serviceId): JsonResponse
    {
        $res = $this->service->createCategory($serviceId, $request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $res = $this->service->updateCategory($id, $request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function deleteCategory(int $id): JsonResponse
    {
        $res = $this->service->deleteCategory($id);
        return response()->json($res, $res['code'] ?? 200);
    }

    // ── Designs ─────────────────────────────────────────────────────────

    public function listDesigns(Request $request): JsonResponse
    {
        $res = $this->service->listDesigns($request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function getDesign(int $id): JsonResponse
    {
        $res = $this->service->getDesign($id);
        return response()->json($res, $res['code'] ?? 200);
    }

    public function createDesign(Request $request): JsonResponse
    {
        $res = $this->service->createDesign($request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function updateDesign(Request $request, int $id): JsonResponse
    {
        $res = $this->service->updateDesign($id, $request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function deleteDesign(int $id): JsonResponse
    {
        $res = $this->service->deleteDesign($id);
        return response()->json($res, $res['code'] ?? 200);
    }

    // ── Specifications ──────────────────────────────────────────────────

    public function addSpecification(Request $request, int $designId): JsonResponse
    {
        $res = $this->service->addSpecification($designId, $request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function updateSpecification(Request $request, int $id): JsonResponse
    {
        $res = $this->service->updateSpecification($id, $request->all());
        return response()->json($res, $res['code'] ?? 200);
    }

    public function deleteSpecification(int $id): JsonResponse
    {
        $res = $this->service->deleteSpecification($id);
        return response()->json($res, $res['code'] ?? 200);
    }

    // ── Search & Filter ─────────────────────────────────────────────────

    public function searchDesigns(Request $request): JsonResponse
    {
        $res = $this->service->searchDesigns($request->all());
        return response()->json($res, $res['code'] ?? 200);
    }
}
