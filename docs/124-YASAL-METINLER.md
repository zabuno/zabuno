# 124 — Yasal metinler: sekiz belge, onay kaydı ve çerez tercihi

**Paket:** FF-198 (`docs/107` Faz 1.2). **Kaynak dil:** İngilizce (`docs/118`
E4). **Durum:** yayında, **hukukçu incelemesi bekliyor** — her sayfa bunu
üstte söylüyor ve `LEGAL_REVIEWED_AT` dolana kadar söylemeye devam edecek.

## 0. Ölçülen boşluk (2026-09-06, paket öncesi)

| Konu | Önce |
| --- | --- |
| `/terms`, `/privacy`, `/kvkk` | "hazırlanıyor" yazan 13 satırlık yer tutucu |
| Mesafeli satış, ön bilgilendirme, iptal-iade, çerez politikası, ticari ileti izni | Yok |
| Kayıt ekranında onay kutusu | Yok |
| Onay kaydı tablosu | Yok |
| Çerez tercihi | `MeasurementConsent::granted()/denied()` vardı, çerezi **yazan uç yoktu** — ölçüm fiilen hep kapalıydı |

## 1. Hangi belge neyi kapsar

Sekiz belge `app/Infrastructure/Legal/Documents/` altında, her biri
`LegalDocument` (anahtar, sürüm, yürürlük tarihi, başlık, numaralı bölümler)
olarak. Tek şablon (`public/legal.blade.php`), tek denetleyici
(`ShowLegalDocumentController`), kabuğun içinde, ikonsuz.

| Adres | Anahtar | Ne söyler | Nerede kabul edilir |
| --- | --- | --- | --- |
| `/terms` | `terms` | Hizmetin ne olduğu (çalışma alanı, menü, kalıcı karekod, isteğe bağlı sipariş/puanlama/ekip/AI aktarımı), hesap, içerik, kabul edilebilir kullanım, ücretli planlara atıf, sorumluluk, uygulanacak hukuk | Kayıt ekranı, zorunlu kutu |
| `/privacy` | `privacy` | Hangi veri toplanıyor (kayıt, çalışma alanı, misafir olayları, günlük dönen takma kimlik), alıcılar (Mailgun, Iyzico, yapılandırılmış AI sağlayıcısı, onaydan sonra GTM), saklama, haklar | Kayıt ekranı, zorunlu kutu |
| `/kvkk` | `kvkk` | 6698 sayılı Kanun madde 10 aydınlatması: veri sorumlusu, işlenen veri, amaç, hukuki sebep (madde 5), aktarım (madde 9), madde 11 hakları. **İngilizce yazıldı**, adres `/kvkk` kaldı | Aydınlatma; hesap verisi talebi bölümü burada |
| `/distance-sales` | `distance-sales` | Taraflar, konu, fiyat (sipariş özetine bağlı), elektronik ifa, cayma (tüketici için kanuni süre), iptal/iade, uyuşmazlık | **Ödeme adımı** — ff-197 paketi `ConsentRecorder::recordCheckout` çağırır |
| `/pre-information` | `pre-information` | Yönetmelik madde 5 başlıkları: satıcı, hizmet, fiyat, ifa, cayma, şikâyet, geçerlilik | Ödeme adımı (ff-197) |
| `/refund-policy` | `refund-policy` | İptal yolu (**bugün iletişim formu**, ürün içi iptal yok), iptalin dönem sonunda etkisi, iade hâlleri, tüketici cayması | Bilgilendirme |
| `/cookies` | `cookies` | Ürünün gerçekten kurduğu çerezler (oturum, `XSRF-TOKEN`, beni hatırla, `zabuno_guest_locale`, `zabuno_measurement_consent`; tema çerez değil yerel depolama), üçüncü taraf ölçüm yalnız onayla, **tercih bölümü** | Şerit ve bu sayfa |
| `/marketing-consent` | `marketing-consent` | Yalnız e-posta kanalı, 6563 dayanağı, geri alma yolu (iletişim formu), neyin kaydedildiği, hangi iletilerin pazarlama olmadığı | Kayıt ekranı, **isteğe bağlı** kutu; altbilgide değil, kayıt formundan bağlanır |

**Metinlerin sınırı:** ürünün depodan ölçülen davranışı. Yanıt süresi,
fiyat, çalışma süresi yüzdesi ya da olmayan bir özellik yazılmadı; bir test
bunu her belgede tarıyor (`LegalDocumentPagesTest`
`no_document_promises_a_response_time_or_an_invented_price`). Kanundan gelen
süre (tüketici cayması) kanuna atıfla yazıldı, uydurulmadı.

