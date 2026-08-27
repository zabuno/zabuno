# 40 — i18n çalışma zamanı yol haritası (adlandırılmış ek plan: `I18N-RUNTIME-v1`)

**Bu belge sabit 38-WP payda sayacını DEĞİŞTİRMEZ.** `docs/17` §4 kuralı
kapsam değiştiğinde önceki sayacın geriye yazılmamasını, bunun yerine
adlandırılmış bir plan açılmasını şart koşar. Bu, o plandır: adı
`I18N-RUNTIME-v1`, kendi maddeleri var ve her maddesi mevcut bir stage'e
eşlenir. `docs/26` matrisindeki WP satırları ve `docs/17` §4 sayacı olduğu
gibi kalır.

Kaynak: `docs/13` §6 ve §7 — owner'ın 2026-08-27 tarihli çeviri sahipliği
kararı ve ondan doğan çalışma zamanı gereksinimi.

## Neden bir ek plan gerekti

Owner kararı basit görünür: *"PO dosyasını FTP ile yüklerim, sistem
güncellensin."* Ama bu cümle, kurulmuş boru hattının karşılamadığı bir şey
ister. Boru hattı **derleme zamanı** çalışır: PO'yu Node okur, MO ve JSON
üretir, JSON paketin içine gömülür. Paylaşımlı barındırmada Node yoktur.

Yani bugün sahibi PO'yu yükler ve **hiçbir şey olmaz.** Ekran hata da
vermez — eski çeviriyi göstermeye devam eder, ki bu daha kötüdür: sessiz
başarısızlık.

Bu, `docs/15` §4'teki paylaşımlı barındırma kısıtının i18n'e yansımasıdır.
Kısıt biliniyordu; i18n boru hattı kurulurken ona bakılmamıştı.

## Ne zaman hazır olmalı

Sahibi PO'ları **olgunluk sonrasında** dolduracağını söyledi. Yetenek o
günden önce hazır olmalı, çünkü yetenek yoksa doldurma işi boşa gider.
Dolayısıyla dört fazın tamamı **Stage 2**'ye eşlenir ve sıralamaları
tetikleyiciyle değil, bağımlılıkla belirlenir.

## Faz 1 — PO çalışma zamanında okunur (Stage 2)

**Bağımlılık: yok. Zincirin tabanı budur.**

| # | İş | Neden | Kanıt |
| --- | --- | --- | --- |
| 1.1 | PHP tarafı PO'yu doğrudan okur; MO zorunlu olmaktan çıkar | Node olmayan sunucuda tek okunabilir kaynak PO'dur | Yalnız PO bulunan bir dizinde çeviri çalışır |
| 1.2 | Önbellek anahtarı dosyanın **içerik özeti**, değişiklik zamanı DEĞİL | Birçok FTP istemcisinde "zaman damgasını koru" seçeneği açıktır; o durumda yeni dosya eski tarihle iner ve zaman damgasına bakan önbellek yüklemeyi ıskalar | Zaman damgası geriye alınmış bir PO yüklendiğinde çeviri yine değişir |
| 1.3 | Önbellek yazılamayan sunucuda istek-içi ayrıştırmaya düşülür | Bazı paylaşımlı planlarda yazılabilir dizin yoktur; yavaş doğru, hızlı yanlıştan iyidir | Salt-okunur dizinde çeviri doğru, sayfa açık |
| 1.4 | Çeviri önbelleği `artisan optimize` dondurulmuş yapılarına girmez | Girerse temizlemek için komut satırı gerekir; sahibinin komut satırı yoktur | `optimize` sonrası PO yüklemesi hâlâ etkili |

## Faz 2 — Tarayıcı katalogu çalışma zamanında gelir (Stage 2)

**Bağımlılık: Faz 1** (uç nokta aynı okuyucuyu kullanır).

