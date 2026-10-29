<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CottageBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminCottageBookingController extends Controller
{
    /**
     * List all cottage bookings with filtering and summary metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CottageBooking::query();

        // Apply filters
        $query->filter($request->only(['search', 'status', 'cottage_id']));

        $bookings = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20));

        // Metric counts for admin dashboard tabs
        $metrics = [
            'total' => CottageBooking::count(),
            'pending' => CottageBooking::where('status', 'pending')->count(),
            'confirmed' => CottageBooking::where('status', 'confirmed')->count(),
            'cancelled' => CottageBooking::where('status', 'cancelled')->count(),
            'completed' => CottageBooking::where('status', 'completed')->count(),
        ];

        return response()->json([
            'status' => true,
            'data' => $bookings->items(),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get single cottage booking.
     */
    public function show(int $id): JsonResponse
    {
        $booking = CottageBooking::find($id);

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'Cottage booking not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $booking,
        ]);
    }

    /**
     * Update cottage booking status, pricing quote, or admin call notes.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $booking = CottageBooking::find($id);

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'Cottage booking not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,confirmed,cancelled,completed',
            'price_quoted' => 'nullable|numeric|min:0',
            'admin_notes' => 'nullable|string|max:5000',
            'last_contacted_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (isset($validated['status'])) {
            $booking->status = $validated['status'];
            if ($validated['status'] === 'confirmed' && !isset($validated['last_contacted_at']) && !$booking->last_contacted_at) {
                $booking->last_contacted_at = now();
            }
        }

        if (array_key_exists('price_quoted', $validated)) {
            $booking->price_quoted = $validated['price_quoted'];
        }

        if (array_key_exists('admin_notes', $validated)) {
            $booking->admin_notes = $validated['admin_notes'];
        }

        if (isset($validated['last_contacted_at'])) {
            $booking->last_contacted_at = $validated['last_contacted_at'];
        }

        $booking->save();

        return response()->json([
            'status' => true,
            'message' => 'Cottage booking updated successfully',
            'data' => $booking,
        ]);
    }

    /**
     * Delete cottage booking.
     */
    public function destroy(int $id): JsonResponse
    {
        $booking = CottageBooking::find($id);

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'Cottage booking not found',
            ], 404);
        }

        $booking->delete();

        return response()->json([
            'status' => true,
            'message' => 'Cottage booking deleted successfully',
        ]);
    }
}
