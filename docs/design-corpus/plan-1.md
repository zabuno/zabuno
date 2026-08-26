# Yalnız Doküman ve Yönerge Üretim Planı

## 1. Kısa durum

Yalnız Markdown dokümanları hazırlanacak. Yönergeler yazılacak fakat uygulanmayacak.

Kesin kapsam dışı:

- Storybook kurulumu veya çalıştırılması
- MCP kurulumu, bağlantısı veya araç çağrısı
- React/component kodu
- Story/story testleri
- Tasarım veya prototip
- Figma
- Paket kurulumu
- Git işlemi
- Tarayıcı testi, build, deploy veya yayınlama

Hedef klasör:

`~/DEV/AI First EA (APE-EAP)/frontend/claudeui`

## 2. Oluşturulacak dokümanlar

### `README.md`

- Doküman paketinin amacı.
- Okuma ve kullanım sırası.
- “Bu paket uygulanmış ürün değildir” uyarısı.
- Diğer dokümanlara bağlantılar.

### `docs/00-istem-kapsam-ve-kaynak-siniri.md`

Kullanıcının bağlayıcı istemini tek kanonik gereksinim halinde belgeler:

- Card, form elementleri, table ve ortak component aileleri.
- Ortak tasarım felsefesi üzerinde A–F ince taneli UI varyantları.
- Bütün yüzeylerde enterprise-glass yaklaşımı.
- Modal açıldığında cool-grey overlay ve `0.125rem` Gaussian blur.
- REM-first fluid ölçü disiplini.
- Storybook MCP için kopyala-yapıştır prompt serisi.
- Figma’nın iptal edildiği.
- Yönergelerin bu görevde uygulanmayacağı.

Ekli belgeler yalnız araştırma kaynağı kabul edilir. İçerikleri `ADOPTED`, `MODIFIED`, `SUPERSEDED`, `REJECTED` veya `REFERENCE_ONLY` olarak sınıflandırılır.

### `docs/01-derin-arastirma-storybook-mcp.md`

Güncel resmî kaynaklara dayalı araştırma raporu:

- Storybook AI ve MCP’nin güncel preview durumu.
- React ve Vite kapsamı.
- Development, docs ve test toolset’leri.
- Components/docs manifests.
- Agentic setup.
- Yerel ve paylaşılan MCP ayrımı.
- Güvenlik ve private-by-default yaklaşımı.
- Story yazımında “ne” kadar “neden” bilgisinin önemi.
- API drift ve sürüm doğrulama riski.

Her araştırma kaydı şu alanları taşır:

- Kaynak
- Erişim tarihi
- Doğrulanan iddia
- Stable/preview durumu
- Yönergeye etkisi

### `docs/02-enterprise-glass-ui-varyant-yonergesi.md`

Ortak tasarım anayasası ve A–F kataloğu:

- Task-first, typography-first, content-first, data-dense ve accessible yaklaşım.
- Sabit renk, tipografi, anatomy, state ve davranış kuralları.
- REM-first uzunluklar; fluid düzen için `%`, `fr`, `dvh` ve birimsiz değerler.
- Glass görünümünün bütün yüzeylerde bulunması.
- Gerçek blur’un yalnız composition root seviyesinde uygulanması.
- Tablo hücresi ve satır başına blur yasağı.
- Reduced-transparency ve forced-colors fallback’i.
- Modal altında değişmez cool-grey scrim + `0.125rem` blur.

A–F profilleri:

| Kod | Profil | Ana fark |
|---|---|---|
| A | Crystal Precision | Daha keskin ve düşük efektli |
| B | Frost Analytical | Cool-grey ve veri ayraçları güçlü |
| C | Quiet Editorial | Daha sakin ve düşük görsel gürültülü |
| D | Layered Modular | Katman seviyeleri daha görünür |
| E | Luminous Liquid | Optik ışık ve derinlik en güçlü |
| F | Executive Balance | Kontrast ve vurgu dengeli |

Varyantlar component API’sini, görev akışını, içeriği veya erişilebilirlik davranışını değiştiremez.

### `docs/03-card-form-table-component-kurallari.md`

Yalnız davranış ve tasarım yönergeleri:

