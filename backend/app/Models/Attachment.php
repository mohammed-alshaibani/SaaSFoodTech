<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'service_request_id',
        'filename',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'service_request_id' => 'integer',
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the service request that owns the attachment.
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * Get the user who uploaded the attachment.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the file size in human readable format.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the file extension.
     */
    public function getFileExtensionAttribute(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    /**
     * Check if the file is an image.
     */
    public function isImage(): bool
    {
        return $this->file_type === 'image';
    }

    /**
     * Check if the file is a document.
     */
    public function isDocument(): bool
    {
        return $this->file_type === 'document';
    }

    /**
     * Get the public URL for the file.
     */
    public function getPublicUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Scope to get only image attachments.
     */
    public function scopeImages($query)
    {
        return $query->where('file_type', 'image');
    }

    /**
     * Scope to get only document attachments.
     */
    public function scopeDocuments($query)
    {
        return $query->where('file_type', 'document');
    }

    /**
     * Scope to get attachments uploaded by a specific user.
     */
    public function scopeUploadedBy($query, $userId)
    {
        return $query->where('uploaded_by', $userId);
    }
}
