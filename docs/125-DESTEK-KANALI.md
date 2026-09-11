# 125 — Destek kanalı: referans, alındı bildirimi, taahhüt ve takip

**Faz 1.6'nın karşılığı (`docs/107`).** Sahibin sorusu üçüncü sorudur:
*"tıkanırsam kime sorarım?"* (`docs/110` P1-01). İletişim formu bunu yarım
cevaplıyordu: mesaj tabloya düşüyor, sahibe e-posta gidiyordu (`docs/88`,
`docs/93`). Ama gönderene **hiçbir şey dönmüyordu** — referans numarası yok,
alındı bildirimi yok, yanıt taahhüdü yok, takip yok; panelden destek istemenin
de yolu yoktu. Bu belge o boşluğu kapatan paketin (FF-201) kararlarını,
ölçülmüş durumunu ve sahibe kalan soruları taşır.

## 0. Ölçülen önce/sonra

| | Önce | Şimdi |
| --- | --- | --- |
| Mesaj nereye düşer | `contact_messages` (ad, e-posta, mesaj) | `support_requests` (referans, kanal, durum, ilk yanıt zamanı, iki gönderim kaydı) |
| Gönderene ne döner | Ekranda "teşekkürler" | Ekranda **referans**; e-postayla referanslı alındı bildirimi; sonucu satıra yazılır |
| Sahibe bildirim | Var (`docs/93`) | Var, artık **referansı da taşıyor** |
| Yanıt taahhüdü | Yok | Yapılandırılmışsa üç yüzeyde **aynı cümle, tek kaynak**; yapılandırılmamışsa **hiçbir yerde** |
| Panelden destek isteme | Yok | `Destek` bölümü: kendi talepleri + yeni talep + yardım bağlantısı |
| Takip | Yok | Durum panelde ve e-postada; kamuya açık sorgu **yok** (§5) |
| Süperadmin | Yok | Kuyruk ekranı + **satırdan cevap gönderme** (§6) |

## 1. Akış

Kamu formu ve panel **aynı sırayı** yürür (`SubmitSupportRequest`):

1. **Sakla ve referans ver.** Bundan sonrası düşse bile talep durur
   (`docs/93` ilkesi: saklamak göndermekten önce gelir).
2. **Gönderene alındı bildirimi** (`SupportRequestAcknowledged`): referans,
   konu, varsa taahhüt cümlesi, panel kanalındaysa "durumu panelden
   izleyebilirsin", destek adresi yapılandırılmışsa "cevaplayabilirsin".
   Sonuç `acknowledged_at` / `acknowledgement_failure` sütunlarına yazılır.
3. **Sahibe bildirim** (`ContactMessageReceived`), yalnız
   `CONTACT_NOTIFICATION_ADDRESS` varsa. Sonuç `notified_at` /
   `notification_failure` sütunlarına yazılır.

