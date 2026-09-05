<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * PDF DENETÇİSİ — sahibin 2026-09-05 kararının güvenlik yarısı.
 *
 * NEDEN VAR. Sahip "PDF açılsın — temizleyiciyle birlikte, aynı pakette"
 * dedi. Depoda PDF OKUYUCU zaten yazılıydı (`MediaViewerRegion`,
 * `ShowMediaViewerController`, `ServeMediaPreviewController`) ama alım kapısı
 * PDF'i kabul etmediği için ölü koddu: restoran sahibi alerjen tablosunu
 * panele hiç koyamıyordu. Kapıyı denetleyicisiz açmak ise SVG'de reddedilen
 * şeyin aynısı olurdu.
 *
 * NEDEN "DENETÇİ", "TEMİZLEYİCİ" DEĞİL. Ad, yaptığı işi söyler.
 * `SvgSanitizer` gerçekten temizler: XML ağacından bir düğüm atıldığında
 * geriye geçerli bir SVG kalır. PDF'te bu doğru değildir — nesneler çapraz
 * referans tablosundaki BAYT KONUMLARIYLA adreslenir; bir nesneyi çıkarmak
 * ondan sonraki her konumu kaydırır ve dosyayı bozar. "Temizlenmiş PDF"
 * üretmek, dosyayı bağımlılıksız biçimde yeniden yazmak demektir; yarım
 * yapılırsa sahibin açamadığı bir belge doğar. Bu sınıf hiçbir baytı
 * DEĞİŞTİRMEZ; yalnız "bu gövde kabul edilir mi?" sorusuna cevap verir.
 *
 * NEDEN REDDEDER, TEMİZLEYİP KABUL ETMEZ. `MaliciousIntakeGateTest` depoda
 * bir söz veriyor: `tests/fixtures/malicious/` içindeki her dosya HİÇBİR
 * ŞEY SAKLANMADAN reddedilir. Saldırı taşıyan bir gövdeyi sessizce
 * kırpıp kabul etmek iki şeyi birden yapardı — saldırıyı arşivlemek ve
 * sahibin dosyasını haber vermeden değiştirmek (`StoreMediaRequest`in SVG
 * kapısıyla aynı yön).
 *
 * NEDEN SAF. Ne çerçeve, ne dosya sistemi, ne ağ, ne dış kütüphane. Girdi
 * bir dize, çıktı bir karar (ADR-L02). Böylece saldırı gövdeleriyle HTTP
 * ve veritabanı olmadan doğrudan sınanabilir.
 *
 * ── NE ARANIR ────────────────────────────────────────────────────────────
 *
 * Bir PDF pasif bir resim değildir: içine, dosyayı AÇAN kişinin makinesinde
 * çalışan şeyler gömülebilir. Aranan yapılar tam olarak bunlardır —
 * açılışta çalışan betik (`/JavaScript`, `/JS`), olayla tetiklenen eylem
 * (`/AA`, `/OpenAction`), dış program çağırma (`/Launch`), ağa veri
 * gönderme/çekme (`/SubmitForm`, `/ImportData`, `/GoToR`), gövdede taşınan
 * ikinci bir dosya (`/EmbeddedFile`, `/Filespec`) ve gömülü medya
 * (`/RichMedia`, `/Movie`, `/Sound`).
 *
 * ── NEREDE ARANIR: AKIŞLARIN İÇİ DEĞİL, DIŞI ─────────────────────────────
 *
 * Eylemler nesne SÖZLÜKLERİNDE yaşar. Sayfanın çizim akışı ise yalnız
 * çizer. Bu yüzden akış gövdeleri (`stream … endstream`) taramanın DIŞINDA
 * tutulur ve bu bir gevşeme değil, yanlış pozitifi kesen bir karardır:
 * "PDF /Launch eylemi nedir?" cümlesini ÇİZEN bir eğitim notu bir saldırı
 * değildir, ve sıkıştırılmış ikili bir gövdede rastgele `/JS` baytları
 * geçebilir.
 *
 * ── /ObjStm: GÖREMEDİĞİMİZ ŞEYİ "TEMİZ" GEÇİRMEYİZ ───────────────────────
 *
 * Bu kuralın tek gerçek deliği vardır ve kapatılmıştır. PDF 1.5'ten beri
 * nesne sözlüklerinin KENDİSİ sıkıştırılmış bir nesne akışına (`/ObjStm`)
 * konabilir; ham baytlarda `/JavaScript` aramak orada hiçbir şey bulmaz.
 * Üç yol vardı: (a) görmezden gelmek — sessizce açık bırakırdı, (b) her
 * `/ObjStm` taşıyan dosyayı reddetmek — bugünün PDF'lerinin neredeyse
 * tamamını reddederdi, (c) akışı ÇÖZÜP içine bakmak. Seçilen (c)'dir ve
 * çözülemeyen akış (b) gibi ele alınır:
 *
 *   - `/FlateDecode` ile sıkıştırılmış nesne akışı AÇILIR ve içi aynı
 *     kurallarla taranır (`zlib` PHP ile birlikte gelir; dış bağımlılık
 *     yok).
 *   - Sıkıştırılmamış nesne akışı doğrudan taranır.
 *   - Açılamayan nesne akışı (LZW, bilinmeyen ya da zincirli filtre, bozuk
 *     gövde) `opaque-object-stream` sayılır ve dosya REDDEDİLİR. İçini
 *     göremediğimiz bir şeye "temiz" demek, denetimin kendisini yalan
 *     yapardı.
 *
 * Açılan gövde `MAX_INFLATED_BYTES` ile sınırlıdır: birkaç kilobaytlık bir
 * akış gigabaytlara açılabilir (zip bombası) ve denetçinin kendisi bir
 * hizmet-dışı bırakma aracına dönüşmemelidir.
 *
 * ── AD KAÇIRMA ───────────────────────────────────────────────────────────
 *
 * PDF'te `/J#61vaScript` ile `/JavaScript` AYNI addır; okuyucu ikisini de
 * çalıştırır. Bu yüzden arama, `#xx` kaçışları ÇÖZÜLDÜKTEN sonra yapılır.
 * Kaçırılmış adı görmeyen bir denetçi, kapıyı açık bırakıp kilitli sanır.
 */
