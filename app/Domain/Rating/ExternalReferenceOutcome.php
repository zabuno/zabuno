<?php

declare(strict_types=1);

namespace App\Domain\Rating;

/**
 * BİR KARAR DENEMESİNİN SONUCU — `docs/116` §5 D4 (P7).
 *
 * ═══ NEDEN İSTİSNA DEĞİL, DÖNÜŞ DEĞERİ ═══
 *
 * `IdentityAlreadyClaimed` bir hata değil, beklenen bir cevaptır: bir dış
 * kimliği yalnız BİR varlık onaylı taşıyabilir ve ikinci talip mutlaka
 * olacaktır ("Lezzet Sarayı" adında üç restoran vardır). İstisna olarak
 * fırlatsaydık, çağıran taraf onu bir arıza gibi ele alır ve sahibe
 * "bir şeyler ters gitti" derdi — oysa söylenmesi gereken şey bellidir.
 *
 * ═══ CEVAP KİMİ TAŞIDIĞINI SÖYLEMEZ ═══
 *
 * Bu değer, kimliği hangi kiracının onayladığını TAŞIMAZ ve taşımamalıdır.
 * Taşısaydı, herhangi bir sahip rastgele kimlikler deneyerek başka
 * işletmelerin hangi platformlara bağlı olduğunu haritalayabilirdi. Kiracı
 * sınırı yalnız okuma sorgularında değil, HATA MESAJINDA da geçerlidir.
 */
enum ExternalReferenceOutcome
{
    /** Karar yazıldı. */
    case Decided;

    /** Bu kiracıda böyle bir eşleme yok (ya da başkasınındır). */
    case NotFound;

    /** Bu dış kimlik başka bir varlık tarafından onaylı taşınıyor. */
    case IdentityAlreadyClaimed;

    /** Bu varlığın bu dış sistemde zaten onaylı başka bir kimliği var. */
    case SubjectAlreadyMapped;
}
