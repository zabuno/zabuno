# 140 — Kurumsal sözleşmeler: DPA, hizmet seviyesi, kabul edilebilir kullanım, üçüncü taraf lisansları

**Paket:** FF-228 (`docs/107` Faz 3.2). **Kaynak dil:** İngilizce (`docs/118`
E4). **Durum:** dört belge yayında, **hukukçu incelemesi bekliyor** — dördü de
üstte bunu söylüyor ve `LEGAL_REVIEWED_AT` dolana kadar söylemeye devam edecek.

> **Bu belge ne yaptığını ve NE YAPMADIĞINI birlikte söyler.** Bir hizmet
> seviyesi sayfası açtık ama içinde tek bir rakam yok — ve bu bir eksiklik
> değil, paketin en önemli kararı. Aşağıda neden öyle olduğu, kimin
> dolduracağı ve doldurulduğu gün ne olacağı yazıyor.

---

## 0. Sahibin sorusu: zincirin hukukçusu üç soru sordu, hangi sayfa hangisini cevaplıyor?

Bir zincir "menülerimizi Zabuno'ya taşıyalım" dediğinde işi yapan kişi
restoran müdürü değildir; satın alma ve hukuk birimidir. O birim ürünü
denemez, **soru sorar** — ve cevap veremediğin her soru satın alma sürecinde
bir "hayır"dır.

Bugüne kadar bu dört sorunun cevabı yoktu. Şimdi var:

| Zincirin sorusu | Hangi sayfa cevaplıyor | Cevabın özü |
| --- | --- | --- |
| *"Verimizi kimler görüyor, nerede duruyor, silmek istersek ne oluyor?"* | `/data-processing` — Veri İşleme Sözleşmesi (DPA) | Alt işleyen listesi sayfa açıldığında ÖLÇÜLÜR; veri Almanya'daki sunucuda; menüyü kendiniz CSV olarak indirirsiniz, hesap silme iletişim formundan |
| *"Ne kadar ayakta kalacağını taahhüt ediyorsunuz?"* | `/sla` — Hizmet Seviyesi Koşulları | **Bugün hiçbir rakam taahhüt edilmiyor** ve sayfa bunu ilk bölümde söylüyor; taahhüt için gereken dört karar adıyla sayılıyor |
| *"Neyi yasaklıyorsunuz ve ihlalde ne yapıyorsunuz?"* | `/acceptable-use` — Kabul Edilebilir Kullanım Politikası | Yasaklar yazılı; yaptırım **ürünün gerçekten yapabildiği** kadar — hız sınırı ve dosya karantinası otomatik, gerisi elle |
| *"Bu yazılım kimin kodunu taşıyor, hangi lisansla?"* | `/third-party-licenses` — Üçüncü Taraf Lisansları | Liste `composer.lock` ve `package-lock.json`'dan TÜRETİLİR; bugün 13 + 7 doğrudan paket, 100 + 494 dolaylı |

**Restoran yolculuğuyla:** on şubeli bir zincirin hukukçusu Cuma günü bir
e-posta yazıyor — *"veri işleme sözleşmenizi ve SLA'nızı gönderin."* Önce
gönderilecek bir şey yoktu; birinin oturup Word'de bir belge yazması,
sahibin de imzalaması gerekirdi ve o belge ürünün gerçekte ne yaptığından
bağımsız olurdu. Şimdi dört bağlantı gönderiliyor. Hukukçu SLA'yı açtığında
rakam görmüyor — **ve bu iyi bir şey**: rakam görseydi, ilk kesintide o rakam
sözleşmeye aykırılık olurdu. Onun yerine "şu dört karar verilmedi" yazan bir
sayfa görüyor ve konuşma doğru yerden başlıyor.

---

## 1. Ölçülen boşluk (2026-09-08, paket öncesi)

| Konu | Önce |
| --- | --- |
| Veri işleme sözleşmesi (DPA) | Yok. Gizlilik Politikası ve KVKK aydınlatması vardı ama ikisi de **veri sorumlusu** sıfatıyla yazılmış; müşteri adına **işleyen** sıfatıyla bir sözleşme yoktu |
| Alt işleyen listesi | Yok. Gizlilik Politikasında düz yazı içinde üç ad geçiyordu (Mailgun, Iyzico, "yapılandırılmış AI sağlayıcısı") ve bir sağlayıcı eklendiğinde eskiyecekti |
| Hizmet seviyesi (SLA) | Yok |
| Kabul edilebilir kullanım | Hizmet Koşullarında **tek paragraf**; ihlalde ne olacağı hiç yazılmamış |
| Üçüncü taraf lisansları | Yok |
| Barındırma bilgisi belgede | Yok. `docs/42`/`docs/43` biliyordu, hiçbir yasal metin söylemiyordu |

