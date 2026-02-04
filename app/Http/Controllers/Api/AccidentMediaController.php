<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accident;
use App\Models\AccidentMedia;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AccidentMediaController extends Controller
{
    private $supabaseStorage;

    public function __construct(SupabaseStorageService $supabaseStorage)
    {
        $this->middleware('auth:api');
        $this->supabaseStorage = $supabaseStorage;
    }

    public function index($accidentId)
    {
        $accident = Accident::findOrFail($accidentId);
        
        // Check authorization
        $user = auth()->user();
        if ($user->isRegularUser() && $accident->reported_by !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $media = $accident->media()->orderBy('created_at', 'desc')->get();
        
        // Add URLs to each media item
        $media->each(function ($item) {
            if ($item->is_public) {
                $item->url = $this->supabaseStorage->getPublicUrl($item->file_path);
            } else {
                $item->url = $this->supabaseStorage->generateSignedUrl($item->file_path, 3600);
            }
        });
        
        return response()->json($media);
    }

    public function store(Request $request, $accidentId)
    {
        $validator = Validator::make($request->all(), [
            'files.*' => 'required|file|mimes:jpg,jpeg,png,gif,mp4,avi,mov,pdf,doc,docx|max:51200', // 50MB max
            'description' => 'nullable|string',
            'is_public' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $accident = Accident::findOrFail($accidentId);
        $user = auth()->user();

        // Check authorization
        if ($user->isRegularUser() && $accident->reported_by !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $uploadedFiles = [];

        foreach ($request->file('files') as $file) {
            $folder = 'accidents/' . $accidentId;
            
            // Upload to Supabase using service
            $uploadResult = $this->supabaseStorage->uploadFile($file, $folder, [
                'is_public' => $request->is_public ?? false,
            ]);

            if (!$uploadResult['success']) {
                continue; // Skip failed uploads
            }

            // Save to database
            $media = AccidentMedia::create([
                'accident_id' => $accidentId,
                'uploaded_by' => $user->id,
                'file_path' => $uploadResult['path'],
                'file_name' => $uploadResult['filename'],
                'file_type' => $uploadResult['extension'],
                'mime_type' => $uploadResult['mime_type'],
                'file_size' => $uploadResult['size'],
                'media_type' => $this->getMediaType($uploadResult['extension']),
                'description' => $request->description,
                'is_public' => $request->is_public ?? false,
            ]);

            // Add URL to response
            $media->url = $media->is_public 
                ? $uploadResult['public_url'] 
                : $uploadResult['signed_url'];

            $uploadedFiles[] = $media;
        }

        return response()->json([
            'message' => 'Files uploaded successfully',
            'files' => $uploadedFiles
        ], 201);
    }

    public function destroy($accidentId, $mediaId)
    {
        $media = AccidentMedia::where('accident_id', $accidentId)
            ->where('id', $mediaId)
            ->firstOrFail();

        $user = auth()->user();
        
        // Check authorization
        if (!$user->isAdmin() && $media->uploaded_by !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Delete from Supabase using service
        $deleteResult = $this->supabaseStorage->deleteFile($media->file_path);
        
        if (!$deleteResult['success']) {
            return response()->json([
                'error' => 'Failed to delete file: ' . $deleteResult['error']
            ], 500);
        }
        
        // Delete from database
        $media->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }

    public function getPublicUrl($accidentId, $mediaId)
    {
        $media = AccidentMedia::where('accident_id', $accidentId)
            ->where('id', $mediaId)
            ->firstOrFail();

        if (!$media->is_public) {
            return response()->json(['error' => 'File is not public'], 403);
        }

        $url = $this->supabaseStorage->getPublicUrl($media->file_path);
        return response()->json(['url' => $url]);
    }

    public function getSignedUrl($accidentId, $mediaId)
    {
        $media = AccidentMedia::where('accident_id', $accidentId)
            ->where('id', $mediaId)
            ->firstOrFail();

        $user = auth()->user();
        
        // Check authorization
        if (!$user->isAdmin() && $media->uploaded_by !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $url = $this->supabaseStorage->generateSignedUrl($media->file_path, 3600); // 1 hour
        return response()->json(['url' => $url]);
    }

    private function getMediaType($extension)
    {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
        $videoTypes = ['mp4', 'avi', 'mov', 'wmv', 'flv'];
        $audioTypes = ['mp3', 'wav', 'aac'];
        $documentTypes = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx'];

        if (in_array($extension, $imageTypes)) return 'photo';
        if (in_array($extension, $videoTypes)) return 'video';
        if (in_array($extension, $audioTypes)) return 'audio';
        if (in_array($extension, $documentTypes)) return 'document';
        
        return 'other';
    }
}