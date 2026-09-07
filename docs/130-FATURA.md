# 130 — Fatura: belge, numara ve e-arşivin AÇIK sınırı (FF-215)

`docs/107` Faz 1.4'ün kod karşılığı. Bu belge KARARLARI ve gerekçelerini
taşır; durum satırları `docs/107`'de, sayısal ve yapılandırılabilir değerler
`config/`'de yaşar — burada tekrar edilmez.

Ödeme paketi (`docs/123`, FF-197) tahsilatı kurdu: kiracı planı seçiyor,
Iyzico'nun sayfasında kartını giriyor, abonelik bir dönem ileri gidiyor ve
defterde bir satır oluşuyor. Eksik olan tek şey, o paranın karşılığında
sahibin eline geçen BELGEYDİ.

---

## 0. Sahibin yolculuğu — Kadıköy'deki kebapçı

Restoran sahibi "Pro"yu seçti, kartını geçirdi, menüsünü yayınladı. Ay sonu
muhasebecisi arıyor:

> *"Zabuno'ya ödediğin paranın belgesi nerede? Gider yazacağım."*

**Önce (2026-09-07 sabahı, ölçüldü):** Panelde defter vardı — "cash / revenue,
1.499,00 ₺". Muhasebecinin işine yaramaz: defter Zabuno'nun iç kaydıdır, ne
numarası vardır ne satıcısı ne alıcısı. Sahibin verecek bir şeyi yoktu.

**Şimdi:** Panelde **Invoices** bölgesi var. Her tahsilatın numaralı bir
belgesi (`2026-000001`), tarihi, tutarı ve **Download PDF** bağlantısı var.
Sahip PDF'i indirir, muhasebecisine yollar. Belgede satıcı, alıcı, satılan
şey (`Pro — 30 days of subscription`) ve tahsil edilen tutar yazar.

**Ama belgenin üstünde bugün şu da yazıyor:**

> *The issuing company details below are incomplete: the fields marked "not
> yet provided" have not been entered yet. This document is not a complete
> commercial invoice until they are.*

Çünkü Zabuno'nun ünvanı, adresi ve vergi numarası `.env`'de HÂLÂ BOŞ
(FF-198, `docs/124`). Belge bunu söylüyor; uydurmuyor. Muhasebeci bu PDF'i
gördüğünde eksiği görür ve sahibe "şirket bilgilerini gir" der — belgeyi
gerçek sanıp gidere yazmaz.

**Kalan engel, ürünün değil sahibin masasında:** e-arşiv/e-fatura kesmek
Türkiye'de kayıtlı bir entegratörle sözleşme ya da GİB portalı ister. Bu bir
kod eksiği değil, yapılmamış bir sözleşmedir (§K4).

---

## 1. Ölçülen başlangıç ve varış

| | Önce (2026-09-07, ölçüldü) | Şimdi |
| --- | --- | --- |
| Tahsilatın izi | `ledger_entries` (iç kayıt) + `payment_transactions` | + `invoices` — numaralı, değişmez belge |
| Numara | Yok | Seri = takvim yılı; sıralı ve BOŞLUKSUZ, veritabanı düzeyinde tahsis |
| İade | Defterde ters kayıt, dönem düşülür (`docs/123` §K6) | + karşı belge (`credit_note`), aynı seriden sıradaki numara |
| Satıcı/alıcı | Hiçbir yerde belge olarak yok | Belgeye MÜHÜRLENİR; satıcı boşsa "not yet provided" |
| KDV | Yok | Yapılandırmadan; BOŞ bırakıldı, ayrım gösterilmiyor |
| Kâğıt | Yok | `GET …/invoices/{id}/document.pdf` — A4, mPDF |
| Panel | Defter tablosu | + Invoices bölgesi (320 pikselde ölçüldü) |
| e-arşiv | Yok | Port var, adaptör YOK; "yapılandırılmadı" diye DURUR |

---

## 2. Kararlar

### K1 — Belge tahsilattan doğar, elle kesilmez