---

## 2. Dört belge, tek sistem — ikinci bir mekanizma kurulmadı

Dördü de **var olan** yasal belge sistemine eklendi (FF-198, `docs/124`):
`LegalDocument` (anahtar, sürüm, yürürlük tarihi, başlık, numaralı bölümler),
tek şablon (`resources/views/public/legal.blade.php`), tek denetleyici
(`ShowLegalDocumentController`), sürüm ve yürürlük tarihi görünür, "pending
legal review" notu üstte, `lang` özniteliği belgenin kendi dilinde.

Kütüphane **dokuzdan on üçe** çıktı ve anahtar listesi artık **tek yerde**:
`LegalLibraryPort::KEYS`. Rota kütüğü (`routes/web.php`) o listeden döngü
kuruyor; önceden adresler elle yazılıydı ve bir belge eklenip rotası
unutulsaydı sayfa 404 dönerdi — bir testle kilitli (`CorporateContractsTest`
`every_key_the_library_knows_has_a_route_that_answers`).

| Adres | Anahtar | Bölüm | Kelime | Satıcı kimliği zorunlu mu |
| --- | --- | --- | --- | --- |
| `/data-processing` | `data-processing` | 14 | 1.956 | **Evet** |
| `/sla` | `sla` | 8 | 883 | **Evet** |
| `/acceptable-use` | `acceptable-use` | 9 | 953 | Hayır |
| `/third-party-licenses` | `third-party-licenses` | 6 | 491 | Hayır |

