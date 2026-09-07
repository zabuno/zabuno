<?php

declare(strict_types=1);

namespace App\Application\Rating\UseCase;

use App\Application\Rating\Port\ExternalReferenceQueryPort;
use App\Application\Rating\Port\ExternalReferenceRepositoryPort;
use App\Domain\Rating\ExternalReferenceOutcome;
use DateTimeImmutable;
use Illuminate\Database\QueryException;

/**
 * SAHİBİN EŞLEME KARARI — `docs/116` §5 D4 (P7).
 *
 * ═══ BU SINIFIN TEK İŞİ: BİR ONAY GERÇEKTEN VERİLEBİLİR Mİ ═══
 *
 * Otomatik eşleme bir ÖNERİdir; sahip onaylayana kadar doğrulanmış
 * sayılmaz. Ama onayın kendisi de her zaman verilebilir değildir: bir dış
 * kimliği yalnız TEK bir varlık taşıyabilir ve bir varlık aynı dış sistemde
 * TEK bir kimliğe bağlanabilir. Bu iki durum burada, sahibe söylenebilecek
 * bir cevaba çevrilir.
 *
 * ═══ ÖNCE KONTROL, SONRA KISIT — İKİSİ DE GEREKLİ ═══
 *
 * Kontrol tek başına yetmez: aynı anda gelen iki onay isteği "önce oku,
 * sonra yaz" arasında birbirini görmez ve tek bir Google kaydı iki
 * restorana birden bağlanırdı. Kısıt tek başına da yetmez: sahibin gördüğü
 * şey ham bir veritabanı hatası olurdu. Kontrol CEVABI üretir, kısıt
 * DOĞRULUĞU garanti eder.
 *
 * ═══ HİÇBİR AĞ ÇAĞRISI YOK ═══
 *
 * Onaylamak, dış sistemden veri çekmek DEĞİLDİR. Çekme işi P8'in
 * adaptörünündür ve bu depoda henüz yoktur (`docs/116` §5 D2).
 */
final class DecideExternalReference
{
    public function __construct(
        private readonly ExternalReferenceRepositoryPort $references,
        private readonly ExternalReferenceQueryPort $query,
    ) {}

    public function confirm(
        int $workspaceId,
        int $referenceId,
        int $ownerUserId,
        DateTimeImmutable $now,
    ): ExternalReferenceOutcome {
        $reference = $this->query->find($workspaceId, $referenceId);

        if ($reference === null || ! $reference->awaitsOwnerDecision()) {
            /*
                KARARSIZ OLMAYAN SATIR DA "BULUNAMADI" DÖNER.

                Ayrı bir cevap vermek, başka bir kiracının satırının VAR
                olduğunu ele verirdi: "bulunamadı" ile "zaten karar
                verilmiş" arasındaki fark, deneyerek komşunun eşlemelerini
                haritalamanın en ucuz yoludur.
            */
            return ExternalReferenceOutcome::NotFound;
        }

        if ($this->query->identityIsConfirmedElsewhere(
            $reference->system,
            $reference->externalId,
            $reference->id,
        )) {
            return ExternalReferenceOutcome::IdentityAlreadyClaimed;
        }

        if ($this->query->subjectIsConfirmedIn(
            $workspaceId,
            $reference->subjectType,
            $reference->subjectId,
            $reference->system,
            $reference->id,
        )) {
            return ExternalReferenceOutcome::SubjectAlreadyMapped;
        }

        try {
            $written = $this->references->confirm($workspaceId, $referenceId, $ownerUserId, $now);
        } catch (QueryException) {
            /*
                KISIT SON SÖZÜ SÖYLEDİ.

                Buraya yalnız iki isteğin aynı anda geldiği durumda düşülür:
                kontrol ikisine de "boş" dedi, indeks ikincisini reddetti.
                Sahibe söylenen şey yine aynı cümledir — ve yine kimin
                taşıdığını söylemez.
            */
            return ExternalReferenceOutcome::IdentityAlreadyClaimed;
        }

        return $written ? ExternalReferenceOutcome::Decided : ExternalReferenceOutcome::NotFound;
    }

    public function reject(
        int $workspaceId,
        int $referenceId,
        int $ownerUserId,
        DateTimeImmutable $now,
    ): ExternalReferenceOutcome {
        /*
            RET HİÇBİR KISITA TAKILMAZ.

            "Bu benim restoranım değil" demek, hiçbir kimliği sahiplenmez —
            dolayısıyla önce kontrol edilecek bir çatışma da yoktur. Sahibin
            hayır deme hakkı, başka hiçbir şeyin durumuna bağlı değildir.
        */
        return $this->references->reject($workspaceId, $referenceId, $ownerUserId, $now)
            ? ExternalReferenceOutcome::Decided
            : ExternalReferenceOutcome::NotFound;
    }
}
