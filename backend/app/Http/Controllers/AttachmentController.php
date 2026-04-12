<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    /**
     * POST /api/attachments/upload
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB
            'service_request_id' => 'required|exists:service_requests,id',
        ]);

        $file = $request->file('file');
        $serviceRequest = ServiceRequest::findOrFail($request->service_request_id);

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('attachments', $filename, 'public');

        $attachment = $serviceRequest->attachments()->create([
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'file_type' => $this->getFileType($file->getClientOriginalExtension()),
            'file_size' => $file->getSize(),
            'path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'data' => array_merge($attachment->toArray(), [
                'download_url' => Storage::disk('public')->url($path),
            ]),
        ], 201);
    }

    private function getFileType(string $extension): string
    {
        return in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'document';
    }
}
