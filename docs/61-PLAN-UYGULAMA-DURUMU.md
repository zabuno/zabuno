# 61 — Plan uygulama durumu

**Kaynak:** sahibin verdiği dört planlama belgesi — marka formu UX raporu, AI-first
SaaS UX/yolculuk/kabuk raporu, SaaS panel kabuk mimarisi, dosya/medya yöneticisi
kapsam raporu.

Bu belge **her turda güncellenir**. Amacı tek: hangi maddenin gerçekten yapıldığını,
hangisinin yapılmadığını ve hangisinin bilerek dışarıda bırakıldığını ayırmak.

## Durum sözlüğü

| İşaret | Anlamı |
| --- | --- |
| ✅ | Kodda var ve testle korunuyor |
| 🔶 | Kısmen var — hangi parçanın eksik olduğu satırda yazılı |
| ⬜ | Yok |
| ⛔ | Bilerek yapılmıyor — gerekçe satırda |

**Kural:** bir madde ancak testi varsa ✅ olur. "Kodu yazdım" yeterli değildir;
FF-03a'da yazılmış ve çalışan bir panel, testi olmadığı için ekranda yok sanılmıştı.

---

## A. Kabuk ve navigasyon

| # | Madde | Durum |
| --- | --- | --- |
| A1 | Hash navigation yerine gerçek adresler | ✅ `sectionHref(slug, section)`, `replaceState` |
| A2 | Tenant / platform / engineering kabuk ayrımı | 🔶 tenant ayrı; platform ve engineering yüzeyleri henüz ayrı kabuk değil |
| A3 | Üç kalıcı sol rail YOK | ✅ tek sidebar |
| A4 | Sidebar görev odaklı gruplandırma (primary/management/utility) | ✅ `group` alanı |
| A5 | Sidebar üstünde workspace switcher | ✅ `WorkspaceSwitcherTrigger` |
| A6 | Sidebar altında account trigger + popover | ⬜ |
| A7 | Sağ context inspector | ✅ menü, marka, şube (`docs/60`) |
| A8 | Inspector mobilde ayrı sheet/route | ⛔ mobil pakette panel HİÇ yok (`docs/54`); sheet gerekirse ayrı karar |
| A9 | Global header + page header iki katman | 🔶 page header var; global header bağlam/araç katmanı eksik |
| A10 | Header'da location context | ⬜ |
| A11 | Global Create düğmesi | ⬜ |
| A12 | Help merkezi | ⬜ |
| A13 | Çalışmayan search/notifications gösterilmiyor | ✅ kaldırıldı |
| A14 | Tenant kabuğunda kalıcı footer yok | ✅ |
| A15 | Sabit tema seçici kaldırıldı, account'a taşındı | 🔶 sabit seçici kaldırıldı; account tercihi A6 ile gelecek |
| A16 | Adaptive cihaz paketleri | ✅ `docs/54` + `adaptive-bundle-gate` |
| A17 | Skip link, landmark, focus, klavye | ✅ |

## B. Omnibox ve komut merkezi

| # | Madde | Durum |
| --- | --- | --- |
| B1 | Tek omnibox, açık modlar (Search / Go to / Create / Command / Ask) | ⬜ |
| B2 | Varsayılan mod DETERMİNİSTİK, AI açıkça seçilir | ⬜ |
| B3 | Komut merkezinde görünür kapsam (workspace/lokasyon/menü/seçim) | ⬜ |
| B4 | Riskli komutlar doğrudan çalışmaz, review yüzeyine gider | ⬜ |
| B5 | `Cmd/Ctrl+K` | ⬜ |

## C. Formlar ve alan sahipliği

| # | Madde | Durum |
| --- | --- | --- |
| C1 | Marka formundan `timezone` çıkar → şubeye | ✅ `locations.timezone` + geri doldurma (`docs/62`) |
| C2 | Marka formundan `currency` çıkar → fiyat listesine | ⬜ fiyat listesi nesnesi yok |
| C3 | Genel `locale` alanı parçalanır (ui / content / supported) | ⬜ |
| C4 | Serbest metin yerine allowlist seçim | ✅ marka VE şube formunda; ülke etiketi `Country` |
| C5 | Saat dilimi listeden, `Europe/Istanbul` saklar | ✅ combobox yerine ülkeye göre daraltılmış liste — gerekçe `docs/62` §4 |
| C6 | Para birimi ISO 4217 combobox | ⬜ |
| C7 | Alan anatomisi: label + description + control + error | ✅ `docs/56` |
| C8 | Hata özeti + ilk hatalı alana odak | 🔶 kısmî |
| C9 | `aria-invalid`, `aria-describedby`, canlı bölge | 🔶 kısmî |
| C10 | 422 / 409 / bağlantı / 5xx ayrı ele alınır | ⬜ tek genel hata |
| C11 | Idempotent submit, çift tıklama koruması | ⬜ |
| C12 | Sabit `form_id` / `field_id` / `error_code` | ⬜ |
| C13 | Sayfa genişliği standardı | ✅ `--container-page-*` |
| C14 | Kontrol kontrastı ≥ 3:1 | ✅ `--border-control` |

## D. Sayfa durumları ve şablonlar

