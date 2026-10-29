<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitEnquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VisitEnquiryController extends Controller
{
    /**
     * Store a new visitor enquiry from the website Visit Us page.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'visit_date' => 'nullable|date',
            'message' => 'nullable|string|max:3000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $enquiry = VisitEnquiry::create([
            'name' => trim($validated['name']),
            'contact' => trim($validated['contact']),
            'visit_date' => $validated['visit_date'] ?? null,
            'message' => isset($validated['message']) ? trim($validated['message']) : null,
            'status' => 'new',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Thank you for your enquiry! Our team will get in touch with you shortly.',
            'data' => $enquiry,
        ], 201);
    }
}
