<?php

declare(strict_types=1);

namespace Tests\Feature\Rating;

use App\Application\Rating\Dto\ExternalReferenceDraft;
use App\Application\Rating\Port\ExternalReferenceQueryPort;
use App\Application\Rating\Port\ExternalReferenceRepositoryPort;
use App\Application\Rating\UseCase\DecideExternalReference;
use App\Domain\Rating\ExternalMatchConfidence;
use App\Domain\Rating\ExternalMatchedBy;
use App\Domain\Rating\ExternalReferenceDecision;
use App\Domain\Rating\ExternalReferenceOutcome;
use App\Domain\Rating\ExternalSystem;
use App\Domain\Rating\RatingSubject;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Rating\Concerns\BuildsRatingFixture;
use Tests\TestCase;

/**
 * DIŞ KİMLİK EŞLEMESİ — `docs/116` §1 Ö4 ve §5 D4 (P7). Ayrıntı: `docs/127`.
 *
 * ═══ NEDEN BU TESTLER VAR ═══
 *
 * "Lezzet Sarayı" adında üç restoran vardır. Eşleme tablosu olmadan dış
 * veri ancak isim benzerliğiyle bağlanır ve yanlış eşleme, BAŞKASININ
 * PUANINI bizim restoranımızda göstermektir. Bu dosya o cümleyi üç ayrı
 * yerden bağlar:
 *
 * 1. Onaylanmamış bir eşleme dış veriyi misafire taşıyamaz — güven düzeyi
 *    ne olursa olsun.
 * 2. Kiracı sınırı her sorgunun İÇİNDEDİR; başka bir kiracının onaylı
 *    eşlemesi bizim okumamızda görünmez.
 * 3. Aynı dış kimliğin iki varlığa (ya da bir varlığın aynı dış sistemde
 *    iki kimliğe) onaylı bağlanması VERİTABANI düzeyinde reddedilir —
 *    uygulama kontrolü tek başına yeterli değildir.
 *
 * Bu pakette HİÇBİR DIŞ AĞ ÇAĞRISI YOKTUR (P8 ayrı pakettir): burada
 * sınanan şey eşleme yüzeyidir, veri çekme değil.
 *
 * Requirement IDs: RATING-EXTERNAL-REF-01.
 */
final class ExternalReferenceMappingTest extends TestCase
{
    use BuildsRatingFixture;
    use RefreshDatabase;

    private const GOOGLE_ID = 'places/ChIJlezzet-sarayi-kadikoy';

    // --- Ö4 / D4: onay olmadan dış veri misafire ulaşamaz --------------------

    public function test_an_automatic_suggestion_never_reaches_the_guest_facing_read(): void
    {
        $scene = $this->ratingScene('esleme-oneri');

        foreach (ExternalMatchConfidence::cases() as $confidence) {
            $this->repository()->suggest($this->draft(
                $scene['workspaceId'],
                $scene['locationId'],
                self::GOOGLE_ID.'-'.$confidence->value,
                $confidence,
            ));
        }

        $confirmed = $this->references()->confirmedForSubject(
            $scene['workspaceId'],
            RatingSubject::Location,
            $scene['locationId'],
        );

        self::assertSame(
            [],
            $confirmed,
            'RATING-EXTERNAL-REF-01: otomatik eşleme bir ÖNERİdir; en yüksek güven düzeyi bile '
            .'sahibin onayının yerine geçemez. Geçseydi, "Lezzet Sarayı" adındaki üç restorandan '
            .'yanlış olanın puanı bizim menümüzde görünürdü.'
        );

        // Öneriler kaybolmaz: sahibin karar kutusunda dururlar.
        self::assertCount(
            count(ExternalMatchConfidence::cases()),
            $this->references()->pendingDecisionsForWorkspace($scene['workspaceId']),
        );
    }

    public function test_the_owners_confirmation_is_what_opens_the_door(): void
    {
        $scene = $this->ratingScene('esleme-onay');

        $id = $this->repository()->suggest($this->draft(
            $scene['workspaceId'],
            $scene['locationId'],
            self::GOOGLE_ID,
            ExternalMatchConfidence::Low,
        ));

        $outcome = $this->decide()->confirm(
            $scene['workspaceId'],
            $id,
            $scene['ownerId'],
            new DateTimeImmutable('2026-09-09T10:00:00+00:00'),
        );

        self::assertSame(ExternalReferenceOutcome::Decided, $outcome);

        $confirmed = $this->references()->confirmedForSubject(
            $scene['workspaceId'],
            RatingSubject::Location,
            $scene['locationId'],
        );

        self::assertCount(1, $confirmed);
        self::assertSame(self::GOOGLE_ID, $confirmed[0]->externalId);
        self::assertTrue($confirmed[0]->mayCarryExternalDataToGuest());
        // KİM eşledi ve KİM onayladı ayrı sorulardır: eşleyen otomatiktir,
        // onaylayan insandır.
        self::assertSame(ExternalMatchedBy::Automatic, $confirmed[0]->matchedBy);
        self::assertSame($scene['ownerId'], $confirmed[0]->decidedByUserId);
        // Öneri kutusu boşalır: karar verilmiş bir satır bekleyen soru değildir.
        self::assertSame([], $this->references()->pendingDecisionsForWorkspace($scene['workspaceId']));
    }