İki gönderim **bağımsızdır**: biri düşünce öteki denenir. Dışarı giden bir
taşıyıcı yoksa (`mail.default = log`, yani kimlik hiçbir kaynaktan gelmemiş —
`docker-compose.yml`'nin dağıtım sözleşmesi) ikisi de **hiç denenmez ve damga
atılmaz**: günlüğe yazılmış bir e-posta kimseye ulaşmamıştır ve ona
"gönderildi" demek, sahibin gelen kutusu için `docs/93`'te reddettiğimiz
yalanın aynısı olurdu.

Kanal farkı tek yerde: kamu formunda ad ve e-posta ziyaretçinin yazdığıdır,
konu mesajın ilk satırından türetilir (form konu sormaz — fiyat soran biri
için fazladan bir engel); panelde ad ve e-posta **hesaptan** gelir, konu
sorulur, talep çalışma alanına ve kişiye bağlanır.

`contact_messages` **silinmedi ve taşınmadı.** Kamu formu artık oraya yazmaz;
bugüne kadar düşen mesajlar yerinde durur. Yeni tablo açmanın sebebi: eski
tablo bir gelen kutusuydu, destek talebi ise bir yaşam döngüsü; aynı tabloya
sütun eklemek eski satırları referanssız ve durumsuz bırakır, her sorguya
"eski mi yeni mi" ayrımı bindirirdi.

## 2. Referans biçimi

`ZB-` + beş karakter, alfabe `23456789ABCDEFGHJKMNPQRSTUVWXYZ`. Örnek:
`ZB-3F7K2`.

- **0/O ve 1/I/L alfabede yok.** Telefonda ve el yazısında karışırlar; bir
  müşteri destek hattında "sıfır mı O mu?" diye sormamalı.
- Otuz bir karakterle beş basamak ≈ 28,6 milyon numara. Tekil indeks
  çarpışmayı veritabanında keser; depo kesildiğinde yeni numara dener (en
  fazla beş), sonra gürültüyle durur. SQLite/MySQL `23000`, PostgreSQL
  `23505` — ikisi de tanınır (`SupportReferenceTest`).
- Üretim `random_int` ile: referans tahmin edilebilir olmamalı, bir gün
  referansla sorgu açılmasa bile.

## 3. Taahhüt nereden gelir — ve neden bugün yok

**Sayı sahibin kararıdır, yazılımcının değil.** Bu paket bir sayı yazmadı.

- `SUPPORT_RESPONSE_COMMITMENT_HOURS` (config `support.response_commitment_hours`)
  boşsa **hiçbir sayfa, e-posta ya da panel bir yanıt süresi yazmaz.** Yedek
  cümle de yoktur — "en kısa sürede", "7/24", "genellikle bir gün içinde"
  hepsi birer vaattir ve kimse onları vermedi. Sıfır, negatif ya da sayı
  olmayan bir değer de "yok" sayılır (`ResponseCommitment`).
- Doluysa üç yüzey **aynı cümleyi tek anahtardan** okur:
  `site.support.commitment` = *"We reply within {hours} hours."*
  - İletişim sayfası: `ShowContactFormController` → `ResponseCommitment`.
  - Alındı e-postası: `MailSupportNotifier` → aynı sınıf.
  - Panel: `GET /workspaces/{w}/support-requests` cevabındaki
    `commitment.sentence`; React kataloğunda **bu cümlenin karşılığı yoktur**
    ve olmayacak (`WorkspaceModuleCatalog.test.ts` açıklaması).

`ResponseCommitmentTest` üçünü aynı testte karşılaştırır: sayfa cümlesi ile
panel cümlesi bire bir aynı olmak zorunda.

## 4. Panel: Destek bölümü

`resources/js/components/workspace/pages/SupportPage.section.tsx` —
`WorkspaceApp.tsx`'e dokunmadan kaydolur (kayıt defteri `*.section.tsx`
dosyalarını toplar). Kenar çubuğunda **Yönetim** (`management`) grubunda,
Şubeler/Medya/Ekip'ten sonra (kayıt sırası 14, kaydın sonu); ikon Phosphor
`Lifebuoy`.

Grup önce `utility` yazılmıştı ("Ayarlar'ın yanı"). O gerekçe main'de yok:
FF-84 Ayarlar'ı kenar çubuğundan hesap menüsüne taşıyıp `utility` grubunu
boşalttı, ama grubun İngilizce başlığı hâlâ "Settings". Destek oraya
konduğunda kenar çubuğu sahibin kaldırdığı başlığı tek maddeyle geri
getiriyordu (`WorkspaceApp.shell.test.tsx` bunu ölçtü). Destek zaten bir
yönetim kanalıdır ve izni Şubeler ile Ekip'inkiyle aynı — Yönetim grubu hem
doğru yer hem de dar ekranda bir satır başlık kazandırmaz.

**Yetki `workspace.manage`** — sahip ve yönetici. Karar gerekçesi: destek bir
yönetim kanalıdır (plan, fatura, hesap) ve editörün göreceği bir liste sahibin
fatura sorusunu da açığa çıkarırdı. Editör ve Mutfak bölümü kenar çubuğunda
görmez, uç nokta 404 döner. Bu bir **açık sorudur** (§7): editörün de
panelden yazabilmesi istenirse, listenin kişiye göre süzülmesi gerekir.

