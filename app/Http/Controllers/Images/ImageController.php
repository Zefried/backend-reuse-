<?php

namespace App\Http\Controllers\Images;

use App\Http\Controllers\Controller;
use App\Models\AiContext;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImageController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'imageable_id' => 'required|integer',
            'imageable_type' => 'required|string',
        ]);

        $images = Image::where(
            'imageable_id',
            $validated['imageable_id']
        )
            ->where(
                'imageable_type',
                $validated['imageable_type']
            )
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $images,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'imageable_id' => 'required|integer',
                'imageable_type' => 'required|string',
                'images' => 'required|array|min:1',
                'images.*' => 'required|image|max:5120',
            ]);

            $imageableType = $validated['imageable_type'];
            $imageableId = $validated['imageable_id'];

            $uploaded = [];
            $duplicates = [];

            foreach ($validated['images'] as $image) {

                $imageHash = hash_file(
                    'sha256',
                    $image->getRealPath()
                );

                $existingImage = Image::where(
                    'imageable_id',
                    $imageableId
                )
                    ->where('imageable_type', $imageableType)
                    ->where('image_hash', $imageHash)
                    ->first();

                if ($existingImage) {
                    $duplicates[] =
                        $image->getClientOriginalName();

                    continue;
                }

                $extension =
                    $image->getClientOriginalExtension();

                $filename =
                    Str::uuid() . '.' . $extension;

                $path = $image->storeAs(
                    'images',
                    $filename,
                    'public'
                );

                $imageRecord = Image::create([
                    'imageable_id' => $imageableId,
                    'imageable_type' => $imageableType,
                    'image_url' => $path,
                    'image_name' =>
                    $image->getClientOriginalName(),
                    'image_hash' => $imageHash,
                    'sort_order' =>
                    Image::where('imageable_id', $imageableId)
                        ->where(
                            'imageable_type',
                            $imageableType
                        )
                        ->count(),
                    'is_primary' =>
                    Image::where('imageable_id', $imageableId)
                        ->where(
                            'imageable_type',
                            $imageableType
                        )
                        ->count() === 0,
                ]);

                $uploaded[] = $imageRecord;
            }

            return response()->json([
                'status' => true,
                'message' => 'Images processed successfully.',
                'uploaded' => $uploaded,
                'duplicates' => $duplicates,
            ], 201);
        } catch (ValidationException $e) {

            $imageErrors = [];

            foreach ($e->errors() as $field => $messages) {

                if (preg_match('/^images\.(\d+)$/', $field, $matches)) {

                    $index = (int) $matches[1];
                    $image = $request->file("images.$index");

                    $friendlyMessages = [];

                    foreach ($messages as $message) {

                        if (str_contains($message, 'must be an image')) {
                            $friendlyMessages[] =
                                'This image appears to be corrupted or is not a valid image. Please choose another image.';
                        } else {
                            $friendlyMessages[] = $message;
                        }
                    }

                    $imageErrors[] = [
                        'index' => $index,
                        'name' => $image
                            ? $image->getClientOriginalName()
                            : null,
                        'errors' => $friendlyMessages,
                    ];
                }
            }

            return response()->json([
                'status' => false,
                'message' => 'Some images could not be uploaded.',
                'errors' => $e->errors(),
                'image_errors' => $imageErrors,
            ], 422);
        } catch (Throwable $e) {

            Log::error(
                'Failed to upload images',
                [
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'status' => false,
                'message' => 'Failed to upload images.',
            ], 500);
        }
    }

    /**
     * Delete a single image.
     */
    public function destroy(int $id)
    {
        $image = Image::find($id);

        if (!$image) {
            return response()->json([
                'status'  => false,
                'message' => 'Image not found.',
                'code'    => 404,
            ], 404);
        }

        if ($image->image_url) {
            Storage::disk('public')->delete($image->image_url);
        }

        $image->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Image deleted successfully.',
            'code'    => 200,
        ], 200);
    }

    /**
     * Replace an existing image with a new image file.
     */
    public function replace(Request $request, int $id)
    {
        $imageRecord = Image::find($id);

        if (!$imageRecord) {
            return response()->json([
                'status'  => false,
                'message' => 'Image not found.',
                'code'    => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'code'    => 422,
            ], 422);
        }

        $newImage = $request->file('image');
        $imageHash = hash_file('sha256', $newImage->getRealPath());

        // Check duplicate
        $exists = Image::where('image_hash', $imageHash)
            ->where('id', '!=', $imageRecord->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'This image has already been uploaded.',
                'code'    => 422,
            ], 422);
        }

        // Delete old file
        if ($imageRecord->image_url) {
            Storage::disk('public')->delete($imageRecord->image_url);
        }

        // Store new file
        $extension = $newImage->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        $path = $newImage->storeAs('images', $filename, 'public');

        $imageRecord->update([
            'image_url'  => $path,
            'image_name' => $newImage->getClientOriginalName(),
            'image_hash' => $imageHash,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Image replaced successfully.',
            'data'    => $imageRecord,
            'code'    => 200,
        ], 200);
    }

    /**
     * Store new images for an existing AI Context.
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
            'images'   => 'required|array|min:1',
            'images.*' => 'image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'code'    => 422,
            ], 422);
        }

        $uploaded = [];
        $duplicates = [];

        foreach ($request->file('images') as $image) {
            $imageHash = hash_file('sha256', $image->getRealPath());

            $exists = Image::where('image_hash', $imageHash)->exists();
            if ($exists) {
                $duplicates[] = $image->getClientOriginalName();
                continue;
            }

            $extension = $image->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            $path = $image->storeAs('images', $filename, 'public');

            $uploaded[] = Image::create([
                'imageable_id'   => $record->id,
                'imageable_type' => AiContext::class,
                'image_url'      => $path,
                'image_name'     => $image->getClientOriginalName(),
                'image_hash'     => $imageHash,
                'sort_order'     => Image::where('imageable_id', $record->id)->where('imageable_type', AiContext::class)->count(),
                'is_primary'     => Image::where('imageable_id', $record->id)->where('imageable_type', AiContext::class)->count() === 0,
            ]);
        }

        return response()->json([
            'status'     => true,
            'message'    => 'Images uploaded.',
            'data'       => $uploaded,
            'duplicates' => $duplicates,
            'code'       => 201,
        ], 201);
    }
}
