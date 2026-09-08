# 135 — Ölçüm kimliği kasaya girer, sunucuya değil

> **Bu belge bir yetenek kaydıdır.** Ölçümün NEDEN tek dikişte (GTM)
> toplandığı `docs/46` §3'te ve `config/analytics.php` başında sahiplenilir;
> hangi kimliğin hangi araca ait olduğu `docs/126` §1'de; kasanın kendi
> disiplini (sır geri okunmaz, kapatmak silmek değildir) `docs/94`'te. Üçü de
> burada **tekrar edilmez**. Bu belge yalnız şunu anlatır: konteyner kimliği
> artık panelden girilir, hangi alanlar açıldı, iki tuzak nasıl çözüldü ve
> **sahip ne yapacak**.

**Sahibin cümlesiyle:** *"Ayarlar tarafında OpenAI API, Claude Anthropic API,
Iyzico API gibi alanlar var. Bir de GTM için alan aç entegrasyonlara, sonra
migrate et, update et."*

## 0. Önce ve sonra

| | Önce | Sonra |
| --- | --- | --- |
| Konteyner kimliği nereye girilir | Üretim sunucusunun `.env` dosyası | **Panel** → Provider keys, ya da (hâlâ) `.env` |
| Sahip bunu tek başına yapabilir mi | **Hayır** — SSH + dosya düzenleme | **Evet** — tarayıcıdan, üç dakikada |
| GA4/Metrica/Hotjar izinleri | `.env`de üç ayrı bayrak | Panelde üç açılır liste, ya da (hâlâ) `.env` |
| Kimlik yoksa | Ölçüm sessizce kapalı | Değişmedi |
| Veritabanı okunamıyorsa | (Ölçüm veritabanına hiç bakmıyordu) | Sayfa çizilir, ölçüm sessizce kapalı |

Bugün üretimde ölçümün kapalı olduğu (`docs/126` §0) bu paketle
**değişmedi**: bu paket kimliğin girilebileceği yeri açar, kimliği girmez.

## 1. Sağlayıcının adı: `google_tag_manager`

`gtm` değil. Bu değer denetim izinde
(`platform_credential_audits.provider`), API cevabında ve panelin URL'sinde
düz metin olarak görünür; oradaki diğer bütün adlar okunabilir ürün
adlarıdır (`mailgun`, `openai`, `iyzico`). Üç harfli bir kısaltma, altı ay
sonra o denetim satırına bakan kişiye hiçbir şey söylemezdi.

## 2. Dört alan — ve neden bu biçimde

| Alan | Sır mı | Zorunlu mu | Varsayılan |
| --- | --- | --- | --- |
| `container_id` | **Hayır** | Evet | yok |
| `ga4` | Hayır | Hayır | `off` |
| `yandex_metrica` | Hayır | Hayır | `off` |
| `hotjar` | Hayır | Hayır | `off` |

**Konteyner kimliği sır DEĞİLDİR.** Tarayıcıya yüklenen script adresinde
(`gtm.js?id=GTM-…`) herkese görünür. Sır sayılsaydı panel yalnız `••••`
maskesi gösterirdi (`docs/94` §"Sır asla düz yazıya çıkmaz") ve sahip
girdiği kimliği bir daha **hiç doğrulayamazdı** — koruduğu bir şey olmadan.
Bu sağlayıcının hiçbir sır alanı yoktur; kasanın "sır deposu" değil
"sahibin girdiği sağlayıcı yapılandırması" olduğunu gösteren ilk kayıt budur.

**Üç hedef neden ayrı alan, virgüllü tek bir liste değil.** Bunlar GTM'i
değil **CSP'yi** yapılandırır (`docs/126` §3) ve her biri bağımsız bir
karardır. Tek bir metin kutusunda `GA4` ya da `ga4, hotjar` yazımı sessizce
"kapalı" sayılırdı: hiçbir hata çıkmaz, yalnız raporlar boş kalırdı — bu
dikişin tamamı tam olarak o sessiz kaybı kapatmak için var. Ayrı alanlar
ayrıca panelin hangi araçların var olduğunu **adıyla** göstermesini sağlar;
sahip sözcük dağarcığını önceden bilmek zorunda kalmaz.

**Hedefler serbest metin değil, kapalı uçlu bir listedir** (`off` / `on`).
Alan tanımı bu listeyi taşır (`CredentialField::$choices`); panel açılır
listeyi ondan çizer ve kasa listede olmayan bir değeri **reddeder**. Ekranda
bir açılır liste, veritabanında iki kelimeden biri, iki yerde de aynı tek
kaynak.