Ekranın sırası dar ekrana göre: yardım bağlantısı ve varsa taahhüt (tek
satır) → **form** (bu ekrana gelen çoğu kişi soracak) → liste. Liste satırı
etkileşimsizdir: talebi açacak ikinci bir ekran yok, cevap e-postayla gelir;
tıklanır görünen bir satır olmayan bir kapıyı gösterirdi.

Formun sonuç cümlesi **sunucunun söylediğidir**: alındı e-postası çıktıysa
"kopyasını gönderdik", çıkmadıysa ya da hiç denenmediyse "kopya gidemedi ama
talep kayıtlı ve aşağıda". İkisini tek "gönderildi" altında toplamak, sahibi
gelmeyen bir e-postayı beklemeye çağırmak olurdu (`docs/110` P0-06 dersi).

Uçlar (`routes/api/support.php`, donmuş imzalar
`ModularApiRouteRegistrationTest`):

| Uç | Yetki | Sınır | Not |
| --- | --- | --- | --- |
| `GET /api/workspaces/{w}/support-requests` | `workspace.manage` | — | `{ requests, commitment }`; en yeni üstte |
| `POST /api/workspaces/{w}/support-requests` | `workspace.manage` | `throttle:5,1` | ad/e-posta hesaptan; 201 + `acknowledgement` |

## 5. Takip: kamuya açık durum sorgusu YOK

Referansla `/support/ZB-3F7K2` gibi bir sayfa **bilerek yok**. Referans
kişisel veri anahtarı olurdu: sekiz karakterle bir başkasının konusunu ve
durumunu görmek, ya da alfabeyi tarayarak talepleri saymak mümkün olurdu.
Durum yalnız **panelde** (oturum + yetki arkasında) ve **e-postada**
(gönderenin kendi kutusunda) görünür. Kamu formundan yazan biri panelsizdir;
onun takibi cevap e-postasıdır — referans o cevabın konusunda durur.

## 6. Süperadmin: kuyruk ekranı ve satırdan cevap

**EKRAN GELDİ (FF-218, `docs/122` Y7, `docs/133`).** Uçlar `/platform` →
**Destek masası**'nın en üstündeki kuyruk kartından okunuyor: bekleyen
talepler (en eski üstte), durum süzgeci, `received`/`answered`/`closed`
geçişleri. Alındı e-postası çıkmamış bir talep ayrıca işaretleniyor —
"yazdım ama cevap gelmedi" çağrısının sebebi çoğu zaman odur.

**CEVAP YÜZEYİ DE GELDİ (SUPPORT-REPLY-01).** Görevli dün kuyruğu okuyup
uygulamadan çıkıyordu: adresi elle kopyalıyor, kendi posta programını
açıyor, cevabı orada yazıyor ve dönüp "Mark answered"a basıyordu — dört
pencere, iki uygulama, ve cevabın gidip gitmediğini kimsenin bilmediği tek
nokta. Artık satırın kendi kutusuna yazıp bir kez basıyor.

| Uç | Sınır | Not |
| --- | --- | --- |
| `GET /api/admin/support-requests?status=` | — | her kanal, en eski üstte (kuyruk); tanınmayan süzgeç = süzgeçsiz. Satır şeması DONMUŞTUR; `SUPPORT_EMAIL`'in var olup olmadığı gövdeye değil `X-Support-Reply-To` başlığına yazılır (`configured`/`missing`) |
| `PUT /api/admin/support-requests/{id}/status` | `throttle:20,1` | `received` / `answered` / `closed`; ilk `answered` geçişi `first_response_at`'i **bir kez** damgalar |
| `POST /api/admin/support-requests/{id}/reply` | `throttle:20,1` | düz metin gövde (kırpıldıktan sonra boş olamaz, üst sınır 5000 karakter); gönderim BAŞARILIYSA 200 ve talep `answered` |

Geçiş kısıtı yok (kapanmış talep yeniden açılabilir); tek değişmez ilk yanıt
damgasıdır — "kaç saatte cevap verdik" ölçümünün kaynağı.

