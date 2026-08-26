# Net cevap

**Ant Design’ın üzerine Bootstrap, Bulma veya başka bir tam CSS framework bindirmeyin.**

Doğru yapı şudur:

```text
Ant Design
= davranış + erişilebilirlik + enterprise bileşen motoru

Sizin Design System’iniz
= renk + tipografi + yoğunluk + radius + yüzey + gölge + sayfa kalıpları

CSS Modules veya isteğe bağlı Tailwind
= uygulama kabuğu + responsive layout + özel bileşenler

Custom React bileşenleri
= markayı taşıyan özel yüzeyler
```

Ant Design, Tailwind gibi sınırsız bir CSS üretim sistemi değildir. Ancak yalnızca birkaç renk değiştirebildiğiniz kapalı bir sistem de değildir.

**AntD’nin esnekliği şu şekildedir:**

| Değişiklik türü                                 |         AntD esnekliği |
| ----------------------------------------------- | ---------------------: |
| Renk, tipografi, radius, kontrol yüksekliği     |             Çok yüksek |
| Dark/light, compact/comfortable                 |             Çok yüksek |
| Button, Table, Card gibi tekil bileşen görünümü |                 Yüksek |
| Bileşenin iç bölümlerini biçimlendirme          |            Orta-yüksek |
| Sayfa kompozisyonu ve layout                    | AntD dışında yapılmalı |
| Bileşenin DOM yapısını tamamen değiştirme       |                  Düşük |
| Radikal biçimde özgün ürün deneyimi             | Custom bileşen gerekir |

Güncel resmî dokümantasyon Ant Design 6.6.0’ı gösteriyor. AntD 6, CSS değişkenlerini varsayılan olarak kullanıyor ve React 18 veya üzerini gerektiriyor. Yani eski AntD sürümlerine göre tema üretme ve farklı sistemlerle birlikte çalışma kapasitesi oldukça gelişmiş durumda. ([Ant Design][1])

---

# Ant Design neden estetik olarak zayıf görünüyor?

Sorun genellikle AntD’nin kendisi değil, aşağıdaki kullanım biçimidir:

```text
AntD kur
→ Default mavi rengi bırak
→ Her yere Card koy
→ Form.Item kullan
→ Table kullan
→ Sidebar ekle
→ Ürün hazır
```

Bu durumda sonuç kaçınılmaz olarak:

* jenerik,
* Çin menşeli yönetim paneli hissi veren,
* fazla beyaz,
* fazla gri,
* hiyerarşisi zayıf,
* birbirine benzeyen kartlarla dolu,
* teknik olarak iyi fakat marka karakteri olmayan

bir ürün olur.

AntD demo ekranları bir **ürün tasarım şablonu** değil, bileşen yeteneklerinin gösterimidir. Demoları sayfa tasarımı olarak kopyalamak, estetik zayıflığın temel nedenlerinden biridir.

---

# Bilinmeyen bilinmeyen: İhtiyacınız olan şey başka CSS framework değil

Eksik olan katman şu:

## Product Design System

```text
Brand identity
      ↓
Semantic design tokens
      ↓
Ant Design theme adapter
      ↓
Product component recipes
      ↓
Page patterns
      ↓
Custom brand surfaces
```

Sizin yüklediğiniz mimari değerlendirmede de doğru yön zaten tarif edilmiş:

* `color.primary`
* `color.danger`
* `spacing.compact`
* `radius.control`
* `density.table`

gibi vendor-independent semantik token’lar tutulmalı; `react-antd` renderer bunları Ant Design token’larına dönüştürmeli. Standart CRUD yüzeylerinde AntD kullanılmalı, marka ve deneyim açısından özel yüzeyler ise `custom` renderer üzerinden geliştirilmelidir.

Yani estetik çözüm:

```text
AntD + Bootstrap
```

değil;

```text
AntD + size ait design system
```

olmalıdır.

---

# AntD hangi seviyelerde özelleştirilebilir?

## 1. Global design token’ları

AntD’nin genel görsel karakterini değiştirir:

