# Figma MCP + Storybook MCP Prompt Seti
## Enterprise SaaS Panel (Form + Data Table) — 2026/2027, vizyon 2030/2035

Bu dosyadaki prompt'lar kopyala-yapıştır kullanım içindir. Her prompt, senin sabit kısıtlarını içine gömülü taşır:

- Font: Roboto, min weight 400, min gövde boyutu 1rem (16px)
- Radius: input alanları hariç max 0.5rem (~8px; senin üst sınırın 12px, güvenli değer 8px)
- Primary: #FFB900 (limon sarısı) — sarı üzerine metin her zaman koyu (#080616), asla beyaz
- Secondary accent: parlement mavisi (#1E3A8A önerisi; kendi hex'inle değiştir)
- Dark background: #080616 ve koyusu
- Mobile first: 320px native başlangıç, sonra fluid adaptive (plus phone / mini tablet / plus tablet / laptop / desktop)
- Emoji yasak, ikon: Phosphor (öncelik) veya FontAwesome, SVG
- Dark/Light çift tema, i18n/l10n/g11n hazır (RTL dahil)

---

## BÖLÜM A — FIGMA MCP PROMPT'LARI

### A1. Design Token Temeli (Figma Variables kurulumu)

```
Figma'da "AEP Design System" adlı dosyada Variables (değişken) koleksiyonları kur.

Koleksiyonlar:
1. "primitive" — ham değerler:
   - color/yellow/500 = #FFB900, color/yellow/600 = #E5A600, color/yellow/700 = #C79000
   - color/blue/700 = #1E3A8A (parlement mavisi), blue/500 = #3B5BD9, blue/300 = #93A8F4
   - color/ink/950 = #080616, ink/900 = #0D0A24, ink/800 = #16123A, ink/50 = #F7F7FB
   - color/semantic ham renkler: success/600 = #15803D, error/600 = #DC2626, warning/600 = #B45309, info/600 = #1D4ED8
   - radius/none = 0, radius/xs = 2, radius/sm = 4, radius/md = 8 (üst sınır; input hariç hiçbir yüzey 12'yi geçmez), radius/input = 4
   - space/1 = 4, space/2 = 8, space/3 = 12, space/4 = 16, space/5 = 24, space/6 = 32, space/7 = 48
   - font/family = Roboto, font/weight/regular = 400, medium = 500, bold = 700 (400 altı weight tanımlama)
   - font/size/body = 16, size/body-lg = 18, size/label = 16, size/caption = 16 (16 altına inme), size/h3 = 20, h2 = 24, h1 = 32

2. "semantic" — iki mode: Light ve Dark. Primitive'lere alias'la:
   - surface/page: Light=ink/50, Dark=ink/950
   - surface/raised: Light=#FFFFFF, Dark=ink/900
   - surface/overlay: Light=#FFFFFF, Dark=ink/800
   - text/primary: Light=ink/950, Dark=#FFFFFF %87 opaklık
   - text/secondary: Light=ink/800 %70, Dark=#FFFFFF %60
   - action/primary/bg = yellow/500 her iki mode
   - action/primary/fg = ink/950 her iki mode (sarı üzerine ASLA beyaz metin)
   - action/secondary = blue/700 (Light), blue/300 (Dark)
   - border/default: Light=#E4E4EE, Dark=#26224A
   - focus/ring = blue/500, 2px, offset 2px

3. "density" — üç mode: comfortable / compact / dense:
   - row-height: 52 / 44 / 36
   - cell-padding-x: 16 / 12 / 8
   - field-height: 48 / 44 / 40

Kural: Hiçbir komponent primitive'e doğrudan bağlanmasın, yalnızca semantic ve density katmanına bağlansın. WCAG AA kontrolü yap: text/primary her surface üzerinde min 4.5:1, action/primary/fg sarı üzerinde min 4.5:1 olduğunu doğrula ve sonuç raporu ver.
```

### A2. Form Bileşen Kiti

```
"AEP Design System" dosyasında Form bileşen kitini oluştur. Auto Layout kullan, tüm değerleri A1'deki semantic ve density variable'larına bağla.

Bileşenler (her biri component set, variant property'leriyle):
1. TextField: state = default / hover / focus / filled / error / disabled / readonly; density = comfortable / compact / dense; leading-icon = boolean; trailing-action = none / clear / reveal.
   - Label her zaman alanın ÜSTÜNDE ve görünür (placeholder asla label yerine kullanılmaz).
   - Label: Roboto 500, 16px. Value: Roboto 400, 16px.
   - Hata mesajı alanın hemen ALTINDA, error/600 renk + Phosphor "warning-circle" ikonu; ikon tek başına anlam taşımasın, metin şart.
   - Helper text slotu: hata yokken görünür, hata gelince yerini hata mesajı alır (yükseklik zıplamasın — alan rezerve).
   - Radius: radius/input (4). Min dokunma yüksekliği 44px.
   - Focus: 2px blue ring, offset 2px; border rengiyle yetinme.
2. Select / Combobox: aynı state seti + open variant; dropdown paneli surface/overlay, radius/md, 1px border, hafif tek yönlü gölge (y=4, blur=16, %12 siyah — dekoratif değil, katman ayrımı için).
3. Checkbox, Radio, Switch: 20px kutu ama 44px dokunma alanı; selected renk = blue/700 (sarı değil — sarı yalnızca primary CTA'ya ayrılır).
4. DatePicker input, SearchField, TextArea, FileUpload (drag-drop + tap-to-browse).
5. FormSection: başlık (h3 20/500) + açıklama + alan grubu; kart yüzeyi surface/raised, radius/md, 1px border. Kart gölgesiz (Flat 2.0: derinlik yalnızca overlay katmanlarında).
6. FormFooter: sticky, sağda primary "Kaydet" (sarı bg, koyu metin), solunda secondary "İptal" (ghost). Mobilde full-width dikey sıralama.

Layout kuralları:
- 320px: tek kolon, alanlar full-width.
- ≥768px: max 2 kolon; ilişkili alanlar (ad/soyad) yan yana, ilişkisizler alt alta.
- ≥1200px: form max genişlik 720px (satır uzunluğu okunabilirlik için sınırlanır; form asla tüm ekrana yayılmaz).

RTL: her bileşenin mirror davranışını Auto Layout direction ile tanımla; ikon-metin sırası ters çevrilebilir olsun.
```

### A3. Data Table Bileşeni

```
"AEP Design System" dosyasında enterprise Data Table component set'ini kur. Tüm ölçüler density variable'larından gelsin.

Yapı:
1. TableHeader hücresi: Roboto 500 16px, text/secondary; sort ikonu (Phosphor caret-up/down) yalnızca hover ve aktif durumda tam opak; sticky.
2. TableRow: yükseklik = density/row-height (52/44/36). Zebra YOK; ayrım 1px border/default + hover'da surface değişimi (Light: ink/50, Dark: ink/900'ün bir ton açığı). Selected satır: blue %8 opak dolgu + solda 2px blue şerit.
3. Hücre tipleri (ayrı component'ler): text, number (sağa hizalı, font-variant: tabular-nums), status-badge (dolgu + metin, asla yalnız renk; radius/sm), user (avatar+isim), date, actions (üç-nokta menü, 44px hedef), checkbox (bulk select).
4. İlk kolon (checkbox + kimlik kolonu) sticky-left; actions kolonu sticky-right.
5. TableToolbar: arama, filtre chip'leri, kolon görünürlük menüsü, density switcher (comfortable/compact/dense), export.
6. BulkActionsBar: satır seçilince toolbar'ın yerine kayar (motion: 200ms ease-out, transform); seçim sayısı + toplu işlemler.
7. States: loading (skeleton satırlar — spinner değil), empty (illüstrasyonsuz sade: başlık + açıklama + primary aksiyon), error, zero-results (filtre temizle aksiyonuyla).
8. Pagination + "satır/sayfa" seçici; alternatif variant: infinite scroll göstergesi.

Responsive dönüşüm (kritik):
- 320–767px: tablo KART LİSTESİNE dönüşür. Her satır = bir kart: üstte kimlik alanı (bold), altında 2-3 anahtar alan label:value çifti olarak, sağ üstte status badge, sağ altta actions. Yatay scroll'lu tabloyu mobilde birincil çözüm yapma; yatay scroll yalnızca kullanıcı "tablo görünümü"nü açıkça seçerse.
- ≥768px: gerçek tablo, yatay overflow'da container içi scroll + sticky kolonlar.

Inline edit variant'ı: hücre çift-tık/enter ile edit moduna girer; kompleks düzenleme için satırdan side-sheet (sağdan panel, surface/overlay, backdrop %40 ink/950) açılır. Side-sheet arkasındaki scrim'de blur KULLANMA (performans); düz yarı saydam katman kullan.
```

### A4. Hi-Fi Ekranlar + Interaktif Prototip

```
Design system bileşenlerini kullanarak şu hi-fi ekranları üret (önce 320px frame, sonra 768 / 1024 / 1440 türevleri):

1. "Kayıt Listesi" ekranı: sol nav (mobilde bottom nav, max 5 öğe), TableToolbar, Data Table (12 satır gerçekçi ama nötr placeholder içerik), pagination.
2. "Kayıt Oluştur" ekranı: 3 FormSection'lı form, sticky FormFooter, bir alanda error state örneği.
3. "Kayıt Detay + Inline Edit": tablo satırından açılan side-sheet düzenleme akışı.

Prototip bağlantıları:
- Satır tık → side-sheet açılışı: smart animate, 240ms ease-out, sağdan slide.
- Density switcher → üç density variant'ı arasında geçiş.
- Dark/Light toggle → semantic variable mode değişimi.
- Form submit → başarılı toast (üstte, 4sn, kapatılabilir) → listeye dönüş.
- prefers-reduced-motion senaryosu için animasyonsuz duplicate flow ekle.

Her ekran için Dark ve Light iki mode'u da üret. Bitince kontrast denetimi yap ve 4.5:1 altında kalan çiftleri listele.
```

---

## BÖLÜM B — STORYBOOK MCP PROMPT'LARI

Not: Storybook tarafında varsayım — stack: Vite + React + TypeScript + SCSS, token kaynağı design-tokens JSON (Figma Variables'tan export). Next.js kullanılmıyor.

### B1. Token Senkronizasyonu ve Tema Altyapısı

```
Storybook 8 projesinde token altyapısını kur:

1. tokens/primitive.json, tokens/semantic.light.json, tokens/semantic.dark.json, tokens/density.json dosyalarını Figma Variables export'undan üret (Style Dictionary ile CSS custom property'lere derle: --surface-page, --action-primary-bg, --row-height...).
2. .storybook/preview.ts içine globalTypes ekle: theme (light/dark) ve density (comfortable/compact/dense). Decorator, html elementine data-theme ve data-density attribute'u bassın; tüm bileşenler yalnızca CSS variable okusun, hex hardcode yasak.
3. Roboto'yu self-host et (Google CDN'e runtime bağımlılık yok), weights: 400/500/700. 400 altı weight bundle'a girmesin.
4. Bir "Tokens" dokümantasyon sayfası üret: renk swatch'leri her iki temada yan yana, kontrast oranları hesaplanmış şekilde; radius ve spacing ölçekleri görsel cetvelle.
5. Viewport addon'una özel breakpoint seti tanımla: 320 (native base), 430 (plus phone), 768 (mini tablet), 1024 (plus tablet/laptop), 1440 (desktop).
```

### B2. Form Bileşen Story'leri

```
Form bileşenleri için story'leri yaz (CSF3, autodocs açık):

Her bileşen (TextField, Select, Checkbox, Radio, Switch, DatePicker, TextArea, FileUpload, FormSection, FormFooter) için:
1. Default, tüm state'ler (hover/focus/filled/error/disabled/readonly) ayrı story.
2. "Matrix" story: state × density × theme kombinasyonlarını tek grid'de gösteren görsel regresyon hedefi.
3. play function ile etkileşim testi: alana yaz → blur → inline validation tetiklenir → hata mesajı role="alert" ile DOM'a girer → düzelt → hata kalkar. Testing-library query'leri role/label üzerinden olsun, test-id son çare.
4. a11y addon (axe) her story'de aktif; şu kuralları fail-blocking yap: color-contrast, label, aria-required-attr, focus-order-semantics.
5. i18n story'leri: aynı form Almanca (uzun kelime taşması), Türkçe ve Arapça (RTL, dir="rtl") locale'lerinde; tarih/sayı alanları Intl API formatlamasıyla.
6. "Long content" story: 120 karakterlik label ve hata mesajıyla layout'un kırılmadığını göster.
```

### B3. Data Table Story'leri ve Performans

```
DataTable bileşeni için story ve test seti:

1. Temel story'ler: 12 satır default; loading (skeleton); empty; error; zero-results.
2. "Dense 10k" story: 10.000 satırlık mock dataset (faker ile üret, story içinde seed sabit) + satır sanallaştırma (TanStack Virtual). Story notunda ölçüm hedefi: scroll sırasında 60fps, ana thread bloğu < 50ms.
3. Etkileşim testleri (play): kolon sırala → sıralamanın aria-sort ile bildirildiğini doğrula; 3 satır seç → BulkActionsBar görünür → bulk delete onay dialog'u; hücre inline edit → Enter kaydet, Esc iptal; klavye ile tam gezinme (tab/ok tuşları) — fare kullanmadan tüm akış tamamlanabilmeli.
4. Responsive story: 320px viewport'ta tablonun kart listesine dönüştüğünü gösteren variant; chromatic modes ile 320/768/1440 üçünde snapshot.
5. RTL story: kolon hizaları, sticky kolonlar ve sort ikonlarının mirror davranışı.
6. Density switcher story: üç density'de row-height'ın token'dan geldiğini görsel olarak kanıtlayan yan yana karşılaştırma.
```

### B4. Kalite Kapıları (CI entegrasyonu)

```
Storybook'u kalite kapısına bağla (CI: GitHub Actions, deploy hedefi Hetzner/Debian):

1. test-runner: tüm play function'lar + axe taraması PR'da çalışır; ihlal varsa PR merge edilemez (insan onayı olmadan main'e otomatik push yok — AI/bot yalnızca PR açar).
2. Chromatic veya Playwright tabanlı görsel regresyon: theme × density × breakpoint matrisi (2×3×3 = 18 snapshot/bileşen); fark çıkarsa insan onayına düşer.
3. Bundle bütçesi: bileşen kütüphanesi tree-shakeable; tek bileşen import maliyeti raporlanır.
4. Token drift kontrolü: Figma export JSON'u ile repo'daki tokens/*.json diff'lenir; fark varsa CI uyarı verir — otomatik güncelleme yapmaz, PR önerir.
```

---

## Prompt kullanım sırası

1. A1 → Figma token temeli (her şeyin bağlandığı yer)
2. A2 + A3 → bileşenler
3. B1 → Storybook token senkronu (Figma export'u hazır olunca)
4. A4 ↔ B2/B3 paralel → tasarım ve kod story'leri birbirini doğrular
5. B4 → kalite kapıları en son, süreç oturunca

2030/2035 hazırlığı için değişmez ilke: stil katmanı (Flat 2.0 bugün) tamamen token + CSS variable üzerinden gitsin, davranış katmanı headless kalsın. Böylece görsel dil değişse bile form/tablo mantığı ve AI-ajan entegrasyonu (bileşenlerin makine-okur state'leri) yeniden yazılmaz.
