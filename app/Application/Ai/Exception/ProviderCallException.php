<?php

declare(strict_types=1);

namespace App\Application\Ai\Exception;

use RuntimeException;

/**
 * Gerçek bir sağlayıcı çağrısı başarısız oldu.
 *
 * Bu, "yetenek kapalı" (503, `AiAvailability`) ile aynı şey DEĞİLDİR: burada
 * yetenek açıktı, çağrı yapıldı ama sağlayıcı hata verdi ya da anlaşılmaz bir
 * cevap döndü. Sebep taşınır (ağ / http-500 / anlaşılmaz), ama sağlayıcının
 * ham hata metni ya da anahtarı BURAYA konmaz — dışa dönük mesaj sızdırmaz.
 */
final class ProviderCallException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $reason,
    ) {
        parent::__construct("AI sağlayıcı çağrısı başarısız: {$provider} ({$reason})");
    }
}
