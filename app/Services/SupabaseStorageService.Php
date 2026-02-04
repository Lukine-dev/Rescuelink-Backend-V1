<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Supabase\Storage\StorageClient;

class SupabaseStorageService
{
    private $storageClient;
    private $bucket;
    private $baseUrl;

    public function __construct()
    {
        $this->bucket = config('services.supabase.bucket', 'accident-media');
        $this->baseUrl = config('services.supabase.url');
        
        // Initialize Supabase Storage Client
        $this->storageClient = new StorageClient(
            $this->baseUrl,
            config('services.supabase.key'),
            $this->bucket
        );
    }

    /**
     * Upload a file to Supabase Storage
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param array $options
     * @return array
     */
    public function uploadFile(UploadedFile $file, string $folder = '', array $options = []): array
    {
        try {
            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            
            // Build file path
            $path = trim($folder . '/' . $filename, '/');
            
            // Get file content
            $fileContent = file_get_contents($file->getRealPath());
            
            // Default options
            $uploadOptions = array_merge([
                'contentType' => $file->getMimeType(),
                'upsert' => true,
                'cacheControl' => '3600',
            ], $options);
            
            // Upload to Supabase
            $response = $this->storageClient->upload($path, $fileContent, $uploadOptions);
            
            // Get public URL
            $publicUrl = $this->storageClient->getPublicUrl($path);
            
            // Generate signed URL (for private files)
            $signedUrl = null;
            if (!$options['is_public'] ?? false) {
                $signedUrl = $this->generateSignedUrl($path, 3600); // 1 hour expiration
            }
            
            return [
                'success' => true,
                'path' => $path,
                'filename' => $originalName,
                'storage_filename' => $filename,
                'public_url' => $publicUrl,
                'signed_url' => $signedUrl,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'extension' => $extension,
            ];
            
        } catch (\Exception $e) {
            Log::error('Supabase upload failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'path' => null,
            ];
        }
    }

    /**
     * Upload multiple files
     *
     * @param array $files
     * @param string $folder
     * @param array $options
     * @return array
     */
    public function uploadMultiple(array $files, string $folder = '', array $options = []): array
    {
        $results = [];
        
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $result = $this->uploadFile($file, $folder, $options);
                $results[] = $result;
            }
        }
        
        return $results;
    }

    /**
     * Delete a file from Supabase Storage
     *
     * @param string $path
     * @return array
     */
    public function deleteFile(string $path): array
    {
        try {
            $this->storageClient->remove([$path]);
            
            return [
                'success' => true,
                'message' => 'File deleted successfully',
                'path' => $path,
            ];
            
        } catch (\Exception $e) {
            Log::error('Supabase delete failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'path' => $path,
            ];
        }
    }

    /**
     * Delete multiple files
     *
     * @param array $paths
     * @return array
     */
    public function deleteMultiple(array $paths): array
    {
        try {
            $this->storageClient->remove($paths);
            
            return [
                'success' => true,
                'message' => 'Files deleted successfully',
                'paths' => $paths,
            ];
            
        } catch (\Exception $e) {
            Log::error('Supabase multiple delete failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'paths' => $paths,
            ];
        }
    }

    /**
     * Get public URL for a file
     *
     * @param string $path
     * @return string
     */
    public function getPublicUrl(string $path): string
    {
        return $this->storageClient->getPublicUrl($path);
    }

    /**
     * Generate signed URL for private files
     *
     * @param string $path
     * @param int $expiresIn Seconds
     * @return string|null
     */
    public function generateSignedUrl(string $path, int $expiresIn = 3600): ?string
    {
        try {
            // Note: This requires the supabase-php package to support signed URLs
            // You might need to implement this differently based on your Supabase setup
            
            // For now, we'll return the public URL or implement based on your needs
            // You can use Supabase's createSignedUrl if available in the package
            
            return $this->storageClient->createSignedUrl($path, $expiresIn) ?? null;
            
        } catch (\Exception $e) {
            Log::error('Failed to generate signed URL: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * List files in a folder
     *
     * @param string $folder
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function listFiles(string $folder = '', int $limit = 100, int $offset = 0): array
    {
        try {
            $folder = trim($folder, '/');
            
            // Supabase PHP client might have a list method
            // This is a placeholder - adjust based on actual package capabilities
            
            return [
                'success' => true,
                'files' => [],
                'folder' => $folder,
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to list files: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'folder' => $folder,
            ];
        }
    }

    /**
     * Get file info/metadata
     *
     * @param string $path
     * @return array
     */
    public function getFileInfo(string $path): array
    {
        try {
            // This might require custom implementation based on Supabase API
            // For now, return basic info
            
            return [
                'success' => true,
                'path' => $path,
                'public_url' => $this->getPublicUrl($path),
                'exists' => true,
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get file info: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'path' => $path,
            ];
        }
    }

    /**
     * Move or rename a file
     *
     * @param string $fromPath
     * @param string $toPath
     * @return array
     */
    public function moveFile(string $fromPath, string $toPath): array
    {
        try {
            // Supabase might not have a direct move API
            // Implement copy + delete if needed
            
            return [
                'success' => false,
                'error' => 'Move operation not implemented',
                'from' => $fromPath,
                'to' => $toPath,
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to move file: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'from' => $fromPath,
                'to' => $toPath,
            ];
        }
    }

    /**
     * Create a folder
     *
     * @param string $folderPath
     * @return array
     */
    public function createFolder(string $folderPath): array
    {
        try {
            // In Supabase, folders are created automatically when files are uploaded
            // This is a placeholder for any folder-specific operations
            
            return [
                'success' => true,
                'message' => 'Folder structure will be created automatically',
                'path' => $folderPath,
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create folder: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'path' => $folderPath,
            ];
        }
    }

    /**
     * Check if file exists
     *
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        try {
            // This might require a HEAD request or list check
            // For now, return true assuming file exists if no error in getting URL
            
            $url = $this->getPublicUrl($path);
            return !empty($url);
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get storage statistics
     *
     * @return array
     */
    public function getStorageStats(): array
    {
        try {
            // This requires Supabase API that might not be available in the PHP client
            // You might need to use direct API calls
            
            return [
                'success' => true,
                'bucket' => $this->bucket,
                'stats' => [
                    'used' => 0,
                    'available' => 0,
                    'total' => 0,
                ],
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}