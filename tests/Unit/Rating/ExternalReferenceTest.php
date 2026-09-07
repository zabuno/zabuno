<?php

declare(strict_types=1);

namespace Tests\Unit\Rating;

use App\Domain\Rating\ExternalMatchConfidence;
use App\Domain\Rating\ExternalMatchedBy;
use App\Domain\Rating\ExternalReference;
use App\Domain\Rating\ExternalReferenceDecision;
use App\Domain\Rating\ExternalSystem;
use App\Domain\Rating\RatingSource;
use App\Domain\Rating\RatingSubject;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * EŞLEMENİN KENDİ YASASI — `docs/116` §1 Ö4 / §5 D4 (P7).
 *
 * Bu testler veritabanına inmez, çünkü sınadıkları şey bir sorgu değil bir
 * KURALDIR: "otomatik eşleme bir öneridir". Kural tipin içinde yaşamazsa,
 * her yeni okuma yolu onu yeniden hatırlamak zorunda kalır — ve bir gün
 * biri hatırlamaz.
 */
final class ExternalReferenceTest extends TestCase
{
    public function test_no_confidence_band_stands_in_for_the_owners_confirmation(): void
    {
        foreach (ExternalMatchConfidence::cases() as $confidence) {
            $suggestion = $this->reference($confidence, null);

            $this->assertFalse(
                $suggestion->mayCarryExternalDataToGuest(),
                $confidence->value.' güven düzeyi sahibin onayının yerine geçemez.'
            );
            $this->assertTrue($suggestion->awaitsOwnerDecision());
        }
    }

    public function test_only_the_owners_confirmation_opens_the_guest_facing_door(): void
    {
        $confirmed = $this->reference(
            ExternalMatchConfidence::Low,
            ExternalReferenceDecision::Confirmed,
        );

        $this->assertTrue($confirmed->mayCarryExternalDataToGuest());
        $this->assertFalse($confirmed->awaitsOwnerDecision());
    }

    public function test_a_rejected_mapping_stays_shut_forever(): void
    {
        $rejected = $this->reference(
            ExternalMatchConfidence::High,
            ExternalReferenceDecision::Rejected,
        );

        $this->assertFalse($rejected->mayCarryExternalDataToGuest());
        // "Bu benim restoranım değil" bir bekleme değil, verilmiş bir
        // cevaptır: yeniden sorulacak bir soru olarak listeye dönmez.
        $this->assertFalse($rejected->awaitsOwnerDecision());
    }

    public function test_every_external_system_names_its_own_signal_source(): void
    {
        $sources = [];

        foreach (ExternalSystem::cases() as $system) {
            $source = $system->ratingSource();

            $this->assertNotSame(
                RatingSource::GuestScan,
                $source,
                'Dış bir sistem masadan gelen oyun kaynağını kullanamaz.'
            );
            $sources[] = $source->value;
        }

        $this->assertSame(
            $sources,
            array_values(array_unique($sources)),
            'İki dış sistem aynı sinyal kaynağını paylaşamaz; paylaşsalardı ağırlıkları ayrışmazdı.'
        );
    }

    private function reference(
        ExternalMatchConfidence $confidence,
        ?ExternalReferenceDecision $decision,
    ): ExternalReference {
        return new ExternalReference(
            id: 1,
            workspaceId: 42,
            subjectType: RatingSubject::Location,
            subjectId: 7,
            system: ExternalSystem::Google,
            externalId: 'places/ChIJexample',
            externalLabel: 'Lezzet Sarayı',
            confidence: $confidence,
            matchedBy: ExternalMatchedBy::Automatic,
            matchedAt: new DateTimeImmutable('2026-09-09T09:00:00+00:00'),
            decision: $decision,
            decidedByUserId: $decision === null ? null : 3,
            decidedAt: $decision === null ? null : new DateTimeImmutable('2026-09-09T10:00:00+00:00'),
        );
    }
}
