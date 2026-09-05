<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
    MARKA GÖRÜNÜMÜ ALANLARI DA DOLDURULABİLİR (FF-174).

    `primary_color`, `secondary_color` ve `skin_variant` bu listede YOKTU.
    `UpdateBrand` onları yazmaya çalışıyor, Eloquent sessizce düşürüyordu:
    istek 200 dönüyor, ekran "kaydedildi" diyor, restoranın seçtiği renk
    hiçbir yere gitmiyordu. Sessiz düşen bir yazma, hata veren bir yazmadan
    kötüdür — çünkü kimse aramaya başlamaz.
*/
#[Fillable(['workspace_id', 'name', 'slug', 'locale', 'timezone', 'currency', 'description', 'contact_email', 'contact_phone', 'primary_color', 'secondary_color', 'skin_variant'])]
final class Brand extends Model
{
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