final class PdfInspector
{
    /**
     * Denetlenecek azami gövde. Kaynağın belge tavanı 25 MB (`docs/108`
     * §6.2); alım kapısı zaten `media-slots.limits.max_bytes` ile daha
     * önce durdurur — bu, sınıf doğrudan çağrılırsa diye duran ikinci
     * kapıdır.
     */
    public const MAX_BYTES = 25 * 1024 * 1024;

    /** Bir nesne akışının açılabileceği azami boy (zip bombası sınırı). */
    public const MAX_INFLATED_BYTES = 8 * 1024 * 1024;

    /**
     * Taranacak azami akış sayısı. Milyonlarca boş akış tarif eden birkaç
     * yüz kilobaytlık bir dosya, denetçiyi tek başına meşgul edebilirdi.
     */
    public const MAX_STREAMS = 5000;

    /** `%PDF-` başlığı bu pencerenin dışındaysa dosya PDF sayılmaz. */
    private const HEADER_WINDOW = 1024;

    /**
     * KOŞULSUZ REDDEDİLEN adlar → sahibe anlatılacak kategori.
     *
     * Hiçbir yemek listesinin, alerjen tablosunun ya da sözleşmenin
     * bunlara ihtiyacı yoktur; varlıkları bir kaza değil bir niyettir.
     *
     * @var array<string, string>
     */
    private const HOSTILE_NAMES = [
        'JavaScript' => 'javascript',
        'JS' => 'javascript',
        'Launch' => 'launch',
        'SubmitForm' => 'network-action',
        'ImportData' => 'network-action',
        'GoToR' => 'network-action',
        'EmbeddedFile' => 'embedded-file',
        'EmbeddedFiles' => 'embedded-file',
        'Filespec' => 'embedded-file',
        'RichMedia' => 'embedded-media',
        'Movie' => 'embedded-media',
        'Sound' => 'embedded-media',
    ];

    /**
     * KOŞULLU adlar: eylem olabilirler, hedef de olabilirler.
     *
     * `/OpenAction [3 0 R /XYZ …]` "açılınca şu sayfada dur" demektir ve
     * Word/LaTeX çıktılarının çoğunda vardır. Onu reddetmek, dünyadaki
     * PDF'lerin büyük kısmını reddetmek olurdu. Değeri bir HEDEF değilse
     * (dizi, adlandırılmış hedef ya da belge içi `/GoTo`), ne yaptığını
     * ucuza çözemeyiz — ve bilmediğimiz eylem reddedilir.
     *
     * @var list<string>
     */
    private const CONDITIONAL_ACTION_NAMES = ['OpenAction', 'AA'];

