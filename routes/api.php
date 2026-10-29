<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\VisitEnquiryController;
use App\Http\Controllers\Api\CottageBookingController;
use App\Http\Controllers\Api\Admin\AdminVisitEnquiryController;
use App\Http\Controllers\Api\Admin\AdminCottageBookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');

// ── Public Visitor Enquiries & Cottage Bookings ────
Route::post('/visit-enquiries', [VisitEnquiryController::class, 'store']);
Route::post('/cottage-bookings', [CottageBookingController::class, 'store']);

// ── Admin Protected Routes ─────────────────────────
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::post('/users', [AuthController::class, 'createUser']);
    Route::get('/users', [AuthController::class, 'listUsers']);

    // Visit Enquiries Management
    Route::get('/visit-enquiries', [AdminVisitEnquiryController::class, 'index']);
    Route::get('/visit-enquiries/{id}', [AdminVisitEnquiryController::class, 'show']);
    Route::patch('/visit-enquiries/{id}', [AdminVisitEnquiryController::class, 'update']);
    Route::delete('/visit-enquiries/{id}', [AdminVisitEnquiryController::class, 'destroy']);

    // Cottage Bookings Management
    Route::get('/cottage-bookings', [AdminCottageBookingController::class, 'index']);
    Route::get('/cottage-bookings/{id}', [AdminCottageBookingController::class, 'show']);
    Route::patch('/cottage-bookings/{id}', [AdminCottageBookingController::class, 'update']);
    Route::delete('/cottage-bookings/{id}', [AdminCottageBookingController::class, 'destroy']);
});
