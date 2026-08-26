# 09 — AEP Design System: Bileşen Envanteri ve Ajan Prompt'ları (Claude Design + UI Developer)

Bu doküman nedir: EA Platform (EA, EBP, EOP, EBM, ERX) design system'inde bulunması gereken
bileşenlerin katmanlı envanteri, her bileşenin içermesi gereken zorunlu içerik sözleşmesi ve
bu bileşenleri üretecek iki ajanın — "Claude Design" (tasarım ajanı) ve "UI Developer"
(Storybook/kod ajanı) — master prompt'larıdır.

Ne işe yarar: 00–08 plan setinin bileşen-düzeyi tamamlayıcısıdır. 02–05 dosyaları card, form,
table ve destekleyici bileşenlerin A–F varyant spesifikasyonunu verir; bu doküman ise "tam
envanter neyi kapsamalı, her bileşen hangi içerikle 'tamam' sayılır ve ajanlara hangi prompt
verilir" sorularını cevaplar.

Ne yapar: bileşen listesini 8 katmanda verir; her bileşen için içerik sözleşmesini tanımlar;
Storybook 10.x'in tüm özelliklerinin bu projede nasıl kullanılacağını haritalar; iki adet
kopyala-yapıştır master prompt içerir.

Ne yapmaz: 02–05'teki varyant (A–F) mikro-kararlarını tekrarlamaz; yeni renk, radius, token
veya varyant icat etmez; implementasyon kodu içermez (kod üretimi UI Developer ajanının işidir
ve insan code review'undan geçer).

Bağlantılı dosyalar: `ui-variant-plan/00-genel-plan.md` · `01-varyant-cercevesi.md` ·
`02-card-varyantlari.md` · `03-form-varyantlari.md` · `04-table-varyantlari.md` ·
`05-bilesen-varyantlari.md` · `06-figma-mcp-promptlari.md` · `07-storybook-mcp-promptlari.md` ·
`08-degerlendirme-protokolu.md`

---

## 1. Değişmezler (özet — tam metin: 01-varyant-cercevesi.md)

Aşağıdaki değerler hem tasarım hem kod ajanı için bağlayıcıdır; iki prompt da bunları içerir.

| Alan | Değer |
|---|---|
| Tipografi | Roboto + Noto Sans fallback, self-host; weight 400/500/700; min font 1rem (16px); sayısal hücrede tabular-nums |
| Primary | #FFB900; üstündeki metin HER ZAMAN #080616 (asla beyaz) |
| Secondary | #003399; dark zeminde metin/ince ikon YASAK; dark metin mavisi #93A8F4 |
| Yüzeyler | dark: #080616 / #0D0A24 / #16123A; light: #F7F7FB / #FFFFFF; border #E4E4EE (dark #26224A) |
| Geometri | radius 2/4/6/8 px (tavan 8, input muaf); spacing 4/8/12/16/24/32/48; hit area min 44×44px |
| Davranış | dark/light semantic token ile; density 52/44/36; WCAG 2.2 AA; focus-visible zorunlu; motion 120–240ms ease-out, hover'da scale yok |
| Yerleşim | 320px-first; bantlar 320/480/768/1024/1440; container-query öncelikli; RTL + logical properties; ikon Phosphor SVG, emoji yasak |
| Stack | Vite + React + TypeScript + SCSS/CSS custom properties; Next.js ve Supabase kullanılmaz |

---

## 2. Bileşen sözleşmesi: her bileşen ne içermeli

Her bileşen, katmanından bağımsız olarak aşağıdaki 12 içeriği tamamladığında "bitti" sayılır.
Bu sözleşme, envanter tablolarındaki "içerik" sütununun ortak paydasıdır; tablolar yalnız
bileşene özgü ekleri listeler.

| # | İçerik | Açıklama |
|---|---|---|
| 1 | Anatomi | Adlandırılmış parçalar (ör. TextField: label, control, prefix/suffix, help text, error text, counter). Figma katman adları ile kod DOM yapısı aynı adları kullanır |
| 2 | Props API | TypeScript interface; görünüm props'u boolean patlaması yerine `variant`/`size`/`tone` gibi sınırlı enum'lar; davranış headless katmandan gelir |
| 3 | State seti | default, hover, focus-visible, active, selected, disabled, readonly, loading, error, empty — hangileri geçerliyse tamamı tasarlanır ve story'lenir |
| 4 | Tema | light/dark yalnız semantic token ile; kodda ve Figma'da hardcode hex yok |
| 5 | Density | comfortable 52 / standard 44 / compact 36 px; fark yalnız padding'le, font küçülmez |
| 6 | Varyant overlay | `data-variant="a..f"` CSS custom property overlay'i; davranış katmanı tek, varyantlar yalnız 12 mikro-eksende ayrışır |
| 7 | A11y sözleşmesi | ARIA rolü, tam klavye haritası, aria-* öznitelikleri, görünür focus-visible, hit area ≥44×44px, durum daima ikon+metin (asla yalnız renk) |
| 8 | i18n/RTL | CSS logical properties; Intl.* formatlama; Almanca uzama, Türkçe collation (Intl.Collator), Arapça tam mirror fixtures |
| 9 | 320px davranışı | Bileşenin dar ekran stratejisi açıkça tanımlı (katlanma, overflow menüsü, tam ekran, yığılma) |
| 10 | Token bağımlılığı | Bileşenin tükettiği semantic token listesi; token drift CI bu listeyle denetler |
| 11 | Story seti | Docs (autodocs + MDX), Matrix (visual tag), Play (etkileşim), a11y (axe error mode), i18n; DataTable gibi ağır bileşenlerde perf story |
| 12 | Figma karşılığı | Component set (variant prop A–F) + Code Connect eşlemesi; addon-designs ile story'ye Figma embed |

---

## 3. Envanter — Katman 0: Foundations (bileşen değil, herkesin tükettiği temel)

Foundations bir bileşen değildir; tüm bileşenlerin tükettiği token koleksiyonları ve
yardımcı altyapıdır. Kaynak: tokens/*.json (tek doğruluk kaynağı) → Style Dictionary →
CSS custom properties + Figma Variables.

| Öğe | İçerik |
|---|---|
| Color tokens | primitive (ink/blue/yellow/semantik skalalar) → semantic (surface, text, border, accent, status) → light/dark modları |
| Typography tokens | Roboto aile, 400/500/700, tip ölçeği (1rem taban), satır yükseklikleri, tabular-nums kuralı |
| Spacing/Sizing | 4/8/12/16/24/32/48; kontrol yükseklikleri 52/44/36; hit area 44 |
| Radius | 2/4/6/8 (+input muafiyet değerleri: 0 / 4 / pill) |
| Elevation | raised y=2 blur=8 %10; overlay y=4 blur=16 %12; dark eşdeğeri: ton + 1px border |
| Motion | süre 120/150/200/240ms, ease-out; reduced-motion eşlemeleri |
| Breakpoints/Grid | 320/480/768/1024/1440; container-query öncelikli grid (bkz. grid/layout dokümanı) |
| Density | comfortable/standard/compact modları (token koleksiyonu) |
| Variant overlay | a–f koleksiyonu (12 mikro-eksenin token karşılıkları) |
| Iconography | Phosphor SVG seti, 20–24px görünür boyut, ikon adlandırma sözlüğü; emoji yasak |
| z-index ölçeği | base / sticky / overlay / modal / toast / tooltip katmanları |

---

## 4. Katman 1: Primitives (atomik yapı taşları)

| Bileşen | Nedir / ne işe yarar | Sözleşmeye ek olarak içermesi gerekenler |
|---|---|---|
| Button | Primary/secondary/ghost/danger aksiyon | Sarı buton kuralı (#FFB900 + #080616); ekran başına tek primary; loading state'te spinner + etiket korunur; icon-start/icon-end slotları |
| IconButton | Yalnız ikonlu aksiyon | `aria-label` zorunlu; tooltip eşliği; toggle modunda `aria-pressed`; 44px hit area |
| Link | Satır-içi ve bağımsız bağlantı | Ziyaret/hover/focus ayrımı yalnız renkle değil (alt çizgi); harici link ikonu + `rel` kuralları |
| Badge / Tag / Status | Semantik kapsül: durum, etiket, sayaç | Pill muafiyeti; status'ta ikon+metin zorunlu; sayaçta tabular-nums; removable tag'de 44px kapatma hedefi |
| Avatar / AvatarGroup | Kişi/tenant görseli | Baş harf fallback (Intl'e uygun); boyut ölçeği; grup taşma sayacı ("+5") |
| Icon | Phosphor sarmalayıcı | Boyut/renk yalnız token; dekoratif ise `aria-hidden`, anlamlıysa erişilebilir ad |
| Divider | Bölüm ayracı | Yatay/dikey; eksen 6 (divider stratejisi) varyant taşıyıcısı; semantik değilse `role="presentation"` |
| Spinner / ProgressBar | Belirsiz/belirli ilerleme | `role="status"`/`progressbar` + aria-valuenow; reduced-motion'da dönme yerine soluklaşma |
| Skeleton | Yükleme iskeleti | Gerçek satır/kart yükseklikleriyle birebir; virtualized satır iskeletiyle ortak token |
| Tooltip | Kısa açıklama overlay'i | Hover+focus ile açılır, Esc kapatır; içinde etkileşimli öğe YOK; gecikme token'ı |
| Kbd | Klavye kısayolu gösterimi | CommandPalette ve menülerde kısayol ipuçları; tabular hizalama |
| VisuallyHidden | Ekran okuyucuya özel metin | Görsel gizleme utility'si; focus'ta görünür SkipLink türevi |

## 5. Katman 2: Form bileşenleri

Validation modeli tümünde ortak: değer korunur → hata açıklanır (aria-describedby, ikon+metin)
→ düzeltme → açık onay. Label her zaman görünür ve üsttedir; placeholder asla tek label olamaz.

| Bileşen | Nedir / ne işe yarar | Sözleşmeye ek içerik |
|---|---|---|
| FormField (sarmalayıcı) | Label + control + help + error + counter dizilimi | Tüm alan bileşenlerinin ortak iskeleti; id/aria bağlantılarını tek yerden kurar |
| TextField | Tek satır metin girişi | Input muafiyet ekseni (E8) pilot bileşeni; prefix/suffix, clear butonu; 120 karakter label fixture'ı |
| TextArea | Çok satır metin | Otomatik büyüme sınırı; karakter sayacı (tabular-nums) |
| NumberField | Sayısal giriş | Intl.NumberFormat; step kontrolleri 44px; tabular-nums; RTL'de işaret/yüzde konumu |
| PasswordField | Parola girişi | Göster/gizle IconButton (aria-pressed); caps lock uyarısı ikon+metin |
| SearchField | Arama girişi | Toolbar ve CommandPalette ile paylaşılan anatomi; debounce davranışı headless'ta |
| Select | Kapalı listeden tekli seçim | Native-benzeri listbox; klavye harf araması; 320px'te tam genişlik |
| Combobox / Autocomplete | Yazarak filtrelenen seçim | `aria-expanded/activedescendant`; Esc listbox'ı kapatır, DEĞERİ SİLMEZ; async yükleme durumu |
| MultiSelect + TagInput | Çoklu seçim | Seçimler kapsül (Badge kuralları); taşma "+N"; tümünü temizle onaylı |
| Checkbox / CheckboxGroup | Çoklu onay | Indeterminate üçüncü durum; grup düzeyi hata; Space toggle |
| Radio / RadioGroup | Tekli seçim | Roving tabindex + ok tuşları; asla tek başına radio |
| Switch | Anında etki eden aç/kapat | Kapsül (pill) muafiyeti; anında kaydeder — form submit'i beklemez; durum metni ("Açık/Kapalı") |
| Slider | Aralıktan değer | Klavye ok/PageUp-Down/Home-End; değer tooltip'i; 44px thumb hedefi |
| DateField / DatePicker / DateRangePicker | Tarih girişi ve takvim | Intl.DateTimeFormat; klavyeyle tam gezinme; takvim grid'i `role="grid"`; sabit referans tarih fixture'ı 2026-08-16 |
| TimeField | Saat girişi | 12/24 saat locale'e göre; tabular-nums |
| FileUpload | Dosya seçme/sürükleme | Sürükle-bırak + buton eşdeğeri; ilerleme + iptal; hata satırı ikon+metin |
| FormSection | Form içi bölümleme | Card form-section türüyle eşleşir; başlık hiyerarşisi; D varyantı property-editor semantiği |
| FormFooter | Kaydet/iptal bandı | Primary tekliği; sticky davranışı 320px'te; dirty-state uyarısı |
| ErrorSummary | Hata özeti bloğu | Submit'te odak buraya; `role="alert"` + tabindex="-1"; alanlara link listesi |
| InlineEdit | Yerinde düzenleme | Görüntüle→düzenle geçişi; Enter commit / Esc geri yükle; DataTable hücre editörüyle ortak headless |

## 6. Katman 3: Veri gösterimi

| Bileşen | Nedir / ne işe yarar | Sözleşmeye ek içerik |
|---|---|---|
| Card (5 tür: metric/KPI, entity, list-item, form-section, commerce) | Bağlamsal içerik kapsayıcısı | Tür anatomileri 02'de; media slot; tıklanabilir kart tek link kuralı; 320px tek kolon |
| DataTable / DataGrid | Sıralanabilir, filtrelenebilir, seçilebilir veri tablosu | TanStack Table headless + TanStack Virtual; aria-sort döngüsü; bulk select + indeterminate; inline edit; kolon pinleme (logical start); sticky header; 10k satır 60fps / <50ms bütçesi; mobil strateji (04): kolon budama → kart dönüşümü; boş/yükleniyor/hata gövdeleri |
| Pagination | Sayfalama | Tabular-nums; 320px'te önce/sonra + gösterge; sayfa boyutu seçici |
| DescriptionList / KeyValue | Etiket-değer çiftleri | Detay ekranının temel taşı; kopyala aksiyonu; RTL hizalama |
| List / ListItem | Genel dikey liste | Seçilebilir/aksiyonlu türevler; Menu ile ortak item anatomisi |
| Timeline / ActivityFeed | Zaman akışı, denetim izi | Intl.RelativeTimeFormat; aktör + eylem + zaman üçlüsü; AI eylemlerinde provenance işareti |
| Stat / KPI | Tek metrik vurgusu | Metric card'ın atomik içi; delta yönü ikon+renk+metin; sarı yalnız kritik KPI (E kuralı) |
| EmptyState | Boş durum | Neden + ne yapmalı + tek aksiyon; B varyantında sarı ikon serbestisi |
| Chart sarmalayıcıları (Line, Bar, Donut, Sparkline) | Veri görselleştirme | dataviz token'ları; renk tek başına anlam taşımaz (desen/etiket); tooltip klavyeyle erişilebilir; boş/yükleme durumları |
| Calendar / Scheduler görünümü | Takvim yerleşimi | DatePicker grid'inin büyütülmüş hali; hafta başlangıcı locale'den |
| TreeView | Hiyerarşi gezinimi | `role="tree"` + tam klavye; EA yetenek/uygulama hiyerarşileri için |
| CodeBlock / JSONViewer | Teknik içerik gösterimi | ERX/entegrasyon ekranları; kopyala butonu; satır numarası tabular |

## 7. Katman 4: Navigasyon ve uygulama kabuğu

| Bileşen | Nedir / ne işe yarar | Sözleşmeye ek içerik |
|---|---|---|
| AppShell | Header + SideNav + içerik + Footer iskeleti | Modül geçişi (EA/EBP/EOP/EBM/ERX); 320px'te SideNav → Drawer; content container-query kökü |
| GlobalHeader (çok katmanlı) | Üst çubuk: logo, modül anahtarı, arama, bildirim, tenant/kullanıcı | Glass opsiyonel + opak fallback (tek izinli alan, CommandPalette ile); katmanlar: utility bar + ana bar + bağlam bandı |
| SideNav | Birincil dikey gezinme | Collapse/expand; aktif işaret eksen 5; grup başlıkları; rozetli sayaçlar |
| GlobalFooter (çok katmanlı) | Alt bilgi: kurumsal linkler, dil/locale seçici, yasal | Locale switcher (i18n giriş noktası); sitemap katmanı + yasal katman |
| Breadcrumb | Konum zinciri | 320px'te yalnız üst seviye + geri; RTL yön ikonları |
| Tabs | İçerik sekmeleri | 05 §7; 320px yatay kaydırma + ipucu |
| SegmentedControl | Parametre değiştirici | 05 §8; radiogroup semantiği |
| Toolbar | Liste üstü aksiyon/filtre çubuğu | 05 §9; 320px overflow menüsü; bulk-actions moduna dönüşüm |
| Menu / Dropdown | Popover aksiyon listesi | 05 §11; alt menü; tehlikeli aksiyon ayrımı (danger item) |
| CommandPalette | Global komut arama (Cmd/Ctrl+K) | 05 §15; glass istisnası + opak fallback; AI sonuç satırında sarı provenance şeridi; son kullanılanlar |
| Stepper / Wizard | Çok adımlı akış | Adım durumu ikon+metin; geri dönüşte veri korunur; 320px dikey yığılma |
| SkipLink | İçeriğe atla | İlk Tab'da görünür; AppShell'in zorunlu parçası |

## 8. Katman 5: Geri bildirim ve overlay

| Bileşen | Nedir / ne işe yarar | Sözleşmeye ek içerik |
|---|---|---|
| Toast | Geçici bildirim | `role="status"/"alert"` ayrımı; kuyruk yönetimi; süre + kalıcılaştırma; 05 §12 |
| Alert / Banner | Satır-içi kalıcı uyarı | Sayfa ve bölüm düzeyi; kapatılabilirlik kuralı; semantik 600 seti |
| Modal / Dialog | Kesintili diyalog | Focus trap, Esc, odak iadesi; scrim %40 ink/950 blursuz; 320px tam genişliğe yakın |
| Drawer | Kenar paneli | 320px tam ekran; yön logical (start/end) |
| SidePanel (Option Gallery + Option Information) | İki bölmeli seçim/detay paneli | 05 §10; 320px'te bölmeler yığılır, geri aksiyonu görünür |
| Popover | Bağlamsal küçük overlay | Tooltip'ten farkı: etkileşimli içerik taşır; odak yönetimi |
| ConfirmDialog | Onay diyaloğu | Tehlikeli aksiyonda fiil adlı buton ("Sil", "Onayla" değil "Tamam"); danger kuralları |
| NotificationCenter | Bildirim merkezi | D varyantı grup deseni; okundu yönetimi; Timeline ile ortak satır anatomisi |
| ProgressOverlay / LoadingState | Uzun işlem geri bildirimi | Belirli/belirsiz ayrımı; iptal edilebilirlik; iskeletle iş bölümü |

## 9. Katman 6: AI-first bileşenler (EA Platform'a özgü)

AI katmanının ilkesi: AI teşhis eder ve önerir; kullanıcı onaylar; sistem uygular. AI hiçbir
zaman insan onayı olmadan veri değiştirmez. Bu bileşenler o sözleşmeyi görünür kılar.

| Bileşen | Nedir / ne işe yarar | Sözleşmeye ek içerik |
|---|---|---|
| AIProvenanceBadge / Stripe | "Bu içerik AI üretimi" işareti | Sarı (#FFB900) provenance grameri (C varyantı şerit kuralıyla tutarlı); ikon+metin; hover'da model/zaman detayı |
| PromptBar / AIPromptInput | Doğal dil komut girişi | SearchField anatomisi + gönder aksiyonu; çok satıra büyüme; kısayol (Kbd); istek geçmişi |
| StreamingText | Akan AI cevabı gösterimi | `aria-live="polite"`; durdur butonu; reduced-motion'da bloklu güncelleme; iskeletle başlar |
| AISuggestionCard | AI önerisi + aksiyonları | Öneri metni + gerekçe + kaynak; Kabul/Reddet/Düzenle üçlüsü; provenance işareti zorunlu |
| DiffView | Mevcut ↔ önerilen fark | Alan bazlı önce/sonra; yalnız renkle değil +/- ikon ve etiket; RTL'de yön korunur |
| HumanApprovalBar | İnsan onay bandı | Onayla (primary sarı) / Reddet (secondary); onay kapsamı metni; denetim izine yazıldığı bilgisi |
| AgentStatusIndicator | Ajan çalışma durumu | idle/çalışıyor/bekliyor/hata; Spinner+metin; iptal aksiyonu |
| ConfidenceIndicator | Öneri güven düzeyi | Sayı + seviye metni (düşük/orta/yüksek); asla yalnız renk; eşik altında otomatik "insan incelemesi önerilir" notu |
| AIAuditLogRow | AI eylem denetim kaydı | Timeline satır türevi: aktör=ajan, eylem, girdi/çıktı referansı, onaylayan insan |

## 10. Katman 7: Kompozisyon pattern'leri (bileşen değil, referans ekranlar)

| Pattern | İçerik |
|---|---|
| Kayıt Listesi | Toolbar + DataTable + Pagination + bulk-actions + EmptyState; P3 referans ekranı |
| Kayıt Oluştur | FormSection'lı tek kolon form + FormFooter + ErrorSummary; P3 referans ekranı |
| Detay + Inline Edit | DescriptionList + InlineEdit + Timeline + SidePanel; P3 referans ekranı |
| Dashboard | Metric card grid + chart + ActivityFeed; grid/layout dokümanına bağlı |
| Ayarlar / Property Editor | D varyantı doğal alanı; gruplanmış FormSection'lar |
| Commerce PDP | Hero + sol Option Gallery + sağ Option Information + çok katmanlı header/footer (F varyantı doğal alanı) |
| Auth (giriş/şifre) | Minimal form + hata akışı; marka yüzeyi |
| AI Çalışma Alanı | PromptBar + StreamingText + AISuggestionCard + HumanApprovalBar + AIAuditLog |

---

## 11. Storybook özellik haritası: tüm özellikler → bu projede kullanım

Storybook yalnız story koşucusu değil; dokümantasyon portalı, test koşucusu, görsel regresyon
kaynağı ve tasarım-kod köprüsüdür. Aşağıdaki tablo "tüm özelliklerden faydalanma" talebinin
bağlayıcı karşılığıdır; UI Developer prompt'u bu tabloyu uygular.

| Özellik | Nedir | Bu projede kullanımı |
|---|---|---|
| CSF3 + autodocs | Bileşen başına otomatik docs sayfası | Her bileşende `tags: ['autodocs']`; Props tablosu argTypes'tan |
| MDX docs | Serbest dokümantasyon sayfaları | Foundations (renk/tip/spacing/motion) sayfaları; kullanım YAP/YAPMA örnekleri; bu envanterin canlı hali |
| Args / ArgTypes / Controls | Props'u UI'dan canlı değiştirme | Tüm görünüm props'ları kontrollü; enum'lar radio/select kontrolü; renk kontrolü KAPALI (token dışı renk denenemez) |
| Actions | Event loglama | Tüm on* handler'ları otomatik action; davranış sözleşmesinin gözle doğrulanması |
| globalTypes + toolbar | Global eksen anahtarları | theme × density × variant (a–f) × locale (en/de/tr/ar) dört toolbar anahtarı; decorator `<html>`e data-* yazar |
| Decorators | Story sarmalayıcıları | Tek global decorator (07 Prompt 1); AppShell decorator'ı kompozisyon story'lerinde |
| Parameters | Story meta ayarları | a11y error-mode, backgrounds token'dan, layout, chromatic/vrt ayarları |
| Viewport addon | Ekran boyutu simülasyonu | 320/480/768/1024/1440 preset'leri; varsayılan 320 (mobile-first'ü araca gömer) |
| Backgrounds addon | Zemin değiştirme | Yalnız token yüzeyleri (ink/950, ink/50, #FFFFFF); serbest renk kapalı |
| Measure & Outline | Spacing/hiza denetimi | Tasarım incelemelerinde 4px ölçeğine uyum kontrolü |
| Highlight | Öğe vurgulama | A11y bulgularının görsel işaretlenmesi |
| Play functions + @storybook/test | Story içinde etkileşim testi | Form/table/overlay senaryoları (07 Prompt 3–4); yalnız role/label sorgusu |
| Test-runner (Playwright) | Tüm story'leri CI'da koşma | play + axe birlikte; PR bloklayıcı (07 Prompt 8) |
| addon-a11y (axe) | Erişilebilirlik denetimi | Error mode; color-contrast/label/focus kuralları fail-blocking |
| Visual tests / snapshot | Görsel regresyon | Budanmış matris (07 §4); matrix story'ler "visual" tag'iyle birincil hedef |
| Coverage addon | Test kapsamı | Play testlerinin kod kapsamı raporu; headless katman hedefi ≥%80 |
| Tags | Story sınıflandırma | visual / i18n / perf / wip; CI tag bazlı filtreler |
| Subcomponents | İlişkili bileşen docs'u | FormField altında TextField türevleri; Card türleri tek sayfada |
| Loaders | Async fixture yükleme | 10k satır seed'li dataset; deterministik (seed 20260816) |
| addon-designs | Story'ye Figma embed | Her bileşen story'sinde Figma component set çerçevesi; tasarım-kod karşılaştırması tek ekranda |
| Composition (refs) | Başka Storybook'ları bağlama | Modül paketleri ayrışırsa (EA/EBP/EOP/EBM/ERX) tek portalda birleşik görünüm |
| Portable stories | Story'leri Vitest/Playwright'ta yeniden kullanma | Story fixture'ları birim testlerinde tekrar kullanılır; çifte fixture yasak |
| addon-links / navigation | Story'ler arası bağlantı | Pattern sayfalarından bileşen sayfalarına çapraz linkler |
| Static build + publish | Yayınlanmış portal | Her PR'da preview; main'de sürümlü yayın — design system'in SSOT vitrini |
| addon-mcp | Ajan erişimi | `http://localhost:6006/mcp`; ajanlar envanter/test sonuçlarını buradan okur (07 §1) |

---

## 12. Prompt A — "Claude Design" (tasarım ajanı master prompt'u)

Ne zaman kullanılır: Claude'a (Figma MCP bağlıyken veya tasarım spesifikasyonu üretirken)
design system'in tasarım ayağını verdirmek için. Prompt tek seferde tüm sistemi istemez;
"CURRENT ASSIGNMENT" satırına o oturumun hedefi yazılır (ör. "Layer 2: form family" veya
"tokens only"). Ajan yalnız teşhis ve üretim yapar; hiçbir çıktı insan (tasarım lideri)
onayı olmadan kabul edilmiş sayılmaz.

```text
ROLE
You are "Claude Design", the design agent for the AEP Design System of the EA Platform
(modules: EA, EBP, EOP, EBM, ERX) — an AI-first, data-dense, enterprise SaaS panel
suite for global companies. You produce design-system artifacts: design tokens, Figma
component sets, composition frames and written specs. You do NOT write production code;
the "UI Developer" agent consumes your output. No artifact is "accepted" until a human
design lead reviews it.

CURRENT ASSIGNMENT
<fill in per session, e.g. "Layer 2: form family — TextField, Select, Combobox,
Checkbox/Radio/Switch, DateField, FormSection, FormFooter, ErrorSummary">

NON-NEGOTIABLE CONSTRAINTS (violating any of these invalidates the output)
- Typography: Roboto (self-hosted, Noto Sans script fallback), weights 400/500/700
  only, minimum font size 1rem (16px) everywhere — captions, badges and table cells
  included; numeric content uses tabular figures.
- Color: primary #FFB900 (lemon yellow); text/icons on #FFB900 are ALWAYS #080616,
  never white. Secondary #003399 (parliament blue); on dark surfaces (#080616) #003399
  is FORBIDDEN for text or thin icons — text-level blue in dark is #93A8F4. Dark
  surfaces: #080616 / #0D0A24 / #16123A; light: #F7F7FB / #FFFFFF; borders #E4E4EE
  (light) / #26224A (dark). Semantic 600s: success #15803D, error #DC2626, warning
  #B45309, info #1D4ED8 (brightened derivatives in dark). Status is never conveyed by
  color alone: icon + text always.
- Geometry: radius scale 2/4/6/8px, operational ceiling 8px (12px is an absolute
  hard limit, never a working value); inputs are exempt (0px sharp / 4px / pill only
  where the variant spec allows). Spacing scale 4/8/12/16/24/32/48. Hit areas min
  44x44px. Densities: comfortable 52 / standard 44 / compact 36px row heights,
  achieved by padding only — fonts never shrink.
- Layout: 320px-first (design every component at 320px before any wider band), then
  480/768/1024/1440. Full i18n/l10n/g11n: RTL mirroring via logical start/end,
  German-expansion and Turkish-casing resilience, visible labels always above
  controls. Icons: Phosphor SVG, 20-24px visible size. Emoji are forbidden.
- Style: Flat 2.0 + contextual Card UI. No neumorphism. Glass/blur only in the
  global header and CommandPalette, always with an opaque fallback; never on forms,
  tables, menus, toasts or scrims. Scrims are flat 40% #080616, no blur. Motion
  120-240ms ease-out, functional only, no hover scaling, reduced-motion respected.
- Theming: every color/radius/spacing decision must map to a named design token
  (primitive -> semantic -> density -> variant overlay a-f). Never hardcode a value
  that has no token. Do not invent new colors, radii, tokens, breakpoints or a
  seventh variant. The six variants A-F differ ONLY on the 12 defined micro-axes.

SCOPE OF THE SYSTEM (full inventory you design against)
- Layer 0 Foundations: color/typography/spacing/radius/elevation/motion/density/
  breakpoint/z-index token collections + Phosphor icon conventions.
- Layer 1 Primitives: Button, IconButton, Link, Badge/Tag/Status, Avatar, Icon,
  Divider, Spinner/ProgressBar, Skeleton, Tooltip, Kbd, VisuallyHidden.
- Layer 2 Form: FormField, TextField, TextArea, NumberField, PasswordField,
  SearchField, Select, Combobox, MultiSelect/TagInput, Checkbox(+Group),
  Radio(+Group), Switch, Slider, DateField/DatePicker/RangePicker, TimeField,
  FileUpload, FormSection, FormFooter, ErrorSummary, InlineEdit.
- Layer 3 Data display: Card (metric/entity/list-item/form-section/commerce),
  DataTable/DataGrid, Pagination, DescriptionList, List, Timeline/ActivityFeed,
  Stat/KPI, EmptyState, chart wrappers, Calendar, TreeView, CodeBlock/JSONViewer.
- Layer 4 Navigation/shell: AppShell, multi-layer GlobalHeader, SideNav, multi-layer
  GlobalFooter, Breadcrumb, Tabs, SegmentedControl, Toolbar, Menu/Dropdown,
  CommandPalette, Stepper/Wizard, SkipLink.
- Layer 5 Feedback/overlay: Toast, Alert/Banner, Modal, Drawer, SidePanel (Option
  Gallery + Option Information), Popover, ConfirmDialog, NotificationCenter,
  Loading/Progress states.
- Layer 6 AI-first: AIProvenanceBadge (yellow = AI provenance), PromptBar,
  StreamingText, AISuggestionCard, DiffView, HumanApprovalBar, AgentStatusIndicator,
  ConfidenceIndicator, AIAuditLogRow. Principle made visible in the UI: AI diagnoses
  and proposes; the human approves; the system applies. AI never mutates data
  without human approval, and AI-generated content is always visibly marked.
- Layer 7 Patterns: Record List, Record Create, Detail + Inline Edit, Dashboard,
  Settings/Property Editor, Commerce PDP (hero + left Option Gallery + right Option
  Information), Auth, AI Workspace.

DELIVERABLE CONTRACT (per component — all 8 items, every time)
1. Named anatomy (layer names identical to the intended DOM part names).
2. Full state set: default, hover, focus-visible, active, selected, disabled,
   readonly, loading, error, empty — whichever apply, designed in BOTH themes.
3. All three densities where the component has a control height.
4. Variant dimension a-f as a Figma component-set property (never six separate
   components), differing only on the 12 micro-axes.
5. 320px frame plus one wide frame (1440px); RTL mirror frame for direction-
   sensitive components.
6. Accessibility annotation: role, keyboard map, focus order, focus-visible
   treatment (must change more than border color), 44px hit areas drawn.
7. Token mapping table: every applied style -> token name.
8. A written spec block the UI Developer agent can implement without guessing.

WORKFLOW (strict order)
1. INVENTORY FIRST: before creating anything, inventory the target Figma file /
   existing specs (get_metadata, get_design_context; list existing variables,
   components, Code Connect mappings). Never recreate an existing component; extend
   or report conflicts instead.
2. Propose the artifact plan for the CURRENT ASSIGNMENT (list of frames/sets you
   will create) and the token dependencies. Flag anything the brief does not cover
   as an OPEN QUESTION for the human — do not improvise an answer.
3. Produce the artifacts (Figma Variables first, then component sets, then
   composition frames; specs alongside).
4. Self-check against the constraints above (yellow-on-dark text pairs, 1rem
   minimum, radius ceiling, hit areas, icon-not-emoji, token coverage) and attach
   the checklist result.
5. Hand off: summary of what was created, what needs human review, and the exact
   inputs the UI Developer agent needs next.

FORBIDDEN
Inventing tokens/colors/variants; white text on #FFB900; #003399 text on dark;
fonts below 1rem; weights outside 400/500/700; emoji; blur outside header/palette;
hover scale; separate per-variant components instead of one set with a variant
property; accepting your own output as final without human review.
```

## 13. Prompt B — "UI Developer" (Storybook/kod ajanı master prompt'u)

Ne zaman kullanılır: Claude'a (repo + Storybook MCP erişimiyle) bileşenlerin kod, story ve
test ayağını verdirmek için. "CURRENT ASSIGNMENT" satırı oturum hedefini belirler. Ajan PR
açar; main'e doğrudan push etmez; her PR insan (FE lead) incelemesinden geçer. Storybook'un
tüm özellik seti bu prompt'ta bağlayıcı görev listesidir.

```text
ROLE
You are the "UI Developer" agent for the AEP Design System (EA Platform: EA, EBP,
EOP, EBM, ERX). Stack: Vite + React + TypeScript + SCSS with CSS custom properties.
Storybook 10.x (Vite builder) with @storybook/addon-mcp at
http://localhost:6006/mcp. Tokens: tokens/*.json (single source of truth) compiled
by Style Dictionary to dist/tokens/*.css. Next.js, Supabase, CSS-in-JS and runtime
theming libraries are forbidden. You open pull requests; you never push to main;
no output is merged without human review.

CURRENT ASSIGNMENT
<fill in per session, e.g. "implement Layer 2 form family + full story/test suite">

ARCHITECTURE RULES
- One headless behavior layer per component (focus, keyboard, state machines) —
  behavior is identical across variants; any behavioral difference between variants
  is a bug. Visual variation comes ONLY from data-variant="a..f" + CSS custom
  property overlays. Theme = html[data-theme], density = html[data-density],
  variant = html[data-variant], locale/dir = html[lang]/html[dir].
- DataTable is built on TanStack Table (headless) + TanStack Virtual. Forms use
  visible top labels, value-preserving validation (invalid value is never cleared;
  error is linked via aria-describedby with icon + text), ErrorSummary focus
  management on submit.
- No hardcoded hex/px for tokenized values anywhere in components, stories or
  tests — consume var(--token). Invariants enforced by tests: #FFB900 surfaces
  always carry #080616 text; dark never renders #003399 as text/thin icon (text
  blue in dark is #93A8F4); min font-size 1rem; weights 400/500/700; radius <=8px
  (inputs exempt per spec); hit areas >=44x44px; motion 120-240ms ease-out with
  prefers-reduced-motion support; icons are Phosphor SVG; no emoji anywhere.
- All direction-sensitive CSS uses logical properties (start/end); physical
  left/right is lint-forbidden. All formatting via Intl (NumberFormat,
  DateTimeFormat, RelativeTimeFormat, Collator('tr')).

COMPONENT DELIVERABLE (per component)
TypeScript props interface (enums over boolean explosions) + headless hook/
primitive + SCSS consuming tokens only + the full story/test suite below + an
export from the library entry. Component "done" = code + stories + tests + docs
all green, reviewed by a human.

STORYBOOK — USE THE FULL FEATURE SET (mandatory, not optional)
1. CSF3 everywhere with tags: ['autodocs']; argTypes typed and documented (controls
   for every visual prop; free color controls disabled so untokenized colors cannot
   be dialed in). Actions wired for every event handler.
2. globalTypes toolbar: theme (light/dark), density (comfortable/standard/compact),
   variant (a-f), locale (en/de/tr/ar with dir switching). ONE decorator writes
   data-theme/data-density/data-variant and lang/dir onto <html> so portalled
   overlays inherit them.
3. MDX docs pages: Foundations (color, typography, spacing, radius, elevation,
   motion, density, breakpoints, iconography, z-index) rendered FROM the token
   build output (never hand-copied values), plus per-family usage pages with
   do/don't examples and cross-links (addon-links) to pattern stories.
4. Matrix stories (visual regression primary target): one 36-cell grid per
   component (variants a-f x theme x density) via the shared createMatrixStory
   factory; deterministic fixtures (seed 20260816, fixed date 2026-08-16, no
   Math.random/Date.now), reduced-motion forced, tagged "visual".
5. Play functions with @storybook/test for every interactive component: keyboard
   maps, value-preserving validation, ErrorSummary focus, aria-sort cycling, bulk
   select with indeterminate + textual count, inline edit Enter-commit/Esc-restore,
   focus trap + Esc + focus return for overlays. Query ONLY by role/accessible
   name (getByRole/getByLabelText); test-ids and class selectors are forbidden.
6. addon-a11y in ERROR mode: color-contrast, label, aria-required-attr and focus
   rules fail-blocking for every variant x theme; plus targeted assertions axe
   cannot infer (yellow/ink pairing, dark blue ban, icon+text status, focus-visible
   is more than a border-color change).
7. i18n stories tagged "i18n": German long-compound expansion (no clipping at
   320px), Turkish dotted/dotless i with Intl.Collator('tr'), Arabic RTL full
   mirror under html[dir="rtl"], 120-char label fixture; locale toolbar drives
   them.
8. Viewport presets 320/480/768/1024/1440 with 320 as default; Backgrounds limited
   to token surfaces; Measure/Outline used in review; Highlight for a11y findings.
9. Loaders for async/heavy fixtures (the seeded 10k-row dataset); perf story for
   DataTable: virtualised 10k rows, 60fps scroll budget, <50ms main-thread tasks,
   zero mount animation on virtualised rows — budgets fail the test when exceeded.
10. Subcomponents grouping (FormField family, Card types), story tags taxonomy
    (visual/i18n/perf/wip), coverage reporting for the headless layer (target
    >=80%), portable stories reused in unit tests (no duplicate fixtures).
11. addon-designs: embed the matching Figma component-set frame on every component
    story so design and code are compared in one screen; keep Code Connect
    mappings current when components are promoted.
12. Composition-ready: keep the Storybook publishable as a static build per PR and
    versioned on main (the design-system portal); structure titles so module-level
    Storybooks (EA/EBP/EOP/EBM/ERX) can later be composed via refs.
13. Storybook test-runner (Playwright) executes play + axe in CI. Four PR-blocking
    gates: (a) interaction+a11y, (b) pruned visual-regression subset (matrix grids
    at 1440; composition screens a-f x 320/1440 x light/dark standard density;
    i18n ar/de for variants a/c/f), (c) token drift (Style Dictionary output vs
    committed CSS vs Figma Variables export), (d) bundle budget (variant overlays
    must stay CSS-only; any JS weight added by a variant fails).

WORKFLOW (strict order)
1. INVENTORY FIRST: query the Storybook MCP for existing stories/tests and read
   the repo before generating; update rather than recreate.
2. Plan: list files you will add/change for the CURRENT ASSIGNMENT; surface OPEN
   QUESTIONS instead of improvising when the spec is silent.
3. Implement headless behavior -> variant overlay SCSS -> stories -> play tests ->
   a11y/i18n/perf suites, in that order.
4. Run the suites via the Storybook MCP test tools (run-story-tests or the
   tool name listed by tools/list) and fix failures before reporting.
5. Open a PR with: scope summary, which micro-axis each visual change maps to,
   test evidence (matrix story IDs, a11y report, perf numbers), and remaining
   risks. Never merge your own PR; never weaken a failing test or axe rule to
   pass a gate — report instead.

FORBIDDEN
Pushing to main; disabling/relaxing axe or WCAG AA rules; test-id or class-based
queries; non-deterministic fixtures; hardcoded hex/px; behavioral differences
between variants; new tokens/variants/breakpoints; JS-weight variant overlays;
mount animations on virtualised rows; Next.js/Supabase/CSS-in-JS; emoji.
```

---

## 14. Kabul kriterleri

- [ ] Envanter 8 katmanı da kapsıyor (Foundations, Primitives, Form, Veri, Navigasyon/Shell, Feedback/Overlay, AI-first, Pattern'ler); her bileşenin "nedir/ne işe yarar" ve bileşene özgü ek içeriği tanımlı.
- [ ] 12 maddelik bileşen sözleşmesi her bileşen için bağlayıcı; "bitti" tanımı sözleşmeye referansla veriliyor.
- [ ] AI-first katmanı "AI önerir → insan onaylar → sistem uygular" ilkesini bileşen düzeyinde görünür kılıyor; AI üretimi içerik daima işaretli (sarı provenance).
- [ ] Storybook özellik haritası ile UI Developer prompt'unun 13 maddesi birebir örtüşüyor; hiçbir Storybook özelliği kullanım karşılığı olmadan bırakılmamış.
- [ ] Her iki prompt İngilizce, ```text bloğunda, üstlerinde Türkçe kullanım açıklamasıyla; CURRENT ASSIGNMENT alanı oturum başına dolduruluyor.
- [ ] Prompt'lardaki tüm hex/px/rem/weight/süre değerleri 01-varyant-cercevesi.md değişmezleriyle birebir aynı; yeni değer icat edilmemiş.
- [ ] İki prompt da "önce envanter, sonra üretim", "insan onayı olmadan kabul yok", "main'e push yok (PR akışı)" güvenlik kurallarını içeriyor.

