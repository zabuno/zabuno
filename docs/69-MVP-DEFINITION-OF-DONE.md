# 69 — MVP Definition of Done: madde madde kanıt

**Kaynak:** AI-first raporu §16 (Definition of Done) ve §6.1 (altın yolculuk
bitiş ölçütü)

Bu belge iddia listesi değil, **kanıt listesidir**. Her satırın yanında onu
donduran testin ya da belgenin adı var. Kanıtı olmayan madde ✅ işaretlenmedi.

## Altın yolculuk

Planın bitiş ölçütü şu:

> Restoran yöneticisi gerçek bir telefonla QR kodu taradığında yayımlanmış
> menüyü açabilmeli ve **bu tarama Analytics ekranına yansımalıdır.**

| Adım | Kanıt |
| --- | --- |
| Kayıt → doğrulama | `RestaurantCriticalJourneyTest` |
| Çalışma alanı → marka → şube → menü → ürün | aynı test, ürünün HTTP yüzeyinden |
| Yayınla (version 1) | `CRIT-JOURNEY-01` |
| QR üret | `CRIT-JOURNEY-01` |
| `/q/{token}` → yönlendirme → halka açık menü | `CRIT-JOURNEY-01` |
| Gerçek ürün adı ve fiyat misafire ulaşır | `CRIT-JOURNEY-01` |
| **Tarama yöneticinin Analytics ekranına yansır** | **`CRIT-JOURNEY-ANALYTICS-01`** |

Son satır bu pakette eklendi. Öncesinde kayıt yolu vardı ama **uçtan uca bağlı
olduğunu hiçbir test görmüyordu** — FF-03a'da aynı boşluk sağ paneli "hiçbir
ekranda yok" sandırmıştı. Test, kaydediciyi devre dışı bırakarak kırıldığı
doğrulanarak yazıldı.

## Definition of Done — 19 madde

| # | Madde | Durum | Kanıt |
| --- | --- | --- | --- |
| 1 | Analytics gerçek QR taramasını ve menü açılışını gösteriyor | ✅ | `CRIT-JOURNEY-ANALYTICS-01` |
| 2 | Ana menüde çalışmayan hiçbir destination yok | ✅ | 8 listeli bölümün hepsi gerçek sayfa çiziyor; `WorkspaceApp.shell.test` |
| 3 | Tenant / platform / engineering yüzeyleri ayrılmış | 🔶 | `/platform` ayrı kabuk; engineering yüzeyi kiracıdan ÇIKARILDI ama kendi kabuğu yok |
| 4 | Her ekranın tek ve açık bir kullanıcı amacı var | ✅ | `docs/55` bilgi mimarisi |
| 5 | Her empty state bir sonraki eyleme yönlendiriyor | ✅ | `PageState` tip düzeyinde eylem-ya-da-gerekçe zorunlu (`docs/59`); sayfa düzeyi boşluklar dönüştürüldü |
| 6 | Her blocked state nedenini ve çözümünü gösteriyor | ✅ | `docs/66` analitik ön koşulları |
| 7 | Core journey AI tamamen kapalıyken çalışıyor | ✅ | AI yüzeyi yok; `docs/65` |
| 8–10 | AI önerileri, onay, audit | ⛔ | Bağlı sağlayıcı yok; `docs/65` §3 |
| 11 | Browser refresh, direct URL, back/forward | ✅ | `WorkspaceApp.shell.test` bölüm içi adres testi (`docs/64`) |
| 12 | Sidebar modül listesi değil görev odaklı | ✅ | `docs/55` |
| 13 | Brand ve Publication doğru kapsama taşınmış | ✅ | ikisi de gezintide değil; Brand → Settings, Publication → menü akışı |
| 14 | Manual payment, ledger, release evidence kiracı arayüzünden çıkarılmış | ✅ | `docs/61` E7/E8 |
| 15 | Theme selector içerik üzerine binmiyor | ✅ | `ThemeFocusClearance.test` (`docs/63`) |
| 16 | Desktop formları gereksiz tam genişlik kullanmıyor | ✅ | `--container-page-*` |
| 17 | Bütün kullanıcı metinleri i18n'den | ✅ | mühürlü katalog, 515 anahtar |
| 18 | Loading / empty / error / permission / success test edilmiş | ✅ | `PageState` testleri + `docs/66` + `docs/67` |
| 19 | Core journey gerçek senaryoyla uçtan uca doğrulanmış | ✅ | `RestaurantCriticalJourneyTest` (5 test) |

### 3 numaralı madde neden 🔶

Engineering içeriği (release readiness, güvenlik kanıtı, yedek tatbikatı)
**kiracı arayüzünden çıkarıldı** — plan bunu istiyordu ve yapıldı. Ama bu
içerik için ayrı bir `EngineeringShell` KURULMADI: bugün o sayfalar ürün
içinde bir yerde yaşamıyor.

Bu bilinçli: olmayan bir izleyici için kabuk kurmak, kullanılmayan bir yüzeyin
bakımını üstlenmektir. Madde ✅ sayılmadı çünkü planın istediği üç kabuktan
ikisi var.

## MVP analitik metrikleri

`docs/68`'de ayrıntılı. Dokuz metriğin dokuzu:

| Metrik | Durum |
| --- | --- |
| Toplam QR çözümleme | ✅ |
| Yaklaşık benzersiz tarama | ✅ |
| Bugün / 7 gün / 30 gün | ✅ |
| Onaylanmış menü açılışı | ✅ |
| Çözümleme → açılış oranı | ✅ |
| Lokasyona göre kırılım | ✅ |
| Karekoda göre kırılım | ✅ |
| Cihaz / OS / tarayıcı | ⛔ post-MVP (plan) |
| Ülke / şehir / referrer / saatlik | ⛔ post-MVP (plan) |

## MVP'nin dışında kalanlar

Hiçbiri unutulmuş değil; her birinin ya bir arka uç ön koşulu ya da yazılı bir
gerekçesi var (`docs/61`):

- Medya/DAM ekranları — veri modeli var, arayüz yok (F bölümü).
- Sunucu tarafı idempotency — arka uç değişikliği ister (`docs/67` §6).
- Ürün analitiği olay taksonomisi — ölçüm soruları önce tanımlanmalı.
- AI yetenekleri — sağlayıcı yok.
- Şablon kataloğu — tekrar henüz ölçülmedi.
- Help merkezi — arkasında içerik yok.
