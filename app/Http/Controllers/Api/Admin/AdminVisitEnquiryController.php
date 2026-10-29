<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisitEnquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminVisitEnquiryController extends Controller
{
    /**
     * List all visit enquiries with filtering and summary metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $query = VisitEnquiry::query();

        // Apply filters
        $query->filter($request->only(['search', 'status']));

        $enquiries = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20));

        // Metric counts for admin dashboard tabs
        $metrics = [
            'total' => VisitEnquiry::count(),
            'new' => VisitEnquiry::where('status', 'new')->count(),
            'contacted' => VisitEnquiry::where('status', 'contacted')->count(),
            'resolved' => VisitEnquiry::where('status', 'resolved')->count(),
        ];

        return response()->json([
            'status' => true,
            'data' => $enquiries->items(),
            'pagination' => [
                'current_page' => $enquiries->currentPage(),
                'last_page' => $enquiries->lastPage(),
                'per_page' => $enquiries->perPage(),
                'total' => $enquiries->total(),
            ],
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get single visit enquiry.
     */
    public function show(int $id): JsonResponse
    {
        $enquiry = VisitEnquiry::find($id);

        if (!$enquiry) {
            return response()->json([
                'status' => false,
                'message' => 'Visit enquiry not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $enquiry,
        ]);
    }

    /**
     * Update visit enquiry status or admin call notes.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $enquiry = VisitEnquiry::find($id);

        if (!$enquiry) {
            return response()->json([
                'status' => false,
                'message' => 'Visit enquiry not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:new,contacted,resolved,archived',
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
            $enquiry->status = $validated['status'];
            if ($validated['status'] === 'contacted' && !isset($validated['last_contacted_at']) && !$enquiry->last_contacted_at) {
                $enquiry->last_contacted_at = now();
            }
        }

        if (array_key_exists('admin_notes', $validated)) {
            $enquiry->admin_notes = $validated['admin_notes'];
        }

        if (isset($validated['last_contacted_at'])) {
            $enquiry->last_contacted_at = $validated['last_contacted_at'];
        }

        $enquiry->save();

        return response()->json([
            'status' => true,
            'message' => 'Enquiry updated successfully',
            'data' => $enquiry,
        ]);
    }

    /**
     * Delete visit enquiry.
     */
    public function destroy(int $id): JsonResponse
    {
        $enquiry = VisitEnquiry::find($id);

        if (!$enquiry) {
            return response()->json([
                'status' => false,
                'message' => 'Visit enquiry not found',
            ], 404);
        }

        $enquiry->delete();

        return response()->json([
            'status' => true,
            'message' => 'Visit enquiry deleted successfully',
        ]);
    }
}
