# 06 — UX, Design System & Themes

**PLANNING ONLY.**

## 1. Beş tema domeni

| Domen | Kapsam | Layout kısıtı |
|---|---|---|
| Storefront/Marketing | Kurumsal site (`example.com/page/*`) | SEO-first shell, ağır component'siz |
| Public Menu | Müşteri menüsü (`example.com/m/*`, `q.*`) | Performans bütçeli (`docs/07`), 320px-first |
| Restaurant Admin | `/panel/restoran` | Global panel shell zorunlu (bkz. §4) |
| Superadmin | `/panel/admin` | Aynı shell, farklı navigasyon ağacı |
| QR Print | mPDF çıktısı | Web layout kısıtları geçersiz — mm/DPI tabanlı (`docs/08`) |

Ortak design token seti (renk, tipografi, spacing, radius) beş domende paylaşılır;
her domenin kendi layout/izin kısıtları vardır. Her domende **draft → preview →
publish → rollback** döngüsü ayrı ayrı geçerlidir (bir domendeki publish diğerini
etkilemez).

## 2. Bileşen kütüphanesi kararı

Flowbite React **first**; shadcn/ui **source-owned** (npm bağımlılığı değil, kod
projeye kopyalanır — güncellemeler bilinçli, kontrol edilebilir); Radix/headless
yalnız ikisinde de eksik **erişilebilir primitive** gerektiğinde adapter katmanından
kullanılır (bkz. `docs/03` ADR-L06). Aynı primitive iki kütüphaneden kurulmaz;
token/CSS çakışması adapter katmanında çözülür.

## 3. Ölçülebilir UX hedefi: 3 tık → 1 tık

Her sık kullanılan işlem (fiyat değiştir, ürünü gizle, QR indir) için hedef:
başlangıç tık sayısını ölçülebilir şekilde azaltmak. Ölçüm üçlüsü:

- **Task success rate** (kullanıcı işlemi hatasız tamamladı mı)
- **Time on task** (saniye)
- **Click/tap count** (adım sayısı)

Bu üçü olmadan "1 tık" iddiası kabul edilmez — `docs/27` acceptance kriterine
bağlıdır (ölçülmeden "iyileşti" denemez).

## 4. Global panel shell (admin domenlerinde zorunlu)

App shell, responsive sidebar, topbar, breadcrumb, workspace selector, restaurant
selector, profile menu, notification center, global search, command palette,
mobile navigation. Bu bileşenler **CORE değil** ama her admin domeninde tekrar
implemente edilmez — tek paylaşılan shell paketi (`modules/page-composition.md`
ile ilişkili, ancak bu bir görsel kabuk, iş modülü değil).

## 5. Form / feedback / veri listeleme davranış sözleşmesi

**Form davranışları**: client + server validation, inline/form-level hata,
required/disabled/loading/dirty state, unsaved-changes uyarısı, save progress,
success feedback, retry, optimistic update **yalnız güvenli (idempotent, geri
alınabilir) işlemlerde**.

**Feedback bileşenleri**: toast, alert, inline callout, loading skeleton,
empty/error/success state, confirmation dialog, destructive-action dialog (ekstra
onay adımı), undo, progress indicator.

**Veri listeleme**: search, filter, sort, pagination, column chooser, bulk
selection/action, saved filters/views, filter chips, query builder, virtualization
(büyük listelerde), export.

Bu üç davranış sözleşmesi her CRUD ekranında **varsayılan**dır; admin CRUD
table/form gelişmiş UX filtresi bu sözleşmeye göre tasarlanır — yetki ve tenant
filtreleri her zaman **backend authoritative**dir, UI'da filtre kaldırmak veri
sızdırmaz.

## 6. Progressive disclosure ve responsive

320px-first responsive; ileri düzey ayarlar varsayılan olarak gizli (progressive
disclosure), gerektiğinde açılır. Offline/empty/error/loading state'lerin dördü
de her ekranda tanımlı olmadan bir ekran "tamamlandı" sayılmaz.

## 7. AI ve destructive action kuralı

AI **önerir**; destructive, publish, payment, permission action'ları **asla**
kullanıcı onayı olmadan tetiklenmez (bkz. `docs/14` §Human Approval). Bu UX
kısıtı, AI Platform modülünün tool-allowlist mekanizmasıyla teknik olarak da
zorlanır — yalnız bir UX kuralı değil, backend-level bir kapı.

## 8. Erişilebilirlik ve i18n

WCAG 2.2 hedefi en az **AA**; kritik akışlarda (ödeme, hesap silme, veri export)
**AAA** ölçütleri aday olarak değerlendirilir (`docs/15`). Arabic RTL tasarım ve
test zorunludur (`docs/13`); tema token'ları LTR/RTL'i aynı anda desteklemelidir.

## 10. Görsel kimlik: Operational Hospitality