## 2. Hangi bilgi `.env`'den gelir — ve gelmediğinde ne olur

`config/legal.php#company` yedi alanı yalnız ortamdan okur, **varsayılanı
yoktur**:

```
LEGAL_COMPANY_LEGAL_NAME  LEGAL_COMPANY_ADDRESS  LEGAL_COMPANY_MERSIS
LEGAL_COMPANY_TAX_OFFICE  LEGAL_COMPANY_TAX_NUMBER
LEGAL_COMPANY_EMAIL       LEGAL_COMPANY_PHONE
LEGAL_REVIEWED_AT         (Y-m-d)
```

Belgeler `{company.legal_name}` gibi yer tutucular taşır; sayfa çizilirken
`CompanyProfile` doldurur. **Girilmemiş alan metinde "not yet provided"
olarak görünür.** Bugün yedisi de boş; yani sözleşmenin tarafı "not yet
provided" diye okunuyor ve bu doğru — bir tüzel kişi adı uydurmak,
sözleşmenin tarafını yanlış göstermek olurdu.

`LEGAL_REVIEWED_AT` geçerli bir tarih olana kadar her sayfa üstte "This text
is pending legal review" notu taşır. "yes" ya da yazım hatası inceleme
sayılmaz; not kalır (`LegalReview`).

## 3. Kayıt onayı ve onay defteri

- Kayıt formunda iki kutu: **zorunlu** "I have read and accept the Terms of
  Service and the Privacy Policy." (altında iki 44 piksellik bağlantı) ve
  **isteğe bağlı** ticari ileti izni (varsayılanı boş; önceden işaretli kutu
  onay değildir). Kutu boşken form sunucuya hiç gitmez; sunucu aynı kuralı
  ayrıca uygular (`CreateNewUser`, `accepted`) ve onaysız kayıt **422** döner.
- `consent_records` (göç `2026_09_06_002000`): `user_id`, `workspace_id`,
  `kind` (`registration` · `marketing` · `checkout`), `document_key`,
  `document_version`, `granted`, `ip`, `user_agent`, `recorded_at`. **Yalnız
  eklenir**; kullanıcı silinirse satır kalır (`nullOnDelete`) — kaydın en
  değerli olduğu an hesabın artık olmadığı andır. E-posta ya da ad
  kopyalanmaz.
- Kayıt anında `terms` + `privacy` (iki satır), işaretlendiyse
  `marketing-consent` (üçüncü satır). Hesap ve onay **aynı veritabanı
  işleminde** yazılır: biri düşerse ikisi de geri alınır.
- Sürüm kütüphaneden okunur, çağırandan değil: metin "0.2" olduğu gün kayıt
  "0.2" yazar.
- `ConsentRecorder::recordCheckout` hazır; ödeme paketi (ff-197) onu ödeme
  adımında çağırır. Bu pakette ödeme akışı **yok**.

## 4. Çerez tercihi — JavaScript'siz

- `POST /consent/measurement` (`decision=accept|decline`, `return_to`),
  CSRF, hız sınırlı. Çerez `zabuno_measurement_consent`, 365 gün,
  `httpOnly`, `Lax`. Dönüş adresi yalnız bu sitenin bir yolu olabilir;
  dış adres ana sayfaya düşer.
- Şerit **üç koşulda** çizilir: ölçüm yapılandırılmış (GTM kap kimliği var),
  karar verilmemiş, sayfa gizlemesini istememiş. Yapılandırılmamış bir ölçüm
  için izin istenmez — olmayan bir şeye onay toplamak olurdu. Şerit yapışkan
  ve altta; ilk ekranı içerikten önce doldurmaz; `<footer>` değildir.
- `/cookies` mevcut kararı gösterir (`undecided` · `granted` · `denied`) ve
  aynı formla yeniden sorar; orada şerit gizlenir.
- Kabul → GTM konteyneri yüklenir; ret ya da karar yok → **hiçbir şey**
  yüklenmez (`MeasurementConsentEndpointTest`).

## 5. Ölçüm (2026-09-06)

**Statik site, gerçek Chrome, 320×568** (`site:export-static` +
`scripts/mobile-ux-audit`, ölçüm yapılandırılmış ve karar verilmemişken —
yani şerit ekrandayken): 12 sayfa; **sekiz yasal sayfanın hepsinde yapısal
bulgu sıfır** (yatay taşma yok, kırpılma yok, 44 piksel altı hedef yok,
sıkışık aralık yok); kullanılabilir içerik genişliği **309/320** (oran
0.97). Aynı koşuda `index.html`, `help/` ve `pricing/` bu paketin dışındaki,
önceden var olan satır içi bağlantı bulgularını taşıyor ("Contact us"
79×18, "Ask us" 48×18; yardım sayfasında menü bağlantıları 43-44 piksel
sınırında) — bu paket #279'un dosyalarını yeniden düzenlemedi, bulgular
raporlanır.

