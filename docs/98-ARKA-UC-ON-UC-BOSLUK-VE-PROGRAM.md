# 98 — Arka uçta var, ön uçta yok: 6 turlu boşluk analizi ve uygulama programı

**Adlandırılmış plan:** `SURFACE-CLOSE-v1`. Sayaç aşağıda, §5.
**Kaynaklar:** `php artisan route:list` ↔ `resources/js` çağrı taraması
(programatik), `docs/61` (plan uygulama envanteri), `docs/49` (DAM planı),
`docs/50` (shell planı), `docs/69` (DoD), sahibin 2026-09-04 talimatı.

---

## 0. Önce dürüst durum — "yapmadın" iddiası karşısında kayıt

Sahibin talimatı dört planı "yapılmadı" sayıyor. Kayıt şunu gösteriyor:

| İstenen | Var mı | Nerede | Ne kadarı gerçek |
| --- | --- | --- | --- |
| Shell/masterpage tasarım kuralları → codebase planı | ✅ | `docs/50` (10 faz), `docs/55` (bilgi mimarisi), `docs/60` (inspector), `docs/63/64/65` | Plan tam; uygulama `docs/61` §A-B: 17 maddenin 14'ü ✅, 3'ü ⛔ gerekçeli |
| Frontend felsefesi eleştirel raporu (`FRONTEND-FOUNDATION-v1`) | ✅ kısmen | FF-00 → `docs/52` Preview Truth; FF-01/02/03 → `docs/59/60/66`; FF-04 → `docs/56/67`; FF-07 → `docs/69` altın yolculuk | FF-05 (ECA/Undo) ⬜, FF-06 (bağlamsal AI) → **bu oturumda FF-51/52/53 ile kapandı** ama `docs/61` G4/G5 hâlâ ⬜ yazıyor — **envanter eskimiş** |
| Medya/dosya yönetimi (DAM) planı + kararlar | ✅ plan, 🔶 kod | `docs/49` 10 faz; Faz 1 ✅ (tablolar, üç eksen, 17 slot, INV-01..07 testleri); sahibin beş kararı §11'de işlenmiş | **Faz 2-10 yazılmadı.** `docs/61` F2-F10: 8 madde ⬜/🔶 |
| `imageoptimization-main`'den alınacaklar | ✅ | `docs/49` §2 (alınacak/alınmayacak/kopyalanacak) | — |
| Superadmin estetiği (Metronic) | ⬜ | hiçbir belgede yok | — |
| Frontpages / Flowbite / masterpage / maturity | ⬜ plan yok | sayfalar VAR (`resources/views/public/*`: home, pricing, help, contact, legal) ama plan belgesi yok | — |
| "Kebapçı için tasarım" (acemi kullanıcı UX) | ⬜ | `docs/47` Ask→Infer→Defer→Validate ve `docs/70` görev listesi dolaylı karşılıyor; adlandırılmış plan yok | — |
| Release readiness: 6 madde "Unavailable" | 🔶 | 3'ünün arka ucu var (tenant izolasyonu ✅ bağlı, yedek tatbikatı ✅ bağlı, **host-capability arka ucu var ama HTTP ucu ve ekranı YOK**); 3'ünün (QR fiziksel tarama, RPO/RTO, ASVS) **hiç arka ucu yok** | ekran görüntüsündeki 6 "Unavailable" bunun sonucu |

**Sonuç:** planlar büyük ölçüde yazılmış ve 6 tur uygulanmış; **gerçek boşluk
DAM Faz 2-10, engineering kabuğu, readiness kanıt kayıtları, üç yeni plan
(Metronic, frontpages, acemi-UX) ve eskimiş envanterdir.** Bu belge o
boşlukları kapatır.

---

## 1. Tur 1 — Rota taraması: arka uçta var, hiçbir ekran çağırmıyor

97 API rotası programatik tarandı (`route:list` ↔ `resources/js` gövdesi,
test/story dosyaları hariç). 16 rota hiçbir istemci kodundan çağrılmıyor;
elle doğrulama sonrası sınıflandırma:

| Rota | Sınıf | Karar |
| --- | --- | --- |
| `PUT .../brand/logo` | **gerçek boşluk?** doğrulanacak — `BrandInspector` logo gösteriyor, bağlama ucu farklı olabilir | FF-64'te doğrula |
| `PUT .../menu/{menu}/stock` (menü geneli "hepsi stokta") | **gerçek boşluk** — `docs/82` yalnız ürün düzeyini bağladı | FF-64 |
| `PUT .../qr-codes/{qr}/destination` | `QrDestinationRegion` var; adresi farklı kalıpla kuruyor olabilir | FF-64'te doğrula |
| `POST .../menu-categories/{c}/products`, `.../menu-items` | **bilinçli eski** — `menu-entries` tek adım onların yerini aldı (`docs/47`) | kaldırılmaz, belgeli |
| `GET/PUT/POST admin/credentials*` | **bilinçli eski** — çok-bağlantı yüzeyi yerini aldı (FF-57) | kalır, "varsayılan bağlantı kısayolu" |
| `POST invitations/accept/{token}` | web sayfası `ShowTeamInvitationController` üzerinden; API ucu ikinci yol | doğrula |
| Iyzico webhook/callback | sağlayıcı çağırır, istemci değil | boşluk değil |
| `probe`, `activate`, `entitlements`, `export.png/svg` | tarayıcı yanlış pozitifi — kullanılıyor | — |

## 2. Tur 2 — Artisan komutları ve kanıt tabloları: ekransız arka uç

| Arka uç | Durum | Ekran |
| --- | --- | --- |
| `security:evidence:tenant-isolation` → `tenant_isolation_evidence` | ✅ | ✅ readiness |
| `security:evidence:backup-restore` → `backup_restore_evidence` | ✅ | ✅ readiness |
| `platform:evidence:host-capability` → `host_capability_evidence` | ✅ tablo + komut | **⬜ HTTP ucu yok, ekran "Unavailable"** |
| QR fiziksel tarama kanıtı | ⬜ tablo yok | ⬜ — **oysa yapıldı** (sahip telefonla taradı; `CRIT-JOURNEY-ANALYTICS-01` yazılım tarafını kanıtlıyor) |
| RPO/RTO kararı | ⬜ tablo yok | ⬜ — bir KARAR kaydıdır, tatbikat değil |
| OWASP ASVS denetimi | ⬜ | ⬜ — üçüncü taraf raporu; uydurulamaz, yalnız **kayıt** edilebilir |

