# 138 — KVKK hakları ürün içinde: veriyi almak, veriyi sildirmek

**Paket:** FF-226 (`docs/107` Faz 3.3). **Kaynak dil:** İngilizce
(`docs/118` E4). **Durum:** iki uç ve bir ekran üründe; yasal metinler
0.2'ye çıktı ve hâlâ **hukukçu incelemesi bekliyor** (`docs/124`,
`docs/131`).

---

## 0. Bir yolculukla: zincir "bizim verimizi silin" diyor

On şubeli bir zincirin hukuk birimi arıyor. Üç soru soruyorlar; ilk ikisinin
cevabı bir belgede duruyordu, üçüncüsünün cevabı **yoktu**.

**"Verimizin bir kopyasını alabilir miyiz?"** Sahip panelde
**Ayarlar → Çalışma alanı**'nı açıyor. En altta, kendi kutusunda, "Take a
copy" diyor. Düğmeye basıyor; ekran beklemiyor — "hazırlanıyor, bu sayfadan
ayrılabilirsiniz" diyor ve iş arka planda koşuyor. Bitince e-postası
geliyor (posta yapılandırılmamışsa gelmiyor, **ama arşiv yine ekranda
duruyor** — `docs/93`). İndirdiği tek `.zip` dosyasının içinde her bölüm
**iki kez** var: bir kez `.json` (başka bir sisteme aktarmak için), bir kez
`.csv` (hukukçunun kendi bilgisayarında açıp okuması için). Yanında bir
`README.txt` duruyor ve **neyin içeride olmadığını da** yazıyor.

**"Silmek istersek ne oluyor?"** Aynı ekranda, kırmızı kenarlıklı kutuda.
Bir onay kutusu yok; **çalışma alanının adını yazması** isteniyor. Yazıyor,
"Ask for erasure" diyor. Hiçbir şey silinmiyor. Ekran "şu tarihte
silinecek" diyor ve yanında tek bir düğme var: "Take the request back."
O tarihe kadar panel çalışmaya, menü yayında kalmaya devam ediyor —
**hesabı kapatmak ile veriyi silmek aynı şey değil.**

**"Her şey mi silinecek?"** Hayır, ve ekran bunu **saklayacaklarını adıyla
sayarak** söylüyor: kesilmiş faturalar, arkalarındaki defter kayıtları,
dayandıkları tahsilat kayıtları ve onay defteri. Hukukçu "iyi, zaten
silmemelisiniz" diyor — çünkü mali kayıtları yok eden bir sağlayıcı,
kendisiyle çalışılamayacak bir sağlayıcıdır.

**Tarih geldiğinde** gece 04:10'da bir zamanlayıcı işi yürütüyor. Silinen
satırlar **sayılıyor** ve sayı deftere yazılıyor. Sahip ertesi gün
**Ayarlar → Denetim izi**'ne bakarsa (bakabiliyorsa — üyeliği de silindi)
o satırı görür; kayıt her hâlükârda durur.

---

## 1. Ölçülen boşluk (2026-09-08, paket öncesi)

