<?php

declare(strict_types=1);

namespace App\Application\Support\Dto;

/**
 * Cevabın gideceği YER, yalnız o kadarı — SUPPORT-REPLY-01.
 *
 * Kuyruk satırının tamamı (mesaj gövdesi, teslim kayıtları, durum) bir
 * cevap göndermek için gerekmez. Gereken altı alan burada durur ve depo
 * yalnız onları okur: yeni tablo, yeni sütun, yeni migration yok.
 */
final readonly class SupportReplyTarget
{
    public function __construct(
        public int $id,
        public string $reference,
        public string $email,
        public string $name,
        public string $subject,
        public ?string $locale,
    ) {}
}
