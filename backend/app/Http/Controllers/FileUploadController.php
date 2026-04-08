<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Http\Requests\DeleteFileRequest;
use App\Models\ServiceRequest;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Log;

class FileUploadController extends Controller
{
    /**
     * Allowed file types and their configurations
     */
    protected array $allowedTypes = [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 5120, // 5MB
            'mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        ],
        'document' => [
            'extensions' => ['pdf', 'doc', 'docx', 'txt'],
            'max_size' => 10240, // 10MB
            'mime_types' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'],
        ],
    ];

    /**
     * Upload a file for a service request.
     */
    public function upload(UploadFileRequest $request): JsonResponse
    {
        try {
            $serviceRequest = ServiceRequest::findOrFail($request->service_request_id);
            
            // Authorization check
            $this->authorize('update', $serviceRequest);

            $file = $request->file('file');
            $fileType = $this->determineFileType($file);
            
            if (!$fileType) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_FILE_TYPE',
                        'message' => 'File type not allowed.',
                        'request_id' => $request->header('X-Request-ID'),
                        'timestamp' => now()->toISOString(),
                    ]
                ], 422);
            }

            // Validate file size and type
            $typeConfig = $this->allowedTypes[$fileType];
            if ($file->getSize() > $typeConfig['max_size'] * 1024) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FILE_TOO_LARGE',
                        'message' => "File size exceeds maximum allowed size of {$typeConfig['max_size']}KB.",
                        'request_id' => $request->header('X-Request-ID'),
                        'timestamp' => now()->toISOString(),
                    ]
                ], 422);
            }

            // Process and store the file
            $storedFile = $this->processAndStoreFile($file, $fileType, $serviceRequest->id);

            // Create attachment record
            $attachment = Attachment::create([
                'service_request_id' => $serviceRequest->id,
                'filename' => $storedFile['filename'],
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $storedFile['path'],
                'file_type' => $fileType,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => auth()->id(),
            ]);

            // Update service request attachments array
            $this->updateServiceRequestAttachments($serviceRequest, $attachment);

            Log::info('File uploaded successfully', [
                'attachment_id' => $attachment->id,
                'service_request_id' => $serviceRequest->id,
                'filename' => $attachment->filename,
                'user_id' => auth()->id(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'original_filename' => $attachment->original_filename,
                    'file_type' => $attachment->file_type,
                    'file_size' => $attachment->file_size,
                    'download_url' => $this->getDownloadUrl($attachment),
                    'created_at' => $attachment->created_at->toISOString(),
                ],
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'service_request_id' => $request->service_request_id,
                'user_id' => auth()->id(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPLOAD_FAILED',
                    'message' => 'File upload failed. Please try again.',
                    'request_id' => $request->header('X-Request-ID'),
                    'timestamp' => now()->toISOString(),
                ]
            ], 500);
        }
    }

    /**
     * Delete a file attachment.
     */
    public function delete(DeleteFileRequest $request, Attachment $attachment): JsonResponse
    {
        try {
            // Authorization check
            $serviceRequest = ServiceRequest::findOrFail($attachment->service_request_id);
            $this->authorize('update', $serviceRequest);

            // Check if user can delete this attachment
            if ($attachment->uploaded_by !== auth()->id() && !auth()->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'You can only delete your own uploaded files.',
                        'request_id' => $request->header('X-Request-ID'),
                        'timestamp' => now()->toISOString(),
                    ]
                ], 403);
            }

            // Delete file from storage
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // Remove from service request attachments array
            $this->removeFromServiceRequestAttachments($serviceRequest, $attachment);

            // Delete attachment record
            $attachment->delete();

            Log::info('File deleted successfully', [
                'attachment_id' => $attachment->id,
                'service_request_id' => $serviceRequest->id,
                'user_id' => auth()->id(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully.',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'error' => $e->getMessage(),
                'attachment_id' => $attachment->id,
                'user_id' => auth()->id(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_FAILED',
                    'message' => 'File deletion failed. Please try again.',
                    'request_id' => $request->header('X-Request-ID'),
                    'timestamp' => now()->toISOString(),
                ]
            ], 500);
        }
    }

    /**
     * Download a file attachment.
     */
    public function download(Request $request, Attachment $attachment)
    {
        try {
            $serviceRequest = ServiceRequest::findOrFail($attachment->service_request_id);
            
            // Authorization check - user must have access to the service request
            $this->authorize('view', $serviceRequest);

            $filePath = storage_path('app/public/' . $attachment->file_path);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FILE_NOT_FOUND',
                        'message' => 'The requested file was not found.',
                        'request_id' => $request->header('X-Request-ID'),
                        'timestamp' => now()->toISOString(),
                    ]
                ], 404);
            }

            Log::info('File downloaded', [
                'attachment_id' => $attachment->id,
                'service_request_id' => $serviceRequest->id,
                'user_id' => auth()->id(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->download($filePath, $attachment->original_filename, [
                'Content-Type' => $attachment->mime_type,
                'Cache-Control' => 'private, max-age=3600',
            ]);

        } catch (\Exception $e) {
            Log::error('File download failed', [
                'error' => $e->getMessage(),
                'attachment_id' => $attachment->id,
                'user_id' => auth()->id(),
                'request_id' => $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOWNLOAD_FAILED',
                    'message' => 'File download failed. Please try again.',
                    'request_id' => $request->header('X-Request-ID'),
                    'timestamp' => now()->toISOString(),
                ]
            ], 500);
        }
    }

    /**
     * Determine file type based on extension and MIME type.
     */
    protected function determineFileType($file): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        foreach ($this->allowedTypes as $type => $config) {
            if (in_array($extension, $config['extensions']) && in_array($mimeType, $config['mime_types'])) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Process and store file with security measures.
     */
    protected function processAndStoreFile($file, string $fileType, int $serviceRequestId): array
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $directory = "attachments/{$serviceRequestId}/{$fileType}s";
        $path = $directory . '/' . $filename;

        // Process images for security and optimization
        if ($fileType === 'image') {
            $image = Image::make($file->getRealPath());
            
            // Limit image dimensions to prevent memory issues
            $image->resize(2000, 2000, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Optimize image quality
            $image->encode($file->getClientOriginalExtension(), 85);
            
            Storage::disk('public')->put($path, $image->getEncoded());
        } else {
            // For documents, store as-is after virus scanning (if available)
            Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));
        }

        return [
            'filename' => $filename,
            'path' => $path,
        ];
    }

    /**
     * Update service request attachments array.
     */
    protected function updateServiceRequestAttachments(ServiceRequest $serviceRequest, Attachment $attachment): void
    {
        $attachments = $serviceRequest->attachments ?? [];
        $attachments[] = [
            'id' => $attachment->id,
            'filename' => $attachment->filename,
            'original_filename' => $attachment->original_filename,
            'file_type' => $attachment->file_type,
            'file_size' => $attachment->file_size,
        ];
        
        $serviceRequest->update(['attachments' => $attachments]);
    }

    /**
     * Remove attachment from service request attachments array.
     */
    protected function removeFromServiceRequestAttachments(ServiceRequest $serviceRequest, Attachment $attachment): void
    {
        $attachments = $serviceRequest->attachments ?? [];
        $attachments = array_filter($attachments, function ($att) use ($attachment) {
            return $att['id'] !== $attachment->id;
        });
        
        $serviceRequest->update(['attachments' => array_values($attachments)]);
    }

    /**
     * Generate download URL for attachment.
     */
    protected function getDownloadUrl(Attachment $attachment): string
    {
        return route('attachments.download', $attachment->id);
    }
}