    public function inspect(string $body): PdfInspectionResult
    {
        if (trim($body) === '') {
            return PdfInspectionResult::unreadable('PDF dosyası boş görünüyor. Lütfen yeniden yükleyin.');
        }

        if (strlen($body) > self::MAX_BYTES) {
            return PdfInspectionResult::unreadable(
                'Bu PDF güvenle incelenemeyecek kadar büyük. Belgeyi bölüp yeniden deneyin.',
            );
        }

        if (! str_contains(substr($body, 0, self::HEADER_WINDOW), '%PDF-')) {
            return PdfInspectionResult::unreadable(
                'Bu dosya bir PDF değil. Belge alanına yalnız PDF yükleyebilirsiniz.',
            );
        }

        /*
            `%%EOF` YOKSA DOSYA YARIMDIR.

            Yarım inen bir yüklemenin sonu kesilmiştir ve tam da o kesilen
            yerde ne olduğunu bilemeyiz. Okunamayan gövde "temiz" değildir.
        */
        if (! str_contains($body, '%%EOF')) {
            return PdfInspectionResult::unreadable(
                'Bu PDF eksik inmiş görünüyor (dosya sonu işareti yok). Yeniden yükleyin.',
            );
        }

        $segments = $this->segment($body);

        if ($segments === null) {
            return PdfInspectionResult::unreadable(
                'Bu PDF\'in yapısı okunamadı: kapanmayan bir veri akışı var. '
                .'Belgeyi kaynağından yeniden dışa aktarıp deneyin.',
            );
        }

        [$outside, $streams] = $segments;

        /*
            ŞİFRELİ GÖVDE. `/Encrypt` varsa nesne sözlükleri şifrelidir ve
            denetçi hiçbir şey göremez. "Göremedim, o hâlde temizdir"
            diyemeyiz; bu tam olarak kapıyı atlatmanın en kısa yoludur.
        */
        if ($this->matches($outside, 'Encrypt')) {
            return PdfInspectionResult::unreadable(
                'Bu PDF şifreli ve içeriği denetlenemiyor. Şifresiz bir kopyasını yükleyin.',
            );
        }

        $threats = $this->threatsIn($outside);

        foreach ($streams as [$dictionary, $payload]) {
            /*
                Yalnız NESNE AKIŞLARI açılır. Bir sayfanın çizim akışını
                ya da gömülü bir fotoğrafı açmak, eylem taşımayan bir
                gövdeyi taramak için bellek harcamak olurdu.
            */
            if (! $this->matches($dictionary, 'ObjStm')) {
                continue;
            }

            $decoded = $this->decodeObjectStream($dictionary, $payload);

            if ($decoded === null) {
                $threats[] = 'opaque-object-stream';

                continue;
            }

            $threats = [...$threats, ...$this->threatsIn($decoded)];
        }

        $threats = array_values(array_unique($threats));

        if ($threats !== []) {
            return PdfInspectionResult::hostile($threats, $this->hostileReason($threats));
        }

        return PdfInspectionResult::safe();
    }

