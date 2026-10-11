<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiContext;
use App\Models\Attachment;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AiContextController extends Controller
{
    /** GET /api/admin/ai-contexts */
    public function index(Request $request)
    {
        $query = AiContext::with(['images', 'attachments'])->orderBy('service_name')->orderBy('attribute_definition');

        if ($request->filled('service_name')) {
            $query->where('service_name', $request->service_name);
        }
        if ($request->filled('attribute_definition')) {
            $query->where('attribute_definition', $request->attribute_definition);
        }

        return response()->json(['status' => true, 'data' => $query->get(), 'code' => 200], 200);
    }

    /** POST /api/admin/ai-contexts */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id'          => 'required|string|max:255',
            'service_name'         => 'required|string|max:255',
            'attribute_definition' => 'required|string|max:255',
            'context'              => 'required|string',
            'prompt'               => 'required|string',
            'images'               => 'nullable|array',
            'images.*'             => 'image|max:5120',
            'documents'            => 'nullable|array',
            'documents.*'          => 'file|mimes:pdf|max:10240',
            'youtube_links'        => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'code' => 422], 422);
        }

        // Global duplicate image check
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imageHash = hash_file('sha256', $image->getRealPath());
                $exists = Image::where('image_hash', $imageHash)->exists();
                if ($exists) {
                    return response()->json([
                        'status' => false, 
                        'message' => "Hey, you are using old images (" . $image->getClientOriginalName() . "). This system only accepts unique images. Please upload new images.", 
                        'code' => 422
                    ], 422);
                }
            }
        }

        // Global duplicate document (PDF) check
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $doc) {
                $docHash = hash_file('sha256', $doc->getRealPath());
                $exists = Attachment::where('file_hash', $docHash)->exists();
                if ($exists) {
                    return response()->json([
                        'status' => false,
                        'message' => "Hey, you are using an existing document (" . $doc->getClientOriginalName() . "). This document has already been uploaded.",
                        'code' => 422
                    ], 422);
                }
            }
        }

        $uploadedFiles = [];

        try {
            DB::beginTransaction();

            $record = AiContext::create([
                'business_id'          => trim($request->business_id),
                'service_name'         => trim($request->service_name),
                'attribute_definition' => trim($request->attribute_definition),
                'context'              => $request->context,
                'prompt'               => $request->prompt,
                'media_resources'      => null,
            ]);

            // 1. Store images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imageHash = hash_file('sha256', $image->getRealPath());

                    $extension = $image->getClientOriginalExtension();
                    $filename = Str::uuid() . '.' . $extension;
                    $path = $image->storeAs('images', $filename, 'public');
                    $uploadedFiles[] = ['disk' => 'public', 'path' => $path];

                    Image::create([
                        'imageable_id'   => $record->id,
                        'imageable_type' => 'App\Models\AiContext',
                        'image_url'      => $path,
                        'image_name'     => $image->getClientOriginalName(),
                        'image_hash'     => $imageHash,
                        'sort_order'     => Image::where('imageable_id', $record->id)->where('imageable_type', 'App\Models\AiContext')->count(),
                        'is_primary'     => Image::where('imageable_id', $record->id)->where('imageable_type', 'App\Models\AiContext')->count() === 0,
                    ]);
                }
            }

            // 2. Store attachments (PDFs & YouTube links)
            $this->storeAttachmentsHelper($record, $request, $uploadedFiles);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Context created successfully',
                'data'    => $record->load(['images', 'attachments']),
                'code'    => 201
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            // Cleanup any files written to disk before failure
            foreach ($uploadedFiles as $f) {
                Storage::disk($f['disk'])->delete($f['path']);
            }

            return response()->json(['status' => false, 'message' => 'Failed to create context: ' . $e->getMessage(), 'code' => 500], 500);
        }
    }

    /**
     * Helper to store PDF documents and YouTube links as attachments.
     */
    private function storeAttachmentsHelper(AiContext $record, Request $request, array &$uploadedFiles): void
    {
        // 1. Process PDF documents
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $doc) {
                $docHash = hash_file('sha256', $doc->getRealPath());
                $extension = $doc->getClientOriginalExtension();
                $filename = Str::uuid() . '.' . $extension;
                $path = $doc->storeAs('attachments', $filename, 'public');
                $uploadedFiles[] = ['disk' => 'public', 'path' => $path];

                Attachment::create([
                    'attachable_id'   => $record->id,
                    'attachable_type' => 'App\Models\AiContext',
                    'type'            => 'pdf',
                    'title'           => pathinfo($doc->getClientOriginalName(), PATHINFO_FILENAME),
                    'url'             => $path,
                    'file_name'       => $doc->getClientOriginalName(),
                    'file_size'       => $doc->getSize(),
                    'file_hash'       => $docHash,
                    'metadata'        => [
                        'mime_type' => $doc->getClientMimeType(),
                    ],
                ]);
            }
        }

        // 2. Process YouTube links
        if ($request->filled('youtube_links') && is_array($request->input('youtube_links'))) {
            foreach ($request->input('youtube_links') as $item) {
                $url = is_array($item) ? ($item['url'] ?? '') : $item;
                $url = trim($url ?? '');
                if (empty($url)) {
                    continue;
                }

                $title = is_array($item) ? ($item['title'] ?? null) : null;

                // Extract YouTube Video ID if present
                $videoId = null;
                if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $url, $matches)) {
                    $videoId = $matches[1];
                }

                Attachment::create([
                    'attachable_id'   => $record->id,
                    'attachable_type' => 'App\Models\AiContext',
                    'type'            => 'youtube',
                    'title'           => $title ?: 'YouTube Video',
                    'url'             => $url,
                    'file_name'       => null,
                    'file_size'       => null,
                    'file_hash'       => null,
                    'metadata'        => [
                        'video_id' => $videoId,
                    ],
                ]);
            }
        }
    }

    /** PUT /api/admin/ai-contexts/{id} */
    public function update(Request $request, int $id)
    {
        $record = AiContext::find($id);

        if (!$record) {
            return response()->json(['status' => false, 'message' => 'Context not found', 'code' => 404], 404);
        }

        $validator = Validator::make($request->all(), [
            'business_id'          => 'required|string|max:255',
            'service_name'         => 'required|string|max:255',
            'attribute_definition' => 'required|string|max:255',
            'context'              => 'required|string',
            'prompt'               => 'required|string',
            'media_resources'      => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'code' => 422], 422);
        }

        $record->business_id          = trim($request->business_id);
        $record->service_name         = trim($request->service_name);
        $record->attribute_definition = trim($request->attribute_definition);
        $record->context              = $request->context;
        $record->prompt               = $request->prompt;
        $record->media_resources      = $request->media_resources;
        $record->save();

        return response()->json(['status' => true, 'message' => 'Context updated successfully', 'data' => $record, 'code' => 200], 200);
    }

    /** DELETE /api/admin/ai-contexts/{id} */
    public function destroy(int $id)
    {
        $record = AiContext::find($id);

        if (!$record) {
            return response()->json(['status' => false, 'message' => 'Context not found', 'code' => 404], 404);
        }

        $record->images()->delete();
        $record->attachments()->delete();
        $record->delete();

        return response()->json(['status' => true, 'message' => 'Context deleted successfully', 'code' => 200], 200);
    }

    /** POST /api/admin/ai-contexts/bulk-delete */
    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids) || !is_array($ids)) {
            return response()->json(['status' => false, 'message' => 'No contexts selected for deletion', 'code' => 400], 400);
        }

        Image::where('imageable_type', 'App\Models\AiContext')->whereIn('imageable_id', $ids)->delete();
        Attachment::where('attachable_type', 'App\Models\AiContext')->whereIn('attachable_id', $ids)->delete();
        AiContext::whereIn('id', $ids)->delete();

        return response()->json(['status' => true, 'message' => 'Selected contexts deleted successfully', 'code' => 200], 200);
    }
}
