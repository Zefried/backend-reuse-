<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AttachmentController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\AiContextController;
use App\Http\Controllers\Admin\CampaignInformationController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Images\ImageController;
use App\Http\Controllers\ChatEngine\ChatEnginerController;
use App\Http\Middleware\CheckAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/register',[AuthController::class, 'register']);
Route::post('/login',[AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');





Route::prefix('admin')
    ->middleware(['auth:sanctum', CheckAdmin::class])
    ->group(function () {

        // ── Bulk API Routes ───────────────────────────
        Route::post('/services/bulk', [ServiceController::class, 'bulkStore']);
        Route::delete('/services/bulk', [ServiceController::class, 'bulkDestroy']);

        // ── User routes ───────────────────────────
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

        // ── Business routes ───────────────────────
        Route::get('/businesses', [\App\Http\Controllers\BusinessController::class, 'index']);
        Route::post('/businesses', [\App\Http\Controllers\BusinessController::class, 'store']);
        Route::put('/businesses/{id}', [\App\Http\Controllers\BusinessController::class, 'update']);
        Route::delete('/businesses/{id}', [\App\Http\Controllers\BusinessController::class, 'destroy']);
        Route::post('/businesses/check', [\App\Http\Controllers\BusinessController::class, 'checkBusiness']);

        // ── Preserved Category lookup routes (for Business, AI Context & Playground dropdowns) ───
        Route::get('/categories/flat', [CategoryController::class, 'flatList']);
        Route::get('/categories', [CategoryController::class, 'index']);

        // ── Service routes ────────────────────────
        Route::get('/services', [ServiceController::class, 'index']);
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{id}', [ServiceController::class, 'update']);
        Route::patch('/services/{id}/toggle', [ServiceController::class, 'toggleStatus']);
        Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

        // ── Preserved Attribute lookup routes (for AI Context & Playground dropdowns) ───
        Route::get('/attributes', [AttributeController::class, 'index']);

        // ── Image routes ──────────────────────────
        Route::get('/images', [ImageController::class, 'index']);
        Route::post('/images', [ImageController::class, 'store']);
        Route::delete('/images/{id}', [ImageController::class, 'destroy']);
        Route::put('/images/{id}', [ImageController::class, 'update']);
        Route::post('/images/{id}/replace', [ImageController::class, 'replace']);
        Route::post('/ai-contexts/{id}/images', [ImageController::class, 'storeForContext']);

        // ── Attachment routes ──────────────────────
        Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy']);
        Route::post('/attachments/{id}/replace', [AttachmentController::class, 'replace']);
        Route::put('/attachments/{id}', [AttachmentController::class, 'update']);
        Route::post('/ai-contexts/{id}/attachments', [AttachmentController::class, 'storeForContext']);

        // ── AI Context routes ─────────────────────
        Route::get('/ai-contexts', [AiContextController::class, 'index']);
        Route::post('/ai-contexts', [AiContextController::class, 'store']);
        Route::post('/ai-contexts/bulk-delete', [AiContextController::class, 'bulkDestroy']);
        Route::put('/ai-contexts/{id}', [AiContextController::class, 'update']);
        Route::delete('/ai-contexts/{id}', [AiContextController::class, 'destroy']);

        // ── Campaign routes ───────────────────────
        Route::get('/campaigns', [CampaignInformationController::class, 'index']);
        Route::post('/campaigns', [CampaignInformationController::class, 'store']);
        Route::put('/campaigns/{id}', [CampaignInformationController::class, 'update']);
        Route::delete('/campaigns/{id}', [CampaignInformationController::class, 'destroy']);

        // ── Lead Qualification routes ────────────────
        Route::post('/lead-qualifications/services', [\App\Http\Controllers\Admin\LeadQualificationController::class, 'getServices']);
        Route::post('/lead-qualifications/save', [\App\Http\Controllers\Admin\LeadQualificationController::class, 'saveQualifications']);
        Route::delete('/lead-qualifications/{id}', [\App\Http\Controllers\Admin\LeadQualificationController::class, 'destroy']);

        // ── Catalogue Module routes ───────────────────
        Route::prefix('catalogue')->group(function () {
            // Niches
            Route::get('/niches', [CatalogueController::class, 'listNiches']);
            Route::post('/niches', [CatalogueController::class, 'createNiche']);
            Route::put('/niches/{id}', [CatalogueController::class, 'updateNiche']);
            Route::patch('/niches/{id}/toggle', [CatalogueController::class, 'toggleNiche']);
            Route::delete('/niches/{id}', [CatalogueController::class, 'deleteNiche']);

            // Businesses under Niche
            Route::get('/businesses/all', [CatalogueController::class, 'listAllBusinesses']);
            Route::get('/niches/{nicheId}/businesses', [CatalogueController::class, 'listBusinessesByNiche']);
            Route::post('/niches/{nicheId}/businesses', [CatalogueController::class, 'createBusinessUnderNiche']);
            Route::post('/niches/{nicheId}/businesses/assign', [CatalogueController::class, 'assignBusinessToNiche']);
            Route::delete('/niches/{nicheId}/businesses/{businessId}/unlink', [CatalogueController::class, 'unlinkBusinessFromNiche']);

            // Services
            Route::get('/businesses/{businessId}/services', [CatalogueController::class, 'listServicesByBusiness']);
            Route::post('/businesses/{businessId}/services', [CatalogueController::class, 'createService']);
            Route::put('/services/{id}', [CatalogueController::class, 'updateService']);
            Route::delete('/services/{id}', [CatalogueController::class, 'deleteService']);

            // Categories
            Route::get('/services/{serviceId}/categories', [CatalogueController::class, 'listCategoriesByService']);
            Route::post('/services/{serviceId}/categories', [CatalogueController::class, 'createCategory']);
            Route::put('/categories/{id}', [CatalogueController::class, 'updateCategory']);
            Route::delete('/categories/{id}', [CatalogueController::class, 'deleteCategory']);

            // Designs
            Route::get('/designs', [CatalogueController::class, 'listDesigns']);
            Route::get('/designs/search', [CatalogueController::class, 'searchDesigns']);
            Route::get('/designs/{id}', [CatalogueController::class, 'getDesign']);
            Route::post('/designs', [CatalogueController::class, 'createDesign']);
            Route::put('/designs/{id}', [CatalogueController::class, 'updateDesign']);
            Route::delete('/designs/{id}', [CatalogueController::class, 'deleteDesign']);

            // Specifications
            Route::post('/designs/{designId}/specifications', [CatalogueController::class, 'addSpecification']);
            Route::put('/specifications/{id}', [CatalogueController::class, 'updateSpecification']);
            Route::delete('/specifications/{id}', [CatalogueController::class, 'deleteSpecification']);
        });
    });

// ── Public Chat routes ────────────────────────
Route::prefix('public/chat')->group(function () {
    Route::post('/session/init', [ChatEnginerController::class, 'initSession']);
    Route::post('/init', [ChatEnginerController::class, 'initSession']);
});

