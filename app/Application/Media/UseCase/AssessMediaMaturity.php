<?php

declare(strict_types=1);

namespace App\Application\Media\UseCase;

use App\Application\Media\Port\MediaEvidencePort;

/**
 * OLGUNLUK DEĞERLENDİRMESİ — kanonik kaynak
 * `docs/reference/panel-v3/MedyaModulu.dc.html`,
 * `data-screen-label="Olgunluk"`; seviye sözlüğü `docs/108` §6.7.
 *
 * ═══ NEDEN BİR TABLO DEĞİL, BİR HESAP ═══
 *
 * Kaynak her yeteneğin yanına bir seviye rozeti koyuyor. O rozeti elle
 * yazmak en kolay yol olurdu ve tam da bu yüzden yasak: bir ürünün kendi
 * olgunluğu hakkında yazdığı sayı, yazıldığı günün ruh hâlini ölçer.
 * Restoran sahibi "Dönüştürme · L4" okuduğunda ürünün bir sözünü duyar;
 * o sözün arkasında bu depoda GERÇEKTEN duran bir şey olmalı.
 *
 * O yüzden burada her basamak KANIT REFERANSLARINA bağlıdır ve
 * referanslar çalışma anında çözülür (`MediaEvidencePort`):
 *
 *   - `endpoint`    → yönlendiricide kayıtlı bir uç.
 *   - `requirement` → test paketinde geçen adlandırılmış gereksinim
 *                     kimliği (`MEDIA-INTAKE-SIZE-REJECT-01`).
 *   - `test`        → adı verilen test yöntemi.
 *
 * ═══ DÖRT DEĞİŞMEZ ═══
 *
 *   1. KANITSIZ BASAMAK GEÇİLMEZ. Boş kanıt listesi bir puan değil, bir
 *      eksikliktir; ekran onu "kanıt yok" diye yazar. Bu, en çok
 *      kullanılan kural: bu depoda birçok yetenek çalışıyor ve testli ama
 *      hiçbir sayaç üretmiyor — onlar L2'de durur.
 *   2. BASAMAKLAR ARDIŞIKTIR. Seviye, L1'den başlayarak KESİNTİSİZ geçilen
 *      basamak sayısıdır. "Ölçülüyor ama güvenli değil" bir olgunluk
 *      derecesi değil, bir çelişkidir. Üst basamağın kanıtı yine de
 *      dürüstçe bildirilir; yalnız seviyeye sayılmaz.
 *   3. DENETLENEMEYEN KANIT GEÇMİŞ SAYILMAZ. `null` cevabı "hayır" değil
 *      "buradan bakınca göremiyorum"dur ve seviyeyi orada durdurur.
 *   4. SEVİYE, ÜRÜNÜN KENDİ İDDİASIDIR. Uç bunu `selfAssessed` ile açıkça
 *      söyler; ekran da yazar. Bağımsız bir denetim raporu değildir.
 *
 * ═══ NE ÇİZİLMEDİ ═══
 *
 * Kaynağın olgunluk listesi bu deponun yetenek listesi DEĞİLDİR. Burada
 * yalnız bu depoda GERÇEKTEN olan yetenekler sayılır; olmayan bir yeteneği
 * "L0" diye satır yapmak, yapılacaklar listesini bir olgunluk ölçüsü gibi
 * göstermek olurdu.
 */
final class AssessMediaMaturity
{
    /** Kaynağın seviye ölçeği (`docs/108` §6.7): L0…L4. */
    public const int MAX_LEVEL = 4;

    public function __construct(private readonly MediaEvidencePort $evidence) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(): array
    {
        $assessed = [];

        foreach (self::catalogue() as $key => $rungs) {
            $resolved = [];
            $level = 0;
            $stillClimbing = true;

            for ($index = 0; $index < self::MAX_LEVEL; $index++) {
                $rung = $this->resolveRung($index + 1, $rungs[$index] ?? []);
                $resolved[] = $rung;

                if ($stillClimbing && $rung['state'] === 'met') {
                    $level = $index + 1;

                    continue;
                }

                // İlk düşen basamaktan sonrası artık seviyeye sayılmaz.
                $stillClimbing = false;
            }

            $assessed[] = ['key' => $key, 'level' => $level, 'rungs' => $resolved];
        }

        return $assessed;
    }