Alan adları `config/analytics.php`'deki `destinations` anahtarlarıyla
birebir aynıdır — ikinci bir sözlük, bir gün ayrışacak bir eşleme tablosu
demek olurdu.

## 3. Öncelik: KASA > env — ve env ÇALIŞMAYA DEVAM EDER

Yeni bir öncelik mantığı **yazılmadı**. Ölçüm, postanın ve ödemenin kullandığı
yolun aynısını kullanır: sıra `CredentialResolverPort`un içinde, tek bir
yerde yaşar (`docs/94` §"Öncelik: KASA > env"). Somut sonuç:

- Kasa boşsa `ANALYTICS_GTM_CONTAINER_ID` çalışmaya devam eder. Bugün
  ölçümü açan tek şey odur ve onu kırmak, kasaya kimlik girilen güne kadar
  ölçümü durdurmak olurdu.
- Kasaya yazılan alan kazanır; **yazılmayan alan env'den gelir**. Sahip
  kasaya yalnız konteyner kimliğini girdiğinde, sunucuda zaten açık olan
  bir hedef sessizce kapanmaz.

**Zincirin ÜÇ okuma noktası da aynı kaynağa bakar** — ve bu, bu pakette
kapatılan gerçek bir açıktı: CSP başlığı, konteyner script'i ve **çerez onay
şeridi**. Şerit yalnız "ölçüm yapılandırılmış" ise çıkar; o yüzden şerit
env'e, konteyner kasaya bakmış olsaydı sahip kimliği panelden girdiği gün
şerit hiç çıkmaz, onay alınamaz ve konteyner de hiç yüklenmezdi — ölçüm
"açıldı" sanılırken kapalı kalırdı ve bunu gösteren tek bir hata olmazdı.

## 4. Birinci tuzak: kasa bir veritabanıdır, sayfa çizimi ona bağlı değildir

Kurumsal site bilerek veritabanısız çizilebiliyor (`site:export-static`).
Ölçüm okuması bir sayfa isteğini **çökertemez**. Okuma yolu istisnayı yutar:
veritabanı yoksa, tablo yoksa ya da bağlantı düşmüşse yapılandırmaya (env)
düşülür; kimlik oradan da gelmiyorsa ölçüm **sessizce kapalıdır** — bugünkü
"kimlik yoksa ölçüm yok" davranışının aynısı — ve sayfa çizilmeye devam eder.

Yutulan istisna bir kural değil, adı konmuş bir istisnadır: yalnız okuma
yolunda geçerlidir. Panelin **yazma** yolu istisnayı yutmaz — orada sessiz
bir başarısızlık, sahibin kaydettiğini sanması demek olurdu.

Testle sabit: `VaultMeasurementIdentityTest` kasanın tablosunu düşürür ve
sayfanın 200 döndüğünü, tek bir ölçüm script'i yüklenmediğini ve CSP'nin
bugünkü kadar sıkı kaldığını ölçer.

## 5. İkinci tuzak: CSP her yanıtta üretilir, ama her istekte kasaya gidilmez

Hedef listesi CSP başlığını belirler; başlık her yanıtta yeniden üretilir.
Her istekte bir veritabanı sorgusu kabul edilemez, dolayısıyla çözülen değer
önbelleğe alınır. Önbellek varsa **geçersiz kılma da olmak zorundadır**:
yoksa sahibin panelden açtığı hedef "sunucuyu yeniden başlat" diyene kadar
CSP'ye yansımaz ve o cevap bu üründe kabul edilemez.

İki mekanizma birlikte çalışır:

- **Anında.** Kasadaki her mutasyon `PlatformCredentialChanged` olayını
  doğurur (denetim satırının yazıldığı tek boğazdan) ve dinleyici önbellek
  anahtarını düşürür. Paylaşılan bir önbellek deposunda (redis / veritabanı /
  dosya) bu, bütün düğümleri kapsar.
- **Tavan: 60 saniye.** Her düğümün kendi önbelleği varsa olay yalnız yazan
  düğümde çalışır; ömür, geri kalanının bayatlığını bir dakikayla sınırlar.
  Süre, sahibin bir ayarı açıp sayfayı yenilerken beklemeye razı olacağı en
  uzun aralık olarak seçildi: daha uzunu "çalışmıyor" diye okunur, daha
  kısası kasanın okunma sıklığını sebepsiz artırırdı.

Olayın kasanın içinde değil, dışında dinlenmesi bilinçlidir: depo kendisini
okuyan tüketicileri tanımak zorunda kalmamalı, yoksa ikinci tüketici geldiği
gün ya unutulur ya da depo şişer.