- Card anatomy, nesting ve interactive-card sınırları.
- Form label, helper, validation, error, readonly, disabled ve loading.
- DataTable/DataGrid karar ağacı.
- Sorting, filtering, selection, bulk actions ve pagination.
- Mobilde table → kayıt listesi dönüşümü.
- Dialog, drawer, sheet, popover, menu, tooltip ve toast.
- Keyboard, focus, RTL, uzun metin ve localized content.
- A–F varyantlarının her component ailesine nasıl uygulanacağı.

Her component bölümü şu şablonu kullanır:

1. Amaç  
2. Anatomy  
3. Değişmez davranış  
4. A–F görsel değişkenleri  
5. State’ler  
6. Responsive kurallar  
7. Accessibility kuralları  
8. Yapılmaması gerekenler  

### `docs/04-storybook-mcp-prompt-serisi.md`

Tek dosyada sıralı ve kopyala-yapıştır prompt’lar:

1. Master gereksinim sözleşmesi
2. Repo ve component envanteri
3. Storybook mevcut durum analizi
4. Storybook MCP yetenek doğrulaması
5. Token ve REM yönergesi
6. Enterprise-glass katman sistemi
7. Card component ailesi
8. Form component ailesi
9. Table/DataGrid ailesi
10. Modal, drawer ve overlay ailesi
11. A–F karşılaştırma story’leri
12. Responsive, RTL ve localization
13. Accessibility ve interaction
14. Performance ve visual regression
15. Manifest ve MCP documentation kalitesi
16. Final audit ve handoff

Her prompt yalnız gelecekte kullanılacak yönergedir ve şu alanları içerir:

- Amaç
- Girdi ve yetkili kaynaklar
- Zorunlu MCP sorguları
- İzinli kapsam
- Non-goals
- Beklenen çıktı
- Acceptance criteria
- Stop/fail-closed koşulları
- Handoff formatı

Dokümanda açık uyarı bulunur:

> Bu prompt bu görev sırasında çalıştırılmayacaktır. Kod, config, component, story veya test üretme yetkisi vermez.

### `docs/05-dokuman-kalite-kontrol-listesi.md`

Yalnız doküman paketini doğrulayan kontrol listesi:

- Markdown ve iç bağlantı bütünlüğü.
- Birincil kaynak izlenebilirliği.
- Ek belgeler ile kullanıcı talebinin ayrılması.
- Figma uygulama yönergesi bulunmaması.
- Yasaklı uzunluk birimlerinin normatif kurallarda bulunmaması.
- A–F matrisinin card, form, table ve overlay’i kapsaması.
- Cool-grey overlay ve `0.125rem` blur kuralının tutarlı olması.
- Hiçbir bölümün uygulanmış ürün, test veya MCP bağlantısı iddia etmemesi.

## 3. Uygulama sırası

1. İstem ve kaynak sınırı yazılır.
2. Resmî Storybook MCP araştırması belgelenir.
3. Ortak tasarım anayasası ve A–F varyantları tanımlanır.
4. Component yönergeleri yazılır.
5. Storybook MCP prompt serisi hazırlanır.
6. Doküman kalite kontrol listesi ve README tamamlanır.

## 4. Risk ve varsayımlar

- Bu paketin çıktısı çalışan Storybook değildir.
- Prompt’ların yazılmış olması MCP’nin kurulu veya erişilebilir olduğu anlamına gelmez.
- Storybook MCP preview API’si değişebileceği için prompt’lar sürüm uydurmaz; gelecekte kullanım anında resmî belgelerin yeniden doğrulanmasını ister.
- `claudeui` bağımsız Git deposu değildir; doküman görevi Git işlemi içermez.
- Mevcut `adsız klasör` değiştirilmez.
- Managed yetki sözleşmesi gereği kalıcı Markdown dosyalarını kapılı Claude writer oluşturur; Codex MASTER kapsamı ve sonucu doğrular.

## 5. Rollback

- Yalnız `README.md` ve `docs/` yeni kapsamdır.
- Başka dosya veya klasör değiştirilmez.
- Doküman manifesti tutulur.
- Geri dönüş gerektiğinde üretilen paket silinmeden karantina klasörüne taşınır.

## 6. MASTER Nihai Kararı

Kapsam yalnız doküman ve yönerge üretimidir. Storybook MCP prompt’ları hazırlanacak fakat çalıştırılmayacak; hiçbir kodlama, tasarım, kurulum, test veya entegrasyon yapılmayacaktır.
