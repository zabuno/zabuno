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

**Sayaç: 0/12 tamamlandı, 1/12 aktif.** Her paket tek writer, RED→GREEN,
Pint+tam QA, kendi PR'ı. Sıra bağımlılığa göre; kurallar arasından
esnetilen tek şey **paket kapsamı** (bkz. §6).

| # | Paket | Kapsam | Kapı |
| --- | --- | --- | --- |
| 1 | **FF-63 Readiness kanıtı** | host-capability HTTP ucu + ekran; `release_evidence` genel kayıt tablosu (QR fiziksel tarama, RPO/RTO kararı, ASVS raporu) + `platform:evidence:record` komutu + ekran; sahibin gerçek taramasını kaydet | 6 maddenin 6'sı gerçek kayıttan okunur; kayıtsız madde dürüstçe "Unavailable" |
| 2 | **FF-64 Rota boşlukları** | menü-geneli stok, brand/logo ve QR destination doğrulaması; bilinçli-eski rotalar belgeli | Tur 1 listesi sıfır "doğrulanacak" |
| 3 | **FF-65 Envanter tazeleme** | `docs/61` G4/G5, E9/E10, A2 güncellemesi; FF-49..62'nin izi | envanter gerçeği söylüyor |
| 4 | **FF-66 Engineering kabuğu** | `/engineering/*` ayrı kabuk: readiness, güvenlik kanıtı, yedek tatbikatı, host-capability, AI denetim izi | `docs/69` madde 3 ✅ |
| 5 | **FF-67 Superadmin estetiği (Metronic-esinli)** | plan belgesi (`docs/99`) + uygulama: yoğunluk, kart/tablo dili, rozet sistemi, sol rail, üst çubuk; Zabuno token'larıyla, Metronic kopyası değil | platform ve engineering kabukları aynı dili konuşur |
| 6 | **FF-68 DAM Faz 2** | upload session + idempotency, magic-bytes/decoder doğrulama, karantina zinciri, SVG reddi, `fixtures/malicious` CI kapısı | `docs/49` Faz 2 kabulü |
| 7 | **FF-69 DAM Faz 3** | immutable original, non-destructive version, `320..1600w` rendition seti, checksum + yinelenen tespiti, reprocess | INV-01..07 yeşil, rollback |
| 8 | **FF-70 DAM Faz 4+5** | kütüphane ızgara/liste/arama/koleksiyon; asset detayı (kullanım/sürüm/rendition); kullanım grafiği; silme etki önizlemesi; yayın snapshot'ı version'a bağlı | kullanılan asset doğrudan silinemez |
| 9 | **FF-71 DAM Faz 6+7** | immutable URL + `Cache-Control`/`ETag`, `srcset`/`<picture>`, kota kalemleri (sahip "sen belirle" dedi → §7), izin matrisi (`download_original` serbest — sahibin kararı), reconciliation | LCP ölçülür; kota dolunca canlı menü kesilmez |
| 10 | **FF-72 Frontpages planı + masterpage** | `docs/100`: kamu sayfaları bilgi mimarisi, header/footer masterpage sözleşmesi, Flowbite bileşen eşlemesi, SEO/URL (`docs/38`) bağı, **maturity seviyeleri** (L0 statik → L4 kişiselleştirilmiş); uygulama: `public.layout` header/footer yeniden | 5 sayfa tek masterpage'den |
| 11 | **FF-73 Acemi-UX programı ("kebapçı")** | `docs/101`: persona, 5 çekirdek yolculuk (menü kur → ürün ekle → fiyat değiştir → yayınla → QR bas), her adımda tek karar/tek ekran, büyük hedefler, sesli-dil metin, hata yerine geri alma; uygulama: Home görev listesi + menü kataloğu sadeleştirme | 5 yolculuk 320px'te ölçülür |
| 12 | **FF-74 Yetki-görünürlük + registry** | gezinti kaydına `permission`/`entitlement`; ön uç `me` ucundan izin okur; yetkisiz eylem çizilmez; Pennant | Editor 403 görmez |

**Ertelenen ve nedeni:** DAM Faz 8-10 (crop stüdyosu, AI önerileri, video)
`docs/49`'un kendi fazlamasıyla sonra; video sahibin kararıyla Faz 2'ye
bağlı ama `tus` sunucusu kurulu değil — FF-68 `asset_kind=video`'yu tanır,
transcoding kurmaz.

## 6. Esnetilen kurallar — açık kayıt

Sahip "kararları boz, kuralları yeniden yaz" dedi. Bozulan tek şey **paket
boyutu disiplini değil, paket sayısı**: 12 paket art arda tek oturumda,
her biri kendi PR'ında. Değişmeyenler: tek writer, RED→GREEN, sır girişi
yasağı, Pint kapısı, "kanıtı olmayan ✅ olmaz" (`docs/61` kuralı). Bunlar
esnetilirse program hızlanmaz, yalnız yalan söylemeye başlar.

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
