<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Content\PagePublicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kurumsal sitenin sayfa kütüğü kaydı — FF-117.
 *
 * @property string $page_key
 * @property string $locale
 * @property string $canonical_path
 * @property string $publication_status
 */
final class ContentPage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'page_key', 'locale', 'canonical_path', 'content_type', 'template_key',
        'parent_key', 'title', 'priority', 'publication_status',
        'is_template', 'is_external', 'was_ever_published',
        'published_at', 'unpublished_at',
    ];

    protected $casts = [
        'is_template' => 'boolean',
        'is_external' => 'boolean',
        'was_ever_published' => 'boolean',
        'published_at' => 'datetime',
        'unpublished_at' => 'datetime',
    ];

    public function status(): PagePublicationStatus
    {
        return PagePublicationStatus::from($this->publication_status);
    }
}