| Konu | Önce |
| --- | --- |
| Kiracının kendi verisini dışa aktarması | **Yok.** Yalnız menünün CSV'si vardı (`docs/124`, gizlilik politikası bunu yazıyordu) |
| Kiracının verisini sildirmesi | **Yok.** Gizlilik politikası "when you ask us to delete your account…" diye söz veriyordu, tek yol iletişim formuydu |
| Hesap düzeyinde denetim kaydı | **Yok.** Medya izi ve yayın geçmişi vardı (`docs/107` Faz 3.3'ün "◐" satırı) |
| Kapsam tanımı | **Yok.** "Kiracının verisi" hiçbir yerde tanımlı değildi |
| Ayarlar → Çalışma alanı, tehlikeli bölge | Bilerek çizilmemişti (`WorkspaceIdentityRegion`: *"Bu üçü doğduğunda buraya gelir"*) |
| KVKK metni ve barındırma olgusu | **Çelişkili.** §7 |

---

## 2. Kapsam bir belge değil, bir KARAR KÜTÜĞÜDÜR

Kapsamı yalnız bu dosyaya yazmak yetmez: yarın biri yeni bir tablo ekler,
belge güncellenmez ve dışa aktarma o tabloyu sessizce atlar — ya da daha
kötüsü, **silme onu atlar** ve "sildik" dediğimiz veri durmaya devam eder.

Bu yüzden kapsam kodda yaşıyor: `App\Domain\DataRights\TenantDataScope`.
Şemadaki **her tablo** ya kiracıya aittir (nasıl bulunacağı, dışa
aktarılıp aktarılmayacağı, silinip silinmeyeceği yazılıdır) ya da açık bir
sebeple kapsam dışıdır. Üçüncü bir hâl — "sınıflandırılmamış" — yoktur ve
`TenantDataScopeCoversTheSchemaTest` bunu her koşuda şemaya karşı ölçer.
**Yeni bir tablo ekleyen paket, bir karar vermeden yeşile dönemez.**

Ölçülen sayılar (2026-09-08):

| | Sayı |
| --- | --- |
| Kiracıya ait tablo | **51** |
| Dışa aktarılan bölüm | **50** (çalışma alanının kendi satırı dâhil) |
| Silinen tablo | **44** |
| Silinmeyen (saklanan) tablo | **7** |
| Açıkça kapsam dışı tablo | **27** |

**Çocuk tablolar ebeveynden türer.** `menu_items` üzerinde `workspace_id`
yoktur ve olmaması bir kusur değil: kategori zaten menüye, menü de çalışma
alanına bağlıdır. Sütun eklemek yerine bağı takip etmek, şemayı bu paketin
ihtiyacına göre bükmemek demektir. Aynı kimlik kümesi hem dışa aktarma hem
silme tarafından kullanılır — iki ayrı sorgu yazılsaydı, bir gün kullanıcının
**indirdiği** veri, **sildirdiği** veriden farklı olurdu ve o fark hiçbir
ekranda görünmezdi.

---

## 3. Dışa aktarma: kapsam ve kapsam DIŞI

Arşiv tek bir `.zip`; içinde:

```
manifest.json          makine okunur özet: bölümler, satır sayıları,
                       kapsam dışı bırakılanlar ve sebepleri, barındırma
README.txt             insan okunur: ne var, ne yok, neden yok
data/<bölüm>.json      satırlar, makine okunur
data/<bölüm>.csv       aynı satırlar, hesap tablosunda açılır
```

**Neden iki biçim birden:** "makine okunur" ile "insan okunur" aynı dosya
olamaz. JSON başka bir sisteme aktarılır ama sahibin kendisi açıp okuyamaz;
CSV bir hesap tablosunda açılır ama iç içe alanı taşımaz. Birini seçmek,
iki okuyucudan birini dışarıda bırakmak olurdu.

**Boş bölüm de yazılır** (sıfır satırlı bir dosya olarak). Dosyanın hiç
olmaması "bu bölüm dışarıda bırakıldı" gibi okunur; boş dosya ise
"bakıldı, hiçbir şey yoktu" der.

### Kapsam dışı bırakılanlar — hepsi ve sebepleri

**(a) Kiracıya ait olan ama arşive girmeyen iki tablo:**

| Tablo | Neden |
| --- | --- |
| `media_processing_jobs` | İç kuyruk durumu: bir dosyanın işlenip işlenmediğini söyler, kiracının kendi içeriğini değil. |
| `ai_connection_assignments` | Platformun sağlayıcı kasasındaki bir bağlantıya işaret eder; kiracının içeriğini taşımaz (`docs/94`). |

**(b) Medyanın İKİLİ DOSYALARI.** Fotoğrafların **üst verisi** (ad, boyut,
sağlama toplamı, hangi üründe kullanıldığı, hangi sürümü var) tam olarak
aktarılır; **dosyaların kendisi aktarılmaz.** Sebep ölçülmüş: bir menü
kütüphanesi gigabaytlarla ölçülür ve tek bir arşive konsa ne üretilebilir
ne indirilebilirdi. Asıllar bugün Medya ekranından tek tek iniyor
(`media.download_original`) ve `README.txt` bunu adıyla söylüyor.

**(c) Kiracıya ait OLMAYAN 27 tablo.** Tam liste
`TenantDataScope::outOfScope()` içinde ve `manifest.json`'a olduğu gibi
yazılır. En çok sorulacak olanlar:

| Tablo | Neden kiracının verisi değil |
| --- | --- |
| `platform_audits` | **Platformun kendi denetim kaydı.** Kiracının kaydı ayrıdır ve Ayarlar → Denetim izi'nde durur (§5). |
| `platform_credentials`, `platform_credential_connections` | Sağlayıcı kasası (`docs/94`): sırlar kiracıya ait değildir ve hiçbir dışa aktarmaya girmez. |
| `users` | **Kişi bir çalışma alanının malı değildir** ve birden fazla çalışma alanında üye olabilir. Hesabın kendisi ayrı bir haktır (§4, "silinmeyen ama silinmesi de istenmeyen"). |
| `plans`, `features`, `taxonomy_terms` | Platform kataloğu; her kiracıya aynı gelir. |
| `contact_messages` | Tanıtım sitesinin iletişim formu; bir çalışma alanına bağlı değildir. |
| `invoice_number_sequences` | Fatura numarası sayacı; seri platform genelindedir. |
| `*_evidence`, `release_attestations` | Platformun kanıt kayıtları; tek bir kiracıya ait değildir. |

### Arşivin ömrü

`DATA_RIGHTS_EXPORT_AVAILABLE_DAYS` (varsayılan **7 gün**). Süresi
dolduğunda bağlantı **410** döner ("süresi doldu", "bulunamadı" değil) ve
dosya bir sonraki gece koşusunda diskten silinir. Sonsuza kadar duran bir
kopya, silme hakkının yanında duran sessiz bir çelişki olurdu.

İndirme adresi **imzalıdır** ve `DATA_RIGHTS_EXPORT_LINK_MINUTES`
(varsayılan 10 dakika) kadar yaşar — `media.original` ile aynı desen.
Oturum istemez, çünkü bağlantı e-postayla da gider.

Adres `/data-export/{workspace}/{request}`. `/workspaces/...` DEĞİL ve bu
bir ölçüm sonucu: her üst düzey yol rezerve edilmek zorunda
(`URL-RESERVED-COVERS-ROUTES-13`, `docs/38`) ve `workspaces` gibi geniş bir
sözcüğü bir işletmenin slug'ı olmaktan çıkarmak, bu paketin isteyeceğinden
çok daha büyük bir karardı. Taslak önizlemesi
(`/menu-preview/{workspace}/{menu}`) aynı sorunu aynı biçimde çözüyor.
`data-export` kökü rezerve edildi, `noindex` ve `robots.txt` disallow
listelerine girdi — imza zaten kapıdır, bunlar ikinci hat.

---

## 4. Silme: pencere, süre, ve silinemeyenler

### Pencere

**Süre yapılandırmadan gelir:** `DATA_RIGHTS_ERASURE_GRACE_DAYS`,
varsayılan **30 gün** (`config/data-rights.php`).

Bu sayı **hukuki bir saklama süresi değildir**, bir **ürün kararıdır**:
geri alınamaz bir işlemle kullanıcı arasına konan mesafe. Ekran, e-posta ve
zamanlayıcı hepsi aynı kaynaktan okur; hiçbir yerde ikinci bir rakam
yazılı değil.

`ErasureWindow` iki sınır koyar ve bunlar bir yapılandırmayla
kaldırılamaz:

- **Alt sınır 1 gün.** Okunamayan bir değer ("otuz", boş, sıfır) pencereyi
  **kapatmaz**, varsayılana düşer. Aksi hâlde bir yazım hatası doğrudan
  veri kaybına dönüşürdü.
- **Üst sınır 180 gün.** Aylarca sürüncemede kalan bir silme talebi,
  sahibin unuttuğu ve bir gün kendiliğinden patlayan bir mayındır.

### Onay bir kutu değil, çalışma alanının adıdır

"Eminim" kutusu refleksle işaretlenir. Adı yazmak, kullanıcıyı **hangi
çalışma alanında olduğuna bakmaya** zorlar; iki restoranı olan bir sahibin
yanlış paneli silmesini önleyen tek şey budur. Kural hem istemcide hem
sunucuda (422).

### Talep hesabı KAPATMAZ

Pencere boyunca çalışma alanı **çalışmaya devam eder**: menü yayında kalır,
panel açılır. Bilinçli: vazgeçme düğmesine ulaşılabilmeli. Paneli aynı anda
kilitleseydik, fikrini değiştiren sahip kendi vazgeçme düğmesine
erişemezdi.

### Yasal saklamaya alınmış dosya varsa silme HİÇ BAŞLAMAZ

Bir uyuşmazlık kaydına bağlı dosya (`media_assets.legal_hold_at`) varsa
istek **409 `legal_hold`** alır ve deftere satır bile yazılmaz. Kilidi
açmak ayrı ve bilinçli bir karardır (`UpdateMediaLegalHoldController`);
silme onu sessizce ezmez.

### Silinemeyecek olanlar ve sebepleri

Yedi tablo silinmez. Liste **koddan** gelir ve ekranda da, bu belgede de
aynı kaynaktan okunur — elle yazılsaydı, bir gün ayrışırlardı.

| Tablo | Neden silinmiyor |
| --- | --- |
| `invoices` | Kesilmiş fatura **yasal bir belgedir**. Ayrıca numara serisi boşluksuzdur (`docs/130`) ve bir satırın silinmesi seride **kanıtlanabilir bir boşluk** açardı. |
| `ledger_entries` | Defter kaydı; faturanın muhasebe karşılığıdır ve tek başına silinemez. |
| `payment_transactions` | Tahsilatın kendi kaydı; faturanın dayanağıdır. |
| `manual_payments` | Elden alınan ödemenin kaydı; aynı gerekçe. |
| `consent_records` | Onayın kanıtı; **en çok hesabın artık olmadığı gün gerekir** (`docs/124` §3). Ad ve e-posta zaten kopyalanmaz. |
| `support_requests` | Destek yazışmasının kaydı; referans numarası müşterinin elindedir (`docs/125`). |
| `workspace_data_requests` | Silme talebinin **kendi kaydı**. Silinseydi "kim ne zaman ne istedi" sorusunun cevabı da silinirdi. |

**Yasal gerekçe hakkında dürüst not:** ilk dördü ticari ve mali kayıt
saklama yükümlülüğüne tabidir. Bu belge **bir gün sayısı vermez** ve bir
madde numarası saymaz: yasal saklama süreleri sahibin hukuki
incelemesinden gelecek (`docs/124` §6.4, `docs/131` §12.6). Uydurulmuş bir
süre, tutulmayacak bir taahhüttür.

### Silinenler gerçekten silinir — ve SAYILIR

- **Sıra terstir.** Kapsam listesi ebeveynden çocuğa yazılıdır; silme
  çocuktan ebeveyne yürür.
- **Tek işlem.** Yarısı silinmiş bir çalışma alanı, silinmemiş bir çalışma
  alanından kötüdür. Ya hepsi ya hiçbiri.
- **Dosyalar da gider.** Satırı silip diskteki fotoğrafı bırakmak, "sildik"
  demenin en sinsi hâli olurdu; veritabanı temiz görünür, veri durur.
  Silinen dosya sayısı da sayılır (`storage_files`).
- **Sayı deftere yazılır.** "Her şey silindi" cümlesi ancak sayılabildiği
  kadar doğrudur. `deleted_counts` tablo başına satır sayısını taşır,
  ekranda ve denetim izinde toplam görünür.

### Çalışma alanının kendi satırı: mezar taşı

Silinmez; `state` `deleted` olur (`WorkspaceState::Deleted` — bu depoda
zaten vardı, yeni bir hâl uydurulmadı). Silinseydi kesilmiş faturanın
`workspace_id` alanı sahipsiz kalır ve silme talebinin kaydı hangi çalışma
alanına ait olduğunu söyleyemezdi.

### Kişinin hesabı bu paketin KAPSAMINDA DEĞİL

Silme **çalışma alanı** düzeyindedir. `users` satırı silinmez, çünkü bir
kişi birden fazla çalışma alanının üyesi olabilir; birinin verisini
sildirmesi, diğerindeki hesabını kapatmamalı. Üyelikler silinir, yani o
çalışma alanına erişim biter. **Kişisel hesabın kendisi ayrı bir haktır ve
bugün hâlâ iletişim formundan yürür** (`/kvkk`, madde 11 bölümü); bu paket
onu çözmedi ve çözdüğünü iddia etmiyor.

---

## 5. Denetim kaydı kiracıda NEREDE görünüyor

**Ayarlar → Denetim izi** — yeni bir ekran açılmadı. Sahip zaten oraya
bakıyor (`docs/133` deseni: "kiracının görebileceği kayıt"). İz iki
kaynaktan besleniyordu (medya, yayın); **üçüncüsü eklendi: veri hakları.**

Her talep, her hâl değişikliğiyle birlikte tek satır olarak düşer:

| Alan | Ne söyler |
| --- | --- |
| Kaynak | `Data request` |
| Eylem | `export.queued`, `export.ready`, `erasure.scheduled`, `erasure.cancelled`, `erasure.completed`, `export.failed` |
| Konu | `sections=50` — yürütülmüşse `sections=44 · rows=1240` |
| Fail | Talebi açan kişinin **e-postası** (bir ekipte iki "Mehmet" olabilir) |
| Zaman | Talebin açıldığı an |

Aynı bilgi Ayarlar → Çalışma alanı'ndaki bölümün alt kısmında da, "Requests
so far" listesinde duruyor: sahip talebi açtığı yerde durumunu da görür.

**Platformun denetim kaydına yazılmadı** ve bu bilinçli: kiracı kendi
hakkını kendi ekranından doğrulayabilmeli. Platform kaydında dursaydı,
"istedim, ne oldu?" sorusu için bize yazmak zorunda kalırdı — ve tam
olarak o yazışmadan kurtulmak için bu paket yazıldı.

---

## 6. İş uzun sürer; kullanıcı ekranda beklemez

- Dışa aktarma **kuyruğa** girer (`BuildWorkspaceDataExportJob`, tek
  deneme). Ekran "hazırlanıyor, bu sayfadan ayrılabilirsiniz" der.
- Bitince **e-posta** gider; içindeki adres imzalı ve kısa ömürlüdür
  (gelen kutusu yıllarca yaşar, bir çalışma alanının bütün verisine giden
  kalıcı bir bağlantı orada durmamalı).
- **Posta yapılandırılmamışsa** (`mail.default` = `log`) hiç denenmez,
  **damga atılmaz** ve bu bir arıza değildir — ama arşiv yine ekranda
  durur ve ekran "e-posta gönderilmedi, arşiv burada" der (`docs/93`).
  Gönderim düşerse sebebi satıra yazılır, ekrana çıkmaz.
- Silme talebi açıldığında da bir bildirim gider: **tarih** taşır, gün
  sayısı değil; ve "her şey silinir" demez — saklananları anar.
- Zamanlayıcı: `zabuno:run-due-erasures`, günde bir, 04:10. Bu satır
  olmadan silme bir **sözden** ibaret kalırdı; aynı ders bu depoda bir kez
  öğrenildi (medya çöp kutusu bir süre vaat ediyordu ve komutu çağıran
  hiçbir şey yoktu, FF-161).

---

## 7. Yasal metinle örtüşme: İKİ ÇELİŞKİ BULUNDU

Kural tekti: **kodu metne uydur, metni koda değil.** Bulunan iki çelişkiden
birincisi tam olarak öyle kapandı; ikincisinde metin bir **olguyu** eksik
söylüyordu ve sunucu koda uydurulamazdı.

### Çelişki 1 — verilen söz, olmayan yol *(kod metne uyduruldu)*

Gizlilik politikası (0.1) diyordu ki:

> *"When you ask us to delete your account we remove the data we are not
> legally required to keep."*

Ürünün içinde silmenin **hiçbir yolu yoktu** ve "yasal olarak saklamak
zorunda olduklarımız" hiçbir yerde sayılmıyordu. Söz veriliyor,
tutulmuyordu. **Bu paket o yolu açtı** ve metin artık saklananları adıyla
anıyor; ekran aynı listeyi aynı kaynaktan okuyor.

### Çelişki 2 — verinin nerede durduğu hiç yazılı değildi *(metin düzeltildi)*

KVKK aydınlatması (0.1) yalnız şunu söylüyordu:

> *"Where a provider operates its systems outside Turkey, the transfer
> abroad is made on the conditions set by Article 9 of the Law."*

Yani yurt dışına aktarım bir **ihtimal** gibi anlatılıyordu. Ölçülen olgu
(2026-09-08): **sunucu netcup GmbH'de, Karlsruhe, Almanya.** Yani verinin
kendisi sürekli yurt dışında duruyor ve madde 9 her gün devrede.
**Yedekler de aynı sunucuda** (sahibin bilinçli, süreli kararı) — yani bir
yedek, canlı veriyle aynı ülkede ve aynı koşullarda.

Gizlilik politikası ise barındırmayı **hiç** anmıyordu; alıcılar listesinde
Mailgun, Iyzico, AI sağlayıcısı ve GTM vardı, sunucunun kendisi yoktu.

Bu, koda uydurulabilecek bir çelişki değildi: sunucu taşınamaz. **Metin
düzeltildi.** İki belge de artık barındırmanın Almanya'da olduğunu ve
yedeklerin aynı sunucuda tutulduğunu söylüyor. Değer `config/data-rights.php`
üzerinden `DATA_HOSTING_PROVIDER` / `DATA_HOSTING_COUNTRY`'den geliyor —
sunucu bir gün taşınırsa cümle de taşınsın diye; koda gömülseydi, taşındığı
gün yalan söyleyen bir cümle kalırdı.

### Sonuç: iki belge 0.2'ye çıktı

| Belge | Sürüm | Ne değişti |
| --- | --- | --- |
| `/kvkk` | 0.1 → **0.2** | Barındırma olgusu ve yedekler; madde 11 haklarının ürün içindeki yolu (diğer yollar KALDIRILMADI — bir hakkı tek kapıya indirmek onu daraltmak olurdu); silme penceresi ve saklananlar |
| `/privacy` | 0.1 → **0.2** | Aynı barındırma cümlesi; silmenin ürün içindeki yolu ve penceresi; saklananların **adıyla** sayılması; dışa aktarmanın iki biçimi ve medyanın neden içinde olmadığı |

**Uydurulmayanlar:** saklama süresi (gün sayısı yok), madde numarası
(yalnız 6698'in gerçekten atıf yapılan maddeleri: 5, 7, 9, 10, 11, 13),
yanıt süresi. `LegalTextAndProductAgreeTest` bu iki cümlenin metinden
düşmesini ve "her şey silinir" iddiasının metne girmesini imkânsız kılıyor.

---

## 8. Yetki: iki ayrı eksen, ikisi de yalnız Sahip'te

| İzin | Ne yapar |
| --- | --- |
| `workspace.data.export` | Verinin bir **kopyasını** alır — geri alınabilir, zararsız |
| `workspace.data.erase` | Verinin **silinmesini** ister — geri alınamaz |

İki ayrı izin, çünkü iki ayrı sonuç. Tek izin olsaydı, bir zincirin merkez
ofisine "indirebilsin ama sildiremesin" demek imkânsız olurdu.

`workspace.manage`'e **yedirilmedi**: Yönetici rolü onu taşır ve o gün
bütün çalışma alanını sildirebilirdi. Yönetici bir çalışma alanını
**yürütür**, ona **sahip değildir**. İzin sayısı 23 → **25**; sayı testle
donduruldu (`RolePermissionMappingTest`).

---

## 9. Ölçüm (2026-09-08) ve kapı sonuçları

**320×568, gerçek Chrome** (`npm run build-storybook` + `scripts/mobile-ux-audit`),
bu paketin beş hikâyesi: `Default`, `ArchiveReady`, `ScheduledErasure`,
`BlockedByLegalHold`, `EverythingAtOnce`.

**Beşinde de yapısal bulgu SIFIR** (yatay taşma yok, kırpılma yok, 44
pikselin altında hedef yok, hedefler arası ayrım tamam, metin kesilmiyor);
kullanılabilir içerik genişliği **320/320** (oran 1.00), sol/sağ iç boşluk
0.

Ölçülen en yoğun hâl `EverythingAtOnce`: hazır bir arşiv (indirme
bağlantısı + tarih + posta durumu), planlanmış bir silme (tarih + vazgeç
düğmesi), üç uzun sebepli saklama listesi ve iki satırlık geçmiş aynı
ekranda. Silme kutusundaki metin alanı 320 pikselde kırpılmıyor; "vazgeç"
ve "arşivi indir" düğmeleri asgari dokunma boyutunda.

### JS bütçesi: ölçüm bir kusur buldu ve iki şey değiştirdi

Bölüm ilk yazımında doğrudan içe aktarılıyordu ve ekranın kaynak dizeleri
`shell.ts` kataloğuna giriyordu. `DS-BUNDLE-BUDGET-07` (`docs/06`) bunu
ölçtü:

| | Masaüstü kapanışı (gzip) |
| --- | --- |
| `origin/main` (taban) | **199.24 KB** |
| Bu paket, ilk hâli | **200.02 KB** — bütçe 200 KB, **aşıldı** |
| Bu paket, düzeltmeden sonra | **199.96 KB** ✅ |

Bütçeyi yükseltmek bir satırlık iş olurdu ve **yanlış** olurdu: 200 KB,
yavaş bir telefonda paneli açan restoran sahibinin beklediği süredir. İki
şey yapıldı:

1. **Bölüm artık `lazy`.** `BillingPage` ile aynı gerekçe: bir çalışma
   alanının ömründe belki bir kez açılan bir yüzey, her açılışta
   indirilmemeli.
2. **Kullanılmayan iki kaynak dizesi silindi.** Ekranda çizilmeyen ama
   katalogda duran iki anahtar vardı; kimsenin okumadığı bir dize de
   indirilir. Otuz üç dize otuz bire indi ve kalanlar kısaltıldı.

| Kapı | Sonuç |
| --- | --- |
| `./vendor/bin/pint --test` | ✅ |
| `php -d memory_limit=-1 artisan test` | **2775 test · 2770 geçti · 3 atlandı** — aşağıya bakınız |
| `npx vitest run` (etkilenen yüzeyler) | ✅ **12 dosya / 95 test** |
| `npx vitest run resources/js` (tam koşu) | ⚠️ **ÖLÇÜLEMEDİ** — aşağıya bakınız |
| `npx prettier --check .` | ✅ |
| `npm run i18n:check` | ✅ |
| `scripts/mobile-ux-audit` | ✅ 5/5 hikâye, bulgu 0 |

**Tam PHP koşusundaki iki düşüş ve ne oldukları:**

1. `WorkspaceContextPermissionsTest` — Sahibin izin sayısı 23'ken 25 oldu ve
   dondurulmuş sayı güncellenmemişti. **Gerçek bir düşüş**, düzeltildi ve
   hedefli olarak yeşil doğrulandı.
2. `StaticSiteExportTest` — koşu sürerken aynı çalışma ağacında `npm run
   build` çalıştırıldı ve varlık dosyalarının parmak izleri altında değişti.
   **Ölçüm hatası**, kod kusuru değil; yeniden koşuldu ve yeşil.

**Tam vitest koşusu neden "ölçülemedi" diye yazılıyor:** ölçüm sırasında bu
makinede başka çalışma ağaçlarına ait kırk küsur vitest süreci koşuyordu ve
düşen dosyaların hepsi zaman aşımıydı (tek bir dosya için 40-100 saniye).
İki tanesi ayrıca doğrulandı: `WorkspaceApp.dashboard.test.tsx` **tertemiz
`origin/main`'de de aynı biçimde düşüyor** (yani bu paketle ilgisi yok) ve
`a11y.guard` "Axe is already running" diyor — eşzamanlılık artefaktı. Ayrıca
o kapı yalnız `components/catalog/**` hikâyelerini tarıyor; bu paketin
hikâyeleri kapsamında değil.

**Bu bir "geçti" iddiası DEĞİLDİR ve öyle sunulmuyor.** Ölçülemeyen bir
sonuç yeşil gösterilmez; tam vitest koşusunun otoritesi CI'dır.

---

## 10. Çeviri yapılmadı

Bu pakette **tek bir Türkçe cümle üretilmedi**. Çeviri kilidi kapalı
(`docs/105` §8, `docs/118` E4, `docs/121`); `shipped_locales` yalnız
`['en']`. Yeni arayüz ve e-posta dizeleri yalnız İngilizce kaynak olarak
yazıldı; PO dosyalarına boş `msgstr` ile girdiler.

---

## 11. Sahibin DIŞARIDAN alması gerekenler

Bu paket **kodla çözülebilecek** kısmı çözdü. Kalanlar:

1. **Hukukçu incelemesi.** İki metin 0.2'ye çıktı ama bir hukukçu hâlâ
   okumadı; `LEGAL_REVIEWED_AT` boş ve her sayfa bunu üstte söylüyor.
2. **Yasal saklama süreleri.** Faturanın, defterin ve onay defterinin kaç
   yıl saklanacağı bir hukuk kararıdır; bu depo bir sayı uydurmuyor.
3. **Yedeklerin ayrı bir yere alınması.** Bugün yedekler aynı sunucuda
   (sahibin bilinçli, süreli kararı) ve metin bunu dürüstçe söylüyor. Ayrı
   bir konum, ayrı bir karardır — ve o gün silmenin yedeklere ne zaman
   ulaştığı da yeniden yazılmalı.
4. **Kişisel hesabın silinmesi.** Bu paket çalışma alanı düzeyinde çalışır;
   kişinin kendi hesabı hâlâ iletişim formundan yürür (§4).
5. **`LEGAL_DATA_REQUEST_ADDRESS`.** Hâlâ boş (`docs/131` §12.4).

---

**kullaniciYolculugu:** §0. **capability_delta:** Bir kiracı bugün kendi
verisinin tam kopyasını üründen alabiliyor (50 bölüm, iki biçim, ne
olmadığını da yazan bir README ile) ve verisinin silinmesini
isteyebiliyor; silme gecikmeli, geri alınabilir, sayılabilir ve kendi
denetim izine düşüyor. **Çalışan:** hukuk biriminin üç sorusundan
üçüncüsünün cevabı artık bir ekran. **Çalışmayan:** metinler hukukçu
incelemesi bekliyor, yasal saklama süreleri girilmedi, yedekler hâlâ aynı
sunucuda, kişisel hesabın silinmesi hâlâ iletişim formunda. Yönetişim
kapılarının yeşil olması ürün hazırlığı değildir.