Testle sabit: aynı dosya bir hedefi kapalıyken okur (önbelleği kurar),
panelden açar ve **yeniden başlatmadan** CSP'de göründüğünü ölçer.

## 6. Göç gerekmedi — ve sebebi

Yeni bir migration **yazılmadı**. `platform_credential_connections` şeması
sağlayıcıdan bağımsızdır: `provider` düz bir metin sütunudur ve alanlar
`plain_fields` JSON sütununda yaşar (`docs/95` Faz 3 göçü). Yeni bir
sağlayıcı, yeni bir sütun değil yeni bir **enum değeri** demektir.

Boş bir migration eklemek, "burada bir şema değişikliği oldu" diyen ve
geri alınırken bir şey yapmayan bir kayıt bırakırdı — ileride bu tabloyu
okuyan kişiyi olmayan bir değişikliği aramaya gönderirdi.

Sahibin "migrate et" cümlesinin karşılığı burada **panelin yeni sağlayıcıyı
göstermesi**dir; o da şemadan (`CredentialProvider`) otomatik gelir ve
`ConnectionApiTest` bunu kilitler.

## 7. Sahip ne yapacak — adım adım

Kimlikler bu belgede **yoktur**; `docs/126` §1 tablosundan alınır. Onlar
sahibin verisidir ve yalnız buradan elle girilir.

1. Panele superadmin olarak gir ve **Provider keys** ekranını aç
   (`/platform/credentials`).
2. **“+ Add a connection”** düğmesine bas.
3. **Provider**: listeden *Google Tag Manager (measurement)* seç.
4. **Connection name**: kendi verdiğin ad — örneğin `Measurement`. Bu ad
   yalnız kartı tanıman içindir.
5. **Who owns this key**: *Platform account* kalsın. (*Customer’s own key*
   yalnız bir restoranın kendi hesabını getirdiği durum içindir; ölçümde
   böyle bir durum yok.)
6. **Container ID (GTM-…)**: `docs/126` §1'deki GTM kimliğini yaz. Yazdığını
   ekranda **göreceksin** — bu alan sır değil.
7. **Allow Google Analytics 4**: *On*. Bunu açmazsan GTM'de kurduğun GA4
   etiketi tetiklenir ama isteği tarayıcı engeller ve rapor boş kalır
   (`docs/126` §3 — bu, dağıtım günü saatlerce aranan kusurun tam kendisi).
8. **Allow Yandex Metrica**: *On*.
9. **Allow Hotjar**: *Off* bırak. Hotjar hesabı yok; kapalı bir araç,
   saldırı yüzeyi olarak da kapalıdır (`docs/126` §6 madde 6).
10. **Save connection**.

Sonra doğrula:

11. Tanıtım sitesini yeni bir sekmede aç. Alt kısımdaki ölçüm sorusunda
    **“Allow measurement”**e bas — onay verilmeden konteyner sayfaya
    **hiç girmez** ve bu bilinçlidir (`docs/46` §6, FF-173). Onaysız bakıp
    "çalışmıyor" sonucuna varmak, bu paketin en olası yanlış teşhisidir.
12. Sayfayı yenile ve sayfa kaynağında `googletagmanager.com` ara. Görünüyorsa
    dikiş çalışıyor. Görünmüyorsa bir dakika bekleyip bir kez daha yenile
    (§5, tavan süre).
13. Bundan sonrası **GTM arayüzünde**: GA4 ve Metrica etiketleri
    (`docs/126` §6 madde 3-4). Depo o tarafı ne görür ne doğrular.

Kimliği kaldırmak istersen: kartın **Disable** düğmesi. Kapatmak silmek
değildir; kimlik yerinde durur ve tek tuşla geri açılır (`docs/94`).

## 8. Bu paketin ÇÖZMEDİĞİ

| Ne | Durum |
| --- | --- |
| Üretimde kimliğin girilmiş olması | **Hayır.** Bu belge o düğmeyi açar, basmaz |
| GTM konteynerinin içindeki etiketler | **Bilinmiyor.** Dış arayüz; depo görmez |
| Ölçümün tenant başına doğru kırıldığı | Değişmedi — `docs/46` §4.3'ün konusu |
| Metabase tarafı | Kapsam dışı; o bir veri modeli meselesi (`docs/46` §5) |
| DNS / SPF / DKIM | Hâlâ eksik (`docs/126` §5) — bu paketle ilgisiz |

"Bilinmiyor" burada "geçti" değildir.
