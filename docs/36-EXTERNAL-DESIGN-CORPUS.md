# 36 — Depo Dışı Tasarım Külliyatı ve Referans Implementasyonu (devir kaydı)

> **Bu dosyayı silmeyin ve taşımayın.** README, `AGENTS.md`, `CLAUDE.md` ve
> `docs/25` buraya işaret eder; bir testi (`tests/Feature/ExternalDesignCorpusManifestTest.php`)
> bu işaretçilerin yerinde olduğunu doğrular ve kaybolurlarsa CI kırılır.

## 0. Neden var — yaşanmış körlük

Zabuno'nun tasarım yaklaşımı bu depoda **sentez** hâlinde bulunur (`docs/06`,
`docs/35`, `docs/03`, `resources/css/app.css`). Ayrıntılı külliyat ve **çalışan
bir referans implementasyonu** ise deponun dışında, owner'ın yerel makinesinde
yaşar.

2026-08-26'da bu doğrudan bir körlüğe yol açtı: tasarım sistemi üzerinde
çalışan bir oturum, aynı sistemin çalışan bir implementasyonunun (tam DTCG
token pipeline'ı, foundations CSS katmanları, AEP renderer pattern'leri, dalga
testleri) yanı başında durduğunu **fark etmeden** sıfırdan bir token katmanı
kurmaya başladı. Kaynak kayıp değildi; yalnız depodan görünmüyordu.

Bu dosya o körlüğün tekrarını engellemek içindir. Devir, exit, yeni geliştirici
veya vibecoding yapan bir ajan — hangisi olursa olsun, depoyu okuyan herkes
bu varlığın **var olduğunu** öğrenir.

## 1. Devir uyarısı (exit / ekip devri)

**2026-08-26 owner kararı:** felsefe belgeleri depoya taşındı ve artık bir
klonla birlikte gelir → [`docs/design-corpus/`](design-corpus/README.md)
(18 kök belge + 10 dosyalık `ui-variant-plan/`, bayt bazında birebir).

