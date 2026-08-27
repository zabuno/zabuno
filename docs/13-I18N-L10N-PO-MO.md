# 13 — i18n / l10n (PO/MO)

**Bu sözleşmenin Stage 1 kapsamı 2026-08-26'da UYGULANDI.** Aşağıdaki plan
artık çalışan koda karşılık gelir; §3a hangi dosyanın neyi sahiplendiğini
gösterir.

## 1. Gettext PO canonical

**PO (Portable Object)** kanonik kaynak formatıdır. Buradan iki projeksiyon
üretilir (tek kaynak, çoklu çıktı — `docs/07`'deki fingerprint felsefesiyle
tutarlı bir "tek sahip, projeksiyon" deseni):

```
PO (kanonik) → MO (PHP/Laravel runtime) → JSON (React frontend runtime)
```

Modül-owned text domain: her modül kendi çeviri metinlerinin sahibidir (bir
modül disable edilirse onun çeviri domain'i de birlikte devre dışı kalır —
`docs/03` ADR-L05 modül izolasyonu ile tutarlı).

## 2. Varsayılan dil ve katalog planı

**Varsayılan: English** (bkz. `docs/01` §5 — plan dili Türkçe, ürün UI dili
İngilizce). Hazır altı katalog / provisional locale seti:

```
en (default), tr, de, fr, ar, ru
```

**Arabic RTL tasarım ve test zorunludur** — `docs/06` tema token'ları LTR/RTL'i
aynı anda destekler; her yeni bileşen RTL modunda görsel regresyon testinden
geçmeden "tamamlandı" sayılmaz (`docs/27`).

## 2a. Stage 1 / Stage 2 sözleşmesi (kanonik — bu bölüm tek kaynaktır)

Bu sözleşme burada **kanoniktir**; `modules/core-localization.md` ve
`docs/26` buraya link verir, tekrar tanımlamaz.

- **Stage 1 MVP**: yukarıdaki **altı katalogun tamamı** (en/tr/de/fr/ar/ru)
  için dizilim/scaffold, her modülün text-domain wiring'i ve
  **PO→MO→JSON extraction/projection pipeline'ının tamamı** hazır ve
  entegredir — bu, "Stage 1'de yalnız en+tr var, diğer katalog yok" **değildir**;
  pipeline ve altı katalog iskeleti Stage 1'den itibaren çalışır durumdadır.
  **English kaynak katalog complete/default**'tur; ürünün varsayılan UI dili
  budur.
- **Stage 2 Post-MVP**: diğer beş dilin (tr/de/fr/ar/ru) **içerik-
  completeness'i** — kullanıcının/çevirmenin PO üzerinden dolduracağı tam
  çeviri, **plural-form** ve **context (`msgctxt`)** completeness dahil —
  burada tamamlanır. **Arabic RTL görsel completeness** (yukarıdaki RTL
  zorunluluğunun tam kapsamlı regresyon testi) de Stage 2'nin parçasıdır;
  Stage 1'de yalnız RTL **altyapısı** (yön/token desteği) hazırdır, tam görsel
  completeness kanıtı Stage 2'de üretilir.

Kanonik sahiplik zinciri: bu bölüm (`docs/13` §2a) sözleşmenin **kaynağıdır**;
`modules/core-localization.md` §Phase delivery/§Acceptance ve `docs/26` §1
CORE-08 satırı bu sözleşmeyi **uygular**, yeniden tanımlamaz. İzlenebilirlik
`docs/29`'da doğrulanır.

## 3. Katalog bakım süreci

Extract → merge → fuzzy işaretleme → missing-string tespiti → plural-form
kontrolü → context (`msgctxt`) kontrolü. Bu adımlar CI'da otomatikleştirilir
(`skills/i18n-catalog` bu sürecin deterministik spesifikasyonunu taşır).

## 3a. Uygulanan boru hattı (2026-08-26)

