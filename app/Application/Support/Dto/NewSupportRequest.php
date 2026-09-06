<?php

declare(strict_types=1);

namespace App\Application\Support\Dto;

use App\Domain\Support\SupportChannel;

/**
 * Henüz kaydedilmemiş bir talep — kanalın kim olduğunu söylediği hâliyle.
 *
 * Konu her iki kanalda da ZORUNLUDUR: kamu formunda alan yok, o yüzden
 * çağıran (kontrolcü) mesajın ilk satırından türetir. Bu DTO türetmez;
 * "konu nereden gelir" kararı kanalın kararıdır, depo bunu bilmemeli.
 */
final class NewSupportRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $subject,
        public readonly string $message,
        public readonly SupportChannel $channel,
        public readonly ?string $locale,
        public readonly ?int $workspaceId,
        public readonly ?int $userId,
    ) {}
}
