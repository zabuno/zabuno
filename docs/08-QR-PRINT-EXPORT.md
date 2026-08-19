# 08 — QR, Print & Export

**PLANNING ONLY.**

## 1. Teknoloji adayları

- **endroid/qr-code**: server-authoritative SVG üretimi — **capability-verified,
  adoption koşullu**. Güncel etiket (tag) `6.1.3`; bu etiketin composer
  gereksinimi PHP `^8.4`, `6.0.0` etiketi PHP `^8.2`, `5.1.0` etiketi PHP
  `^8.1` gerektirir (`docs/28` kaynak kaydı, 2026-08-19 erişim doğrulandı).
  Kütüphanenin QR üretim kabiliyeti güçlü bir adaydır, ancak **hangi major/
  minor sürümün seçileceği**, hedeflenen Laravel/PHP/shared-host baseline'ı
  kilitlenmeden (`docs/16` DEP-01) **karara bağlanamaz**. Production kararı
  yalnız server-authoritative adaptör katmanı + decode-parity testleri
  (üretilen QR'ın gerçekten okunabilir olduğunun otomatik doğrulaması) ile
  birlikte verilir. Nihai QR çıktısı her zaman backend'de deterministik
  üretilir ve doğrulanır; frontend'deki `qr-code-styling` (adayı) yalnız canlı
  önizleme içindir, **nihai kaynak değildir**.
- **mPDF**: print/export için PDF üretimi — **capability-verified, adoption
  koşullu**. Güncel etiket `v8.3.1`; bu etiketin composer gereksinimi PHP
  `5.6`/`7.x` ve `8.0`–`8.5` aralığını kapsar (`docs/28`, 2026-08-19 erişim
  doğrulandı). Named/custom page size ve orientation kabiliyeti resmi
  dokümantasyonla doğrulanmıştır (§3, §6). Production kullanımı şu şartlara
  **koşulludur**: sürüm pinlenir (compatibility spike sonrası); yalnız
  **kontrollü template'ler** kullanılır — tenant/kullanıcının kendi keyfi
  HTML/CSS'i doğrudan PDF motoruna **verilmez** (XSS/SSRF/resource-exhaustion
  yüzeyini sınırlamak için); PDF snapshot testi, fiziksel baskı testi,
  performans ve güvenlik testleri tamamlanmadan production'a alınmaz.

## 2. QR immutability modeli

- QR'ın kendisi **immutable bir public ID** taşır (tahmin edilemez random token —
  sıralı DB ID veya restoran slug'ına bağımlılık **yasaktır**).
- Destination (QR'ın yönlendirdiği hedef) ve destination'ın versiyonu
  **değişebilir**. QR içine doğrudan menü URL'si **yazılmaz**; QR her zaman bir
  destination resolver'a gider:

```
https://q.example.com/{randomToken} → resolver → Destination → MenuPublication | Menu | URL | ...
```

- Revoke/rotate: bir destination iptal edilebilir/değiştirilebilir; QR'ın kendisi
  fiziksel olarak sabit kaldığı için bu işlemler **audit'e** yazılır
  (`docs/04` CORE-07).

## 3. Basılı boyutlar ve düzen

**ISO 216** standardı: A4, B4, A5, B5, A6, B6, A7, B7 — landscape/portrait, 6 tema
varyasyonu (Theme 1..6). Aynı **versioned layout JSON** hem preview hem export
kaynağıdır (tek kaynak, iki çıktı — preview ile basılan asla farklı olmaz).

Çıktı meta verisi: fiziksel mm cinsinden ölçüler, DPI **yalnız çıktı
metadata/hesabı** için kullanılır (ekranda "DPI" göstermek yanıltıcıdır — asıl
gerçek mm boyutudur). Calibration ruler (kullanıcının çıktısını gerçek cetvelle
doğrulayabileceği bir referans çizgi), margin/bleed/safe-zone tanımlı.

## 4. Scannability kalite kapıları

- Minimum kontrast oranı, minimum quiet zone (QR etrafındaki boş alan).
- Maksimum logo alanı (error-correction seviyesine göre sınırlı).
- Invalid renk kombinasyonu engeli (örn. düşük kontrastlı arka plan/ön plan).
- **Server-side QR doğrulama**: üretilen her QR, üretim sırasında otomatik decode
  edilerek doğrulanır (üretilen QR'ın gerçekten okunabilir olduğu garanti edilir).
- Test scan durumu + son test tarihi kullanıcı tarafından kaydedilebilir
  (fiziksel ortamda gerçek telefon testi sonucu).

## 5. Bulk wizard

Floor/area/section/table sayısı → adlandırma prefix/sequence/range → koltuk
sayısı → destination tipi → tema/boyut/yön seçimi → önizleme → PDF → OS print.
Bu akış `docs/02` §4'te uçtan uca onboarding akışının bir parçası olarak
tanımlanmıştır; burada teknik üretim detayları (layout JSON, PDF üretim adımları)
yaşar.

## 6. MVP kapsamı

**Stage 1 MVP (baseline, açık bir M0/M1 tercihi değil — sabit kapsam)**: basic
designer, PNG/SVG export, masa/alan bulk wizard, ISO 216 A4/B4/A5/B5/A6/B6/
A7/B7 boyutları, portrait/landscape, altı temel tema (Theme 1..6), paylaşılan
preview/export layout (tek versioned layout JSON), server-side PDF export
(mPDF), browser/OS print, scannability/decode doğrulama kapısı (`docs/08` §4).

**Stage 2 Post-MVP**: gelişmiş tema tasarımcısı (advanced theme designer) ve
matbaa/vendor özellikleri — gelişmiş bleed/crop mark, CMYK/EPS, büyük ölçekli
production batch üretimi (`docs/26` §1, OPT-05).

Baseline PDF/bulk üretimi açık bir M0/M1 seçimi **değildir** — bu külliyatta
sabit MVP kapsamıdır; kesin sınır `docs/18-STAGE-01-MVP.md` Scope bölümünde
tekrarlanır.

## 7. Destination tipleri

**MVP**: Published menu, Menu category. **MVP sonrası**: URL, social profile,
campaign, PDF, contact page, Wi-Fi page, reservation page.

## 8. Kanonik sahiplik

QR/print/export teknik mimarisi burada kanoniktir. Destination'ın veri modeli
(`QRCode`, `Destination`, `QRScanEvent`) `docs/05` §5'te özetlenmiştir, burada
tekrar edilmez — yalnız link verilir.