`ManageCheckout::settle()` başarılı bir ödemeyi uç duruma taşıdığında belge
de kesilir; `ManageInvoices::ensureForPayment` çağrılır. Panelde hiçbir
"fatura oluştur" düğmesi yoktur ve olmayacaktır: elle kesilebilen bir belge
kanıt değeri taşımaz.

Çağrı, geçişi YAPAN dala değil, her uzlaşma denemesine bağlıdır. Webhook ile
tarayıcı geri dönüşü aynı ödeme için sırayla gelir ve yalnız biri geçişi
yapar; belgeyi yalnız o dala bağlasaydık, o çağrı belgeyi yazamadığında
(örneğin fatura profili o an okunamadığında) ikinci çağrı eksiği hiç
kapatamazdı. `ensureForPayment` tekrar tekrar çağrılabilir ve kesilmiş
belgeyi yeniden kesmez.

Başarısız ödemenin belgesi olmaz: tahsil edilmeyen paranın karşılığı yoktur.

### K2 — Numara SIRALI, BOŞLUKSUZ ve yarışa dayanıklı; güvence veritabanında

Mali bir belgenin numarası bir görünüm tercihi değildir. İki eşzamanlı
tahsilat aynı numarayı alırsa iki ayrı satış tek belgeye benzer; numara
atlarsa "kesilmemiş bir fatura mı var" sorusu cevapsız kalır.

Bu yüzden numara UYGULAMADA üretilmez. "Önce en büyüğü oku, bir ekle, sonra
yaz" iki isteğin arasına sığan bir boşluktur ve o boşlukta ikisi de aynı
sayıyı okur. Üç güvence üst üste durur ve **üçü de veritabanının kendi
işidir**:

1. **Atomik artırım.** `UPDATE invoice_number_sequences SET next_number =
   next_number + 1 WHERE series = ?`. Sayıyı veritabanı hesaplar; o `UPDATE`
   satır kilidini alır ve ikinci isteği kendi sırası gelene kadar bekletir.
2. **Tahsis ile yazma AYNI işlemde.** Belge yazılamazsa sayaç da geri sarar.
   Bir PostgreSQL dizisi (`SEQUENCE`) bunu yapamaz: geri sarılan işlemde
   tükettiği sayıyı geri vermez ve seride açıklanamayan bir boşluk bırakır.
   Sayacın bir TABLO olmasının tek nedeni budur.
3. **`unique(series, number)`.** Uygulama ne yaparsa yapsın, aynı numara bir
   seride ikinci kez yazılamaz.

**Ölçüm** (`tests/Feature/Billing/InvoiceNumberingRaceTest.php`, iki motorda
birden): beş belge 1–5 numaralarını alıyor; aynı (seri, numara) çifti
doğrudan yazılmaya çalışıldığında veritabanı reddediyor; `AbortOnInsertFixture`
ile INSERT'i düşürdüğümüzde sayaç geri sarıyor ve SIRADAKİ belge 2 oluyor
(3 olsaydı denetimde açıklanamayan bir boşluk kalırdı).

**Gerçek eşzamanlılık ayrı ve yalnız PostgreSQL'de**
(`PostgresInvoiceNumberingConcurrencyTest`): ikinci bir bağlantı, ilk
bağlantının commit etmediği tahsisin üstünden geçmeye çalışıyor ve
`lock_timeout` ile bekletildiği ölçülüyor. SQLite'ın bellek veritabanında
ikinci bağlantı aynı veritabanı bile değildir; orada sonuç "geçti" değil
**BİLİNMİYOR** ve test o cümleyle atlanıyor. CI'ın `DB_CONNECTION=pgsql`
ayağı ölçer.

Biçim: `[ÖNEK-]SERİ-NNNNNN`, örneğin `2026-000001`. Seri takvim yılıdır,
numara her yıl 1'den başlar. Önek `config/billing.php#invoice.number_prefix`
ile gelir ve **varsayılanı yoktur**: uydurulmuş üç harflik bir seri kodu,
GİB'in e-arşiv seri biçimini taklit ederdi ve belge olmadığı bir şeye
benzerdi.

### K3 — Belge DEĞİŞMEZ; düzeltmenin yolu karşı belgedir