* marka rengi,
* font ailesi,
* temel font boyutu,
* yazı ağırlığı,
* sayfa arka planı,
* container arka planı,
* metin renkleri,
* border renkleri,
* radius,
* kontrol yüksekliği,
* gölgeler,
* focus görünümü,
* animasyonlar.

AntD resmî tema sistemi global token, component token ve algoritma katmanlarını destekliyor. Ayrıca dark, default ve compact algoritmaları birlikte veya ayrı kullanılabiliyor. ([Ant Design][2])

## 2. Component token’ları

Her bileşeni ayrı biçimlendirebilirsiniz.

Örneğin Table için:

* header arka planı,
* header metin rengi,
* satır hover rengi,
* seçili satır rengi,
* hücre padding değerleri,
* border rengi,
* header radius,
* font boyutu

ayrı ayrı düzenlenebilir. AntD Table’ın yalnızca hücre padding’i için bile large, medium ve small seviyelerinde ayrı token’ları vardır. ([Ant Design][3])

Card için:

* body padding,
* header padding,
* header yüksekliği,
* header arka planı,
* header font boyutu,
* action alanı

özelleştirilebilir. ([Ant Design][4])

## 3. Semantic DOM styling

AntD 6 ile bileşenlerin anlamlı iç bölümlerine `classNames` ve `styles` üzerinden erişim genişledi.

Örneğin Button:

```text
root
icon
content
```

gibi bölümler üzerinden biçimlendirilebiliyor. Bu, kırılgan `.ant-btn > span:first-child` türü selector yazma ihtiyacını azaltır. ([Ant Design][5])

## 4. Wrapper ve recipe bileşenleri

Asıl estetik güç burada oluşur.

Uygulamanızda doğrudan şunu kullanmamalısınız:

```tsx
import { Button, Card, Table } from 'antd';
```

Bunun yerine:

```tsx
import {
  AppButton,
  AppPanel,
  AppDataTable,
} from '@metaframer/ui';
```

kullanılmalıdır.

`AppButton`, içeride AntD Button kullanır fakat şunları standartlaştırır:

* hangi variant’ın nerede kullanılacağı,
* hangi ölçünün varsayılan olduğu,
* loading davranışı,
* yazı ağırlığı,
* primary/secondary/danger hiyerarşisi,
* mobilde tam genişlik davranışı,
* icon-only kullanım kuralları,
* tooltip gereksinimi.

Bu katman kurulmadan yalnız token değiştirerek güçlü bir SaaS estetiği elde edemezsiniz.

---

# Tailwind gerekli mi?

## Tailwind zorunlu değil

Benim varsayılan önerim:

```text
Ant Design theme tokens
+
CSS Modules
+
Product wrapper components
```

Bu kombinasyon daha kontrollü ve daha düşük karmaşıklıklıdır.

## Tailwind ne zaman eklenebilir?

Tailwind şu alanlarda yardımcı olabilir:

* uygulama kabuğu,
* responsive grid,
* sidebar ve header yerleşimi,
* sayfa spacing’i,
* özel dashboard widget’ları,
* landing veya portal yüzeyleri,
* AntD dışında geliştirilen özel bileşenler,
* mobile-first görünüm değişimleri.

Örneğin:

```tsx
<div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
  <section className="xl:col-span-8">
    <RevenuePanel />
  </section>

  <aside className="xl:col-span-4">
    <ActivityPanel />
  </aside>
</div>
```

Bu doğru kullanımdır.

Aşağıdaki ise yanlış kullanımdır:

```tsx
<Button className="!h-10 !rounded-xl !border-gray-300 !bg-white !px-5">
  Kaydet
</Button>
```

Her AntD bileşenini Tailwind sınıflarıyla tekrar tekrar düzeltmeye başladığınız anda iki ayrı tasarım sistemi oluşur:

```text
AntD token sistemi
+
Tailwind token sistemi
+
manuel override’lar
```

Bunun sonucu zamanla tutarsızlaşır.

AntD, Tailwind ile resmî olarak birlikte çalışabiliyor. AntD’nin selector ağırlığı `@layer` ile düşürülebiliyor ve Tailwind katmanlarıyla sıralanabiliyor. Ancak bu, Tailwind’in AntD’yi yeniden boyaması gerektiği anlamına gelmez; yalnızca birlikte güvenli çalışabilecekleri anlamına gelir. ([Ant Design][1])

