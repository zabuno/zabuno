<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * `media_folders` satırının Eloquent karşılığı.
 *
 * Model, deponun kendi yanında (Infrastructure) durur: klasör bugün yalnız
 * medya deposunun içinden okunuyor ve paylaşılan bir model dizinine
 * konulsaydı, ilerideki her modül onu doğrudan sorgulamaya davet edilmiş
 * olurdu — kiracı sınırını her çağıranın ayrı ayrı hatırlaması gereken
 * hâle gelirdi. Buradaki tek okuyucu `EloquentMediaFolderRepository`'dir
 * ve o da `workspace_id` olmadan sorgu kurmaz.
 */
#[Fillable(['workspace_id', 'parent_id', 'name', 'position'])]
final class MediaFolderRecord extends Model
{
    protected $table = 'media_folders';
}