| # | Madde | Durum |
| --- | --- | --- |
| D1 | Durum sözlüğü (loading/empty/error/permission/prerequisite/plan) | ✅ `docs/59` `PageState` |
| D2 | `partial`, `success`, `degraded` durumları | ⬜ |
| D3 | Şablon kataloğu (Overview/Collection/List-detail/Editor/Settings/Analytics/Task-flow/Preview/Review) | ⬜ |
| D4 | Her empty state sonraki eyleme yönlendirir | 🔶 çoğu ekranda var |
| D5 | Disabled kontrol nedenini açıklar | ✅ `whyNoAction` tip düzeyinde zorunlu |

## E. Ekranlar

| # | Madde | Durum |
| --- | --- | --- |
| E1 | Home: onboarding görev listesi + günlük operasyon | 🔶 |
| E2 | Menus: liste + detay sekmeleri (Overview/Content/Design/Languages/Publish/QR/Activity) | ⬜ tek düzey |
| E3 | Publication: checkbox yerine otomatik preflight | 🔶 `isDraftReady` otomatik; ayrıntılı liste eksik |
| E4 | Analytics: 5 ayrı boş durum | 🔶 loading/error/plan/notConnected var; "menü yayımlanmadı" ve "QR yok" ayrımı yok |
| E5 | Team: rol + lokasyon kapsamı seçen davet diyaloğu | ⬜ rol sabit `editor` |
| E6 | Team: üye tablosu (rol/kapsam/durum/son etkinlik) | 🔶 |
| E7 | Billing: yalnız tenant yüzeyi | ✅ ledger/manuel ödeme ayrıldı |
| E8 | Launch readiness tenant kabuğundan çıktı | ✅ |
| E9 | Media: grid/list, filtre, upload drawer | ⬜ |
| E10 | Media: teknik iç süreçler kullanıcıdan gizli | 🔶 |

## F. Medya / DAM

| # | Madde | Durum |
| --- | --- | --- |
| F1 | asset / blob / version / rendition / usage / job tabloları | ✅ göç mevcut |
| F2 | Üç durum ekseni (processing / lifecycle / visibility) | 🔶 |
| F3 | Slot bazlı medya politikası | 🔶 slot kavramı var |
| F4 | Upload session, resumable, idempotency | ⬜ |
| F5 | Sunucu tarafı doğrulama + karantina + AV | 🔶 |
| F6 | Responsive rendition seti + `srcset` | ⬜ |
| F7 | Immutable/versioned URL | ⬜ |
| F8 | Use mapping'e göre silme etki analizi | ⬜ |
| F9 | Tenant kotası kalemleri | ⬜ |
| F10 | Yayın snapshot'ı asset version'a bağlı | ⬜ |

## G. AI-first

| # | Madde | Durum |
| --- | --- | --- |
| G1 | Boş AI assistant kartları kaldırıldı | ✅ |
| G2 | Sağlayıcı yokken AI girişi gösterilmez | ✅ |
| G3 | Deterministik yol AI kapalıyken çalışır | ✅ |
| G4 | AI önerisi: kapsam + etkilenen kayıt + diff + onay + undo + audit | ⬜ |
| G5 | Bağlamsal AI aksiyonları (ürün açıklaması, çeviri, alt metin) | ⬜ |
| G6 | AI işareti yalnız gerçek AI içeriğinde | ✅ |

## H. Ölçüm

| # | Madde | Durum |
| --- | --- | --- |
| H1 | `qr_resolved` ve `public_menu_open_confirmed` ayrımı | ✅ |
| H2 | Ürün analitiği olay taksonomisi | ⬜ |
| H3 | Form olayları (`form_viewed`…`form_succeeded`) | ⬜ |
| H4 | Tenant bazında ölçülebilirlik | 🔶 |

## I. Altyapı

| # | Madde | Durum |
| --- | --- | --- |
| I1 | Navigation registry (permission/entitlement/featureFlag) | 🔶 `group`, `path`, `labelKey` var; yetki/flag alanları yok |
| I2 | Feature flag sistemi | ⬜ Pennant kurulu değil |
| I3 | Laravel policy/gate ile nihai yetki | ✅ |
| I4 | Flowbite/Radix görev ayrımı | 🔶 |
| I5 | i18n: bütün metinler katalogdan | ✅ mühürlü katalog |

---

## Tur günlüğü

Her tur hangi maddeleri kapattığını buraya yazar.

### Tur 1 — tamamlandı
- Envanter kuruldu (bu belge).
- **C1** saat dilimi markadan şubeye taşındı; `locations.timezone` eklendi ve
  markadan geri dolduruldu. `brands.timezone` bilerek yerinde bırakıldı.
- **C4** şube formunda ülke serbest metin olmaktan çıktı, listeden seçiliyor.
- **C5** saat dilimi ülkeye göre daraltılmış listeden seçiliyor.
- Yol boyunca iki kusur bulundu ve kapatıldı: liste gelmediğinde kayıtlı
  değerin kaybolması, ve önerilen saat diliminin gösterilip gönderilmemesi.
- Belge: `docs/62`.

### Tur 2 — sırada
- **A6** sidebar altında account trigger + popover (tema tercihi dâhil, A15).
- **A9/A10** global header katmanı ve lokasyon bağlamı.
- **A11** Global Create.
