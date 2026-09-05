# 112 — dataLayer olay taksonomisi: panel tarafı ölçüm sözleşmesi

**Sahibin kuralı (kilit, `docs/46`):** Google Analytics, Yandex Metrica,
Google Tag Manager, dataLayer, Hotjar ve Metabase ile **her şey tenant
bazında** analiz edilebilir olmalı. Ve (2026-09-05): *"GTM dataLayer, en
ince ayrıntısına kadar, çok detaylı raporlamalar için, altyapı hazır
olmalı."*

## 0. Bugünkü gerçek

Altyapı **var ve doğru kurulmuş:**

- Uygulamaya yalnız GTM giriyor; GA4, Metrica ve Hotjar konteynerin içinden
  yönetiliyor (`config/analytics.php`). Yeni bir araç eklemek deploy
  gerektirmiyor.
- Tenant bağlamı her olaya otomatik ekleniyor: `zabuno_tenant_id`,
  `zabuno_tenant_slug`, `zabuno_plan`, `zabuno_role`
  (`resources/js/lib/analytics.ts`).
- Bağlam gelmeden basılan olaylar **kuyruğa alınıyor** ve bağlam gelince
  serbest bırakılıyor — yani ilk saniyedeki olaylar tenant'sız kaybolmuyor.
- Kişisel veri koruması var: `assertNoPersonalData` olayı reddediyor.
- Hiçbir kimlik verilmezse ölçüm tamamen kapalı; tek script yüklenmiyor.

**Eksik olan tek şey OLAYLARIN KENDİSİ.** Tüm uygulamada `trackEvent`
çağrısı **iki tane**: bir hata sınırı ve bir sürüm bandı. Yani boru hattı
kurulu ve boş akıyor — GTM konteyneri bugün açılsa raporlarda yalnız sayfa
görüntülemeleri olurdu.

**Ayrım önemli:** misafir tarafının kendi ölçümü ZATEN VAR ve sunucuda
yaşıyor (`analytics_events` tablosu: `qr_resolve`, `menu_open`, `item_view`,
`search_no_results`). Bu belge onun yerine geçmez — **panel tarafını**,
yani restoran sahibinin ürünü nasıl kullandığını ölçer.

## 1. Neden iki ayrı ölçüm var ve karıştırılmamalı

| | Misafir tarafı | Panel tarafı (bu belge) |
| --- | --- | --- |
| Nerede yaşar | Sunucu, `analytics_events` | Tarayıcı, `dataLayer` → GTM |
| Kim üretir | Masadaki misafir | Restoran sahibi/ekibi |
| Soru | "Menüm işe yarıyor mu?" | "Ürün işe yarıyor mu?" |
| Kime gösterilir | Sahibe, Insights ekranında | Bize, GA4/Metrica/Metabase'de |
| Neden GTM değil | Misafir sayfası hafif olmalı; üçüncü taraf script'i menü açılışını yavaşlatır | Panel zaten oturumlu ve ağır; ölçüm oraya girer |

Misafir olaylarını GTM'e taşımak cazip görünür ve **yanlıştır**: masadaki
misafir bir pazarlama konteyneri indirmek zorunda değildir, ve o veri
sahibin ürününün bir parçasıdır — bizim raporumuzun değil.

## 2. Adlandırma sözleşmesi

- Olay adı `snake_case`, fiil + nesne: `menu_item_price_changed`.
- **Geçmiş zaman**, çünkü olay olduktan sonra basılır: `published`, `changed`.
- Alan adları `snake_case` ve ön eksiz; tenant alanları `zabuno_` önekli
  (zaten otomatik ekleniyor).
- GA4 sınırı: olay adı 40 karakter, alan adı 40, alan değeri 100. Uzun bir
  ad GA4'te sessizce kırpılır — bu yüzden hiçbir ad 40'ı geçmez.
- **Sayılar sayı olarak basılır, dize olarak değil.** GA4'te dize bir metrik
  olamaz; `"3"` basan bir alan bir daha toplanamaz.

## 3. YASAKLAR — bu bölüm sözleşmenin kendisidir

1. **Kişisel veri basılmaz.** Ad, e-posta, telefon, adres, IP. Ürün adı ve
   kategori adı kişisel veri değildir ve basılabilir; **misafirin arama
   terimi de basılmaz** — o sunucuda kalır.
2. **Sır basılmaz.** Anahtar, token, imzalı adres, oturum kimliği.
3. **Ham sunucu hatası basılmaz.** `error_code` evet, yığın izi hayır.
4. **Uydurma sayı basılmaz.** Bir alanın değeri yoksa alan HİÇ
   gönderilmez — `null`, `0` ya da `"unknown"` değil. Sıfır bir ölçümdür ve
   bilinmeyenin yerine geçemez.
5. **Onay olmadan ölçüm yok.** `analytics-consent-tagging` modülünün kuralı
   geçerli; bu belge onu gevşetmez.

## 4. Olay listesi

Ölçüt: *bu olayı bilmek bir ürün kararını değiştirir mi?* Değiştirmiyorsa
listede yok. Her satır bir soruya bağlı.

### 4.1 Aktivasyon — "sahip ürüne girdi mi, ilk değerine ulaştı mı?"

| Olay | Alanlar | Cevapladığı soru |
| --- | --- | --- |
| `workspace_created` | `has_brand` | Kayıt sonrası kaç kişi çalışma alanı kuruyor |
| `brand_saved` | `is_first` | Marka adımı kaç kişide takılıyor |
| `location_created` | `location_count` | İkinci şube ne zaman geliyor |
| `menu_created` | `source` (`manual`/`csv`/`ai_photo`) | Menü hangi yoldan kuruluyor |
| `menu_item_added` | `item_count`, `has_photo`, `has_description` | İlk menü ne kadar dolu |
| `first_publish_completed` | `minutes_since_signup`, `item_count` | **Time to First QR** — `docs/110` §7'nin ölçemediği sayı |
| `qr_downloaded` | `format`, `size`, `count`, `is_bulk` | Kod gerçekten basılıyor mu |