Tailwind kullanılacaksa yapı şöyle olmalıdır:

```text
AntD token’ları
→ AntD bileşenlerinin görünümü

Tailwind
→ layout, responsive ve custom surface

CSS Modules
→ ürün bileşenlerinin özel ayrıntıları
```

---

# Bootstrap neden bindirilmemeli?

Bootstrap ile AntD aynı sorunları ayrı ayrı çözmeye çalışır:

* button,
* input,
* modal,
* dropdown,
* form,
* spacing,
* grid,
* typography,
* reset,
* breakpoint,
* z-index.

İkisini aynı projede görsel sistem olarak kullanırsanız:

* iki farklı Button,
* iki farklı Modal,
* iki farklı form dili,
* iki farklı radius ölçeği,
* iki farklı spacing ölçeği,
* farklı focus davranışları,
* CSS specificity sorunları,
* reset çakışmaları

oluşur.

Bootstrap yalnızca çok sınırlı, izole bir legacy bölüm için mevcutsa birlikte tutulabilir. Yeni bir SaaS panelinin tasarım sistemi olarak AntD’nin üzerine eklenmemelidir.

---

# Güçlü SaaS görünümü için önerdiğim estetik yön

Sizin ürününüz için en uygun yaklaşım:

## Quiet Enterprise / Premium Data-Dense

Ne aşırı süslü ne de klasik ERP kadar kuru.

### Tipografi

Roboto tercihiniz korunabilir:

```text
Body: 14 px / 400
Label: 13–14 px / 500
Button: 14 px / 500
Table header: 12–13 px / 500 veya 600
Section title: 16 px / 600
Page title: 22–24 px / 600
Metric: 24–32 px / 600
```

En önemli konu yalnız font ailesi değil, ağırlık ve hiyerarşidir. AntD varsayılanını bırakıp tüm başlıkları aynı görsel ağırlıkta kullanmak paneli düzleştirir. AntD’nin kendi tipografi sistemi de font ailesi, temel boyut, ölçek, satır yüksekliği, ağırlık ve renk katmanlarının birlikte ele alınmasını öneriyor. ([Ant Design][6])

### Yüzeyler

```text
Page background: çok açık soğuk gri
Main surface: beyaz
Elevated surface: beyaz veya hafif açık ton
Border: düşük kontrastlı gri
Primary accent: tek güçlü marka rengi
```

Her alanı gölgeyle ayırmayın.

```text
Sayfa bölümleri → spacing
Container sınırları → 1 px border
Dropdown / Modal / Popover → shadow
Önemli seçili durum → fill veya accent
```

Bu yaklaşım paneli daha modern ve daha sakin gösterir.

### Radius

```text
Input / Button: 7–8 px
Card / Drawer iç yüzeyi: 10–12 px
Modal: 12–16 px
Tag: 5–6 px
```

Her şeyi 16–24 px yuvarlamak data-dense enterprise ürünlerde oyuncak hissi oluşturabilir.

### Kontrol yüksekliği

```text
Dense desktop: 32 px
Standart desktop: 36 px
Comfortable: 40 px
Mobile touch: 44–48 px
```

AntD’nin `compactAlgorithm` seçeneği vardır; fakat bütün ürünü sürekli compact yapmak yerine ekran veya kullanıcı tercihi bazlı yoğunluk profilleri oluşturmak daha doğrudur. ([Ant Design][2])

---

# Örnek AntD SaaS theme

Aşağıdaki başlangıç teması, varsayılan AntD görünümünü daha modern, sakin ve premium bir SaaS yönüne çeker:

