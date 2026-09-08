<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Application\Legal\Port\SubprocessorRegistryPort;
use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Legal\ServiceLevelCommitment;
use App\Domain\Legal\Subprocessor;
use App\Domain\Legal\SubprocessorInventory;
use App\Infrastructure\Content\Pages\ProductOverviewPage;
use Throwable;

/**
 * YATIRIMCI DOSYASI — sayfada yazan her olgunun TEK kaynağı (FF-251).
 *
 * ═══ NEDEN AYRI BİR SINIF ═══
 *
 * Sahibin isteği açıktı: *"yatırımcı ilişkileri, pitch deck vb. bilgiler
 * için sayfalar olmalı"* — ve aynı cümlede *"tabii ki uydurulmuş rakamla
 * değil"*.
 *
 * Bu ürün ürün-pazar uyumundan ÖNCEDİR. Müşteri sayısı, gelir, büyüme
 * oranı, pazar büyüklüğü — hiçbiri bu depoda ÖLÇÜLEMEZ. Ölçülemeyen bir
 * sayıyı bir yatırımcı sayfasına yazmak, ilk soruda çöken bir iddiadır ve
 * çöktüğü an yalnız o sayıyı değil sayfadaki her cümleyi götürür.
 *
 * Bu yüzden dört sayfa da metnini değil, OLGULARINI buradan alır ve bu sınıf
 * hiçbir şey uydurmaz: her alanı ürünün kendi kodundan okur.
 *
 *   · Zincir, parçalar, sınırlar → `HomeStory` (yani metin kataloğu) ve her
 *     birinin arkasındaki DOSYA → `ProductOverviewPage` envanteri.
 *   · Hizmet seviyesi taahhüdü → `config/sla.php`. Bugün DÖRT ALAN DA BOŞ ve
 *     sayfa bunu rakamla değil, boş anahtarların ADIYLA söyler.
 *   · Barındırma ve alt işleyenler → `MeasuredSubprocessors` (kasadan ve
 *     yapılandırmadan ölçülür, elle yazılmaz).
 *   · Kapılar → `scripts/` altında GERÇEKTEN duran dosyalar.
 *
 * ═══ TEST SAYISI BİLEREK YOK ═══
 *
 * İlk taslak "depoda kaç test var" diye sayıyordu. Ölçüldü ve VAZGEÇİLDİ:
 * `tests/` ve `docs/` üretim imajına GİRMİYOR (`.dockerignore`). Yani o sayı
 * yerel makinede 367, canlı sitede 0 olurdu — ve sıfır test iddia eden bir
 * yatırımcı sayfası, uydurulmuş bir sayıdan daha kötüdür: yanlış YÖNDE
 * yalan söyler ve kimse fark etmez.
 *
 * Onun yerine sayfanın ölçtüğü şey, imajda GERÇEKTEN duran iki şey:
 * iddiaların dayandığı dosyalar ve kapı betikleri. İkisi de `file_exists`
 * ile doğrulanır; doğrulanamayan bir satır sayfada "bu dağıtımda yok" diye
 * görünür, sessizce düşmez.
 *
 * ═══ RAKAMLARIN KAPISI ═══
 *
 * `measuredNumerals()` bu sınıfın ÜRETTİĞİ her dizedeki rakamları toplar.
 * `InvestorPagesContractTest` sayfadaki her rakamın bu kümede olduğunu
 * ölçer: yani bir gün biri katalog metnine "%40 büyüme" yazarsa test kırılır.
 * Kapının kurduğu şart tek cümledir — **bir sayı sayfaya ancak bu sınıftan
 * geçerek girebilir.**
 */
final class InvestorDossier
{
    /**
     * Ölçülen kapılar — `scripts/` altındaki dosyalar.
     *
     * Liste elle yazılıdır ama İDDİA değildir: her satır `file_exists` ile
     * doğrulanır ve olmayan bir kapı sayfada "yok" diye görünür. Silinen bir
     * kapının sayfada var görünmeye devam etmesi, tam olarak bu sayfanın
     * engellemeye çalıştığı şeydir.
     *
     * @var list<string>
     */
    public const GATES = [
        'scripts/scene-budget-gate',
        'scripts/scene-perf-gate',
        'scripts/scene-visual-gate',
        'scripts/mobile-ux-audit',
        'scripts/logical-direction-gate',
        'scripts/adaptive-bundle-gate',
        'scripts/module-graph-gate',
        'scripts/preview-truth',
        'scripts/speed-gate',
        'scripts/snapshot-gate',
    ];

