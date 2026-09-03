<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workspace_id', 'disk_path', 'original_name', 'mime_type', 'size_bytes', 'original_checksum_sha256', 'alt_text', 'slot', 'idempotency_key', 'status'])]
final class MediaAsset extends Model
{
    use SoftDeletes;

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
