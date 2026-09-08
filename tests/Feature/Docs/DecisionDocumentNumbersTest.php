<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use Tests\TestCase;

/**
 * BELGE NUMARASI BİR ADRESTİR VE İKİ ŞEYİN AYNI ADRESİ OLAMAZ.
 *
 * ── Neden bu kapı var ────────────────────────────────────────────────────
 *
 * 2026-09-08'de üç paket aynı gün, birbirinden habersiz, aynı `main`
 * tabanından "bir sonraki boş numarayı" aldı ve üçü de `docs/149` oldu:
 * yatırımcı ilişkileri, dağıtım sırası ve masaüstü bileşenleri. Üçü de
 * yeşil CI'dan geçti, çünkü hiçbir kapı numaraya bakmıyordu.
 *
 * Bedeli bir dosya adı karmaşasından büyük: kodun içindeki `docs/149`
 * atıfları artık ÜÇ ayrı belgeyi işaret ediyordu ve hangisini kastettiği
 * yalnız o satırın bağlamından anlaşılıyordu. Bir karar belgesinin işi tam
 * olarak bunun tersidir — "bu neden böyle" sorusunun tek bir cevabı olsun
 * diye yazılır.
 *
 * ── Paralel çalışma varsayımı ────────────────────────────────────────────
 *
 * Bu depo aynı anda birden çok pakete açık. Numarayı elle seçmek, iki
 * paketin aynı anda aynı seçimi yapmasını mümkün kılar; kapı da bu yüzden
 * "dikkatli ol" demiyor, ÇAKIŞMAYI KIRIYOR. Çakışan paket, birleşmeden
 * önce numarasını değiştirir.
 */
final class DecisionDocumentNumbersTest extends TestCase
{
    /**
     * DEVRALINAN ÇAKIŞMALAR — sayıları DONDURULDU, affedilmedi.
     *
     * Kapı yazıldığında `docs/` altında dört çakışma daha vardı ve hepsi
     * aynı sebepten doğmuştu: aynı gün, paralel paketler, aynı taban.
     * Bunları burada ADIYLA saymak, sıfırdan büyük bir eşiği "geçti" diye
     * yuvarlamaktan farklıdır: liste kısalabilir, uzayamaz. Yeni bir
     * çakışma bu listede olmadığı için kapıyı kırar.
     *
     * Neden bugün düzeltilmedi: dört numaraya yapılan atıflar iki ayrı
     * belgeye dağılmış durumda (ör. `docs/124` hem yedek tatbikatını hem
     * yasal metinleri işaret ediyor) ve hangisinin kastedildiği ancak
     * satırın bağlamından anlaşılıyor. Seksen küsur atıfı otomatik
     * ayrıştırmayı denedim; 23'ü belirsiz kaldı. Yanlış ayrıştırılmış bir
     * atıf, çakışmanın kendisinden kötüdür — okuyucuyu YANLIŞ karara
     * götürür ve bunu yaptığını söylemez. Ayrı bir paketin işi.
     *
     * @var array<string, list<string>>
     */
    private const INHERITED_COLLISIONS = [
        '123' => ['123-DEVIR-TESLIM-ENVANTERI.md', '123-ODEME-KIP-ANAHTARI-VE-KENDI-KENDINE-ABONELIK.md'],
        '124' => ['124-YASAL-METINLER.md', '124-YEDEK-TATBIKATI.md'],
        '138' => ['138-ANASAYFA-VE-HAREKET-DAGARCIGI.md', '138-KVKK-HAKLARI-URUNDE.md'],
        '139' => ['139-FIYAT-SAYFASI-METNI.md', '139-ROL-VE-YETKI-MATRISI.md'],
    ];

    public function test_no_two_decision_documents_share_a_number(): void
    {
        $seen = [];

        foreach ($this->numberedDocuments() as $file => $number) {
            $seen[$number][] = $file;
        }

        $collisions = array_filter($seen, static fn (array $files): bool => count($files) > 1);

        foreach ($collisions as $number => $files) {
            $inherited = self::INHERITED_COLLISIONS[$number] ?? null;

            if ($inherited === null) {
                continue;
            }

            sort($files);
            $expected = $inherited;
            sort($expected);

            // Devralınan çakışma AYNEN durduğu sürece muaf; bir dosya
            // eklenirse ya da adı değişirse muafiyet düşer.
            if ($files === $expected) {
                unset($collisions[$number]);
            }
        }

        self::assertSame(
            [],
            $collisions,
            'BELGE-NUMARA-01: aynı numarayı taşıyan karar belgeleri var — '
            .json_encode($collisions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\nBir numara bir adrestir; koddaki `docs/NNN` atıfları tek bir belgeyi "
            .'işaret etmek zorunda. Birleşmeden önce yeni gelen paket numarasını değiştirir.'
        );
    }

    /**
     * `docs/` altındaki numaralı karar belgeleri: `NNN-BASLIK.md`.
     *
     * @return array<string, string>
     */
    private function numberedDocuments(): array
    {
        $found = [];

        foreach ((array) glob(base_path('docs/*.md')) as $path) {
            if (! is_string($path)) {
                continue;
            }

            $name = basename($path);

            if (preg_match('/^(\d+)-/', $name, $matches) !== 1) {
                continue;
            }

            // Baştaki sıfırlar bir fark yaratmaz: `049` ile `49` aynı adrestir.
            $found[$name] = ltrim($matches[1], '0');
        }

        self::assertNotEmpty($found, 'BELGE-NUMARA-01: numaralı hiçbir belge bulunamadı; kapı kör kalmış.');

        return $found;
    }
}
