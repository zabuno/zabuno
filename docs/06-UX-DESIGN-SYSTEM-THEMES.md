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

## 9. Kanonik sahiplik

Beş tema domeni, bileşen kütüphanesi kararı ve davranış sözleşmeleri burada
kanoniktir. QR'a özgü fiziksel/mm tasarım kısıtları `docs/08`'de, erişilebilirlik
detay kontrol listesi `docs/15`'te yaşar.