| Adım | Nerede | Ne yapar |
| --- | --- | --- |
| Kaynak | `resources/js/i18n/*.ts` (`domains.ts` listeler) | İngilizce metnin **tek** sahibi. Kod burada okunur, tahmin edilmez. |
| Extract | `npm run i18n:extract` | POT + altı locale PO'su üretir; var olan çeviriyi korur, kaynaktan düşen anahtarı siler. Kaynağı boş olan anahtar kataloğa hiç girmez — çevrilecek metin yoktur ve boş bir `msgstr` eksik sayımını kalıcı yanıltırdı. |
| Çeviri | `lang/po/<domain>.<locale>.po` | Çevirmenin çalıştığı yer. Kod deposunu açmadan, standart PO araçlarıyla. |
| Build | `npm run i18n:build` | `lang/mo/<locale>/<domain>.mo` (PHP) + `resources/js/i18n/generated/<domain>.<locale>.json` (React). Fuzzy satır projeksiyona **girmez**. |
| PHP runtime | `App\Infrastructure\Localization\MoFileTranslator` | MO'yu saf PHP ile okur. `ext-gettext`/`setlocale` **kullanılmaz**: süreç geneli durum, çok kiracılı bir istekte bir tenant'ın dilini diğerine sızdırabilir. |
| React runtime | `resources/js/i18n/generated-overrides.ts` | Üretilmiş JSON'u alan adına göre dağıtır; her katalog `overridesFor(domain)` ile alır. Kaynak locale JSON'a yazılmaz — taban zaten koddadır. |
| Kapı | `npm run i18n:check` (CI adımı) | İşlenmiş projeksiyon PO ile aynı değilse CI kırılır. İşlenen bir üretim çıktısı, bayatladığında sessizce yanlış metin gösterir. |

Düşme sırası her iki çalışma zamanında da aynıdır: **istenen dil → kaynak dil
→ anahtarın kendisi**. Anahtarı göstermek son çaredir ve kasten çirkindir:
eksik çeviri fark edilmeli, boş bir arayüzün arkasına saklanmamalıdır.

## 4. Yerel format kuralları

Tarih/saat, para, ölçü birimi, adres, telefon formatları locale'e göre
değişir; bunlar **Money/Ledger** (CORE-12) ve **Taxonomy** (CORE-09) modülleriyle
koordineli çalışır — para birimi formatlaması burada değil, CORE-12'de
tanımlanır (tek kanonik sahip kuralı).

**Bu kural 2026-08-26'da uygulandı.** O tarihte aynı işi yapan beş ayrı kod
parçası vardı ve dördü kuruşu sabit 100'e bölüyordu. Bu her para biriminde
doğru değildir: Japon yeninde ondalık yoktur (1499 minor = ¥1.499), Kuveyt
dinarında üç basamak vardır (1499 minor = 1,499 KWD). Yayınlanmış bir menüde
bu, yüz kat yanlış fiyat demektir — ve fiyat, restoranın müşterisine verdiği
taahhüttür.

Tek sahip artık şunlardır:

| Taraf | Sahip | Not |
| --- | --- | --- |
| PHP | `App\Domain\Money\MoneyFormatter` | Bölen, para biriminin kendi `fractionDigits()` değerinden türetilir. `ext-intl` varsa kullanılır; yoksa ayırıcılar açık bir tablodan gelir (paylaşımlı barındırma). |
| React | `resources/js/money/format.ts` | Aynı kural, `Intl.NumberFormat` ile. Para birimi kodu biçime değil, `Intl.supportedValuesOf('currency')` listesine sorulur — `Intl` iyi biçimli ama var olmayan bir kodu sessizce kabul eder. |
| Yayınlanan menü | `App\Support\Money\PriceLabel` | Para birimi çözülemezse fiyat **gösterilmez** ve sayfa ayakta kalır: eksik fiyat görünür biçimde eksiktir, yanlış fiyat ise verilemeyecek bir sözdür. |

Biçim okuyucunun dilinden gelir (`<html lang>`), geliştiricinin tercihinden
değil. Türkçe bir belgede `₺1.499,00`, İngilizce bir belgede `TRY 1,499.00`.
Kanıt: `MONEY-FORMAT-DIGITS-01`, `-LOCALE-02`, `-STRICT-03`,
`MENU-PRICE-CURRENCY-04`, `-NO-BLANK-PAGE-05`, `MONEY-FE-DIGITS-06`,
`-UNKNOWN-07`.

## 5. Kanonik sahiplik

PO/MO/JSON projeksiyon zinciri ve locale planı burada kanoniktir. Uygulama
detayları `modules/core-localization.md`'de, süreç otomasyonu
`skills/i18n-catalog`'da yaşar.

## 6. Kaynak dil ve çeviri sahipliği (owner kararı, 2026-08-27)

**Karar sahibinindir ve üç parçalıdır:**

