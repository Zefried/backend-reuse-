<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CottageBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CottageBookingController extends Controller
{
    /**
     * Store a new cottage booking request from the website modal.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cottage_id' => 'required|string|max:100',
            'cottage_name' => 'required|string|max:255',
            'guest_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'arrival_date' => 'nullable|date',
            'nights' => 'nullable|string|max:50',
            'guests_count' => 'nullable|string|max:50',
            'special_requests' => 'nullable|string|max:3000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $booking = CottageBooking::create([
            'cottage_id' => trim($validated['cottage_id']),
            'cottage_name' => trim($validated['cottage_name']),
            'guest_name' => trim($validated['guest_name']),
            'phone' => trim($validated['phone']),
            'email' => isset($validated['email']) ? trim($validated['email']) : null,
            'arrival_date' => $validated['arrival_date'] ?? null,
            'nights' => $validated['nights'] ?? null,
            'guests_count' => $validated['guests_count'] ?? null,
            'special_requests' => isset($validated['special_requests']) ? trim($validated['special_requests']) : null,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Your cottage reservation enquiry has been received! Our team will contact you to confirm details.',
            'data' => $booking,
        ], 201);
    }
}