## 3. Tur 3 — Tablolar: veri modeli var, kullanıcı görmüyor

| Tablo | Ekran |
| --- | --- |
| `media_versions`, `media_renditions`, `media_usages`, `media_processing_jobs`, `media_blobs` | ⬜ hiçbiri — Media sayfası düz liste (`docs/61` E9/E10, F2-F10) |
| `ai_artifacts` (`applied_at`, `used_fallback`) | ✅ FF-51/53 |
| `ai_invocations` (maliyet, süre, sonuç) | ⬜ — `docs/95` Faz 4 maliyet panosu |
| `platform_credential_audits` | ⬜ — denetim izi ekranı yok |
| `ai_connection_assignments` | ⬜ — "hangi tenant hangi hesapta" görünmüyor |
| `ledger` / manual payments | ✅ platform |

## 4. Tur 4-6 — Plan belgeleri, yetki modeli, kamu yüzeyi

- **Tur 4 (plan↔kod):** `docs/61`'in ⬜ satırları: A2, B4, C2/C3/C6/C12, D3,
  E2/E6/E9/E10, F2-F10, G4/G5 (**aslında ✅ — güncellenecek**), H2-H4, I1/I2/I4.
- **Tur 5 (yetki):** 13 `Permission` var; ön uç hiçbirini okumuyor (`0` referans).
  Gezinti kaydında `permission`/`entitlement`/`featureFlag` alanı yok (I1).
  Pennant kurulu değil (I2). Sonuç: bir Editor, kullanamayacağı bir eylemi
  görüp 403 alabilir.
- **Tur 6 (kamu yüzeyi):** 5 Blade sayfası var; plan/maturity belgesi yok;
  header/footer masterpage sözleşmesi yok; Flowbite yalnız uygulama içinde.

---

## 5. Program — `SURFACE-CLOSE-v1`

**Sayaç: 13/13 tamamlandı.** Her paket tek writer, RED→GREEN,
Pint+tam QA, kendi PR'ı. Sıra bağımlılığa göre; kurallar arasından
esnetilen tek şey **paket kapsamı** (bkz. §6).

| # | Paket | Kapsam | Kapı |
| --- | --- | --- | --- |
| 1 ✅ | **FF-63 Readiness kanıtı** | host-capability HTTP ucu + ekran; `release_evidence` genel kayıt tablosu (QR fiziksel tarama, RPO/RTO kararı, ASVS raporu) + `platform:evidence:record` komutu + ekran; sahibin gerçek taramasını kaydet | 6 maddenin 6'sı gerçek kayıttan okunur; kayıtsız madde dürüstçe "Unavailable" |
| 2 ✅ | **FF-64 Rota boşlukları** | menü-geneli stok, brand/logo ve QR destination doğrulaması; bilinçli-eski rotalar belgeli | Tur 1 listesi sıfır "doğrulanacak" |
| 3 ✅ | **FF-65 Envanter tazeleme** | `docs/61` G4/G5, E9/E10, A2 güncellemesi; FF-49..62'nin izi | envanter gerçeği söylüyor |
| 4 ✅ | **FF-66 Engineering kabuğu** | `/engineering/*` ayrı kabuk: readiness, güvenlik kanıtı, yedek tatbikatı, host-capability, AI denetim izi | `docs/69` madde 3 ✅ |
| 5 ✅ | **FF-67 Superadmin estetiği (Metronic-esinli)** | plan belgesi (`docs/99`) + uygulama: yoğunluk, kart/tablo dili, rozet sistemi, sol rail, üst çubuk; Zabuno token'larıyla, Metronic kopyası değil | platform ve engineering kabukları aynı dili konuşur |
| 6 ✅ | **FF-68 DAM Faz 2** | upload session + idempotency, magic-bytes/decoder doğrulama, karantina zinciri, SVG reddi, `fixtures/malicious` CI kapısı | `docs/49` Faz 2 kabulü |
| 7 ✅ | **FF-69 DAM Faz 3** | immutable original, non-destructive version, `320..1600w` rendition seti, checksum + yinelenen tespiti, reprocess | INV-01..07 yeşil, rollback |
| 8 ✅ | **FF-70 DAM Faz 4+5** | kütüphane ızgara/liste/arama/koleksiyon; asset detayı (kullanım/sürüm/rendition); kullanım grafiği; silme etki önizlemesi; yayın snapshot'ı version'a bağlı | kullanılan asset doğrudan silinemez |
| 9 ✅ | **FF-71 DAM Faz 6+7** | immutable URL + `Cache-Control`/`ETag`, `srcset`/`<picture>`, kota kalemleri (sahip "sen belirle" dedi → §7), izin matrisi (`download_original` serbest — sahibin kararı), reconciliation | LCP ölçülür; kota dolunca canlı menü kesilmez |
| 10 ✅ | **FF-72 Frontpages planı + masterpage** | `docs/100`: kamu sayfaları bilgi mimarisi, header/footer masterpage sözleşmesi, Flowbite bileşen eşlemesi, SEO/URL (`docs/38`) bağı, **maturity seviyeleri** (L0 statik → L4 kişiselleştirilmiş); uygulama: `public.layout` header/footer yeniden | 5 sayfa tek masterpage'den |
| 11 ✅ | **FF-73 Acemi-UX programı ("kebapçı")** | `docs/101`: persona, 5 çekirdek yolculuk (menü kur → ürün ekle → fiyat değiştir → yayınla → QR bas), her adımda tek karar/tek ekran, büyük hedefler, sesli-dil metin, hata yerine geri alma; uygulama: Home görev listesi + menü kataloğu sadeleştirme | 5 yolculuk 320px'te ölçülür |
| 12 ✅ | **FF-74 Yetki-görünürlük + registry** | gezinti kaydına `permission`/`entitlement`; ön uç `me` ucundan izin okur; yetkisiz eylem çizilmez; Pennant | Editor 403 görmez |
| 13 ✅ | **FF-75 Toplu orkestra** (sahibin 2026-09-04 sorusu) | 40 sayfalık menü: `ai_batches` (kalıcı hafıza) + kuyrukta sayfa başına iş (geçici hafıza) + **parti-bazlı** yönlendirme (sağlıklı bağlantılar arasında, bağlantı başına dakikalık bütçe — yapışkanlığa "amaç" boyutu, R30'un Faz 5'e bıraktığı iş öne çekilir) + `CollectorJob` (artifact'ları tek inceleme listesine toplar, yinelenenleri ayıklar) + mevcut insan-onaylı `apply`. `docs/adr/` klasörü ve `agents/*.md` sözleşmeleri (docs/96'daki üç ajan) bu pakette resmileşir | 40 sayfa tek limitte şişmez; sonuç TEK listede incelenir; onaysız hiçbir satır yazılmaz |