    public function __construct(
        private readonly HomeStory $story,
        private readonly SubprocessorRegistryPort $subprocessors,
    ) {}

    /**
     * Sayfaların okuduğu tek yapı.
     *
     * @return array{
     *     chain: list<array{title: string, body: string, source: ?string, present: bool}>,
     *     parts: list<array{title: string, body: string, source: ?string, present: bool}>,
     *     limits: list<array{title: string, body: string, source: ?string, present: bool}>,
     *     counts: array{chain: int, parts: int, limits: int, sources: int, gates: int, subprocessors: int},
     *     gates: list<array{path: string, present: bool}>,
     *     commitment: array{committed: bool, missing: list<string>},
     *     subprocessors: list<array{name: string, role: string, data: string, location: string}>,
     *     vaultUnreadable: bool
     * }
     */
    public function facts(?string $locale = null): array
    {
        $chain = $this->join(HomeStory::CHAIN, BlockType::HowItWorks, $locale);
        $parts = $this->join(HomeStory::PARTS, BlockType::Capabilities, $locale);
        $limits = $this->join(HomeStory::LIMITS, BlockType::Limitations, $locale);

        $gates = $this->gates();
        $commitment = ServiceLevelCommitment::fromConfig();
        $subprocessors = $this->subprocessorRows($locale ?? 'en');

        $sources = 0;

        foreach ([$chain, $parts, $limits] as $rows) {
            foreach ($rows as $row) {
                if ($row['present']) {
                    $sources++;
                }
            }
        }

        return [
            'chain' => $chain,
            'parts' => $parts,
            'limits' => $limits,
            'counts' => [
                'chain' => count($chain),
                'parts' => count($parts),
                'limits' => count($limits),
                'sources' => $sources,
                'gates' => count(array_filter($gates, static fn (array $gate): bool => $gate['present'])),
                'subprocessors' => count($subprocessors),
            ],
            'gates' => $gates,
            'commitment' => [
                'committed' => $commitment->isComplete(),
                'missing' => $commitment->missing(),
            ],
            'subprocessors' => $subprocessors,
            'vaultUnreadable' => $this->vaultUnreadable($locale ?? 'en'),
        ];
    }

    /**
     * BU SINIFTAN ÇIKAN her rakam.
     *
     * Kapı (`INVESTOR-HONEST-01`) sayfadaki rakamların bu kümenin içinde
     * kalmasını şart koşar. Küme sayfanın kendi çıktısından türediği için
     * kapı bir sayı LİSTESİ tutmaz — tuttuğu şey bir KURAL: sayfaya giren
     * her rakam ölçülmüş bir olgudan gelmiş olmalı.
     *
     * @param  list<string>  $extra  Sayfanın çizdiği, dosyanın dışından gelen
     *                               ölçülmüş dizeler (bugün: plan kataloğunun
     *                               fiyatları, `PlanCatalogueSeeder`).
     * @return list<string>
     */
    public function measuredNumerals(?string $locale = null, array $extra = []): array
    {
        $facts = $this->facts($locale);
        $haystack = $extra;

        foreach ([$facts['chain'], $facts['parts'], $facts['limits']] as $rows) {
            foreach ($rows as $index => $row) {
                $haystack[] = $row['title'];
                $haystack[] = $row['body'];
                $haystack[] = (string) $row['source'];
                /*
                    SIRA NUMARASI da bir rakamdır ve listeden türer: deck
                    sayfası panelleri numaralandırıyor, zincir kartları da.
                    Numarayı kümeye elle yazmak, listenin uzunluğu değişince
                    ayrışan ikinci bir gerçek üretirdi.
                */
                $haystack[] = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            }
        }

        foreach ($facts['counts'] as $count) {
            $haystack[] = (string) $count;
        }

        foreach ($facts['gates'] as $gate) {
            $haystack[] = $gate['path'];
        }

        foreach ($facts['commitment']['missing'] as $key) {
            $haystack[] = $key;
        }

        foreach ($facts['subprocessors'] as $row) {
            $haystack[] = $row['name'].' '.$row['role'].' '.$row['data'].' '.$row['location'];
        }

        $numerals = [];

        foreach ($haystack as $value) {
            if (preg_match_all('/\d[\d.,]*/', $value, $matches) === false) {
                continue;
            }

            foreach ($matches[0] as $token) {
                $numerals[rtrim($token, '.,')] = true;
            }
        }

        /*
            ANAHTARLAR DİZEYE GERİ ÇEVRİLİR. PHP, "12" gibi sayısal bir dizi
            anahtarını sessizce tam sayıya çevirir; kapı ise `in_array(...,
            true)` ile KATI karşılaştırıyor ve o karşılaştırmada 12 ile "12"
            aynı değildir. Ölçülmüş bir sayıyı ölçülmemiş göstermek, kapının
            yapabileceği en kötü hatadır: gürültü üreten bir kapı kapatılır.
        */
        return array_map(static fn ($token): string => (string) $token, array_keys($numerals));
    }