**SIRA: ÖNCE GÖNDER, SONRA DAMGALA.** Ters sırada taşıyıcı düştüğünde satır
cevaplanmış görünürdü, müşteri beklerdi ve ölçüm gönderilmemiş bir cevabı
sayardı. Sürücü `log` ise gönderim hiç DENENMEZ: uç `409
{"reason":"no_outbound_transport"}` döner, satır `received` ve damgasız
kalır. Seçim ya da gönderim patlarsa yine 409'dur ve sebep SABİT bir koddur
— ham sağlayıcı cümlesi (içinde bir anahtar olabilir) yalnız sunucu
günlüğünde kalır, tarayıcıya ve `platform_audits`'e çıkmaz. Satır
değişmediği için AYNI gövde yeniden gönderilebilir.

**GÖVDE HİÇBİR YERDE SAKLANMAZ:** ne satırda, ne denetim izinde. Yeni tablo,
yeni sütun, yeni migration yok. `platform_audits` yalnız `reply_sent` /
`reply_failed`, aktör ve referansı taşır — cevabın METNİ müşterinin
cümlesidir ve denetim izi onu saklamak için yapılmadı.

**BU UÇ EXACTLY-ONCE DEĞİLDİR.** Yinelenen gönderimi eleyen bir anahtar
(ledger, idempotency tablosu) bu pakette YOKTUR: elle atılan iki eşzamanlı
POST iki e-posta üretir. Tek savunma ekrandadır — gönderim sürerken düğme
basılamaz. Kart ayrıca manuel damganın dürüstlüğünü koruyor: "marking a
request answered records the timing, it does not send anything".

**CEVAP ADRESİ HÂLÂ EKSİK (§7.2).** `SUPPORT_EMAIL` boşken cevap yine çıkar
ama `Reply-To` konmaz; kart bunu bir kez, kendi altında söyler. Ekran ilk
yükte adresin durumunu bilmez (`unknown`) ve o sırada UYARMAZ: henüz
bilinmeyen bir eksikliği ilan etmek sahte bir arıza üretirdi.

## 7. Sahibe açık sorular

1. **Yanıt taahhüdü kaç saat?** Verilene kadar üç yüzeyde de cümle yok.
   Değer `SUPPORT_RESPONSE_COMMITMENT_HOURS` ile sunucunun `.env`'ine
   yazılır; deploy gerekmez, `config:cache` yenilenir.
2. **Destek adresi ne?** `SUPPORT_EMAIL` alındı e-postasının "cevapla"
   adresidir. Boşken müşteri alındı e-postasına cevap yazamaz (cümle de
   yazılmaz). Sahibe giden bildirimin adresi ayrıdır
   (`CONTACT_NOTIFICATION_ADDRESS`); ikisi aynı kutu olabilir.
3. **Editör panelden destek isteyebilsin mi?** Bugün hayır (§4). Evetse
   liste kişiye göre süzülür ya da ayrı bir "benim taleplerim" görünümü açılır.
4. **Kum havuzu alanı** (`docs/93`): Mailgun kum havuzu yalnız yetkili
   alıcılara teslim eder. Alındı e-postası **rastgele bir müşteriye
   ulaşmaz** — doğrulanmış alan adı gelene kadar. Kod bunu bilemez; sonuç
   satıra "taşıyıcı devraldı" olarak yazılır, çünkü Mailgun mesajı kabul edip
   sessizce düşürür. Bu, kayıt doğrulama ve davet için zaten bilinen kısıtın
   aynısıdır.

## 8. Kanıt