| # | İş | Neden | Kanıt |
| --- | --- | --- | --- |
| 2.1 | Katalog uç noktası: locale + alan adı başına JSON, PO'dan üretilir | Paket derleme anında donar; ağdan gelen donmaz | Yeni PO yüklendikten sonra sayfa yenilenince metin değişir |
| 2.2 | **İngilizce pakette kalır**, hedef diller ağdan gelir | Kaynak dil İngilizce olduğu için paket tek başına tam bir uygulamadır: ağ isteği başarısız olsa da ekran boş kalmaz, ham anahtar görünmez | Uç nokta 500 dönerken arayüz İngilizce ve eksiksiz |
| 2.3 | Sürüm damgalı URL + `ETag` | Damgasız URL tarayıcı ve ara önbelleklerde takılır; sahibi "yükledim ama değişmedi" görür | Katalog değişince damga değişir; değişmeyince 304 |
| 2.4 | Uç nokta yalnız katalog verir | Genel erişimli bir uç noktadır; katalog dışına açılırsa sızıntı yüzeyi olur | Token, kiracı verisi, yayınlanmamış alan adı dönmediği doğrulanır |

## Faz 3 — Sunucu tarafındaki 71 dize kataloğa taşınır (Stage 2)

**Bağımlılık: yok** (Faz 1–2'ye paralel yürüyebilir).
**Kilidi bugün kurulu:** `I18N-SSR-RATCHET-16`, `lang/untranslatable-debt.json`.

| # | İş | Dize | Not |
| --- | --- | --- | --- |
| 3.1 | Açılış sayfası | 35 | En büyük tek yığın; pazarlama metni olduğu için sahibinin en çok değiştirmek isteyeceği yer |
| 3.2 | Ortak yerleşim (`public/layout`) | 14 | Gezinme, altbilgi, yasal bağlantılar — her genel sayfada görünür |
| 3.3 | Genel menü kabuğu | 9 | Misafirin gördüğü çerçeve; menü içeriği zaten kiracı verisidir, çeviri konusu değildir |
| 3.4 | 404 ve sekme başlıkları | 13 | Sekme başlığı da ekranda görünen bir dizedir; bugün hiçbiri çevrilemez |

Borç eridikçe `lang/untranslatable-debt.json` düşürülür; kapı yalnız
azalmaya izin verir ve düşen sayının geri alınmasını da engeller.

## Faz 4 — Sahibi için kontrol ve güvenlik ağı (Stage 2)

**Bağımlılık: Faz 1–2.**

| # | İş | Neden | Kanıt |
| --- | --- | --- | --- |
| 4.1 | **Bozuk PO siteyi düşürmez** | FTP ile doğrulamasız dosya iner; yarım yüklenmiş ya da bozuk sözdizimli bir PO çeviriyi değil, sayfayı öldürebilir | Bozuk PO yüklendiğinde önceki iyi katalog kullanılır, hata kaydedilir, sayfa açık kalır |
| 4.2 | Katalog durumu ekranı | Sahibi bugün yüklemesinin işe yarayıp yaramadığını göremez | Locale başına doluluk, son yükleme zamanı, ayrıştırma hatası görünür |
| 4.3 | "Katalogları yeniden kur" düğmesi | Otomatik geçersizleştirmeye güvenilmediği anda elle kapak gerekir; komut satırı yok | Düğme önbelleği düşürür, sonraki istek PO'yu yeniden okur |
| 4.4 | Yükleme yolu belgesi (sahibi için) | Hangi dosya nereye, hangi kodlamayla | Belge var ve `docs/13` §7'den erişilebilir |

## Owner kararı gerekir mi?

**Hayır.** Karar zaten verildi (kaynak dil İngilizce, çeviriyi sahibi yapar,
FTP ile yükleme etkili olmalı). Bu plan o kararın uygulanmasıdır. Geri
çevrilebilir teknik ayrıntılar — PO ayrıştırıcısının seçimi, önbellek
biçimi, uç nokta yolu — karar verenin işi değildir.

Tek istisna 4.2/4.3: sahibinin panelde bir i18n bölümü görmek isteyip
istemediği bir **ürün** kararıdır. İstemezse 4.1 yine yapılır — o bir
güvenlik ağıdır, tercih değil.

## İlerleme

`I18N-RUNTIME-v1`: **0/4 faz tamam.** Faz 3'ün kilidi kuruludur ve borç
ölçülmüştür; fazın kendisi başlamamıştır.

Bu sayaç `docs/17` §4'teki sabit 38-WP payda sayacından ayrıdır ve onun
yerine geçmez.