**Referans implementasyonu depoda DEĞİLDİR** ve bir klonla gelmez. Owner,
çalışan kodu (token pipeline'ı, foundations, AEP renderer) public repoya
taşımamayı seçti; bu bilinçli bir IP kararıdır.

**Devir veya exit sırasında §4'teki referans implementasyonu ayrıca
aktarılmalıdır.** Aktarılmazsa alıcı taraf tasarım *gerekçesini* alır
(§3 artık depoda) fakat çalışan implementasyonu kaybeder.

`docs/25` (Stage 8 Exit Ready) bu aktarımı bir devir kalemi olarak kaydeder.

## 2. Konum

Owner makinesinde: `~/DEV/zabuno/frontend`

İki bölümden oluşur: **felsefe külliyatı** (§3) ve **referans implementasyonu**
(§4).

## 3. Felsefe külliyatı — ARTIK DEPODA ✅

Taşındı: [`docs/design-corpus/`](design-corpus/README.md). Aşağıdaki tablo
neyin nerede olduğunu gösterir; dosya adları depo uyumlu hâle getirildi ve
özgün adlar dizin dosyasında kayıtlıdır.

| Belge | Ne dondurur |
|---|---|
| `SaaS Panel Tasarım Sistemi.md` | Öncelik sırası; Flat 2.0 + contextual cards formülü; form/table disiplinleri; 2026–2035 vizyonu |
| `Adaptive Semantic Grid.md` | ASG-320: 320px-first, semantic-priority, constraint-driven düzen; container-first bileşen; logical koordinatlar |
| `olcu-birimleri.md` | **Ölçü birimi nihai kararı** (§5'te özetlendi) |
| `AEP Design System — Grid ve Layout Sistemi…md` | AI tarafından okunabilir layout state'leri, grid ve responsive/adaptive sözleşmeleri |
| `Tasarım Paradigmaları.md` | Data-dense Flat 2.0; form, tablo, kart, motion ve sınırlı glass kullanımı |
| `ui-variant-plan/00…09` | A–F varyant çerçevesi, bileşen envanteri, Figma/Storybook promptları, değerlendirme protokolü |

## 4. Referans implementasyonu — `~/DEV/zabuno/frontend/claudeui/`

`worktrees/aep-storybook-foundations/` altında çalışan bir pnpm monorepo:

| Paket / dizin | İçerik |
|---|---|
| `packages/tokens` | **Tam DTCG pipeline.** Kaynak: `primitive` / `semantic` / `density` / `variant-overlay`. Build + validate script'leri. Çıktı: CSS, TS, **Figma variables**, **Ant Design theme**, **ECharts theme**, Storybook token-docs |
| `packages/foundations` | 7 CSS katmanı: reset, base, layout, components, utilities, overrides, main |
| `packages/renderer-aep` | AEP renderer; `ai-command-card` pattern'i 30 dosya (AiCommandCard, ExpandedPanel, CollapsedBar, AiPresenceOrb, NavCardGrid, BreadcrumbTrail … + ayrı motion CSS) |
| `tests/` | Dalga testleri: `w0-tokens-contract`, `w0-storybook-foundations`, `w1-card-contract-reconciliation`, `w2-card-services-pilot`, `w3-card-depth-material` |
| `docs/ui-variant-plan/` | 14 dosya — **bu depoda olmayan ikisi kritik**: `10-frontend-katman-mimarisi.md` ve `13-foundation-contract.md` |

### 4.1 Depoda karşılığı olmayan iki sözleşme

- **`10-frontend-katman-mimarisi.md`** — uzlaşılmış nihai katman haritası:
  A sözleşme → B app core → C renderer (**R1** token, **R2** CSS temeli,
  **R3** grid, **R4** görsel primitive, **R5** davranış, **R6** bileşen,
  **R7** durum, **R8** pattern) → D kompozisyon → E ürün. Kesen eksenler:
  varyant overlay, a11y kapıları, i18n/RTL, tema+density, X5 etkileşim
  durumu grameri. Bağımlılık yasağı: *"Her katman yalnız kendinden ALTTAKİ
  katmana bağımlıdır; yatay ve yukarı bağımlılık yasak."*
- **`13-foundation-contract.md`** — W-1 Foundation Contract: dondurulmuş token
  taksonomisi, `@layer` sırası ve scope yasaları, 320×480 viewport sözleşmesi,
  density, RTL, tema, forced-colors, motion; Storybook bilgi mimarisi (00–08)
  ve kapı kuralı; aile-sahiplik tablosu (MK-2, tek sahip); MK-16 golden-slice
  kapısı.

## 5. Külliyattan çıkarılan kanonik kararlar

Bunlar burada **özet** olarak durur ki külliyat aktarılmasa bile kararların
kendisi depoda kalsın. Ayrıntı ve gerekçe için §3–§4'e bakılır.

1. **Öncelik sırası:** görev tamamlama → içerik → tipografi → data semantics →
   erişilebilirlik → affordance → responsiveness → i18n → performans →
   dönüşüm → motion → estetik. Operasyonel modüllerde doğruluk, hata önleme,
   karşılaştırılabilirlik ve auditability öne geçer.
2. **Kimlik:** Flat 2.0 tabanı + *contextual* cards. Her bilgi grubunu karta
   sokmak yasak — spacing ve proximity zaten gruplama üretir.
3. **Ölçü birimi:** font `rem`; UI geometrisi logical design token; web
   çıktısı CSS `px`; responsive `fr`/`%`/container unit; `mm/cm/in` yalnız
   print. Atomik grid 4, ana ritim 8.
4. **En önemli madde (külliyatın kendi vurgusu):** *bileşen hiçbir zaman
   doğrudan `8px`, `16px`, `12px radius` bilmez; yalnız semantic token bilir.*
   Zincir: Primitive → Semantic → Component → Context Resolver →
   Platform Adapter.
5. **Density:** comfortable / standard / compact. Satır yüksekliği
   **height + padding** ile değişir, **asla font-size ile değil**.
6. **320px gerçek başlangıç noktasıdır**; container-query öncelikli, logical
   property tabanlı, RTL-native.
7. **AI düzenin katılımcısıdır, otoritesi değildir** (`docs/14` ile tutarlı):
   AI slot'lar üzerinden çalışır; kritik yolculuk AI kapalıyken deterministik
   yürür.
8. **Tema ve density token seviyesinde (R1) çözülür, bileşene sızmaz.**

## 6. Bu depodaki karşılıkları

| Külliyat kavramı | Bu depoda |
|---|---|
| Token zinciri | `resources/css/app.css` (semantic yüzey `@theme`'den yayınlanır), `docs/35` §1 |
| Katman sözleşmesi | `resources/js/design-system/semantic-map.ts`, `docs/35` §2a |
| Zorlayıcı kontrol | `resources/js/design-system/design-system.guard.test.ts` |
| Palet borcu | `resources/js/design-system/raw-palette-debt.json` |
| Tasarım kimliği | `docs/06` |
| AI duruşu | `docs/14`, `docs/32` |

Bu depodaki katman modeli (micro/compound/macro) külliyattaki R1–R8 modelinin
**kaba bir yaklaşımıdır** ve onunla birebir örtüşmez. Bilinen fark: R4 görsel
primitive'i ile R6 bileşeni bu depoda aynı "micro/compound" kutusuna düşer, bu
yüzden yatay bağımlılık yasağı burada tam uygulanamaz. İnceltme yapılana kadar
bu sapma bilinçli ve kayıtlıdır.