```tsx
// saas-theme.ts
import type { ThemeConfig } from 'antd';

export const saasTheme: ThemeConfig = {
  token: {
    colorPrimary: '#4F46E5',

    colorBgLayout: '#F6F7FB',
    colorBgContainer: '#FFFFFF',
    colorBgElevated: '#FFFFFF',

    colorText: '#111827',
    colorTextSecondary: '#4B5563',

    colorBorder: '#D8DDE6',
    colorBorderSecondary: '#E7EAF0',

    fontFamily:
      '"Roboto", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    fontSize: 14,
    fontWeightStrong: 600,

    borderRadius: 8,
    borderRadiusLG: 12,

    controlHeight: 36,
    controlHeightLG: 44,

    lineWidth: 1,

    boxShadowSecondary:
      '0 12px 32px rgba(15, 23, 42, 0.12)',
  },

  components: {
    Button: {
      fontWeight: 500,
      paddingInline: 14,
      primaryShadow: 'none',
      defaultShadow: 'none',
    },

    Card: {
      bodyPadding: 20,
      headerPadding: 20,
      headerHeight: 52,
      headerBg: 'transparent',
    },

    Table: {
      headerBg: '#F8FAFC',
      headerColor: '#374151',
      borderColor: '#E5E7EB',

      rowHoverBg: '#F8FAFC',
      rowSelectedBg: '#EEF2FF',
      rowSelectedHoverBg: '#E0E7FF',

      cellPaddingBlockMD: 10,
      cellPaddingInlineMD: 12,

      headerBorderRadius: 0,
    },
  },
};
```

Uygulama:

```tsx
import { ConfigProvider } from 'antd';
import { saasTheme } from './saas-theme';

export function Application() {
  return (
    <ConfigProvider
      theme={saasTheme}
      componentSize="medium"
      variant="filled"
    >
      <App />
    </ConfigProvider>
  );
}
```

`filled`, daha yumuşak ve modern veri giriş yüzeyleri için kullanılabilir. Daha klasik enterprise görünümü istenirse `outlined` tercih edilir. ConfigProvider, veri giriş bileşenleri için global `outlined`, `filled` ve `borderless` varyantlarını destekliyor. ([Ant Design][7])

Dark mode için aynı token haritası AntD’nin `darkAlgorithm` algoritmasına bağlanabilir. Tema sistemi dinamik ve nested theme kullanımını da destekliyor. ([Ant Design][2])

---

# Tailwind ile kullanılacaksa doğru kurulum

AntD’nin üzerine Tailwind zorla bindirmek yerine CSS katmanlarını açıkça ayırın:

```tsx
import { StyleProvider } from '@ant-design/cssinjs';
import { ConfigProvider } from 'antd';

export function Providers({ children }: React.PropsWithChildren) {
  return (
    <StyleProvider layer>
      <ConfigProvider theme={saasTheme}>
        {children}
      </ConfigProvider>
    </StyleProvider>
  );
}
```

Tailwind v4 global CSS:

```css
@layer theme, base, antd, components, utilities;

@import "tailwindcss";
```

AntD resmî dokümantasyonu üçüncü taraf stil sistemleriyle birlikte kullanımda `@layer` yaklaşımını öneriyor ve Tailwind v4 için aynı katman sıralamasını gösteriyor. ([Ant Design][1])

Ancak bunu yalnız şu amaçlarla kullanın:

```text
Tailwind
├── app shell
├── responsive layout
├── custom dashboard
├── portal
├── landing
└── custom widgets

AntD tokens
├── Button
├── Input
├── Select
├── Table
├── Modal
├── Drawer
└── Form controls
```

---

# Estetiği asıl güçlendirecek component recipe katmanı

Şu ürün bileşenlerini oluşturmanızı öneririm:

```text
AppShell
Page
PageHeader
PageActions
PageToolbar
FilterBar
SearchToolbar
FormSection
FormFooter
DataPanel
DataTable
MetricPanel
DetailPanel
SidePanel
EmptyState
ErrorState
PermissionState
StatusBadge
DensitySwitcher
```

Örneğin ham AntD Card yerine:

```tsx
<DataPanel
  title="Siparişler"
  description="Son 30 günlük sipariş hareketleri"
  toolbar={<OrderFilters />}
>
  <OrderTable />
</DataPanel>
```

Bu bileşen şu kuralları merkezi olarak uygular:

* başlık ölçüsü,
* açıklama rengi,
* toolbar hizası,
* mobil davranış,
* border,
* padding,
* loading,
* empty state,
* error state,
* header/body ayrımı.

Bu sayede ürün “AntD kullanan bir uygulama” değil, AntD üzerinde çalışan kendi tasarım sisteminiz haline gelir.