**Kayıt formu, Storybook, 320×568** (`surface-auth-registerform--default`,
`.storybook/main.ts`'e `auth/**` hikâye kökü eklendi): **yapısal bulgu
sıfır**, kullanılabilir genişlik **320/320**. On hedefin hepsi ölçüldü:
dört metin girdisi etiketiyle 320×73, zorunlu onay kutusu etiketiyle
320×48, isteğe bağlı kutu 320×72, üç belge bağlantısı 190×44 / 169×44 /
320×44, "Register" düğmesi 320×44; kırpılma yok, belge genişliği 320.

**ÖNCE/SONRA — taban (02534e6) ile bu paket aynı yedi hikâyede, aynı
koşulda:** taban Storybook'u `git archive` ile çıkarılıp ayrı derlendi ve
tam koşuda "yeni ihlal" görünen yedi hikâye iki tarafta yeniden ölçüldü.
Yedisi de **birebir aynı**: `profilepage--without-brand-permission`
tight-gap (taban listesinde zaten var), `qrcodelistitem--named`
wasted-width (ortam-duyarlı), diğer beşi temiz. Yani bu paket hiçbir
hikâyeye yeni ihlal getirmedi; tam koşudaki altı "YENİ" satırı ölçüm
gürültüsüydü.

**Ölçüm aracı hakkında iki gözlem (bu paketin dışı, `scripts/` değişmedi):**
(1) `mobile-ux-audit` gezintiden 450 ms sonra ölçüyor; soğuk ilk yüklemede
Storybook kökü henüz BOŞ ve sonuç "sıfır bulgu" — boş bir ölçüm. Kök çocuk
taşıyana kadar bekleyen bir kopya ile aynı hikâyeler gerçekten ölçüldü;
yukarıdaki sayılar o kopyadan. (2) Bu makinede 318 hikâyelik tam koşu
100-300. hikâye civarında Chrome `Page.navigate` zaman aşımıyla üç kez
düştü; iki yarım hâlinde koşuldu, yarım-B bitti, yarım-A iki kez düştü.
Uzun koşudaki gürültü, taban/paket karşılaştırmasıyla ayıklandı.

## 6. Hukukçu incelemesi ve Türkçe sürüm — sahibe bağlı

1. **İnceleme:** metinler standart ve dürüst ama bir hukukçu okumadı. Sahip
   bir hukukçuya okutur, gerekli düzeltmeler belge sınıflarında yapılır,
   sürüm yükseltilir, `LEGAL_REVIEWED_AT` doldurulur. O gün not kalkar.
2. **Şirket bilgisi:** yedi `.env` alanı sahibin girmesiyle dolar; kod
   değişmez.
3. **Türkçe sürüm:** çeviri kilidi kapalı (`docs/105` §8, `docs/118` E4).
   Bu pakette **tek bir Türkçe cümle yazılmadı**; PO dosyalarına yalnız boş
   `msgstr` ile anahtar girdi. Sahip `ÇEVİRİLERE BAŞLA` dediğinde belge
   katmanına Türkçe yuva açılır — şablon, kütüphane ve kayıt dilden
   bağımsızdır.
4. **Saklama süresi ve ticari ileti geri alma kaydı:** hukuk kararı;
   defter yalnız ekler, silmez.
5. **İYS (İleti Yönetim Sistemi):** entegrasyon yok ve metin bunu iddia
   etmiyor. Ticari e-posta gönderilmeye başlanmadan önce sahibin kararı.

**kullaniciYolculugu:** Kadıköy'deki kebapçı kayıt ekranında "I have read and
accept the Terms of Service and the Privacy Policy" kutusunu görür; altındaki
"Read the Terms of Service" bağlantısına dokunur, sözleşme aynı kabuğun
içinde sürüm 0.1 ve 2026-09-06 yürürlük tarihiyle açılır, üstte "hukuki
inceleme bekliyor" notunu ve tarafın "not yet provided" olduğunu okur — çünkü
sahip henüz şirket bilgisini girmedi. Kutuyu işaretlemeden "Register"a
basarsa form gitmez; işaretleyince hesabı açılır ve `consent_records`a iki
satır düşer. Fiyat sayfasında altta kompakt bir şerit ölçüm izni sorar;
"Decline" derse hiçbir üçüncü taraf betiği yüklenmez ve bir yıl boyunca
sorulmaz. Ödeme adımı bugün hâlâ yok (Faz 1.1).