1. **Kaynak dil İngilizce'dir.** Kodda yazılan her dize İngilizce yazılır.
2. **Çeviriyi sahibi yapar.** PO dosyaları olgunluk sonrasında elle
   doldurulur. Kod tarafı — insan ya da model — bir hedef dile çeviri
   YAZMAZ.
3. **Ekranda görünen her dize PO dosyasından çevrilebilir olmalıdır.**

Üçüncü madde ilk ikisinin bedelidir ve tek teknik şart odur. Bir dize
kaynak katalogda yoksa PO içinde satırı da yoktur; sahibi dosyayı açtığında
görecek bir şey bulamaz. Yani "çevrilmemiş" ile "çevrilemez" aynı şey
değildir: birincisi beklenen durumdur, ikincisi kusurdur.

### Bunun kapılara yansıması

| Kural | Nasıl korunuyor |
| --- | --- |
| Kaynak katalog (`en`) eksiksiz | `I18N-SIX-CATALOGS-10` — her alan adı için `missingCount('en') === 0` |
| Hedef dilin eksikliği hata değildir | `I18N-TARGET-OPTIONAL-15` — eksiklik ölçülür, şart koşulmaz |
| Sunucuda üretilen metin de çevrilebilir | `I18N-SSR-RATCHET-16` — `lang/untranslatable-debt.json` kilidi |

**Kaldırılan kural:** "Türkçe menü kataloğu tamdır" iddiası
`TranslationPipelineTest` içinden çıkarıldı. O iddia sistemi korumuyordu;
İngilizce'ye her yeni dize eklendiğinde CI'ı kırıyor ve tek çıkış yolu
olarak makine çevirisini dayatıyordu — yani 2. maddenin tam tersini. Yerine
konan `I18N-TARGET-OPTIONAL-15` aynı boşluğu ölçer ama hata saymaz.

**Devralınan borç:** hedef dillerde bugün 604 anahtarın 38'i doludur ve o
38'i model yazmıştır. Sahibi PO'ları elden geçirirken bunları da gözden
geçirmelidir; kod bunları doğru kabul etmez, yalnız var olduklarını bilir.

### Sunucu tarafındaki açık

Bu karar alındığında Blade görünümlerinde **tek bir çeviri çağrısı yoktu**.
React tarafı katalogdan besleniyordu, sunucu tarafı beslenmiyordu. Ölçülen
boşluk **71 görünür dize**: açılış sayfası (35), ortak yerleşim (14), genel
menü kabuğu (9), 404 (3), ve her kabuğun sekme başlığı.

Bunlar tek pakette taşınmaz; kilit borcun artmasını imkânsız kılar,
erimesini serbest bırakır. Erime planı `docs/40-I18N-RUNTIME-ROADMAP.md`'te (Faz 3) fazlanmıştır.

## 7. Çalışma zamanı gereksinimi: FTP ile yüklenen PO etkili olmalı

**Owner iş akışı:** PO dosyası FTP ile sunucuya yüklenir. Sistem
güncellenmelidir — ya anında, ya belirli bir süre sonra kendiliğinden.

**Bugünkü gerçek: yükleme hiçbir şey yapmaz.** Sebep mimaridir, hata değil:

| Katman | Çeviriyi nereden alır | FTP ile PO yüklenince |
| --- | --- | --- |
| PHP (sunucuda üretilen) | `lang/mo/{locale}/{domain}.mo` | **Değişmez** — MO'yu Node üretir |
| React (tarayıcıda) | JS paketine gömülü JSON | **Değişmez** — paket derleme anında donar |

İki katman da bir **derleme adımına** bağlı. Paylaşımlı barındırmada
(Turhost, Natro, Güzel Hosting) Node yoktur, SSH çoğu planda yoktur.
Dolayısıyla sahibi PO'yu yükleyebilir ama hiçbir şey görmez.

Bu, `docs/15` §4'teki paylaşımlı barındırma kısıtının i18n'e yansımasıdır
ve boru hattı kurulurken gözden kaçmıştır. Çözümü `docs/40-I18N-RUNTIME-ROADMAP.md`'te
`I18N-RUNTIME-v1` planı olarak fazlanmıştır; Stage 2'ye eşlenir çünkü
sahibinin PO'ları doldurma zamanı olgunluk sonrasıdır ve yetenek o günden
önce hazır olmalıdır.
