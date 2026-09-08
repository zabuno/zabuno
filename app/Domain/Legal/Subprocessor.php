<?php

declare(strict_types=1);

namespace App\Domain\Legal;

use InvalidArgumentException;

/**
 * Bir ALT İŞLEYEN — veri işleme sözleşmesinin (DPA) ekindeki tek satır.
 *
 * FF-228, `docs/107` Faz 3.2, `docs/140`.
 *
 * Dört olgu taşır ve dördü de bir zincirin hukukçusunun SORDUĞU şeydir:
 * kim (`name`), neyi yapıyor (`role`), hangi veriyi görüyor (`data`),
 * nerede (`location`). Beşinci bir alan — "sözleşme tarihi", "denetim
 * raporu" — bilerek YOK: bugün ölçülebilen bir olgu değil ve boş bir sütun
 * o belgenin var olduğunu ima ederdi.
 */
final readonly class Subprocessor
{
    public function __construct(
        public string $name,
        public string $role,
        public string $data,
        public string $location,
    ) {
        foreach (['name' => $name, 'role' => $role, 'data' => $data, 'location' => $location] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Subprocessor field \"{$field}\" cannot be empty.");
            }
        }
    }

    /**
     * DPA metnine giren tek cümle — belge bir tablo değil, düz metindir.
     *
     * Nokta `rtrim` ile tekilleştirilir: bazı konum değerleri kendi
     * cümlesini nokta ile bitirir ("…does not measure where the provider
     * stores data."), bazıları bitirmez ("Karlsruhe, Germany"). İkisini de
     * körü körüne noktalamak, bir sözleşme metninde ".." bırakırdı.
     */
    public function sentence(string $locale = 'en'): string
    {
        if ($locale === 'tr') {
            return $this->name.' — '.$this->role.' Görülen veriler: '.$this->data.' İşleme konumu: '.rtrim($this->location, '.').'.';
        }

        return $this->name.' — '.$this->role.' Data seen: '.$this->data.' Processing location: '.rtrim($this->location, '.').'.';
    }
}