    /**
     * Bir basamağın durumu, KANITLARININ toplamıdır.
     *
     * Sıra önemlidir: bir kanıt bile yoksa basamak `unmet`'tir — çünkü
     * kanıtsız puan yasak. Bulunamayan bir kanıt varsa `unmet`; hepsi
     * bulunduysa `met`; kalanı denetlenemiyorsa `unverifiable`.
     *
     * @param  array<int, array{kind: string, ref: string}>  $declared
     * @return array<string, mixed>
     */
    private function resolveRung(int $level, array $declared): array
    {
        $evidence = [];
        $absent = false;
        $unverifiable = false;

        foreach ($declared as $item) {
            $state = $this->resolve($item);

            if ($state === 'absent') {
                $absent = true;
            }

            if ($state === 'unverifiable') {
                $unverifiable = true;
            }

            $evidence[] = ['kind' => $item['kind'], 'ref' => $item['ref'], 'state' => $state];
        }

        $state = match (true) {
            $evidence === [] => 'unmet',
            $absent => 'unmet',
            $unverifiable => 'unverifiable',
            default => 'met',
        };

        return ['level' => $level, 'state' => $state, 'evidence' => $evidence];
    }

    /**
     * @param  array{kind: string, ref: string}  $item
     * @return 'found'|'absent'|'unverifiable'
     */
    private function resolve(array $item): string
    {
        $answer = match ($item['kind']) {
            'endpoint' => $this->resolveEndpoint($item['ref']),
            'requirement' => $this->evidence->hasRequirement($item['ref']),
            'test' => $this->resolveTest($item['ref']),
            // Tanınmayan bir kanıt türü "var" sayılmaz.
            default => false,
        };

        return match ($answer) {
            true => 'found',
            false => 'absent',
            default => 'unverifiable',
        };
    }

    /** Referans biçimi: `POST api/workspaces/{workspace}/media`. */
    private function resolveEndpoint(string $ref): bool
    {
        [$method, $uri] = array_pad(explode(' ', $ref, 2), 2, '');

        return $uri !== '' && $this->evidence->hasEndpoint($method, $uri);
    }

    /** Referans biçimi: `MediaViewerTest::test_...`. */
    private function resolveTest(string $ref): ?bool
    {
        [$class, $method] = array_pad(explode('::', $ref, 2), 2, '');

        if ($method === '') {
            return false;
        }

        return $this->evidence->hasTestMethod($class, $method);
    }