Zabuno'nun iç tasarım yönünün adı **Operational Hospitality / Operasyonel
Misafirperverlik**dir: sıcak ama oyuncak değil, ferah ama boş değil, yoğun
ama sıkışık değil, enterprise ama bürokratik değil, AI destekli ama
kontrolsüz değil. Bu, beş tema domeninin (§1) ortak duygusal çerçevesidir —
her domen kendi yüzey grameriyle bu çerçeveyi somutlaştırır:

- **Dashboard**: görev, risk, aksiyon ve operasyon kartları.
- **Menu/Media/Locations**: analytical list-detail-workspace yapısı.
- **Brand/Settings/Billing/Team**: section-first, form-first, ferah düzen.
- **Publication/QR**: state machine, readiness, preview, diff, approval,
  rollback (bkz. `docs/10` §2 durum makineleri).
- **Analytics/Audit**: data table, saved views, filters, editorial açıklama.
- **Public Menu**: daha ifade gücü yüksek, fotoğraf ve restoran markasına
  uyarlanabilir tema.

Görsel formül: Precision Flat 2.0 + Tonal SaaS Shell + Contextual Cards +
Glass-capable Surfaces + Status Stripes + Editorial Evidence + Expressive
Public Menu + AI Presence.

## 11. AEP mirası: primitive → semantic alias

Zabuno'nun başlangıç marka/token kararları `/Users/karaca/DEV/zabuno/frontend/`
altındaki AEP (320px-First, AI-First Enterprise SaaS) araştırmasından
devralınır, ancak ham biçimde kopyalanmaz:

- **`#FFB900`** yalnız bir **primitive** token'dır (örn. `color-brand-primitive`);
  hiçbir component ham hex tüketmez. Semantic alias'lara (`action.primary`,
  `ai.presence` vb., §12 token katmanları ile tutarlı — bkz. `docs/35` §1)
  bağlanır; light/dark/forced-colors eşleri ayrıca tanımlanır. Açık zeminde
  küçük sarı metin olarak kullanılmaz; gerekli yerde koyu foreground ile
  eşlenir; production kabulü ölçülmüş kontrast (WCAG AA, §8) olmadan
  yapılmaz.
- **Roboto** başlangıç UI fontudur, typography token'ı üzerinden uygulanır;
  Turkish glyph/numeral QA'sı ve test edilmiş Arabic script fallback'i
  zorunludur (`docs/13` RTL disipliniyle tutarlı); para/fiyat/analitik
  alanlarında tabular numerals desteklenir.
- **Glass yüzeyler** form surface, data table container, validation summary,
  global header, command palette, AI surface, contextual inspector, overlay/
  drawer ve public menu surface'larında kullanılabilir (yasak değildir);
  her glass yüzey minimum opacity/scrim, ölçülmüş foreground/background
  kontrastı, `backdrop-filter` desteklenmediğinde solid fallback, forced-colors
  fallback'i, reduced-transparency/performance fallback'i ve light/dark
  parity taşır. Glass; okunabilirliği, form affordance'ını veya tablo satır
  ilişkisini zayıflatamaz.
- AEP'nin dual-renderer hazırlığı, semantic token yaklaşımı ve Storybook
  merkezli sürdürülebilir component sistemi mirası `docs/03` ADR-L10'da
  (dual-renderer readiness) ve `docs/35`'te (Storybook fabrika sözleşmesi)
  ayrıca kanoniktir — burada tekrar edilmez.

## 12. 320 start / fluid-first / adaptive-second

320 CSS px bir **başlangıç kanıtı**dır, bir breakpoint değildir (§6'daki
320px-first ilkesinin genişletilmiş biçimi — bu bölüm onu **çelişmez**,
netleştirir). Görev modeli önce 320'de eksiksiz çözülür; alan büyüdükçe
işlev değişmez, bilgi kaybolmaz, yalnız eşzamanlı gösterim kapasitesi artar
(fluid-first). Named adaptive profiller (`mobile`, `tablet`, `laptop`,
`desktop`, `wide-desktop`) QA, shell davranışı, platform konvansiyonu ve
input ergonomisi için kullanılır — bu profiller cihaz adına göre davranan
ayrı bir bileşen seti **değildir**; reusable component'ler mümkün olduğunca
kendi container'ına göre morfolojik olarak değişir (container query
yaklaşımı).

**"Responsive" bu külliyatta bir plan/acceptance etiketi olarak
kullanılmaz** — kabul kriteri her zaman somut adaptive profil + 320px
reflow kanıtıdır (§8 WCAG matrisindeki "Reflow/320px" satırı, `docs/35` §9
ile tutarlı), genel "responsive" ifadesi tek başına bir acceptance ifadesi
sayılmaz.

## 13. Kanonik sahiplik

Beş tema domeni, bileşen kütüphanesi kararı, davranış sözleşmeleri, görsel
kimlik (Operational Hospitality), AEP token mirası ve 320/fluid/adaptive
layout ilkesi burada kanoniktir. QR'a özgü fiziksel/mm tasarım kısıtları
`docs/08`'de, erişilebilirlik detay kontrol listesi `docs/15`'te,
Storybook/token uygulama sözleşmesi `docs/35`'te, dual-renderer kararı
`docs/03` ADR-L10'da yaşar.