`invoices` tablosunda `updated_at` YOKTUR — `ledger_entries` ile aynı
gerekçe: bir faturanın güncellenebildiği yer fatura değildir.

İade geldiğinde (`docs/123` §K6) fatura silinmez, tutarı değişmez: aynı
seriden sıradaki numarayı alan bir `credit_note` kesilir ve
`counter_of_invoice_id` hangi faturayı tersine çevirdiğini söyler. Defterdeki
ters kayıtla birebir aynı karar — hem satış hem iadesi görünür kalır.

`unique(payment_transaction_id, kind)`: bir ödemenin bir faturası ve en fazla
bir karşı belgesi olur.

### K4 — e-arşiv/e-fatura: PORT var, ADAPTÖR yok; sessiz başarı YOK

Türkiye'de e-arşiv ya da e-fatura kesmek kayıtlı bir entegratörle sözleşme
ya da GİB portalı ister. Sözleşme **sahibin işidir ve bugün yoktur.** Bu
paket o sözleşmenin YERİNİ açar, kendisini değil.

`EArchiveGatewayPort`'un bugünkü tek uygulaması `UnconfiguredEArchiveGateway`
ve o bir "boş nesne" (null object) DEĞİLDİR — boş nesne sessizce başarılı
döner, bu sınıf **durur**: `submit()` `EArchiveGatewayNotConfiguredException`
fırlatır.

Daha da sert bir kural sözlüktedir: `EArchiveDispatchState` yalnız
`not_configured` ve `not_dispatched` taşır. **"gönderildi" diye bir durum
ürünün sözlüğünde YOKTUR** ve `EArchiveGatewayPortTest` sözlüğü kapalı
tutar. Aradaki fark, bir gün vergi denetiminde kesilmemiş bir faturayı
kesilmiş sanmakla sanmamak arasındaki farktır.

Panel de aynı cümleyi kurar ve iyimser bir söz vermez: *"No e-Arşiv /
e-Fatura provider is connected, so these records have not been sent to any
tax authority."* — "yakında e-fatura" diye bir anahtar yok ve olmayacak.

### K5 — KDV oranı yapılandırmadan gelir ve BOŞ bırakıldı

Bu depoda ölçülmüş bir KDV oranı kaynağı yok. Bildiğimizi sandığımız bir
oranı gömmek, belgeye yanlış bir vergi tutarı yazdırmanın en sessiz yoludur.

`config/billing.php#invoice.vat_rate_basis_points` **varsayılansızdır**
(onbinde: %20 → 2000). Boşken belge yalnız TAHSİL EDİLEN tutarı gösterir ve
ayrımın neden olmadığını söyler. Sıfır yazmak yanlış olurdu: sıfır "KDV yok"
demektir, oysa doğru cümle "bilinmiyor"dur — bu yüzden `net_minor`,
`vat_minor` ve `vat_rate_basis_points` sütunları NULL kalır, 0 değil.

Sahip oranı doldurduğunda aynı zamanda şunu BEYAN ETMİŞ olur: plan fiyatları
bu oranda KDV DAHİLDİR. Ayrım o beyandan türetilir ve belgeye o günkü
oranıyla mühürlenir; oran sonradan değişse bile kesilmiş belge değişmez.

Hesap tamsayıdır (`VatBreakdown`): kayan noktalı bir bölme kuruşu bir yukarı
ya da bir aşağı kaydırıp toplamı tutmayan bir belge üretebilirdi. Net
yarım-yukarı yuvarlamayla bulunur, KDV artıktan gelir; `net + kdv` her zaman
tahsil edilen tutara eşittir.

### K6 — Ekran kipi ile KÂĞIT kipi ayrıdır

Ekranda düzgün duran bir tablo kâğıtta taşar; kâğıtta yatay kaydırma diye
bir şey yoktur. İkisi ayrı yazıldı:

- **Ekran:** `InvoiceListPanel` (sunum) + `WorkspaceInvoices` (kap). Tablo
  YOK — beş sütunlu bir tablo 320 pikselde ya yana kayar ya sütunları
  okunmaz olur. Her belge kendi bloğunda, tek sütunda; indirme bağlantısı
  44 piksel. Altı hikâye `scripts/mobile-ux-audit` ile 320×568'de ölçüldü.