**Ertelenen ve nedeni:** DAM Faz 8-10 (crop stüdyosu, AI önerileri, video)
`docs/49`'un kendi fazlamasıyla sonra; video sahibin kararıyla Faz 2'ye
bağlı ama `tus` sunucusu kurulu değil — FF-68 `asset_kind=video`'yu tanır,
transcoding kurmaz.

### FF-63 teslim notu

Altı madde de artık gerçek kayıttan okunuyor. Üçü makine kanıtı (komut
koşturulur), üçü **insan tanıklığı** — ve ikisi ekranda farklı etiketlenir:
"Attested" ile "Passed" aynı rozet değildir. Tanıklık kaydı `kim/ne zaman`
taşır, düzeltilmez, yenisi eklenir; satır elle değiştirilirse uç 500 verir.

**Sahibin fiziksel QR taraması** için kayıt artık 30 saniyelik iş: readiness
sayfasında "Physical QR scan evidence" altındaki formu doldur (cihaz + bir
cümle) → "Record this". Ya da sunucudan:
`php artisan platform:evidence:attest qr-physical-scan --status=passed --summary="…" --payload=device=iPhone`.

**RPO/RTO kararı (MASTER, geri döndürülebilir):** `docs/42`'deki günlük
`db-backups` hacmiyle tutarlı olarak **RPO 24 saat, RTO 4 saat**. Aynı formdan
ya da `--payload=rpo_hours=24 --payload=rto_hours=4` ile kaydedilir.
Kayıt üretimde bir insan tarafından düşülür — bu belge onu düşmüş saymaz.

**ASVS:** `security/OWASP-ASVS-BASELINE.md` bir öz-değerlendirmedir, üçüncü
taraf denetim DEĞİLDİR; kayıt "recorded" durumuyla ve bu cümleyle yapılır,
form yardım metni sertifika iddiasını açıkça yasaklar.

### FF-64 teslim notu

Tur 1'in üç "doğrulanacak" satırı üçü de GERÇEK boşluk çıktı ve kapandı:

