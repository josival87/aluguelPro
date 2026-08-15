<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaseDocument extends Model
{
    protected $fillable = [
        'lease_id', 'uploaded_by', 'category', 'original_name', 'mime_type',
        'size_bytes', 'checksum_sha256', 'description', 'document_base64',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFormattedSizeAttribute(): string
    {
        if ($this->size_bytes >= 1_048_576) {
            return number_format($this->size_bytes / 1_048_576, 1, ',', '.').' MB';
        }

        return number_format(max(1, $this->size_bytes / 1024), 1, ',', '.').' KB';
    }
}