    /**
     * YETENEK KATALOĞU — dört basamak, her basamak kendi kanıtıyla.
     *
     * Bir basamağın kanıt listesi BOŞSA, o yetenek için bu depoda
     * gösterilecek bir şey yok demektir ve satır orada durur. Boşluklar
     * bilerek doldurulmadı: sayacı olmayan bir yeteneğe "ölçülüyor" demek,
     * ekranın tek işini (dürüstlük) bozardı.
     *
     * @return array<string, array<int, array<int, array{kind: string, ref: string}>>>
     */
    private static function catalogue(): array
    {
        return [
            /*
                YÜKLEME. Ucu var, sınır ve sahtecilik reddi testli, denetim
                izine yazıyor, başarısız işi yeniden denenebiliyor ve sebebi
                kullanıcıya yazılıyor.
            */
            'intake' => [
                [['kind' => 'endpoint', 'ref' => 'POST api/workspaces/{workspace}/media']],
                [
                    ['kind' => 'requirement', 'ref' => 'MEDIA-INTAKE-SIZE-REJECT-01'],
                    ['kind' => 'requirement', 'ref' => 'MEDIA-INTAKE-MIME-SPOOF-REJECT-01'],
                ],
                [['kind' => 'requirement', 'ref' => 'MEDIA-AUDIT-WRITE-01']],
                [
                    ['kind' => 'endpoint', 'ref' => 'POST api/workspaces/{workspace}/media/{media}/reprocess'],
                    ['kind' => 'requirement', 'ref' => 'MEDIA-FAILURE-VISIBLE-01'],
                ],
            ],

            /*
                VİRÜS TARAMASI. Çalışıyor ve enfekte dosyanın reddi testli —
                ama bu depoda taramanın bir SAYACI yok: kaç dosya tarandı,
                kaçı karantinada kaldı, hiçbir yerde sayılmıyor. L3 ve L4
                bilerek boş; sahibin gördüğü şey "L2" ve sebebi.
            */
            'scan' => [
                [['kind' => 'requirement', 'ref' => 'MEDIA-ACCEPT-CLEAN-01']],
                [
                    ['kind' => 'requirement', 'ref' => 'MEDIA-ACCEPT-INFECTED-01'],
                    ['kind' => 'requirement', 'ref' => 'MEDIA-SCAN-REJECTED-NO-REQUEUE-01'],
                ],
                [],
                [],
            ],

            /*
                TÜREV ÜRETİMİ (boyut motoru). Kuyruk gerçek sayı üretiyor ve
                uydurma ilerleme çubuğu çizmiyor; başarısız iş yeniden
                denenebiliyor.
            */
            'derivatives' => [
                [['kind' => 'endpoint', 'ref' => 'GET api/workspaces/{workspace}/media/derivative-rules']],
                [
                    ['kind' => 'requirement', 'ref' => 'MEDIA-DERIVATIVE-HONESTY-02'],
                    ['kind' => 'requirement', 'ref' => 'MEDIA-NO-UPSCALE-01'],
                ],
                [
                    ['kind' => 'endpoint', 'ref' => 'GET api/workspaces/{workspace}/media/jobs'],
                    ['kind' => 'requirement', 'ref' => 'MEDIA-QUEUE-COUNTS-02'],
                ],
                [
                    ['kind' => 'endpoint', 'ref' => 'POST api/workspaces/{workspace}/media/{media}/reprocess'],
                    ['kind' => 'requirement', 'ref' => 'MEDIA-QUEUE-NO-FAKE-PROGRESS-03'],
                ],
            ],

            /*
                BİÇİM DÖNÜŞTÜRME. Kazanç TARTILMIŞ sayıdan geliyor
                (uydurma yüzde değil), desteklenmeyen hedef reddediliyor ve
                kendi hattını kurmak yerine yeniden üretim yolunu kullanıyor.
            */
            'convert' => [
                [['kind' => 'endpoint', 'ref' => 'GET api/workspaces/{workspace}/media/conversion-targets']],
                [
                    ['kind' => 'requirement', 'ref' => 'MEDIA-CONVERT-UNSUPPORTED-REFUSED-06'],
                    ['kind' => 'requirement', 'ref' => 'MEDIA-CONVERT-LIMIT-07'],
                ],
                [['kind' => 'requirement', 'ref' => 'MEDIA-CONVERT-MEASURED-03']],
                [
                    ['kind' => 'requirement', 'ref' => 'MEDIA-CONVERT-REUSES-REPROCESS-04'],
                    ['kind' => 'requirement', 'ref' => 'MEDIA-CONVERT-HONESTY-02'],
                ],
            ],

            /*
                GÖRÜNTÜLE. Açılamayan tür "açılmıyor" diye söyleniyor ve
                taranmamış dosya hiç açılmıyor — ama kaç kez ne açıldığı
                HİÇ sayılmıyor. L3 boş; bu, ekranın kendi hakkında söylediği
                en dürüst cümle.
            */
            'viewer' => [
                [['kind' => 'endpoint', 'ref' => 'GET api/workspaces/{workspace}/media/{media}/viewer']],
                [
                    ['kind' => 'test', 'ref' => 'MediaViewerTest::test_a_type_the_panel_cannot_open_is_said_so_instead_of_being_served'],
                    ['kind' => 'test', 'ref' => 'MediaViewerTest::test_a_file_that_has_not_passed_the_scan_is_not_opened_in_the_panel'],
                ],
                [],
                [],
            ],

            /*
                ÇÖP VE GERİ GETİRME. Silmek çöpe atar, süresi dolan kalıcı
                gider, çöp kırılımda AYRI sayılır — yani ölçülüyor. Kendini
                onaran bir yanı yok: L4 boş.
            */
            'trash' => [
                [['kind' => 'endpoint', 'ref' => 'POST api/workspaces/{workspace}/media/{media}/restore']],
                [
                    ['kind' => 'requirement', 'ref' => 'FAZ5-TRASH-IS-NOT-DELETE-01'],
                    ['kind' => 'requirement', 'ref' => 'FAZ5-PURGE-AFTER-RETENTION-01'],
                ],
                [
                    ['kind' => 'endpoint', 'ref' => 'GET api/workspaces/{workspace}/media/storage-breakdown'],
                    ['kind' => 'requirement', 'ref' => 'MEDIA-STORAGE-TRASH-SEPARATE-02'],
                ],
                [],
            ],

            /*
                TOPLU İŞLEM. Kuru çalışma sebepleriyle sayıyor, kalıcı silme
                yazılı onay istiyor, biten iş denetim izine yazıyor ve aynı
                iş anahtarı iki kez çalışmıyor — kopan bir bağlantıdan sonra
                yeniden denemek güvenli.
            */
            'bulk' => [
                [['kind' => 'endpoint', 'ref' => 'POST api/workspaces/{workspace}/media/bulk/run']],
                [
                    ['kind' => 'test', 'ref' => 'MediaBulkOperationTest::test_permanent_delete_requires_the_typed_word_and_changes_nothing_without_it'],
                    ['kind' => 'test', 'ref' => 'MediaBulkOperationTest::test_published_usage_is_a_real_skip_reason_for_destructive_actions'],
                ],
                [['kind' => 'test', 'ref' => 'MediaBulkOperationTest::test_a_finished_run_writes_a_real_audit_row']],
                [
                    ['kind' => 'test', 'ref' => 'MediaBulkOperationTest::test_the_same_operation_key_never_runs_twice'],
                    ['kind' => 'test', 'ref' => 'MediaBulkOperationTest::test_dry_run_counts_real_skips_with_reasons_and_touches_nothing'],
                ],
            ],

            /*
                KOTA. Sınır gerçekten uygulanıyor ve kırılım "yeri ne
                dolduruyor" sorusunu sayıyla cevaplıyor. Kendini onarmıyor:
                dolan kota kendiliğinden boşalmaz, L4 boş.
            */
            'quota' => [
                [['kind' => 'endpoint', 'ref' => 'GET api/workspaces/{workspace}/media/quota']],
                [['kind' => 'requirement', 'ref' => 'FAZ7-QUOTA-01']],
                [
                    ['kind' => 'endpoint', 'ref' => 'GET api/workspaces/{workspace}/media/storage-breakdown'],
                    ['kind' => 'requirement', 'ref' => 'MEDIA-STORAGE-TOTALS-05'],
                ],
                [],
            ],

            /*
                YÖNETİŞİM. Yetki gerçek, yasal saklama gerçek bir kilit ve
                denetim izi kim ne yaptı sorusunu cevaplıyor. Kendini onaran
                bir yanı yok: L4 boş.
            */
            'governance' => [
                [['kind' => 'endpoint', 'ref' => 'GET api/workspaces/{workspace}/media/governance']],
                [
                    ['kind' => 'test', 'ref' => 'MediaLegalHoldAndGovernanceTest::test_only_a_workspace_manager_can_place_a_legal_hold'],
                    ['kind' => 'test', 'ref' => 'MediaLegalHoldAndGovernanceTest::test_a_held_file_cannot_be_trashed_at_all'],
                ],
                [
                    ['kind' => 'endpoint', 'ref' => 'GET api/workspaces/{workspace}/media/audits'],
                    ['kind' => 'test', 'ref' => 'MediaLegalHoldAndGovernanceTest::test_the_audit_trail_merges_single_file_and_bulk_records'],
                ],
                [],
            ],
        ];
    }
}