**Satıcı kimliği zorunluluğu ne demek** (FF-216'dan devralınan kural):
`{company.*}` alanları `.env`'de boşken belge üstte görünür bir uyarı bandı
taşır, hangi alanların girilmediğini adıyla sayar, `X-Robots-Tag: noindex,
nofollow` döner ve sitemap'ten düşer. DPA ve SLA bu kurala **girdi**: bir veri
işleme sözleşmesinin "işleyen"i ve bir hizmet seviyesi taahhüdünün taahhüt
edeni adıyla anılmak zorundadır — tarafı "not yet provided" yazan bir DPA
gönderilebilir bir belge değildir.

Kabul edilebilir kullanım ve lisans listesi bu kuralın **dışında**: ikisi de
bir sözleşme değil bir **bildirimdir** ve tarafsız hâlleriyle de doğrudur;
gereksiz yere `noindex` yapmak, bir zincirin arama motorundan bulacağı iki
sayfayı gizlemek olurdu.

**`/sla` adı neden `/service-level` değil:** `/kvkk` ile aynı gerekçe. Bir
zincirin satın alma birimi aradığı şeyi o üç harfle arar; adres o kelimeyi
taşır, belgenin **başlığı** ise ("Service Level Terms", "Agreement" değil) ne
taahhüt edip etmediğini söyler.

---

## 3. Alt işleyen listesi neden ELLE YAZILMADI — ve nasıl güncel kalıyor

**Karar: türetildi.** Görev iki seçenek sunuyordu ("ya türetilsin ya da
eskidiğini yakalayan bir kapı olsun") ve ikisi de yapıldı — çünkü tek başına
hiçbiri yetmiyor.

### 3.1 Neden bu kararı verdik

Elle yazılmış bir alt işleyen listesi, **yazıldığı gün doğru olan ve ertesi
gün sessizce yanlışa dönen** bir belgedir. Sahip süperadmin panelinden bir AI
sağlayıcısı açtığında ya da ölçüm aracını devreye aldığında, listeyi
güncellemeyi hatırlayacak kimse yoktur. Ve bir DPA'nın yanlış olduğu tam
olarak orasıdır: müşteriye "verinizi şu üç şirket görüyor" dedikten sonra
dördüncüsünü sessizce eklemek, sözleşmeye aykırılıktır.

### 3.2 Liste nereden ÖLÇÜLÜYOR

`MeasuredSubprocessors` üç kaynaktan okur ve sayfa **her çizildiğinde**
yeniden okur:

1. **Barındırma** — `config/legal.php#hosting`. Verinin fiziksel olarak
   durduğu yer; koşulsuz, her dağıtımda var.
2. **Kimlik kasası** — `PlatformCredentialAdminPort::all()`. Kasada **etkin
   bir kaydı olmayan** bir sağlayıcı bugün veri işlemiyordur ve listede yer
   almaz. Mailgun ve Iyzico için sunucu `.env` yedeği de sayılır
   (`docs/93` FF-36 aktarımı): kasa boşken env devrede olabilir.
3. **Ölçüm** — `config/analytics.php`. GTM kap kimliği boşsa tek bir script
   yüklenmez, hedef kapalıysa CSP engeller. İkisi de açıkken bile araç ancak
   ziyaretçi kabul ettikten sonra çalışır ve metin bunu yazar.

**Sır okunmaz.** Enjekte edilen port `PlatformCredentialAdminPort`'tur ve o
port bir sırrı geri okuyamaz — yalnız "var mı / etkin mi" söyler. Herkese açık
bir sayfayı çizen kod bir anahtarın değerine **fiziksel olarak** erişemez.

### 3.3 Bugün kaç kayıt var

**Bir.** Yerel geliştirme ortamında (`.env`'de posta/ödeme anahtarı yok, GTM
kap kimliği boş) liste yalnız barındırmayı sayıyor: **netcup GmbH, Karlsruhe,
Almanya**. Sayı üretim sunucusunda farklı olacaktır ve o farkı kimsenin elle
yazmasına gerek yok — sayfa açıldığında kendisi sayar.

### 3.4 Kapı: eskiyeceği gün CI kırılır

Türetme tek başına yetmez, çünkü **yeni bir sağlayıcı tarif edilmeden**
eklenebilir. Bu yüzden iki kapı kondu:

- `MeasuredSubprocessors::describe()` **varsayılansız bir `match`**tir.
  `CredentialProvider`'a bir case eklendiği anda `UnhandledMatchError` fırlar;
  `CorporateContractsTest` bunu **her sağlayıcı için tek tek** ölçer
  (`every_credential_provider_is_described_for_the_subprocessor_list`).
- `config/analytics.php#destinations` içindeki her anahtarın
  `MeasuredSubprocessors::MEASUREMENT_TOOL_NAMES` içinde bir adı olması
  ölçülüyor. GTM'e yeni bir araç eklemek CSP kaynağı eklemeyi gerektirir; o
  gün adı da eklenmezse araç sessizce listenin dışında kalırdı.

### 3.5 Kasa okunamazsa

Boş liste dönmek **yalandır**: "hiçbir alt işleyen yok" ile "bakamadık" aynı
cümle değildir. Envanter `vaultUnreadable` taşır ve belge okuyucuya bunu
söyler ("The credential store could not be read while this page was being
generated…"). Testi var.

---

## 4. SLA'da hangi alanlar boş bırakıldı, neden ve kim dolduracak

### 4.1 Neden hiçbir rakam yazılmadı

Bir SLA bir **taahhüttür**. Bugün bu üründe çalışma süresini ölçen hiçbir şey
yok: durum sayfası kurulmadı (`docs/107` Faz 3.4 ❌) ve üretimde tek bir
kullanılabilirlik kaydı bulunmuyor. Bu koşulda "%99,9" yazmak, tutulup
tutulmadığı **hiç kimse tarafından bilinemeyecek** bir söz vermektir; ilk
kesinti günü sözleşmeye aykırılık doğar.

Desen yeni değil: destek yanıt süresi de aynı sebeple boş
(`SUPPORT_RESPONSE_COMMITMENT_HOURS`, `docs/125` §3). Değer yoksa cümle de
yoktur ve **yedek bir cümle yazılmaz** — "genellikle bir gün içinde" de bir
vaattir ve kimse onu vermedi.

### 4.2 Boş bırakılan dört alan

`config/sla.php`, hepsi `.env`'den, **varsayılanı yok**:

| Ortam değişkeni | Ne karar veriyor | Kim doldurur |
| --- | --- | --- |
| `SLA_AVAILABILITY_TARGET_PERCENT` | Takvim ayı başına taahhüt edilen kullanılabilirlik yüzdesi (ör. `99.5`) | **Sahip**, hukuki inceleme sonrası |
| `SLA_MEASUREMENT_SOURCE` | O yüzdenin **müşterinin kendisinin** okuyabileceği kamuya açık adres (durum sayfası) | **Sahip** — ama önce Faz 3.4'ün yapılması gerekir |
| `SLA_INCIDENT_NOTIFICATION_MINUTES` | Kesinti fark edildikten sonra müşterinin haberdar edilme süresi (dakika) | **60 — sahip verdi (2026-09-08)** |
| `SLA_SERVICE_CREDIT_PERCENT` | Hedef tutmadığında o ayın ücretine uygulanacak telafi oranı (yüzde) | **Sahip** |

### 4.3 Dördü BİRLİKTE ya da hiçbiri

Üçü girilip biri boş bırakıldığında sayfa yine **hiçbir rakam göstermez**.
Sebep: ölçüsü olmayan bir oran doğrulanamaz, telafisi olmayan bir hedef bir
temennidir, bildirimi olmayan bir kesinti müşterinin kendi başına fark etmesi
gereken bir olaydır. "Kısmi taahhüt" diye bir şey yok; okuyucu onu tam bir SLA
diye okur. Testi var (`three_values_out_of_four_still_commit_nothing`).

Yazım hatası da taahhüt değildir: `=yes`, `=%99,9`, `=0` ya da `=400`
"girilmedi" sayılır (`LegalReview`'ün "yes bir inceleme değildir" kuralıyla
aynı).

### 4.4 Rakamsız sayfa neye benziyor

İkinci bölümün başlığı: **"No availability figure is committed today"**.
Sayfada tek bir **yüzde işareti** yok (testle kilitli) ve eksik dört alan
`availability_target_percent` gibi **yapılandırma adıyla** sayılıyor — sahip
sayfayı okuyup hangi değeri nereye gireceğini görebilsin diye.

Dördü girildiğinde aynı bölüm taahhüt cümlelerine dönüşür ve geri kalan yedi
bölüm (kapsam, ölçüm yöntemi, hariç tutulanlar, bildirim, telafi, destek,
değişiklik) hiç değişmez — yapı zaten kurulmuş durumda.

---

## 5. Lisans listesi nasıl türetildi

`ManifestThirdPartyLicenses`, deponun zaten tek gerçeği olan dört dosyayı
okur: `composer.json` + `composer.lock`, `package.json` + `package-lock.json`.

**Ölçülen (2026-09-08):**

| Ekosistem | Doğrudan | Dolaylı | Kaynak |
| --- | --- | --- | --- |
| PHP (Composer) | **13** | 100 | `composer.json#require` ∩ `composer.lock#packages` |
| JavaScript (npm) | **7** | 494 | `package.json#dependencies` ∩ `package-lock.json#packages` |

**Üç eleme kararı ve gerekçesi:**

1. **`php` ve `ext-*` listelenmez.** Biri dilin kendisi, diğerleri sunucudaki
   eklentiler; lisansı olmayan bir şeye lisans aramak olurdu.
2. **`devDependencies` listelenmez.** Vite, ESLint, Storybook, TypeScript
   ziyaretçinin tarayıcısına ya da çalışan sunucuya hiç ulaşmaz; dağıtılmayan
   kod için lisans bildirimi yapmak listeyi gürültüyle şişirirdi. Sayfa bu
   kararı **yazıyor**, sessizce atlamıyor.
3. **Dolaylı paketler SAYILIR, listelenmez.** 594 paketi bir hukuk sayfasına
   basmak, gerçekten dikkat edilmesi gereken 20 tanesini görünmez kılardı.
   Sayfa sayıyı verir ve tam listenin kilit dosyasında olduğunu söyler. Bir
   sayım da bir olgudur; "birçok" değildir.

**npm'de iç içe kopya tuzağı — ölçüldü ve düzeltildi.** `tailwind-merge` bu
depoda üç sürümle kurulu (3.6.0 üst düzey, 2.6.1 ve 3.4.0 başka paketlerin
altında). Yalnız ada bakan bir eşleme üçünü de "doğrudan" sayıyordu; eşleme
artık **yolun tam olarak `node_modules/<ad>` olmasını** istiyor. Testi var
(aynı paket iki kez listelenmez).

**Lisans metni kopyalanmadı.** Sayfa lisansın adını ve paketin sürümünü
söyler; MIT ya da Apache-2.0 metnini buraya çoğaltmak, kopyanın bir gün asıl
metinden ayrışması demekti.

**Lisans uydurulmaz.** Kilit dosyasında lisans alanı yoksa satır "licence not
declared in the lock file" yazar.

---

## 6. Kabul edilebilir kullanım: uygulanamayan yaptırım yazılmadı

Yasak listesi (içerik, hizmete karşı davranış, mesajlar, AI özellikleri)
sıradan bir hukuk metnidir. Asıl karar **yaptırım bölümündedir** ve o bölüm
depodan ölçüldü.

**Ürünün bugün gerçekten yapabildikleri:**

| Yaptırım | Nerede | Otomatik mi |
| --- | --- | --- |
| Hız sınırlama | `throttle:` ara katmanları (`routes/web.php`) | Evet |
| Dosya karantinası ve tarama | `ScanQuarantinedMediaAsset` | Evet |
| **Taranamayan dosya yayına alınmaz** | Aynı yer — `unknown` bir "temiz" değildir | Evet |
| Medya yasal saklama kilidi | `UpdateMediaLegalHoldController` (`workspace.manage`) | Hayır, elle |
| İçeriği kaldırma / menüyü yayından çekme | Panel | Hayır, elle |
| Hesabı kapatma | İşletmecinin sunucuda yaptığı iş | Hayır, elle |

**Üründe OLMAYAN ve bu yüzden yazılmayan:** panelden tek tıkla çalışma alanı
askıya alma. `WorkspaceState` enum'unda `suspended` durumu **var** ama onu
yazan hiçbir yüzey yok — ölçüldü (`grep -r "WorkspaceState::Suspended"` yalnız
enum tanımını buluyor). Metin bu yüzden "askıya alma bugün panelde bir düğme
değil, işletmecinin sunucuda yaptığı bir iştir" diyor. **Otomatik içerik
denetimi de yok** ve sayfa bunu söylüyor: hiçbir şey menü metnini okuyup
hüküm vermiyor.

**Bug bounty yok, açıklama takvimi yok.** İkisi de yazılmadı; bir gün
taahhüt edilirse kendi sürüm numarasıyla yazılacak.

---

## 7. Uydurmama sınavı: ne yazılmadı

| Uydurulabilecek şey | Ne yazıldı |
| --- | --- |
| Çalışma süresi oranı | Hiçbir rakam yok; SLA neden olmadığını söylüyor |
| Yanıt süresi | Yok; SLA "taahhüt edilmişse iletişim sayfasında görürsünüz, görmüyorsanız edilmemiştir" diyor |
| ISO 27001 / SOC 2 sertifikası | **"There is no ISO 27001 certificate, no SOC 2 report and no external security audit."** DPA §8'de |
| Yedeğin sunucu dışı kopyası | **YOK ve DPA bunu yazıyor:** "Backups are kept on the same server… There is no copy outside that server today… if that server were lost entirely, the backups would be lost with it." (`docs/124` §7.4, §8) |
| Nokta-zaman kurtarma | Yok; DPA "the best that a restore can recover to is the moment of the last dump" diyor |
| Saklama/silme süresi (gün) | Yok; "no deletion period in days is stated here, because none is measured today" |
| İhlal bildirim süresi (saat) | Yok; "no notification deadline in hours is stated here" |
| Alt işleyenin işleme ülkesi | Ölçülemiyor ve öyle yazıldı: yalnız **barındırma** konumu ölçülüyor |
| Alt işleyenle imzalı DPA | İddia edilmedi: "this text does not assert one that has not been signed" |
| Yerinde denetim hakkı | Verilmedi: "there is no audit programme to receive one" |
| Hesap düzeyi denetim kaydı | Yok; DPA §5 "a wider trail is planned but is not in place today" diyor (`docs/107` Faz 3.3 ◐ ile tutarlı) |

Bu satırların çoğu bir **teste** bağlandı:
`LegalDocumentPagesTest::no_document_promises_a_response_time_or_an_invented_price`
artık `iso 27001 certified`, `soc 2 certified`, `off-site`, `offsite`,
`separate location`, `geographically separate`, `penetration test report`,
`annual audit` dizelerini de **on üç belgenin hepsinde** tarıyor.

---

## 8. Barındırma bilgisi neden yapılandırmada — ve neden bir varsayılanı var

`config/legal.php#hosting` iki alan taşıyor ve **şirket kimliğinin aksine bir
varsayılanı var**:

```
LEGAL_HOSTING_PROVIDER  (varsayılan: netcup GmbH)
LEGAL_HOSTING_LOCATION  (varsayılan: Karlsruhe, Germany — outside Turkey)
```

İkisi farklı **türde** olgu. Bir tüzel kişinin ünvanı **bilinemez**: bu
yazılımı kuran kişinin kim olduğunu kod bilemez ve bir varsayılan yazmak
sözleşmenin tarafını uydurmak olurdu. Barındırma ise **bu ürünün üretim
dağıtımı için ölçülmüş** bir olgudur (sahip doğruladı 2026-09-08; `docs/42`,
`docs/43`) — uydurma değil, kayıt.

Yine de `.env`'den geliyor, çünkü bu yazılım tek bir kuruluma ait değil
(`SAAS-DOMAIN`): kendi sunucusuna kuran biri kendi sağlayıcısını yazar ve
DPA'sı doğru olur. Değeri **boşaltan** bir dağıtımda sayfa "not yet provided"
der.

`LEGAL_HOSTING_LOCATION` içinde **ülke adıyla** geçmek zorunda: DPA'nın yurt
dışı aktarım bölümü (KVKK madde 9) okuyucunun o satırı okumasına dayanıyor.

---

## 9. Diğer yasal metinlerle çelişki var mı — satır satır kontrol

| Konu | Mevcut metin | FF-228'in söylediği | Çelişki |
| --- | --- | --- | --- |
| Alt işleyenler | Gizlilik Politikası ve KVKK: Mailgun, Iyzico, AI sağlayıcısı, GTM | DPA aynı sağlayıcıları **ölçerek** sayıyor; adlar aynı | Yok — DPA daha dar (yalnız devrede olanlar), ikisi de doğru |
| Yurt dışı aktarım | KVKK: "Where a provider operates its systems outside Turkey… Article 9" | DPA: barındırma Almanya, aktarım madde 9 koşullarında | Yok — DPA olguyu ekliyor, kuralı değiştirmiyor |
| Saklama ve silme | Gizlilik: "hesap varken saklanır; silme talebinde kanunen tutulması gerekmeyen kaldırılır" | DPA aynı cümleyi kuruyor, **gün sayısı eklemiyor** | Yok. `docs/138` (KVKK hakları paketi) bir süre taahhüt ederse DPA §11 o sürümle güncellenir |
| İade | İptal ve İade Politikası: iade hâlleri sayılı | DPA ve AUP iade **yaratmıyor**, ikisi de o politikaya yolluyor | Yok — bilerek |
| Kabul edilebilir kullanım | Hizmet Koşulları: tek paragraf | AUP onu genişletiyor, çelişmiyor | Yok — AUP §1 bunu açıkça yazıyor |
| Kesinti | Teslimat/İfa Koşulları: "These terms do not state an availability figure, because none is measured or promised today" | SLA aynı cümleyi kuruyor | Yok — **birebir tutarlı** |
| Sorumluluk sınırı | Hizmet Koşulları: ödenen ücretle sınırlı | SLA: telafi tek çare, kanuni haklar saklı | Yok — SLA sorumluluk maddesini değiştirmiyor |
| Mesafeli satış | Tüketici cayması 14 gün, kanuna atıfla | Dört belge de cayma süresine dokunmuyor | Yok |

**`docs/138` uyarısı:** bu paket yazılırken `docs/138` (KVKK hakları) henüz
`origin/main`'de yoktu ve o dosyaya **dokunulmadı**. DPA §11 bilerek bir gün
sayısı taşımıyor; o paket bir silme takvimi taahhüt ettiğinde DPA §11 onunla
hizalanır ve sürüm 0.2 olur.

---

## 10. Bulunabilirlik — ve bu pakette bilerek yapılmayan

Dört belge **kendi rotalarından** erişilebilir ve `sitemap.xml`'de ilan
ediliyor (`ShowSitemapController::LIVING_PATHS`). Dört adres `reserved_slugs`
listesine de eklendi (URL-RESERVED-COVERS-ROUTES-13): bir işletme `sla`
slug'ını alsaydı hizmet seviyesi sayfası o işletmenin menüsüyle gölgelenirdi.

**Altbilgi bağlantısı bu pakette YAPILMADI.** `SiteNavigation`, `header`,
`footer` ve `layout` dosyaları başka bir ajanın paketindedir ve aynı dosyaya
iki writer dokunamaz. Sonuç bugün şu: dört sayfa yayında, sitemap'te ve
adresinden okunuyor; ama siteyi gezen biri onlara **bir bağlantıyla**
ulaşamıyor. Kalan iş tek satır — `SiteNavigation::GROUPS['footer']` içindeki
`legal` grubuna dört madde ve katalogda dört etiket.

---

## 11. Ölçüm

### 11.1 Kapılar (2026-09-08)

| Kapı | Sonuç |
| --- | --- |
| `vendor/bin/pint --test` | **passed** |
| `php -d memory_limit=-1 artisan test` | **passed** — 2.796 test, 2.793 geçti, **0 başarısız**, 3 atlandı, 20.383 doğrulama (1.058 sn) |
| `npx prettier --check .` | **passed** — eşleşen bütün dosyalar |
| `npm run i18n:check` | **passed** — projeksiyonlar PO kataloglarıyla eşleşiyor. **Yeni katalog anahtarı gerekmedi:** dört belgenin metni kütüphanede (İngilizce kaynak), şablonun etiketleri zaten vardı |
| `npx vitest run resources/js` | *(aşağıda, §11.3)* |
| `scripts/mobile-ux-audit` (320×568) | *(aşağıda, §11.2)* |

### 11.2 Dar ekran — gerçek Chrome, 320×568

`php artisan site:export-static` (18 sayfa) + `node scripts/mobile-ux-audit`.

| Sayfa | Kullanılabilir genişlik | LTR bulgu | RTL bulgu |
| --- | --- | --- | --- |
| `/data-processing` | **309/320** (0,97) | 2 — ikisi de kabuğun üst menüsü | 0 |
| `/sla` | **309/320** | 0 | 0 |
| `/acceptable-use` | **309/320** | 0 | 3 — üçü de kabuğun üst menüsü |
| `/third-party-licenses` | **309/320** | 2 — ikisi de kabuğun üst menüsü | 0 |

**Dördünde de sıfır:** yatay taşma, kenardan kırpılma, metin kırpılması, boşa
giden genişlik. 1.956 kelimelik bir sözleşme 320 pikselde okunuyor.

**Raporlanan bulgular bu paketin değil.** Hepsi kabuğun üst menüsündeki üç
bağlantı (`a "Features"`, `a "How it works"`, `a "Pricing"` → 294×44 ve
aralarında 0 piksel) ve **aynı bulgu bu paketin dokunmadığı sayfalarda da
çıkıyor**: LTR koşusunda `delivery`, `help`, `privacy`, `index`, `pricing`;
RTL koşusunda `about`, `kvkk`, `help`, `index`. İki koşuda **hangi sayfada**
çıktığı değişiyor, hangi bağlantı olduğu değişmiyor — yani bulgu sayfanın
içeriğine değil kabuğa ait, mevcut borç. Kabuk başka bir ajanın paketinde
(§10).

### 11.3 vitest — sonuç "bilinmiyor", ve bu "geçti" diye yazılmıyor

Bu paket **tek bir `.ts`/`.tsx`/`.js`/`.css` dosyasına dokunmadı** (`git diff
--name-only`: sıfır) ve `vite`/`vitest`/`package.json` yapılandırmalarına da
dokunmadı. React yüzeyinde bu paketin değiştirebileceği bir davranış yok.

Yine de tam koşu **temiz bir ölçüm vermedi** ve bunu saklamıyoruz. Aynı
makinede o sırada **üç ayrı çalışma ağacı** kendi tam takımlarını koşuyordu
(`wt-kvkk` PHPUnit, `wt-daisy` vitest, `wt-urun-foto` karışık); yük ortalaması
**38–57** ölçüldü. İki tam koşu birbirini tutmadı: birincisinde 8, ikincisinde
57 başarısız — aynı depo, aynı dosyalar. Bu fark ölçümün kendisinin
güvenilmez olduğunun kanıtıdır.

Ayırt etmek için başarısızların bir alt kümesi **yalnız başına** koşuldu
(25 sn, çekişmesiz):

- `PlatformApp.subscriptions.test.tsx` → **geçti**. Yani tam koşudaki
  başarısızlığı yüke bağlı bir zaman aşımıydı.
- `WorkspaceApp.dashboard.test.tsx` → **4 test hâlâ kırmızı**. Ama bunlar
  gürültü değil: takımın adı zaten `(S1-WP01A foundation, RED)` ve testlerden
  biri adında `DASHBOARD_SETUP_JOURNEY_RED` taşıyor — bu depoda **bilerek
  kırmızı bırakılmış**, henüz yazılmamış bir işin testleri. Mevcut borç,
  bu paketin dışında ve çalışma alanı panosuna ait.

Sonuç: vitest için bu makinede bugün verilebilecek dürüst cevap **"bu paket
etkilemiyor"**dur; "tam takım yeşil" değildir ve öyle yazılmadı
(`docs/117`: ölçülemeyen sonuç "bilinmiyor"dur, yeşil gösterilmez).

---

## 12. Sınırlar ve bilinmeyenler

- **Metinler bir hukukçu tarafından okunmadı.** Dördü de "pending legal
  review" notu taşıyor ve o not kaldırılmadı. Bu paket bir hukukçunun üzerine
  çalışacağı **tam bir taslak** üretir; hukuki görüş üretmez.
- **SLA'da rakam yok** ve olmayacak — durum sayfası (`docs/107` Faz 3.4)
  kurulup ölçüm başlamadan bir oran taahhüt edilemez. Sıra: önce Faz 3.4,
  sonra `SLA_*` değerleri, sonra sürüm 0.2.
- **Alt işleyen listesi yerel ortamda bir kayıt** gösteriyor. Üretimde kaç
  kayıt göstereceği, o sunucunun kasasına ve `.env`'ine bağlıdır ve bu paket
  üretim sunucusunu görmedi.
- **Altbilgi bağlantısı yok** (§10).
- **`mpdf/mpdf` GPL-2.0-only.** Lisans listesi bunu ilk kez görünür kıldı ve
  bu bir **olgudur, bir uyarı değildir**: bu depo açık kaynak olduğu için
  bugün bir sorun görünmüyor, ama kapalı kaynak bir dağıtım düşünüldüğü gün
  bu satır ilk bakılacak yerdir. Karar hukukçunun ve sahibindir.
- **Türkçe metin yok.** Dördü de İngilizce kaynak metindir ve çeviri kilidine
  tabidir (`docs/121`); bu pakette tek bir çeviri üretilmedi.

## Kullanılabilirlik oranı — sahibin ölçümü (2026-09-08)

Sahip birebir şunu söyledi: *"benim netcup server zaten bu yazılım 5 ay+
%99.8, bunu yaz."*

Bu bir **gözlemdir** ve kaynağı sahibin kendi işletmesidir: aynı yazılım aynı
sunucuda beş aydan uzun süredir koşuyor. Oran `SLA_AVAILABILITY_TARGET_PERCENT`
alanına yazıldı.

**Gözlem ile taahhüt aynı şey değildir ve bu belge ikisini ayırır.**

- **Gözlem:** geçmişte ne olduğu. Beş ay, %99.8. Sahibin bilgisi, uydurulmadı.
- **Taahhüt:** gelecekte ne olacağına dair söz. %99.8 aylık taahhüt, ayda
  yaklaşık **87 dakika** kesinti hakkı demektir. Tek bir iki saatlik olay onu
  aşar.

Bu yüzden dört alan **birlikte ya da hiçbiri** kuralına bağlı kaldı. Oran
girildi; üçü hâlâ sahibin kararını bekliyor ve o üçü girilene kadar `/sla`
sayfası tek bir yüzde işareti bile yazmıyor:

| Alan | Ne demek | Neden bekliyor |
| --- | --- | --- |
| `SLA_MEASUREMENT_SOURCE` | Oranın okunacağı **kamuya açık** adres | Durum sayfası (`docs/107` Faz 3.4) henüz kurulmadı. Müşterinin kendi doğrulayamadığı bir oran taahhüt değil beyandır |
| `SLA_INCIDENT_NOTIFICATION_MINUTES` | Kesinti fark edildikten sonra müşteriye haber verme süresi | **60 dakika — verildi** |
| `SLA_SERVICE_CREDIT_PERCENT` | Oran tutmadığında verilecek kredi | **0 — verildi, ve sayfa bunu SUSARAK değil yazarak söylüyor** |

**Sıra önemli:** ölçüm kaynağı durum sayfasına bağlı. Durum sayfası yayına
girmeden yazılan bir oran, tutulup tutulmadığı kimsenin doğrulayamayacağı bir
sözdür — ve ilk itirazda savunulamaz.

## Sahibin verdiği üç değer (2026-09-08) ve biri neden hâlâ eksik

| Alan | Değer | Kaynak |
| --- | --- | --- |
| Kullanılabilirlik | **%99.8** | Sahibin ölçümü: aynı yazılım netcup'ta beş aydan uzun süredir çalışıyor |
| Bildirim süresi | **60 dakika** | Sahibin kararı |
| Hizmet kredisi | **0** | Sahibin kararı |
| Ölçüm kaynağı | — | Durum sayfası yayına girince (`status.zabuno.com`) |

**Birim saatten dakikaya çevrildi.** Alan "saat" olarak tanımlanmıştı; sahip
kararını dakikayla verdi ve haklıydı — saat cinsi bir alan ileride "30 dakika"
diyemez, kararı birime uydurmak zorunda bırakırdı.

**Sıfır kredi bir karardır, bir boşluk değil — ve bu ayrım kodda yaşıyor.**
Doğrulama `0`ı "girilmedi" sayıyordu; artık saymıyor. Sayfa sıfır kredide
susmuyor, açıkça yazıyor: *"no service credit is paid… your remedy is to
cancel."* Söylenmemiş bir "yok" her zaman müşterinin aleyhine çalışır, çünkü
okuyan taraf bir telafi olduğunu varsayar.

**Sayfa hâlâ hiçbir rakam göstermiyor** ve göstermemeli: dördü birlikte ya da
hiçbiri. Ölçüm kaynağı olmadan yazılan bir oran, müşterinin kendi
doğrulayamayacağı bir sözdür.