    public function test_the_owner_can_say_this_is_not_my_restaurant(): void
    {
        $scene = $this->ratingScene('esleme-ret');

        $id = $this->repository()->suggest($this->draft(
            $scene['workspaceId'],
            $scene['locationId'],
            self::GOOGLE_ID,
            ExternalMatchConfidence::High,
        ));

        $outcome = $this->decide()->reject(
            $scene['workspaceId'],
            $id,
            $scene['ownerId'],
            new DateTimeImmutable('2026-09-09T10:00:00+00:00'),
        );

        self::assertSame(ExternalReferenceOutcome::Decided, $outcome);
        self::assertSame([], $this->references()->confirmedForSubject(
            $scene['workspaceId'],
            RatingSubject::Location,
            $scene['locationId'],
        ));

        $reference = $this->references()->find($scene['workspaceId'], $id);
        self::assertNotNull($reference);
        self::assertSame(ExternalReferenceDecision::Rejected, $reference->decision);
    }

    // --- Kiracı sınırı sorgunun İÇİNDE --------------------------------------

    public function test_another_tenants_confirmed_mapping_is_invisible(): void
    {
        $ours = $this->ratingScene('esleme-biz');
        $theirs = $this->ratingScene('esleme-onlar');

        $theirId = $this->repository()->mapByOwner(
            $this->draft(
                $theirs['workspaceId'],
                $theirs['locationId'],
                self::GOOGLE_ID,
                ExternalMatchConfidence::High,
            ),
            $theirs['ownerId'],
            new DateTimeImmutable('2026-09-09T09:00:00+00:00'),
        );

        // Aynı şube kimliği iki kiracıda da olabilir; sorgu kiracıyı
        // görmezse komşunun eşlemesi bize görünür.
        self::assertSame([], $this->references()->confirmedForSubject(
            $ours['workspaceId'],
            RatingSubject::Location,
            $theirs['locationId'],
        ));
        self::assertSame([], $this->references()->pendingDecisionsForWorkspace($ours['workspaceId']));
        self::assertNull($this->references()->find($ours['workspaceId'], $theirId));

        // Komşunun satırına bizim kiracımızdan karar verilemez.
        self::assertSame(
            ExternalReferenceOutcome::NotFound,
            $this->decide()->reject(
                $ours['workspaceId'],
                $theirId,
                $ours['ownerId'],
                new DateTimeImmutable('2026-09-09T11:00:00+00:00'),
            ),
        );
        self::assertNotNull($this->references()->find($theirs['workspaceId'], $theirId));
    }

    public function test_an_identity_already_confirmed_elsewhere_is_refused_without_naming_the_holder(): void
    {
        $ours = $this->ratingScene('esleme-catisma-biz');
        $theirs = $this->ratingScene('esleme-catisma-onlar');

        $this->repository()->mapByOwner(
            $this->draft(
                $theirs['workspaceId'],
                $theirs['locationId'],
                self::GOOGLE_ID,
                ExternalMatchConfidence::High,
            ),
            $theirs['ownerId'],
            new DateTimeImmutable('2026-09-09T09:00:00+00:00'),
        );

        $ourSuggestion = $this->repository()->suggest($this->draft(
            $ours['workspaceId'],
            $ours['locationId'],
            self::GOOGLE_ID,
            ExternalMatchConfidence::High,
        ));

        self::assertSame(
            ExternalReferenceOutcome::IdentityAlreadyClaimed,
            $this->decide()->confirm(
                $ours['workspaceId'],
                $ourSuggestion,
                $ours['ownerId'],
                new DateTimeImmutable('2026-09-09T12:00:00+00:00'),
            ),
        );

        self::assertSame([], $this->references()->confirmedForSubject(
            $ours['workspaceId'],
            RatingSubject::Location,
            $ours['locationId'],
        ));
    }

    // --- Veritabanı düzeyinde tek doğru eşleme ------------------------------

    public function test_the_database_refuses_one_external_identity_confirmed_for_two_entities(): void
    {
        $scene = $this->ratingScene('esleme-cift-varlik', ['Kahve', 'Çay']);

        $this->insertRow($scene['workspaceId'], RatingSubject::Location, $scene['locationId'], self::GOOGLE_ID, ExternalReferenceDecision::Confirmed);

        $this->expectException(QueryException::class);

        // Aynı Google kimliği, aynı kiracıda başka bir varlığa onaylı
        // bağlanamaz: dış puan tek bir yere yazılır.
        $this->insertRow($scene['workspaceId'], RatingSubject::Product, $scene['products']['Kahve'], self::GOOGLE_ID, ExternalReferenceDecision::Confirmed);
    }