---

# Table estetiği nasıl güçlendirilir?

AntD Table tek başına bırakıldığında kolayca jenerik görünür. Şu kompozisyon uygulanmalı:

```text
Table Surface
├── Context header
│   ├── Başlık
│   ├── Sonuç sayısı
│   └── Ana aksiyon
├── Filter toolbar
│   ├── Arama
│   ├── hızlı filtreler
│   ├── gelişmiş filtre
│   └── görünüm / yoğunluk
├── Active filter summary
├── Table
├── Bulk action bar
└── Pagination / result summary
```

Görsel kurallar:

* Table header çok koyu olmamalı.
* Header metni body’den biraz küçük fakat daha güçlü olmalı.
* Her kolon çizgiyle ayrılmamalı.
* Row hover fark edilir fakat baskın olmamalı.
* Durum alanları yalnız renge dayanmamalı.
* Ana aksiyon table içinde sürekli tekrarlanmamalı.
* Satır aksiyonları düşük vurgulu tutulmalı.
* Sayısal kolonlar sağa hizalanmalı.
* Kolon genişlikleri veri tipine göre sabitlenmeli.
* Kullanıcıya compact ve comfortable yoğunluk seçenekleri verilmeli.

Estetik kaliteyi çoğunlukla `headerBg` değiştirmek değil, bu bütünsel tablo kompozisyonu üretir.

---

# Form estetiği nasıl güçlendirilir?

Ham `Form.Item` dizisi yerine:

```text
Form Page
├── Page context
├── Form section
│   ├── Section title
│   ├── Section description
│   └── Related fields
├── Form section
├── Conditional section
└── Sticky action footer
```

Kural seti:

* 30 alan tek bir beyaz kutuda gösterilmemeli.
* Alanlar iş bağlamına göre bölümlenmeli.
* Label ve yardımcı metin hiyerarşisi ayrılmalı.
* Zorunlu alan işareti görsel gürültüye dönüşmemeli.
* Hata mesajları yalnız submit sonrasında topluca patlamamalı.
* Desktop iki kolon, mobile tek kolon olmalı.
* Uzun formlarda sticky footer kullanılmalı.
* Drawer form yalnız kısa görevlerde kullanılmalı.
* Ana kayıt ekranı drawer içine sıkıştırılmamalı.

Bu kurallar CSS framework’ten bağımsız olarak estetiği belirler.

---

# Hangi alanlar custom olmalı?

AntD’yi her yüzeye zorlamayın.

## AntD ile kalması gerekenler

* standart CRUD,
* filtreler,
* tarih seçiciler,
* seçim bileşenleri,
* modal ve drawer,
* standart listeler,
* standart tablolar,
* basit detay ekranları,
* yönetim formları.

## Custom geliştirilmesi gerekenler

* ana uygulama kabuğu,
* dashboard metric sistemleri,
* veri görselleştirme kompozisyonu,
* AI assistant yüzeyi,
* onboarding,
* empty state,
* komut paleti,
* özel workflow ekranları,
* kanban,
* process designer,
* mobil table alternatifi,
* marka-özel müşteri portalı,
* storefront,
* gerçek zamanlı operasyon ekranı.

Bu ayrım, yüklediğiniz mimarideki `projected + Ant Design` ve `custom renderer` ayrımıyla tam olarak örtüşüyor.

---

# Ant Design Pro veya ProComponents çözüm mü?

Kısmen.

ProComponents şu tür yapıları hızlandırır:

* ProLayout,
* ProTable,
* ProForm,
* ProList,
* ProCard.

Resmî dokümantasyonda ProLayout’un menü ve breadcrumb düzenini, ProTable’ın tablo ve request akışını, ProForm’un ise form düzenini soyutladığı belirtiliyor. ([ProComponents][8])

Ancak:

```text
ProComponents = geliştirme hızı
ProComponents ≠ güçlü marka estetiği
```

Ham biçimde kullanılırsa AntD görünümünü daha da baskın hale getirebilir. Bu nedenle ProComponents da sizin wrapper katmanınızın arkasında bulunmalıdır.

---

