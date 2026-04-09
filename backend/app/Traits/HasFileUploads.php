<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Attachment;

trait HasFileUploads
{
    /**
     * Upload an array of files and attach them to the model.
     * 
     * @param UploadedFile[] $files
     * @param string $path
     * @return array Array of created Attachment models
     */
    public function addAttachments(array $files, string $path = 'attachments'): array
    {
        $uploadedAttachments = [];

        foreach ($files as $file) {
            $uploadedAttachments[] = $this->processAndAttachFile($file, $path);
        }

        return $uploadedAttachments;
    }

    /**
     * Process a single file, store it, and create an attachment record.
     *
     * @param UploadedFile $file
     * @param string $path
     * @return Attachment
     */
    protected function processAndAttachFile(UploadedFile $file, string $path = 'attachments'): Attachment
    {
        // Store the file in the specified disk (e.g., public disk)
        $filePath = $file->store($path, 'public');

        // Create the polymorphic attachment record associated with this model
        return $this->attachments()->create([
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }
}