- **Kâğıt:** `InvoiceDocumentHtml` + `MpdfInvoiceDocumentAdapter` — A4,
  sabit sütun genişlikli tablolar, uzun adres SARAR. QR baskı sayfasıyla
  aynı mPDF yolu.

Belgenin metni katalogda değil, belgenin kendi kaynak dilindedir (İngilizce)
— `LegalDocument` ile aynı karar: belge bir arayüz yüzeyi değil, bir
belgedir.

### K7 — Satıcı ve alıcı belgeye MÜHÜRLENİR

Kesilmiş bir belgenin üstündeki ünvan, sahibin altı ay sonra `.env`'i
doldurmasıyla geriye dönük değişemez. Bu yüzden satıcı
(`config/legal.php#company`) ve alıcı (`billing_profiles`) belgeye anlık
kopya olarak yazılır.

Satıcı alanları NULL olabilir ve bugün hepsi NULL'dır: belge o alanları
"not yet provided" gösterir ve eksik olduğunu ayrıca bir cümleyle söyler.
Uydurulmuş bir ünvan, sözleşmenin ve belgenin tarafını yanlış gösterirdi.

**Alıcı alanları NULL olamaz: alıcısı olmayan bir fatura kesilmez.** Ödeme
yolu fatura profilini zaten zorunlu tutuyor (`docs/123` §K3). Buraya
profilsiz düşen bir kayıt (elle yazılmış eski bir satır) sessizce yutulmaz:
`GET …/invoices` cevabındaki `payments_without_document` onu SAYAR ve panel
o sayıyı yazar.

---

## 3. Yüzeyler

| Uç | Kim | Ne |
| --- | --- | --- |
| `GET /api/workspaces/{w}/invoices` | billing.view | Belgeler, `payments_without_document`, `earchive.configured` |
| `GET /api/workspaces/{w}/invoices/{id}/document.pdf` | billing.view, 30/dk | A4 PDF indirme |

Defterle aynı kapı ve aynı sessizlik: yetkisiz istek varlığı bile sızdırmaz
(403 değil, 404). Yazma ucu YOKTUR.

---

## 4. Kapılar (ölçüldü)

Bkz. bu paketin commit mesajı — sayılar orada, burada tekrar edilmez.

---

## 5. Ürün iddiası

**Çalışır (ölçüldü):** başarılı tahsilat → numaralı, değişmez belge; aynı
ödemenin tekrarı ikinci belge yaratmaz; iade → karşı belge; numara sıralı,
boşluksuz ve yazma düşerse sayaç geri sarar; belge A4 PDF olarak indirilir;
şirket bilgisi boşken belge EKSİK olduğunu söyler; KDV oranı yokken ayrım
gösterilmez; e-arşiv kapısı "yapılandırılmadı" diye durur; panel 320
pikselde ölçüldü.

**Çalışmaz / kanıtlanmadı:**

1. **e-arşiv/e-fatura gönderimi.** Kod yok ve olmayacak — önce sahibin bir
   entegratörle sözleşmesi ya da GİB portalı erişimi gerek. O gün porta
   ikinci bir adaptör yazılır.
2. **Belgenin tam bir ticari fatura olması.** `.env`'deki yedi şirket alanı
   (`LEGAL_COMPANY_*`) doldurulana kadar belge kendi eksikliğini taşır.
3. **KDV ayrımı.** `BILLING_INVOICE_VAT_RATE_BASIS_POINTS` sahibin
   muhasebecisinden gelecek bir olgudur; girilene kadar belge vergi ayrımı
   göstermez.
4. **Seri harfleri.** `BILLING_INVOICE_NUMBER_PREFIX` boş; muhasebeci bir
   seri belirlerse girilir.
5. **Gerçek eşzamanlılık ölçümü** yerelde yapılamaz (PostgreSQL yok);
   CI'ın `pgsql` ayağında ölçülür.