- **Marka logosu:** arka uç 2026-08-29'dan beri bağlıyordu, hiçbir ekran
  bağlamıyordu ve hiçbir uç bağlı olanı geri söylemiyordu. `GET brand`
  artık `logoMediaAssetId` taşır; Settings → Brand altında seçici var
  (yükleme yine Media'da, `logo` slotu).
- **QR kodu başka şubeye taşıma:** `docs/81` P1-03 "teslim edildi" diyordu —
  arka uç için doğruydu, ekran yoktu. Kart artık "Move to another location"
  ile taşınır; şube listesi ancak istenince yüklenir; uç `locationId` de
  kabul eder (ekranın elinde menü kimlikleri yok, N istek atılmaz).
- **Kategori geneli tükendi:** `docs/82` kriter 3'ün arka ucu vardı; ekran
  ürün ürün işaretletiyordu. "Balıklar bitti" tek tıklama, tek istek.

Bilinçli-eski rotalar (`products`/`menu-items` tekil uçları, `admin/credentials*`)
kalır ve Tur 1 tablosunda gerekçeli.

### FF-70 teslim notu

`docs/49` Faz 4-5'in ekranı ve kullanım grafiği. Önce/şimdi:

- **Önce:** Media sayfası düz bir listeydi; küçük resim yok, arama yok,
  "nerede kullanılıyor" yok. Silme dosyayı ANINDA diskten siliyordu — geri
  yolu yoktu; kullanılan görselde sunucu 409 döner, ekran "silinemedi" derdi.
- **Şimdi:** liste/ızgara + küçük resim + arama + slot/durum/"kullanılmayan"
  süzgeci; ada tıklayınca çekmece (dosya bilgisi, kullanım, sürümler, geri al,
  yeniden üret); silmede etki önizlemesi ("Adana Kebap ve Urfa Kebap bu
  fotoğrafı kullanıyor → bağı kes ve çöpe at / vazgeç"); silme çöpe atar, Çöp
  sekmesinden geri gelir; `media:purge-trash` 30 gün sonra kalıcı siler,
  yayında olanı hiç silmez.
- **Kullanıcı yolculuğu:** Ayşe yanlış fotoğrafı siler → ürün kartı yer
  tutucuya düşer → ertesi gün Çöp → Geri al → fotoğraf ve bağ aynen döner,
  çünkü dosya hiç silinmemişti.
- **Kalan engel:** koleksiyon/etiket, "yerine başka görsel seç", fallback
  zinciri, asıl indirme (imzalı adres), kota sayaçları → FF-71. Purge komutu
  zamanlayıcıya bağlı değil (işletim kararı, `docs/42`).
- **Arka uç:** `GET media/{id}/usages`, `POST media/{id}/detach`,
  `POST media/{id}/restore`, `GET media?trashed=1`; liste `previewUrl`,
  `usageCount`, `versionCount`, `originalName`, `sizeBytes`, `createdAt`,
  `lifecycle` taşır. Testler: `MediaLibraryTrashAndUsagesTest` (7),
  `MediaLibraryRegion.library.test.tsx` (8); eski `MediaStorageFailureTest`
  silme senaryosu purge'a taşındı (dosya silinemezse satır kalır).

### FF-71 teslim notu

`docs/49` Faz 6-7: teslim ve yönetişim.

- **Önce:** rendition adresi değişmezdi ama tarayıcı her seferinde gövdeyi
  çekiyordu; asıl dosyaya hiçbir yoldan ulaşılamıyordu; fotoğraf inene kadar
  misafir boş kutu görüyordu; kota yoktu (bir kiracı diski doldurabilirdi);
  Member dahil herkes yükleyip silebiliyordu; diskle tablo arasındaki
  tutarsızlığı kimse ölçmüyordu.
- **Şimdi:** `ETag` → 304; imzalı 10 dakikalık asıl indirme (her rol,
  sahip kararı); LQIP arka planı; plan bazlı kota (`config/media-quota.php`,
  rakamlar §7) — dolunca yalnız yükleme durur, sebep okunur, ekranda sayaç;
  `media.manage` / `media.download_original` izinleri (15 izin); purge her
  çalışma alanının kendi plan süresiyle; `media:reconcile [--fix]`.
- **Kullanıcı yolculuğu:** Ayşe telefonda menüyü ikinci kez açar → fotoğraflar
  sıfır bayt (304); 100. görseli yüklemeye kalkar → "sınıra ulaşıldı, çöpü
  boşaltın ya da planı yükseltin", canlı menü yine açık; "aslı ver" → 10
  dakikalık bağlantı yeni sekmede.
- **Kalan engel:** CDN/önbellek anahtarı (netcup yerel disk kararı; CDN
  gelince), egress/dönüşüm ölçümü, medya audit log, video/PDF, koleksiyon/
  etiket, "yerine başka görsel seç", fallback zinciri — DAM Faz 8-10 ile
  birlikte ayrı bir program.
- **Testler:** `MediaDeliveryAndGovernanceTest` (10), `MediaQuotaRegion.test`
  (3), drawer indirme testi; `RolePermissionMappingTest` 13 → 15.

### FF-72 teslim notu

`docs/100` yazıldı (bilgi mimarisi, masterpage sözleşmesi MP-01…06, Flowbite
eşlemesi, L0–L4 olgunluk cetveli, sayfa başına bugünkü seviye, 4 faz).
Uygulanan Faz 1:

- **Önce:** header/footer `layout.blade.php`'nin içindeydi; gezintide 4 çıpa,
  `/pricing` ve `/help` gerçek sayfa olduğu hâlde yoktu; metin Blade'e gömülü
  İngilizce; "16/16 modules registered" ziyaretçiye görünür paragraf.
- **Şimdi:** `public/partials/header|footer`; gezinti Features/How it works
  (çıpa), Pricing/Help/Contact (gerçek sayfa), Log in/Create account; footer
  Product + Legal + marka satırı; 15 katalog anahtarı (`site.nav.*`,
  `site.footer.*`, `site.skipToContent`), Türkçe tarayıcı Türkçe okur;
  mühendislik satırı `<meta name="zabuno-build">` (kayıt sözleşmesi bozulmadı,
  ziyaretçi görmüyor); `PublicMasterpageContractTest` (6 sözleşme, 7 sayfa).
- **Kullanıcı yolculuğu:** kebapçı telefonda `zabuno.com` → üstte "Fiyat" ve
  "Yardım" Türkçe → kaydolmadan fiyat → Türkçe "ilk 15 dakika" → altta
  İletişim → "gönderildi". Hiçbir bağlantı ölü değil.
- **Kalan engel:** ana sayfa gövdesi hâlâ İngilizce gömülü (borç 29, Faz 2),
  görünür dil seçici ve `hreflang` (Faz 2), dönüşüm olayları (Faz 3),
  kişiselleştirme (Faz 4, Pennant → FF-74).

### FF-73 teslim notu

`docs/101` yazıldı: persona (Mehmet Usta), 5 çekirdek yolculuk (menü kur →
ürün ekle → fiyat değiştir → yayınla → QR bas), 8 acemi kuralı (A1 tek
"şimdi", A3 hata yerine geri alma, A5 nadir iş kapalı…), yolculuk başına
bugünkü ölçüm, 4 faz. Uygulanan Faz 1:

- **Önce:** Home'daki görev listesi beş satırla durumu gösteriyordu; "şimdi
  ne yapmalıyım" sorusunun cevabı listeyi okuyup çıkarsamaktı. Menü ekranı
  boş menüde bile AI içe aktarma, CSV indir/yükle ve kategori formunu aynı
  ağırlıkta gösteriyordu.
- **Şimdi:** Home'da tek büyük "Şimdi" düğmesi — bitmemiş ilk adımın fiiliyle
  ("Add your first product") ve tek tıkla oraya; hepsi bitince "Everything is
  set up" + karekod ekranı. Menüde fotoğraftan/CSV içe aktarma tek `<details>`
  kutusunda: boş menüde açık ve tek cümlelik yol tarifi, ürün varken kapalı.
- **Kullanıcı yolculuğu:** Mehmet Usta girer → "Restoranının adını yaz" →
  "Şubeni ekle" → "İlk ürününü ekle" → boş menüde açık kutu: fotoğrafı yükler,
  40 ürün gelir → "Menünü yayınla" → "Karekodlarını bas" → "Her şey hazır".
- **Kalan engel:** fiyat sonrası "yayınla" hatırlatması ve yayın ekranı
  metinleri (Faz 2), QR iki tık (Faz 3), gerçek acemi ölçümü (Faz 4 — sahibin
  çevresinden 3 kişi).

### FF-74 teslim notu

- **Önce:** 15 `Permission` sunucuda vardı, ön uç hiçbirini okumuyordu;
  gezinti kaydında yetki alanı yoktu; Pennant kurulu değildi. Editor "Team"i
  görüp tıklıyor, 403 alıyordu.
- **Şimdi:** `GET/PUT /workspace-context` gövdesi `role`, `permissions[]`,
  `features{}` taşır (`BuildWorkspaceContextPayload`;
  `AuthorizationPort::permissionsFor`). Bölüm kaydında `permission` alanı
  (billing.view, analytics.view, workspace.manage ×2, menu.view ×2, qr.view);
  kenar çubuğu, omnibox "git" grubu ve global oluştur menüsü izinsiz maddeyi
  çizmez; liste yoksa (eski gövde) süzme yapılmaz. Pennant kuruldu
  (`features` tablosu, `config/pennant.php`); tek gerçek bayrak `novice-home`
  (FF-73 Home "şimdi" kutusu) kiracı kapsamında, kapatılan kiracıda kutu
  çizilmez.
- **Kullanıcı yolculuğu:** Editor Ayşe kabuğu açar → Team/Billing yok,
  "Create → Location" yok; menüyü düzenler, yayınlayamaz; hiçbir 403 görmez.
- **Kalan engel:** entitlement (plan yetkisi) alanı kayıtta yok — plan
  kataloğu `entitlements` JSON'u ile bağ FF-71 kota deseniyle sonra;
  `docs/50` Faz 2 navigation registry'nin sunucu tarafı kopyası yok
  (bilerek: iki liste ayrışır).

### FF-75 teslim notu

Sahibin sorusu ("multi agents, skills, ADR, kalıcı/geçici hafıza, hesaplar
arası parçalama, collector") bir **iş boru hattı** olarak kuruldu —
`docs/adr/ADR-L11` neden ajan sürüsü değil, iş boru hattı olduğunu yazar.

- **Önce:** toplu okuma en çok 10 fotoğraf, tek istekte, eşzamanlı; 40 sayfa
  sığmıyordu; tek kiracı sağlayıcının dakikalık limitini tüketebiliyordu;
  toplu trafik etkileşimli hesapla aynı bağlantıya gidiyordu (`docs/97` R30
  "Faz 5'e bırakıldı"); ADR klasörü ve ajan sözleşme dosyaları yoktu.
- **Şimdi:** `POST menu/{menu}/ai-batches` (40 sayfa) → `ai_batches` +
  `ai_batch_pages` (kalıcı hafıza) → sayfa başına kuyruk işi
  (`ExtractMenuBatchPageJob`, geçici hafıza; `RateLimiter('ai-batch')`
  kiracı başına dakikada N, `config/ai.php`) → `purpose=batch` amaç boyutu
  (`ai_connection_assignments.purpose`; `plain_fields.purpose=batch` etiketli
  bağlantı öne) → `MenuBatchCollector` (deterministik: yinelenen
  `kategori|ürün` sayılır ve atlanır, düşen sayfa sebebiyle listelenir) →
  `GET ai-batches/{batch}` ile ilerleme → aynı insan-onaylı `apply`. Ekran
  11+ fotoğrafta partiyi izler: "Reading page 3 of 11…" → "2 rows from 10
  pages. 9 duplicate rows were skipped." `docs/adr/`, `templates/AGENT-SPEC.md`,
  `agents/{collector,core-eca-rules,opt-13-pos-integrations,integration-hub}.md`.
- **Kullanıcı yolculuğu:** Mehmet Usta 12 sayfayı yükler → "Oku" → ilerleme →
  tek liste, Ayran bir kez → "Ekle".
- **Kalan engel:** `purpose=batch` etiketini kasa formundan vermek (bugün
  bağlantının `plain_fields` alanı; superadmin ekranında ayrı bir seçici
  yok); kuyruk cron'la yürür (`routes/console.php`: dakikada bir
  `queue:work --stop-when-empty`, HOST-QUEUE-04); canlı konteynerde
  supervisord `schedule:work` süreci eklendi (`docker/supervisord.conf`) —
  2026-09-04'e kadar canlıda zamanlayıcı da kuyruk işçisi de yoktu; üç planlı ajan
  (`agents/*.md`) kodda yok — sözleşme var, kod yok, ve dosyalar bunu
  söyler.

### FF-76 ek paket — Media sayfası: "burada değişen bir şey yok" (2026-09-04)

Sahip canlıdaki Media ekranını gönderdi: kota kutusu ve Library/Trash sekmesi
gelmişti ama boş kütüphanede sayfa hâlâ tek sütun eski form + altında slot
envanteri gürültüsüydü. Düzeltme: iki sütun düzen (ekle | fotoğraflar),
çoklu dosya yükleme (dosya adından ad, satırda düzeltme, sırayla), çekmecede
ad düzeltme (`PATCH media/{id}`), slot/yaşam döngüsü listeleri katlanır,
boş durum yol tarifi. Testler: `MediaUploadRegion.multi.test` (1),
`MediaLibraryTrashAndUsagesTest::alt_text_can_be_corrected_later…`.

### FF-77 ek paket — Restoran paneli estetik olgunluğu (2026-09-04)

Sahip Home ekranını gönderdi: "maturity level bir UX estetiği istedim,
yapmadın." Kök neden: 13 paket yapı/davranıştı, estetik yalnız superadmin
kabuğuna (`docs/99`) uygulandı, sayfaya tarayıcıda hiç bakılmadı. `docs/102`
yazıldı (aynı Metronic dili iki kabukta; yüzey başına L0–L4; kabul). Faz 1:
main zemini `surface-subtle`, aside `surface`, sekiz bölüme Phosphor ikonu
(kayıt `icon`), Home tek `h1` + "Şimdi" marka şeritli kart + Setup kartı +
`StatCard`'lar + tablo kartı (`OpsCard`, thead tonlu). Media/Menus/QR/Insights
gövdeleri Faz 2 (FF-78).

### FF-78 ek paket — Faz 2 kart grameri + koyu tema zemini (2026-09-04)

`PanelCard` (= `OpsCard`) ve `WorkspacePageFrame.cardChildren`; Menus, Media,
QR, Insights, Locations, Team, Settings, Publication gövdeleri kartta —
`docs/102` yüzey tablosu L1 → L2. Ayrıca kök neden: koyu temada `surface`
(0.20) kart, `surface-subtle` (0.24) zemin — kart zeminden KOYU, derinlik
ters; sahip her ekranı koyu temada görüyordu ve kart grameri görünmüyordu.
Yeni `--canvas` token'ı (açık 0.975 / koyu 0.15) uygulama zeminidir; kart
her iki temada zeminden açıktır.

### FF-79 ek paket — görsel dil pası ve `--color-canvas` hatası (2026-09-04)

Storybook statik derlenip tarayıcıda AÇILDI (bu turda ilk kez): koyu temada
zemin açık kalıyordu, çünkü `--color-canvas` takma adı `.dark` bloğunda
yeniden tanımlanmamıştı (Tailwind v4 `@theme` takma adı kök değere donar).
Düzeltildi. Ardından görsel dil pası: kart yarıçapı/dolgusu, kart başlığı
ölçeği, sayı kartı tipografisi, gezinti ritmi, üst çubuk dolgusu, tablo
başlığı hizası ve satır hover'ı (`docs/102` §5b).

### FF-80 ek paket — Faz 3 ritim ve durumlar (2026-09-04)

`PageState` ortalandı ve ferahladı (boş/hata/kısıt yüzeyleri); sayfa başlığı
ile gövde arası ve kabuk ana alanı dolgusu `space-fluid-lg`. `docs/102` §5c.

### FF-81 ek paket — Y3 yayın hatırlatması + menü kataloğu yoğunluğu (2026-09-04)

`docs/101` Y3'ün unutulan adımı ekranda: fiyat kaydedilince marka şeritli bir
satır "Misafirler hâlâ son yayınlanan menüyü görüyor" der ve yayın ekranına
götürür. Kategori kutuları panel kart grameriyle aynı yüzeye taşındı
(`docs/102` §5d).

### FF-83 ek paket — kabuk tutarlılığı ve telefon alt gezintisi (2026-09-04)

Sahibin ekran görüntülerinden üç kusur kapandı: (1) kenar çubuğu bağlam
paneli açılan sayfada daralıyordu — raylar sabit genişliğe alındı; (2)
başlıkta ürün adı + çalışma alanı adı + şube seçici üst üste "Zabuno" diye
okunuyordu — çalışma alanı adı kenar çubuğundaki tek yerine bırakıldı, şube
seçici yalnız birden çok şubede çizilir; (3) telefonda gezinti üst köşedeki
hamburgerdeydi — alt sticky çubuk geldi (dört hedef + More), hamburger üst
çubuktan kalktı. Dört donmuş test yeni yerleşimle güncellendi (`docs/102` §5e).
### FF-82 ek paket — yayın ekranı sesli dile geçti (2026-09-04)

`PublishActionConfigRegion` dört teknik cümle ve KALICI DEVRE DIŞI bir
"yayın kipi" seçimi taşıyordu. Devre dışı seçim `docs/44` yasağıydı;
"zamanlanmış yayın henüz yok" cümlesi yapılmamış özelliği ekrana taşıyordu
(`docs/64` §4); "izniniz gerekir" cümlesi koşulsuzdu (yetki FF-74'te zaten
süzülüyor). Bölge iki gerçek cümleye indi ve adı "What publishing does"
oldu. İki donmuş test daha güçlü sözleşmeyle güncellendi: böyle bir kontrol
HİÇ olmayacak.

### FF-84 ek paket — sistem menüsü ve Ayarlar'ın yeri (2026-09-04)

Sahibin kararı: Ayarlar kenar çubuğundan sistem (hesap) menüsüne taşındı;
`docs/50` §8 kuralı bu yönde güncellendi (kayıt notu belgenin başında).
Menü baştan tasarlandı: kimlik başlığı (baş harf dairesi + e-posta), ikonlu
satırlar, dokunma yüksekliği, içeriden yuvarlanan vurgu, tema seçiminde
hizasız nokta yerine onay imi. Yedi donmuş test yeni yerleşimle güncellendi;
yeni test `AccountMenu.test`.

### FF-85 ek paket — QR sihirbazı tek soruya indi (2026-09-04)

`docs/101` Y5: karekod bastırmak için altı zorunlu alan vardı (bölge sayısı,
masa sayısı, koltuk, ad öneki, sıra başlangıcı, aralık). Beşinin makul bir
varsayılanı var; varsayılanı olan alan kullanıcıya sorulmaz (`docs/47` Kural
4). Görünen tek soru "kaç masa", gerisi "Advanced options" altında ve DOM'da
kalır (klavye/ekran okuyucu). Dört donmuş test yeni sıra ve varsayılanlarla
güncellendi.

### FF-86 ek paket — platform kabuğunda sabit ray + kural testi (2026-09-04)

FF-83 yalnız kiracı kabuğunu düzeltmişti; `OpsShell` atlanmış ve orada ray
ekranın yarısını kaplıyordu. Düzeltildi ve kural `OpsShell.layout.test` ile
dondu: kabuk dosyalarında büyüyen ray yazımı bulunamaz.

### FF-87 ek paket — persona rengi ve mavi kaçağı (2026-09-04)

Sahibin kararı: superadmin lacivert zemin, restoran kromasız. Kaçağın kaynağı
bulundu (açılır menü/çekmece/diyalog Flowbite'ın mavi tonlu gri paletiyle
çiziliyordu) ve üç aile token temasına bağlandı; persona `[data-persona]`
altında yalnız yüzey jetonlarını değiştirir. `persona.guard.test` kapsamı ve
kalıcılığı dondurur (`docs/102` §5h).

## 6. Esnetilen kurallar — açık kayıt

Sahip "kararları boz, kuralları yeniden yaz" dedi. Bozulan tek şey **paket
boyutu disiplini değil, paket sayısı**: 12 paket art arda tek oturumda,
her biri kendi PR'ında. Değişmeyenler: tek writer, RED→GREEN, sır girişi
yasağı, Pint kapısı, "kanıtı olmayan ✅ olmaz" (`docs/61` kuralı). Bunlar
esnetilirse program hızlanmaz, yalnız yalan söylemeye başlar.

**FF-72'de esnetilen ikinci kural:** "N/16 modules registered" satırı
kayıt sözleşmesi gereği (`FoundationStatusDeliveryArchitectureTest`,
`PublicLegalPagesTest`) her kamu sayfasında **kaynakta** bulunmalıydı; kural
yeniden yazıldı: satır `<meta name="zabuno-build">` olarak kaynakta kalır,
ziyaretçiye görünmez (`docs/100` MP-04). Sözleşme "kayıttan türetilir"
demeye devam eder; "ziyaretçi okur" hiç dememişti.

**FF-72'de esnetilen üçüncü kural — JS bütçesinin BİRİMİ:** `DS-BUNDLE-BUDGET-07`
`public/build/assets` altındaki bütün JS'in toplamını 200 KB'ye vuruyordu:
auth + platform + mühendislik + çalışma alanı (masaüstü + mobil) birlikte.
Hiçbir ziyaretçi o toplamı indirmez; misafir menüsü hiç JS yüklemez (`docs/38`
§16). FF-70/71'in medya kütüphanesi toplamı 200,8 KB'ye taşıyınca ölçüm
dürüstleştirildi: her giriş noktasının manifest kapanışı ölçülür, en büyüğü
(workspace.desktop ≈ 175 KB) bütçeye vurulur. **Sayı yükseltilmedi** — sahibin
kararı gerektiren şey sayıdır (`bundle-budget.json` notu); birim, ölçümün
neyi temsil ettiğidir ve yanlış temsil düzeltildi.

## 7. Kota rakamları — sahip "sen belirle" dedi

| Plan | Original | Rendition | Asset | Aylık upload | Trash retention |
| --- | --- | --- | --- | --- | --- |
| Free/Deneme | 200 MB | kota dışı | 100 | 100 | 7 gün |
| Standart | 2 GB | kota dışı | 1.000 | 1.000 | 30 gün |
| Pro | 10 GB | kota dışı | 10.000 | sınırsız | 90 gün |

Gerekçe: rendition'lar sistemin ürettiği türevlerdir, kullanıcıya
faturalanmaz (ürettiğimiz şeyi ödetmeyiz); trash kotaya DAHİL (silmek boş
alan açmalı ki kullanıcı "sildim, hâlâ dolu" demesin). Rakamlar
`config/media-quota.php`'de yaşar, koda gömülmez; plan tablosu sonra
bağlanır (FF-71).

## 8. Kanonik sahiplik

Rota/tablo envanteri burada; DAM planı `docs/49`; shell `docs/50`; envanter
`docs/61`; AI `docs/95-97`. Yeni planlar: `docs/99` (superadmin estetiği),
`docs/100` (frontpages), `docs/101` (acemi-UX).

## 9. FF-88 — Profil ekranı (kişisel bilgi, fotoğraf, tema, marka rengi)

Sahibin isteği (2026-09-04): "bu menüde 'profile' adlı menü item olsun, kişi
profil bilgilerini buradan düzenleyebilsin. Ve tokens (renk, theme) buradan
değiştirebilsin. Restoran yöneticisi olarak marka renklerimi (primary color,
secondary color) değiştirebilmeliyim, profil ve kişisel bilgilerimi
güncelleyebilmeliyim, avatar profil fotoğrafımı (media components ile)
yükleyebilmeliyim."

**Boşluk neydi:** hesap menüsünde kişinin kendisine ait TEK bir hedef yoktu.
Ad değiştirme, Ayarlar'ın "Hesap" sekmesinde saklıydı ve o sekmenin adresi
bile çalışmıyordu (`settings/account` sessizce Marka sekmesine düşüyordu).
Profil fotoğrafı ve marka rengi ise hiç yoktu — ne tabloda, ne uçta, ne
ekranda.

**Ne yapıldı:**

| Katman | Değişiklik |
| --- | --- |
| Şema | `brands.primary_color`, `brands.secondary_color` (`#rrggbb`, boş olabilir), `users.avatar_media_asset_id` |
| Uç | `PUT /api/user/avatar` (bağla/kaldır), `/api/user` gövdesine `avatarMediaAssetId` + `avatarUrl` |
| Marka ucu | `PUT .../brand` artık iki rengi de kabul eder; biçim `#rrggbb`, kısa biçim ve renk adı reddedilir |
| Ekran | `/app/{ws}/profile` — fotoğraf, kişisel bilgi, görünüm (tema), marka renkleri |
| Menü | Hesap menüsünde en üstte **Profil**; Ayarlar onun altında |
| Onarım | `settings/account` adresi artık gerçekten Hesap sekmesini açar ve mevcut adı ön-doldurur |

**Kararlar ve gerekçeleri:**

1. **Profil, Ayarlar'ın sekmesi DEĞİL, kendi ekranı.** Ayarlar çalışma alanına
   aittir ve çalışma alanı değişince içeriği değişir; profil kişiye aittir ve
   kişi hangi restorana geçerse geçsin aynı kalır. Tek ekranda toplamak "adımı
   değiştirdim, diğer restoranda da değişti mi?" sorusunu her seferinde
   doğururdu.
2. **Marka rengi profil ekranında bir istisnadır.** Renk çalışma alanına
   aittir, kişiye değil. Sahibin istediği yer burası olduğu için buradadır ve
   bölüm `workspace.manage` izni olmayana HİÇ çizilmez — dokunamayacağı bir
   kontrolü görüp deneyen kullanıcı, 403 ile karşılaşır ve ürüne güvenini
   kaybeder.
3. **Fotoğraf ayrı bir dosya yolu değil, MEDYA VARLIĞIDIR.** Karantina,
   tarama, türev üretimi ve kota zaten `docs/49` boru hattındadır; ikinci bir
   yükleme yolu açmak, taranmamış bir dosyanın ürüne girebileceği ikinci bir
   kapı olurdu. Yükleme alanı Media sayfasının bileşenidir (`MediaDropzone`),
   yalnız yuvası `profileAvatar` olarak sabitlenir.
4. **Yabancı çalışma alanının görseli profil fotoğrafı yapılamaz** ve bu
   reddediliş 404'tür, 403 değil: 403 "o kayıt var" bilgisini sızdırırdı
   (`ACCOUNT-AVATAR-ESCAPE-01`).
5. **Renk seçici iki girdilidir:** renk kutusu ve altı haneli kod alanı, aynı
   değeri paylaşır. Kurumsal kimliği `#C8102E` olarak yazılı bir restoran
   sahibi o kodu YAZMAK ister; rengi hiç bilmeyen ise kutuyu kullanır.
6. **Tema kişisel, renk kurumsaldır.** Gündüz müdürü açık temayı, gece
   kapanışı yapan koyu temayı seçer; ikisi de aynı menüyü aynı renklerde
   yayınlar. Bu yüzden iki ayrı bölümdür.

**Kanıt:** `tests/Feature/Account/ProfileAvatarTest.php` (bağla/kaldır,
yabancı varlık 404, kimliksiz çağrı 401), `ProfilePage.test.tsx` (bölümler,
izin kapısı, zorunlu alanlarla kayıt), `AccountMenu.test.tsx` (madde sırası).

## 10. FF-89 — Marka rengi misafirin gördüğü menüye ulaştı

FF-88 renkleri düzenlenebilir yaptı ama hiçbir yer onları ÇİZMİYORDU. Profil
ekranındaki cümle ("bu iki renk yayınlanan menünüzde kullanılır") tutulmayan
bir sözdü; düzenlenip hiçbir yerde görünmeyen bir alan, kullanıcıya ürünün
onu dinlemediğini öğretir.

**Ne yapıldı:** renkler yayın kimliğine (`MenuIdentity`) eklendi ve yayınla
birlikte DONDU. Misafir sayfası onları iki yerde kullanır: sayfanın üstündeki
4 piksellik marka şeridi (birincil) ve kategori başlıklarının altındaki çizgi
(ikincil).

**Kararlar:**

1. **Renk yayınla donar, canlı markadan okunmaz.** Renk yarın değişirse dünkü
   yayın değişmez — yayın, sahibin "bunu onayladım" dediği hâldir (`docs/75`).
   Aynı gerekçe marka adı ve telefonu için zaten geçerliydi.
2. **Renk yalnız DEKORASYONDUR; metin ya da metin arkası değildir.** Restoran
   açık sarı bir kurumsal renk seçebilir; onu yazı rengi yapmak menüyü beyaz
   üstünde okunmaz hâle getirirdi ve kontrastı biz garanti edemeyiz. Şerit ve
   çizgi, kontrast kaybı olmadan markayı taşır.
3. **Renk seçmemiş restoran seçmiş gibi gösterilmez.** Değişken hiç yazılmaz,
   şerit yüksekliği sıfır kalır, kategori çizgisi nötr sınıra düşer.
4. **Eski yayınlarda renk alanı yoktur ve olmaması normaldir.** Onları geriye
   dönük boyamak, donmuşluk sözünü bozardı.

**Kanıt:** `tests/Feature/Publication/PublicationBrandColorsTest.php` —
snapshot taşıma, renk değişince eski yayının değişmemesi, misafir sayfasının
rengi çizmesi, renksiz markanın nötr kalması.

## 11. FF-91…FF-93 — ürünün dili

Üç paket aynı boşluğu kapatıyor: **ürün altı dil taşıyordu ama hiçbir yerde
bir dil SEÇMİYORDU.**

| Paket | Ne yaptı |
| --- | --- |
| FF-91 | Ana sayfanın 29 dizesi `site.home.*` altına taşındı; çevrilemez borç 48 → 19 |
| FF-92 | 72 `site.*` anahtarının tamamı Türkçeye çevrildi; tanıtım sitesi baştan sona Türkçe |
| FF-93 | İstek dili `Accept-Language`'dan seçilir; kimlik ve kabuk metinleri Türkçe; sekme başlıkları katalogdan; borç 19 → 10 |

**Kök neden.** `app()->getLocale()` her istekte yapılandırmadaki `en` kalıyordu.
`<html lang>` ondan türüyor, istemci çevirici de locale'i o etiketten okuyor.
Yani Türkçe çeviriler yazılsa bile hiçbir Türk kullanıcı onları göremezdi:
çeviri vardı, kapı yoktu. Kamu sayfaları bunu tek tek kendi içinde çözüyordu;
kabuklar hiç çözmüyordu — aynı üründe iki farklı gerçek vardı.

**Kararlar:**

1. **Seçim sunucuda yapılır**, JavaScript'te değil: dil ilk boyanan pikselden
   önce belli olmalıdır. Sonradan değişirse kullanıcı önce yanlış dilde bir
   sayfa görür, sonra sayfanın altından dili değişir.
2. **Başlık yoksa hiçbir şey seçilmez.** Sinyal yokken karar vermek, dili
   bilerek ayarlamış olan tarafı (bir konsol komutu, ileride bir kullanıcı
   tercihi) sessizce ezmek olurdu.
3. **Bölgeli etiket taban dile iner** (`tr-TR` → `tr`): katalog taban
   dillerle anahtarlanır ve desteklenen bir dili desteklenmiyormuş gibi
   göstermek olurdu.
4. **`SiteText` varsayılanı uygulamanın dilidir**, sabit `'en'` değil. İkinci
   bir varsayılan tutmak, istekte yapılan seçimi görmezden gelmek olurdu.
5. **Kullanıcı tercihi (hesapta saklanan dil) BU TURDA YAPILMADI.** Tarayıcı
   dili çoğu kullanıcı için doğru cevaptır; hesapta saklanan tercih ayrı bir
   pakettir ve `NegotiateLocale` onun için hazır: tercih daha erken
   ayarlanırsa başlık onu ezmez.

**Kanıt:** `tests/Feature/Localization/RequestLocaleNegotiationTest.php`
(Türkçe tarayıcı, bölgeli etiket, desteklenmeyen dil, başlıksız istek, Arapça
RTL, çalışma alanı kabuğu).

## 12. FF-94…FF-95 — bütün yüzeyler Türkçe

| Paket | Ne yaptı |
| --- | --- |
| FF-94 | `workspace` (683) ve `menu` (86) Türkçeye çevrildi; çeviri tabloları isteğe bağlı indirilir |
| FF-95 | `platform` ve `engineering` (206) Türkçeye çevrildi |

Bugün altı katalogda da Türkçe boş `msgstr` YOKTUR: `site`, `auth`,
`dashboard`, `workspace`, `menu`, `platform`, `guest`.

**Paket bütçesi neden aynı kaldı.** Türkçe tamamlanınca çalışma alanı paketi
207 KB'ye çıkıp 200 KB bütçesini aştı — ve o ağırlığın çoğu hiçbir
kullanıcının okumadığı dillerdi. Sayıyı yükseltmek yerine ölçülen şey
düzeltildi: projeksiyonlar ana pakete gömülmüyor, yalnız AÇIK OLAN dilin
tablosu ayrı bir parça olarak iniyor. İngilizce okuyan kullanıcı hiçbir
çeviri indirmez. Uygulama tablo inmeden çizilmez; önce İngilizce sonra Türkçe
bir ekran göstermek, dili hiç bilmemekten kötü görünürdü. Yükleme başarısız
olursa uygulama yine çizilir ve İngilizce metne düşer — çizilmeyen bir
uygulamanın düşeceği bir yer yoktur.
