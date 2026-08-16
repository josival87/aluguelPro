<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyMedia extends Model
{
    public const DISPLAY_COLUMNS = ['id', 'property_id', 'mime_type', 'sort_order', 'created_at'];

    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
        'video/mp4',
        'application/mp4',
        'video/webm',
        'video/quicktime',
    ];

    public const MAX_ITEMS = 10;

    public const MAX_SIZE_KB = 51200;

    protected $table = 'property_media';

    protected $fillable = ['property_id', 'mime_type', 'media_base64', 'sort_order'];

    protected $hidden = ['media_base64'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function getIsVideoAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'video/') || $this->mime_type === 'application/mp4';
    }

    public static function normalizeMimeType(string $mimeType): string
    {
        return $mimeType === 'application/mp4' ? 'video/mp4' : $mimeType;
    }
}
