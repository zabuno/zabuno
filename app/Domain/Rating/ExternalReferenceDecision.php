<?php

declare(strict_types=1);

namespace App\Domain\Rating;

/**
 * SAHİBİN CEVABI — `docs/116` §5 D4 (P7).
 *
 * ═══ NEDEN ÜÇÜNCÜ BİR DEĞER YOK ═══
 *
 * "Beklemede" bu enumda bir değer DEĞİLDİR; sütunun boş olmasıdır. Sebep
 * veritabanı düzeyinde: tek doğru eşleme kısıtları KISMÎ indekslerdir ve
 * kapsamlarını `owner_decision = 'confirmed'` koşuluyla çizerler. Karar
 * verilmemiş satırların ayrı bir değeri olsaydı da aynı işi görürdü — ama
 * o zaman "karar yok" iki farklı biçimde (boş sütun ve `pending` değeri)
 * yazılabilir olurdu ve ikisi bir gün ayrışırdı.
 */
enum ExternalReferenceDecision: string
{
    /** Bkz. `ExternalSystem::MAX_VALUE_LENGTH` gerekçesi. */
    public const MAX_VALUE_LENGTH = 16;

    /** "Evet, burası benim restoranım." Dış verinin kapısı budur. */
    case Confirmed = 'confirmed';

    /** "Hayır, burası benim restoranım değil." Geri sorulmaz. */
    case Rejected = 'rejected';
}