    /**
     * Katalog metnini envanterin KANIT DOSYASIYLA birleştirir.
     *
     * Eşleştirme SIRAYA göredir ve bu güvenli: `HOME-REAL-07` iki listenin
     * aynı sırada, aynı terimlerle durduğunu zaten donduruyor. Sıra bozulursa
     * önce o kapı konuşur — burada sessiz bir yanlış eşleşme doğmaz, çünkü
     * eşleşmeyen satır kaynaksız kalır ve sayfa onu kaynaksız gösterir.
     *
     * @param  list<string>  $stems
     * @return list<array{title: string, body: string, source: ?string, present: bool}>
     */
    private function join(array $stems, BlockType $type, ?string $locale): array
    {
        $entries = $this->entries($type);
        $rows = [];

        foreach ($this->story->lists($locale)[$this->bucket($type)] as $index => $row) {
            $entry = $entries[$index] ?? null;
            $source = $entry?->source;

            $rows[] = [
                'title' => $row['title'],
                'body' => $row['body'],
                'source' => $source,
                /*
                    DOSYA GERÇEKTEN VAR MI. Bir kanıt adresi, işaret ettiği
                    dosya silindiğinde bir kanıt olmaktan çıkar ve sayfa onu
                    kanıt diye göstermeye devam ederse iddia dayanaksız kalır.
                */
                'present' => $source !== null && file_exists(base_path($source)),
            ];
        }

        return $rows;
    }

    /** @return list<BlockEntry> */
    private function entries(BlockType $type): array
    {
        $block = ProductOverviewPage::content()->block($type);

        return $block === null ? [] : array_values($block->entries);
    }

    private function bucket(BlockType $type): string
    {
        return match ($type) {
            BlockType::HowItWorks => 'chain',
            BlockType::Capabilities => 'parts',
            default => 'limits',
        };
    }

    /** @return list<array{path: string, present: bool}> */
    private function gates(): array
    {
        return array_map(
            static fn (string $path): array => [
                'path' => $path,
                'present' => file_exists(base_path($path)),
            ],
            self::GATES,
        );
    }

    /** @return list<array{name: string, role: string, data: string, location: string}> */
    private function subprocessorRows(string $locale): array
    {
        $rows = [];

        foreach ($this->inventory($locale)->active as $entry) {
            if (! $entry instanceof Subprocessor) {
                continue;
            }

            $rows[] = [
                'name' => $entry->name,
                'role' => $entry->role,
                'data' => $entry->data,
                'location' => $entry->location,
            ];
        }

        return $rows;
    }

    private function vaultUnreadable(string $locale): bool
    {
        return $this->inventory($locale)->vaultUnreadable;
    }

    private function inventory(string $locale): SubprocessorInventory
    {
        try {
            return $this->subprocessors->inventory($locale);
        } catch (Throwable) {
            /*
                KASA OKUNAMAZSA SAYFA ÖLMEZ ama SUSMAZ da.

                `MeasuredSubprocessors` kasayı okuyamadığında zaten boş liste
                değil bir BAYRAK döndürüyor ("bakamadık" ile "yok" aynı cümle
                değildir). Burada yakalanan şey daha kabası: veritabanının hiç
                cevap vermediği hâl. O hâlde de sayfa açılır, listenin
                okunamadığını söyler — ve boş bir liste "hiç alt işleyen yok"
                diye okunmaz.
            */
            return new SubprocessorInventory([], true);
        }
    }
}