| Test | Ne donduruluyor |
| --- | --- |
| `PublicSupportRequestTest` (10) | Kamu formu → `support_requests`; referans biçimi; ekranda referans; alındı e-postası ve sonucu; sahibe bildirim referanslı; adres/taşıyıcı yokken damga yok; bal küpü; eski tablo yazılmaz ama durur |
| `ResponseCommitmentTest` (3) | Taahhüt yokken hiçbir yüzeyde yok; varken üç yüzey aynı cümle; geçersiz değer = yok |
| `WorkspaceSupportRequestTest` (7) | Hesaptan ad/e-posta; liste yalnız bu çalışma alanı; başkası 404; editör 404, yönetici 201; auth/verified; doğrulama; throttle |
| `PlatformSupportRequestAdminTest` (8) | Süperadmin sınırı; liste ve süzgeç; ilk yanıt bir kez; geçersiz durum 422; **gerçekten çıkan cevap satırı `answered` yapar ve damgayı bir kez atar; taşıyıcı yokken 409 ve satır değişmez; arıza sanitize edilir ve aynı gövde yeniden gönderilir; rolsüz 404 / boş gövde 422 / olmayan talep 404** |
| `SupportReferenceTest` (4) | Alfabe; çarpışmada yeni numara; tükenince gürültü; 23000 ve 23505 |
| `SupportDeploymentContractTest` (2) | İki değişken konteynere `${...}` ile geçer; örnek dosyalarda ad var, değer yok |
| `ModularApiRouteRegistrationTest` | Beş imza (cevap ucu dahil) ve `routes/api/support.php` |
| `SupportPage.test.tsx` (8) | Liste, taahhüt yalnız sunucudan, gönderim ve yeniden okuma, üç sonuç cümlesi, varsayılan istemcinin adresleri, bölüm kaydı (izin + Yönetim grubu), bölümün kayıtta en sonda durması |
| `SupportQueue.test.tsx` (8) | Satırın kim/ne/nasıl ulaşılır bilgisi; alındı uyarısı yalnız çıkmayanda; durum geçişi ve tutulan durumun düğmesizliği; sunucuda süzme; masa düğmesi yalnız hesaplı satırda; boş kuyruk cümlesi; **satırdan tek tıkla cevap ve busy'de ikinci tıklamanın yollamaması; arızada taslağın durması, hatanın yalnız o satırda duyurulması ve cevap adresi yokken bunun söylenmesi** |
| `forms.guard.test.ts` | Talep formu `noValidate` taşır: tarayıcının kendi baloncuğu `submit` olayını yutmaz (`docs/47` Kural 5b) |
| `scripts/mobile-ux-audit` | Üç hikâye kökü (`SupportPage` 4, `SupportRequestForm` 3, `SupportRequestList` 4), 320×568 gerçek Chrome: 11/11 hikâye ölçüldü, bulgu sıfır; kullanılabilir genişlik sayfa 288/320, kart 270/320 (eşik 230). Ölçüm, aynı makinede eşzamanlı ikinci bir denetim 9355 portunu tuttuğu için ayrı portta ve 2 sn bekleme ile alındı — betiğin 450 ms'lik beklemesi yük altında hikâyeyi çizilmeden ölçüyor ve boş ölçümü "sorun yok" diye raporluyor; bu, aracın kendi açık borcudur (`docs/117` §0 ile aynı aile) |

## 9. Ürün iddiası

**Çalışır:** Kadıköy'deki kebapçı iletişim formuna "menüm görünmüyor" yazar;
ekranda `ZB-3F7K2` görür; (taşıyıcı yapılandırılmışsa) aynı numarayı taşıyan
bir alındı e-postası alır; sahibe giden bildirim aynı numarayı taşır. Oturum
açmışsa panelden `Destek`'e girer, adını yazmadan talep açar, talebini
listede durumuyla görür.

Süperadmin `/platform` → Destek masası'nda kuyruğu görür, satırın kutusuna
cevabını yazar ve bir kez basar: (taşıyıcı yapılandırılmışsa) e-posta
referansı konu satırında taşıyarak Hüseyin'e çıkar, talep `answered` olur ve
ilk yanıt damgası bir kez düşer. Taşıyıcı yoksa hiçbir şey gönderilmez,
satır olduğu yerde kalır ve aynı cevap sonra yeniden gönderilebilir.

**Çalışmaz:** aynı cevabın **iki kez gönderilmesini sunucuda eleyen** bir
anahtar (§6: ekrandaki kilit dışında koruma yok, elle atılan iki eşzamanlı
POST iki e-posta üretir); müşterinin cevaba **"cevapla" diyebilmesi**
(`SUPPORT_EMAIL` boş, §7.2); gönderilmiş cevabın **metninin bir yerde
saklanması** (bilerek, §6 — gövde hiçbir yere yazılmaz, bu yüzden "ne
cevapladık" sorusunun kaydı da yok); kamu formundan yazan birinin **durum
takibi** (bilerek, §5); yanıt süresi vaadi (sahip sayıyı verene kadar, §3);
Mailgun kum havuzunda rastgele alıcıya teslim (§7.4).