    public function test_the_database_refuses_one_entity_confirmed_to_two_identities_in_one_system(): void
    {
        $scene = $this->ratingScene('esleme-cift-kimlik');

        $this->insertRow($scene['workspaceId'], RatingSubject::Location, $scene['locationId'], self::GOOGLE_ID, ExternalReferenceDecision::Confirmed);

        $this->expectException(QueryException::class);

        $this->insertRow($scene['workspaceId'], RatingSubject::Location, $scene['locationId'], self::GOOGLE_ID.'-ikinci', ExternalReferenceDecision::Confirmed);
    }

    public function test_unconfirmed_suggestions_may_compete_for_the_same_entity(): void
    {
        $scene = $this->ratingScene('esleme-yarisan-oneri');

        $this->insertRow($scene['workspaceId'], RatingSubject::Location, $scene['locationId'], self::GOOGLE_ID.'-a', null);
        $this->insertRow($scene['workspaceId'], RatingSubject::Location, $scene['locationId'], self::GOOGLE_ID.'-b', null);

        // Kısıt ONAYLI satırlar üzerindedir: eşleştirici üç aday
        // önerebilmeli, sahip birini seçebilmelidir. Kısıt tüm satırlara
        // konsaydı "hangisi doğru?" sorusu hiç sorulamazdı.
        self::assertSame(2, DB::table('external_references')
            ->where('workspace_id', $scene['workspaceId'])
            ->whereNull('owner_decision')
            ->count());
    }

    public function test_a_rejected_pair_cannot_be_silently_proposed_again(): void
    {
        $scene = $this->ratingScene('esleme-tekrar-oneri');

        $this->insertRow($scene['workspaceId'], RatingSubject::Location, $scene['locationId'], self::GOOGLE_ID, ExternalReferenceDecision::Rejected);

        $this->expectException(QueryException::class);

        // Sahip "bu benim restoranım değil" dedi. Eşleştirici yarın aynı
        // çifti yeniden önerebilseydi, verilen cevap hiçbir şey ifade etmezdi.
        $this->insertRow($scene['workspaceId'], RatingSubject::Location, $scene['locationId'], self::GOOGLE_ID, null);
    }

    public function test_the_two_correctness_constraints_live_in_the_schema_not_in_a_comment(): void
    {
        $indexes = collect(Schema::getIndexes('external_references'))
            ->pluck('name')
            ->all();

        // İsimle sınıyoruz çünkü bu iki indeks KISMÎdir ve kısmî bir indeks
        // sessizce koşulsuza dönüşürse davranış testleri hâlâ geçerdi —
        // yalnız üç aday öneremez hâle gelirdik.
        self::assertContains('external_references_confirmed_identity_unique', $indexes);
        self::assertContains('external_references_confirmed_subject_unique', $indexes);
        self::assertContains('external_references_pair_unique', $indexes);
    }

    // --- Yardımcılar ---------------------------------------------------------

    private function draft(
        int $workspaceId,
        int $subjectId,
        string $externalId,
        ExternalMatchConfidence $confidence,
    ): ExternalReferenceDraft {
        return new ExternalReferenceDraft(
            workspaceId: $workspaceId,
            subjectType: RatingSubject::Location,
            subjectId: $subjectId,
            system: ExternalSystem::Google,
            externalId: $externalId,
            externalLabel: 'Lezzet Sarayı',
            confidence: $confidence,
            matchedAt: new DateTimeImmutable('2026-09-09T08:00:00+00:00'),
        );
    }

    private function insertRow(
        int $workspaceId,
        RatingSubject $subject,
        int $subjectId,
        string $externalId,
        ?ExternalReferenceDecision $decision,
    ): void {
        DB::table('external_references')->insert([
            'workspace_id' => $workspaceId,
            'subject_type' => $subject->value,
            'subject_id' => $subjectId,
            'external_system' => ExternalSystem::Google->value,
            'external_id' => $externalId,
            'external_label' => 'Lezzet Sarayı',
            'confidence' => ExternalMatchConfidence::High->value,
            'matched_by' => ExternalMatchedBy::Automatic->value,
            'matched_at' => now(),
            'owner_decision' => $decision?->value,
            'decided_by_user_id' => null,
            'decided_at' => $decision === null ? null : now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function repository(): ExternalReferenceRepositoryPort
    {
        return app(ExternalReferenceRepositoryPort::class);
    }

    private function references(): ExternalReferenceQueryPort
    {
        return app(ExternalReferenceQueryPort::class);
    }

    private function decide(): DecideExternalReference
    {
        return app(DecideExternalReference::class);
    }
}