    /**
     * Gövdeyi AKIŞ DIŞI metin ve akışlar olarak ayırır.
     *
     * `stream` anahtar kelimesi bir satır sonuyla biter ve `endstream` ile
     * kapanır. `endstream` içindeki "stream" yakalanmasın diye harf
     * geriye-bakışı vardır; kapanmayan akış YAPISAL BOZUKLUKTUR ve `null`
     * döner.
     *
     * @return array{0:string, 1:list<array{0:string,1:string}>}|null
     */
    private function segment(string $body): ?array
    {
        $offset = 0;
        $outside = '';
        $streams = [];

        while (preg_match('~(?<![A-Za-z])stream(\r\n|\n|\r)~', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $keywordStart = (int) $match[0][1];
            $payloadStart = $keywordStart + strlen((string) $match[0][0]);
            $end = strpos($body, 'endstream', $payloadStart);

            if ($end === false) {
                return null;
            }

            $text = substr($body, $offset, $keywordStart - $offset);
            $outside .= $text."\n";
            $streams[] = [$text, substr($body, $payloadStart, $end - $payloadStart)];

            if (count($streams) > self::MAX_STREAMS) {
                return null;
            }

            $offset = $end + strlen('endstream');
        }

        return [$outside.substr($body, $offset), $streams];
    }

    /**
     * Nesne akışının içi.
     *
     * @return string|null `null`: açılamadı — içini göremiyoruz.
     */
    private function decodeObjectStream(string $dictionary, string $payload): ?string
    {
        $payload = trim($payload, "\r\n");

        if ($payload === '') {
            return '';
        }

        $filters = $this->matches($dictionary, 'Filter');

        if (! $filters) {
            // Sıkıştırılmamış nesne akışı: olduğu gibi okunur.
            return $payload;
        }

        if (! $this->matches($dictionary, 'FlateDecode')) {
            // LZW, bilinmeyen ya da zincirli filtre: açamayız.
            return null;
        }

        // `gzuncompress` zlib başlıklı gövdeyi açar; bazı üreticiler ham
        // deflate yazar, o yüzden ikinci deneme `gzinflate`dir.
        $decoded = @gzuncompress($payload, self::MAX_INFLATED_BYTES);

        if ($decoded === false) {
            $decoded = @gzinflate($payload, self::MAX_INFLATED_BYTES);
        }

        return $decoded === false ? null : $decoded;
    }

    /**
     * Bu metin parçasında hangi saldırı yapıları var?
     *
     * @return list<string>
     */
    private function threatsIn(string $text): array
    {
        $normalised = $this->withoutNameEscapes($text);
        $threats = [];

        foreach (self::HOSTILE_NAMES as $name => $category) {
            if ($this->matches($normalised, $name, false)) {
                $threats[] = $category;
            }
        }

        foreach (self::CONDITIONAL_ACTION_NAMES as $name) {
            if ($this->isHostileAction($normalised, $name)) {
                $threats[] = 'auto-action';
            }
        }

        return $threats;
    }

    /**
     * `/OpenAction` ya da `/AA` bir HEDEF mi, bir EYLEM mi?
     *
     * Hedef üç biçimde yazılır ve üçü de belgenin dışına çıkamaz: bir dizi
     * (`[3 0 R /Fit]`), adlandırılmış bir hedef (`/BasSayfa`) ve belge içi
     * sıçrama (`<< /S /GoTo … >>`). Geri kalan her biçim — bir sözlük ya
     * da dolaylı başvuru — ne yaptığını burada ucuza çözemediğimiz bir
     * EYLEMDİR ve fail-closed reddedilir.
     */
    private function isHostileAction(string $text, string $name): bool
    {
        $found = preg_match_all('~/'.$name.'(?![A-Za-z0-9])\s*(.{0,64})~s', $text, $matches);

        if ($found === false || $found === 0) {
            return false;
        }

        foreach ($matches[1] as $value) {
            $value = ltrim((string) $value);

            if (str_starts_with($value, '[') || str_starts_with($value, '/')) {
                continue;
            }

            if (preg_match('~^<<\s*/S\s*/GoTo(?![A-Za-z0-9])~', $value) === 1) {
                continue;
            }

            return true;
        }

        return false;
    }

    /** `/Ad` biçiminde bir ad geçiyor mu? (`/JS` ile `/JSFoo` aynı şey değildir.) */
    private function matches(string $text, string $name, bool $normalise = true): bool
    {
        $haystack = $normalise ? $this->withoutNameEscapes($text) : $text;

        return preg_match('~/'.preg_quote($name, '~').'(?![A-Za-z0-9])~', $haystack) === 1;
    }

    /**
     * `#xx` kaçışlarını çözer: `/J#61vaScript` → `/JavaScript`.
     *
     * Yalnız ARAMA için; hiçbir bayt geri yazılmaz.
     */
    private function withoutNameEscapes(string $text): string
    {
        if (! str_contains($text, '#')) {
            return $text;
        }

        return (string) preg_replace_callback(
            '~#([0-9A-Fa-f]{2})~',
            static fn (array $match): string => chr((int) hexdec($match[1])),
            $text,
        );
    }

    /** @param  list<string>  $threats */
    private function hostileReason(array $threats): string
    {
        // Sahip teknik değildir: "network-action" ona hiçbir şey anlatmaz.
        // Cümle NE OLDUĞUNU ve NE YAPACAĞINI söyler (`docs/76`).
        $human = [
            'javascript' => 'açılınca çalışan betik',
            'auto-action' => 'kendiliğinden çalışan bir eylem',
            'launch' => 'bilgisayarda program çalıştıran bağlantı',
            'network-action' => 'dışarıya veri gönderen/çeken eylem',
            'embedded-file' => 'içine gömülmüş başka bir dosya',
            'embedded-media' => 'gömülü ses/video',
            'opaque-object-stream' => 'açılamayan, içi görülemeyen sıkıştırılmış bölüm',
        ];

        $found = [];

        foreach ($threats as $threat) {
            $found[] = $human[$threat] ?? 'tanınmayan çalıştırılabilir yapı';
        }

        return 'Bu PDF '.implode(', ', array_values(array_unique($found))).' içeriyor ve bu yüzden kabul edilmedi. '
            .'Böyle bir dosya, onu AÇAN kişinin bilgisayarında iş yapabilir; kütüphaneye alınmaz. '
            .'Belgeyi kaynağından "yalnız yazdır/PDF olarak kaydet" ile yeniden verip deneyin.';
    }
}