# Önerdiğim nihai teknoloji yapısı

```text
React + TypeScript + Vite
        ↓
Ant Design 6
        ↓
Vendor-neutral semantic tokens
        ↓
AntD theme adapter
        ↓
Product UI wrapper library
        ↓
CSS Modules
        ↓
İsteğe bağlı Tailwind
yalnız layout ve custom surface için
        ↓
Storybook
        ↓
Playwright visual regression
```

Paket yapısı:

```text
packages/
  design-tokens/
    semantic-tokens.ts
    light-theme.ts
    dark-theme.ts
    density.ts

  renderer-antd/
    theme-adapter.ts
    config-provider.tsx

  ui-core/
    app-button.tsx
    app-input.tsx
    app-select.tsx
    status-badge.tsx

  ui-data/
    data-table.tsx
    filter-bar.tsx
    bulk-action-bar.tsx

  ui-layout/
    app-shell.tsx
    page.tsx
    page-header.tsx
    data-panel.tsx

  ui-forms/
    form-section.tsx
    form-footer.tsx
    field-feedback.tsx
```

---

# Uygulama sırası

1. Önce “premium data-dense enterprise” görsel yönü sabitlenir.
2. Vendor-neutral light/dark semantic token seti yazılır.
3. Semantic token’lar AntD global ve component token’larına eşlenir.
4. Ham AntD kullanımını engelleyen wrapper paketi oluşturulur.
5. Altı golden screen hazırlanır:

   * dashboard,
   * liste/table,
   * form,
   * detail,
   * drawer workflow,
   * mobile görünüm.
6. Compact, comfortable, light ve dark varyantları hazırlanır.
7. Storybook visual regression ile görünüm korunur.
8. Yalnız marka taşıyan yüzeyler custom geliştirilir.

---

# OpenClaw + n8n doğrulama senaryosu

OpenClaw’a aynı golden screen setini üç ayrı görsel yönde ürettirebilirsiniz:

```text
A: Quiet Enterprise
B: High-Contrast Data Dense
C: Soft Premium SaaS
```

n8n akışı:

```text
Git push
→ Storybook build
→ Playwright screenshot
→ light/dark/mobile/desktop varyantları
→ visual diff
→ axe accessibility testi
→ CSS ve bundle ölçümü
→ sonuçları GitHub issue veya rapora yaz
```

Böylece “hangisi daha güzel?” tartışması yalnız kişisel kanaate kalmaz. Tutarlılık, erişilebilirlik, responsive davranış ve geliştirme maliyeti birlikte ölçülür.

# Nihai karar

**Bootstrap veya başka bir tam CSS framework eklemeyin.**

Ant Design’ı şu şekilde kullanın:

```text
AntD
= component engine

Semantic tokens
= görsel temel

Wrapper components
= ürün dili

Page patterns
= SaaS estetiği

Custom surfaces
= marka farklılaşması

Tailwind
= isteğe bağlı layout aracı
```

En doğru başlangıç kombinasyonu:

```text
Ant Design 6
+ ConfigProvider theme
+ component tokens
+ semantic DOM styling
+ CSS Modules
+ ürününüze ait wrapper UI library
```

Tailwind ancak responsive layout ve custom yüzey ihtiyacı belirginleştiğinde eklenmelidir. Güçlü estetik, başka bir framework bindirmekten değil, **AntD’nin üzerinde kontrollü bir MetaFramer Design System kurmaktan** gelecektir.

[1]: https://ant.design/docs/react/compatible-style/ "CSS Compatible - Ant Design"
[2]: https://ant.design/docs/react/customize-theme/ "Customize Theme - Ant Design"
[3]: https://ant.design/components/table/ "Table - Ant Design"
[4]: https://ant.design/components/card/?utm_source=chatgpt.com "Card"
[5]: https://ant.design/components/button/?utm_source=chatgpt.com "Button"
[6]: https://ant.design/docs/spec/font/?utm_source=chatgpt.com "Font"
[7]: https://ant.design/components/config-provider/ "ConfigProvider - Ant Design"
[8]: https://procomponents.ant.design/en-US/components/?utm_source=chatgpt.com "Component Overview - ProComponents - Ant Design"