`first_publish_completed` bu listenin en değerlisi: sahibin kaydolmaktan
menüsünü yayınlamaya kadar geçen süresi bugün **hiçbir yerde ölçülmüyor**
ve `docs/110` §7'deki "5 dakika mı 15 dakika mı" tartışması bu sayı olmadan
kapanamaz.

### 4.2 Günlük işletim — "ürün her gün kullanılıyor mu?"

| Olay | Alanlar | Cevapladığı soru |
| --- | --- | --- |
| `menu_item_price_changed` | `direction` (`up`/`down`) | Fiyat güncelleme gerçek bir alışkanlık mı |
| `menu_item_stock_toggled` | `to` (`out`/`back`) | "Bugün bitti" günde kaç kez kullanılıyor |
| `menu_item_visibility_toggled` | `to` | Gizleme ne sıklıkta |
| `menu_published` | `change_count`, `is_scheduled`, `is_rollback` | Yayın döngüsü ne kadar sık döner |
| `publication_rolled_back` | `versions_back` | Geri alma gerçekten kullanılıyor mu |
| `media_uploaded` | `kind`, `size_bucket`, `outcome` | Yükleme kaç kez takılıyor |

`size_bucket` bilerek kova (`<1mb`, `1-5mb`, `5-15mb`, `15mb+`): ham bayt
GA4'te yüksek kardinalite üretir ve kimse "7.318.402 bayt" diye rapor
okumaz.

### 4.3 Sürtünme — "ürün nerede zorluyor?"

| Olay | Alanlar | Cevapladığı soru |
| --- | --- | --- |
| `form_validation_failed` | `form`, `field`, `reason` | Hangi alan kaç kişiyi durduruyor |
| `action_blocked` | `action`, `reason` (`permission`/`plan`/`state`) | Kaç kişi yapamadığı bir şeye tıklıyor |
| `upload_rejected` | `reason`, `kind` | Boyut/tür sınırı kaç kişiyi kesiyor |
| `empty_state_seen` | `screen` | Hangi ekran boş kalıyor |
| `retry_clicked` | `surface` | Hangi hata tekrar denettiriyor |

`action_blocked` özellikle değerli: bu depo "yapılamayan iş çizilmez"
kuralını uyguluyor, ama sunucunun 403/402 döndüğü her yer bir tasarım
eksiğinin izidir.

### 4.4 AI — "makine gerçekten yardım ediyor mu?"

| Olay | Alanlar | Cevapladığı soru |
| --- | --- | --- |
| `ai_import_started` | `source`, `page_count` | Kaç kişi deniyor |
| `ai_import_reviewed` | `accepted_count`, `rejected_count`, `edited_count` | İnsan onayı ne kadar düzeltiyor |
| `ai_suggestion_accepted` | `kind` | Öneriler işe yarıyor mu |
| `ai_unavailable_seen` | `capability`, `reason` | Sağlayıcı yokluğu kaç kişiyi vuruyor |

`ai_import_reviewed`'ın üç sayısı birlikte anlamlıdır: reddedilen oranı
yüksekse model kötü, düzeltilen oranı yüksekse model yaklaşıyor ama
yetmiyor. Tek bir "başarı" sayısı bu ikisini ayırt edemezdi.

## 5. Ölçüm KAPSAMI DIŞI — ve nedenleri

| Basılmayacak | Neden |
| --- | --- |
| Misafirin arama terimi | Sahibin verisidir, bizim raporumuzun değil; sunucuda kalır |
| Menü içeriğinin kendisi (fiyat değeri, ürün adı) | Ticari veri; `direction` yeter |
| Ekip üyesinin kimliği | Kişisel veri; `zabuno_role` yeter |
| Klavye/fare hareketi, kaydırma derinliği | Hotjar'ın işi, GTM konteynerinden açılır; koda girmez |
| Sunucu tarafı olaylar | GA4'e sunucudan olay göndermek ikinci bir gerçek kaynağı olurdu |

## 6. Uygulama sırası

1. **Sürtünme olayları** (§4.3) — en ucuz ve en çok öğreten. Kod zaten o
   noktalarda hata gösteriyor; olay basmak tek satır.
2. **Aktivasyon** (§4.1), özellikle `first_publish_completed` — pilot
   başlamadan önce ölçülmeye başlamalı, yoksa pilotun ilk haftası kayıp.
3. **Günlük işletim** (§4.2) — pilot sırasında anlam kazanır.
4. **AI** (§4.4) — sağlayıcı anahtarı girildikten sonra.

## 7. Kapı

Olay adları ve alan adları bir yerde **tek** tanımlanır ve serbest dize
kabul edilmez; bir test hem taksonomiyi hem yasakları zorlar:

- Bilinmeyen bir olay adı basılamaz (yazım hatası GA4'te sessizce yeni bir
  olay yaratırdı ve rapor ikiye bölünürdü).
- Yasaklı alan adları (`email`, `phone`, `name`, `token`, `search_term`)
  hiçbir yükte geçemez.
- Ad ve alan uzunlukları GA4 sınırlarının altında kalır.

Bu kapı olmadan taksonomi bir gün sonra dağılır: ölçümün en sık ölme
biçimi yanlış ölçüm değil, **tutarsız** ölçümdür.
