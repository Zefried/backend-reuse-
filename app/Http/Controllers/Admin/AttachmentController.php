<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiContext;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    /**
     * Delete an attachment (and physical file if stored).
     */
    public function destroy(int $id)
    {
        $attachment = Attachment::find($id);

        if (!$attachment) {
            return response()->json([
                'status'  => false,
                'message' => 'Attachment not found.',
                'code'    => 404,
            ], 404);
        }

        if ($attachment->type === 'pdf' && $attachment->url) {
            Storage::disk('public')->delete($attachment->url);
        }

        $attachment->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Attachment deleted successfully.',
            'code'    => 200,
        ], 200);
    }

    /**
     * Replace an existing attachment (file or YouTube link).
     */
    public function replace(Request $request, int $id)
    {
        $attachment = Attachment::find($id);

        if (!$attachment) {
            return response()->json([
                'status'  => false,
                'message' => 'Attachment not found.',
                'code'    => 404,
            ], 404);
        }

        if ($attachment->type === 'pdf') {
            $validator = Validator::make($request->all(), [
                'document' => 'required|file|mimes:pdf|max:10240',
                'title'    => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                    'code'    => 422,
                ], 422);
            }

            $file = $request->file('document');
            $fileHash = hash_file('sha256', $file->getRealPath());

            // Check duplicate against other attachments
            $exists = Attachment::where('file_hash', $fileHash)
                ->where('id', '!=', $attachment->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status'  => false,
                    'message' => 'This document is already uploaded.',
                    'code'    => 422,
                ], 422);
            }

            // Delete old file
            if ($attachment->url) {
                Storage::disk('public')->delete($attachment->url);
            }

            // Store new file
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            $path = $file->storeAs('attachments', $filename, 'public');

            $attachment->update([
                'title'     => $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'url'       => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_hash' => $fileHash,
                'metadata'  => [
                    'mime_type' => $file->getClientMimeType(),
                ],
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Document replaced successfully.',
                'data'    => $attachment,
                'code'    => 200,
            ], 200);
        }

        if ($attachment->type === 'youtube') {
            $validator = Validator::make($request->all(), [
                'url'   => 'required|url',
                'title' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                    'code'    => 422,
                ], 422);
            }

            $url = trim($request->input('url'));
            $videoId = null;
            if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $url, $matches)) {
                $videoId = $matches[1];
            }

            $attachment->update([
                'title'    => $request->input('title') ?: ($attachment->title ?: 'YouTube Video'),
                'url'      => $url,
                'metadata' => [
                    'video_id' => $videoId,
                ],
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'YouTube link updated successfully.',
                'data'    => $attachment,
                'code'    => 200,
            ], 200);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Unsupported attachment type for replacement.',
            'code'    => 400,
        ], 400);
    }

    /**
     * Update attachment metadata (e.g. title or link).
     */
    public function update(Request $request, int $id)
    {
        $attachment = Attachment::find($id);

        if (!$attachment) {
            return response()->json([
                'status'  => false,
                'message' => 'Attachment not found.',
                'code'    => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'url'   => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'code'    => 422,
            ], 422);
        }

        $data = [];
        if ($request->has('title')) {
            $data['title'] = $request->input('title');
        }

        if ($attachment->type === 'youtube' && $request->filled('url')) {
            $url = trim($request->input('url'));
            $data['url'] = $url;
            $videoId = null;
            if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $url, $matches)) {
                $videoId = $matches[1];
            }
            $data['metadata'] = ['video_id' => $videoId];
        }

        $attachment->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Attachment updated successfully.',
            'data'    => $attachment,
            'code'    => 200,
        ], 200);
    }

    /**
     * Add attachments to an existing AI context.
     */
    public function storeForContext(Request $request, int $id)
    {
        $record = AiContext::find($id);

        if (!$record) {
            return response()->json([
                'status'  => false,
                'message' => 'Context not found.',
                'code'    => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'documents'       => 'nullable|array',
            'documents.*'     => 'file|mimes:pdf|max:10240',
            'youtube_links'   => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'code'    => 422,
            ], 422);
        }

        $created = [];

        // 1. Process documents
        if ($request->hasFile('documents')) {
            $docTitles = $request->input('documents_titles', []);
            foreach ($request->file('documents') as $i => $doc) {
                $docHash = hash_file('sha256', $doc->getRealPath());

                $exists = Attachment::where('file_hash', $docHash)->exists();
                if ($exists) {
                    return response()->json([
                        'status'  => false,
                        'message' => "Document '" . $doc->getClientOriginalName() . "' already exists.",
                        'code'    => 422,
                    ], 422);
                }

                $extension = $doc->getClientOriginalExtension();
                $filename = Str::uuid() . '.' . $extension;
                $path = $doc->storeAs('attachments', $filename, 'public');

                $customTitle = is_array($docTitles) && !empty($docTitles[$i])
                    ? trim($docTitles[$i])
                    : pathinfo($doc->getClientOriginalName(), PATHINFO_FILENAME);

                $created[] = Attachment::create([
                    'attachable_id'   => $record->id,
                    'attachable_type' => AiContext::class,
                    'type'            => 'pdf',
                    'title'           => $customTitle,
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
                $videoId = null;
                if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $url, $matches)) {
                    $videoId = $matches[1];
                }

                $created[] = Attachment::create([
                    'attachable_id'   => $record->id,
                    'attachable_type' => AiContext::class,
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

        return response()->json([
            'status'  => true,
            'message' => 'Attachments added successfully.',
            'data'    => $created,
            'code'    => 201,
        ], 201);
    }
}
