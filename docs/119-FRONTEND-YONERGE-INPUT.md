<!--
    SAHİBİN VERDİĞİ GİRDİ — 2026-09-04. Bu dosya bir KAYNAKTIR, bir karar
    belgesi DEĞİLDİR ve olduğu gibi korunur.

    Depoya kopyalandı çünkü `docs/106` (site haritası) için geçerli olan
    gerekçenin aynısı burada da geçerli: kaynak `~/Downloads` altında
    kalsaydı, hangi girdiden hangi kararın çıktığı bir daha izlenemezdi.
    İkiz girdilerden yalnız birinin kopyalanmış olması bir eksiklikti.

    ÖNEMLİ — BU BELGE KISMEN GEÇERSİZDİR.

    Sahibin 2026-09-05 kararı: "bu dosyaları, bugün verdiğimiz kararları
    master (değişmez) karar sayarak ezeceğiz, güncelleyeceğiz. Kararlarımızı
    bu dosyaların değiştirmesine izin vermeyeceğiz."

    Hangi maddesinin ezildiği ve NEDEN ezildiği `docs/118`de madde madde
    yazılıdır. Çelişkide sıra: `docs/118` > `docs/105` > bu dosya.

    Bu dosya DÜZENLENMEZ. Bir girdiyi sonradan düzeltmek, kararın hangi
    girdiden çıktığını gizlemek olurdu.
-->

# Zabuno.com Frontend, Yayınlama, SEO ve Çeviri Uygulama Yönergesi

Bu belge Claude Code, Cursor, Windsurf veya başka bir coding agent tarafından doğrudan uygulanacak teknik ve ürün yönergesidir.

Birlikte kullanılacak ana girdi:

- `zabuno-com-tam-site-haritasi.md`

Bu yönerge, site haritasındaki bütün canonical sayfaların iskeletini üretmek; henüz tamamlanmamış sayfaları tek bir yaratıcı hazırlanıyor bileşeniyle yönetmek; sayfaları sırayla yayına almak; Laravel tabanlı içerik ve çeviri altyapısını kurmak; arama, cevap ve yapay zekâ sistemlerine uygun bir yayın mimarisi oluşturmak içindir.

## 1. Değiştirilemez kararlar

1. Ana ve kaynak dil Türkçedir.
2. Bütün Türkçe sayfalar tamamlanmadan gerçek çeviri üretimine başlanmayacaktır.
3. Kullanıcı açıkça `ÇEVİRİLERE BAŞLA` demeden:
   - AI çeviri çağrısı yapma.
   - Çeviri kuyruğu başlatma.
   - Otomatik çeviri üretme.
   - İngilizce içerik yazma.
   - Eksik alanları tahmini İngilizce içerikle doldurma.
4. Çeviri altyapısı, veritabanı, durum makineleri, fallback ve testler önceden kurulabilir. Gerçek içerik çevirisi kilitli kalmalıdır.
5. Site haritasındaki her canonical URL tek bir içerik kaydına karşılık gelmelidir.
6. Aynı içerik için ikinci URL, kopya landing page veya SEO doorway sayfası oluşturma.
7. Header, footer, mega menü ve içerik içi bağlantılar yeni sayfa yaratmaz; aynı canonical sayfaya bağlanır.
8. Site haritasındaki tüm sayfalar kayıt altına alınacak ancak yalnızca tamamlanan ve onaylanan sayfalar indekslenebilir biçimde yayınlanacaktır.
9. Black-hat SEO, negative SEO, gizli metin, doorway page, parasite SEO, link şeması, cloaking ve yanıltıcı structured data uygulanmayacaktır.
10. İkon kullanılmayacaktır. İkon yerine açık metin, tipografi, boşluk, çizgi, renk ve geometrik kompozisyon kullanılmalıdır.
11. Enterprise görünüm bir hazır temanın aynen kopyalanmasıyla değil; tutarlı tasarım tokenları, erişilebilirlik, test, dokümantasyon ve bileşen yönetimiyle sağlanacaktır.
12. İlk görev çeviri değil; altyapı, Türkçe içerik, kalite ve yayınlama sistemidir.

## 2. Kesin teknoloji kararı

### 2.1 Önerilen ana mimari

| Katman | Teknoloji | Görevi |
| --- | --- | --- |
| Backend ve içerik kaynağı | Laravel | Sayfa kayıtları, içerik blokları, yayın durumu, medya, çeviri altyapısı ve API |
| Public frontend | Astro | Server-rendered veya prerendered HTML, routing, layout, metadata ve içerik sayfaları |
| Etkileşimli parçalar | React islands | Yalnızca gerçekten etkileşim gerektiren küçük bileşenler |
| Stil altyapısı | Tailwind CSS | Tasarım tokenları ve utility katmanı |
| Temel React bileşenleri | shadcn/ui registry ve Radix tabanlı bileşenler | Erişilebilir ve sahiplenilebilir kaynak kod |
| Hafif hareket | CSS ve Motion | Basit geçişler ve erişilebilir animasyon |
| Gelişmiş hareket | SmoothUI, Animate UI ve seçili Magic UI bileşenleri | Sınırlı sayıdaki imza bileşen |
| İçerik formatı | Laravel structured content ve gerektiğinde Markdown/MDX | Tek içerik kaynağı ve düzenlenebilir içerik |
| Test | Vitest, Playwright, axe ve Lighthouse CI | Fonksiyon, erişilebilirlik, görsel ve performans doğrulama |

Astro server-first ve content-driven bir yapı sağlar; React bileşenleri yalnızca ihtiyaç olan adalarda hydrate edilebilir. Bu nedenle yüzlerce içerik sayfası için tam React SPA kurmaktan daha uygundur.

### 2.2 Neden diğer seçenekler ana teknoloji değil?

| Seçenek | Karar | Gerekçe |
| --- | --- | --- |
| React SPA | Kullanma | İçeriği istemci tarafında kurmak gereksiz JavaScript, daha karmaşık status code ve daha fazla render riski doğurur. React yalnızca island olarak kullanılmalıdır. |
| Flowbite | Ana tasarım sistemi yapma | Hızlı prototip için iyidir ancak shadcn tabanlı MCP ekosistemiyle aynı projede ikinci temel sistem hâline getirilmemelidir. |
| DaisyUI | Kullanma | Çok hızlıdır fakat tema sınıfları ve hazır görünümü Zabuno'nun özgün kurumsal kimliğini sınırlayabilir. |
| Material Design | Kullanma | Uygulama panelleri için güçlüdür; Zabuno pazarlama sitesi için fazla belirgin ve marka kimliğini Google benzeri bir dile yaklaştırır. |
| Yalnızca Laravel Blade | Yedek seçenek | Mevcut public site Blade ile ileri seviyede kurulmuşsa korunabilir. Yeni kurulumda Astro daha iyi içerik, animasyon ve MCP bileşen ekosistemi sağlar. |
| Next.js | Kullanma | Bu proje için gereksiz full-stack tekrar ve runtime karmaşıklığı yaratır. Laravel zaten backend'dir. |

### 2.3 Blade yedek kararı

Repository incelemesinde çalışan ve kapsamlı bir Laravel Blade public site zaten varsa yeni Astro uygulaması açıp sistemi parçalama. Bu durumda:

- Laravel Blade kullan.
- Raw PHP `require` yerine class-based Blade component ve middleware kullan.
- Tailwind + Flowbite + Alpine.js tercih et.
- React tabanlı SmoothUI, Animate UI, Magic UI, Aceternity ve React Bits bileşenlerini projeye sokma.

Yeni veya henüz iskelet seviyesindeki public sitede varsayılan karar Astro + React islands olmalıdır.

## 3. PHP require fikrinin doğru karşılığı

Kullanıcının hedefi doğrudur: Tek bir hazırlanıyor bileşeni bütün tamamlanmamış sayfalarda çalışmalı ve sayfa bittiğinde yalnızca o sayfa için kapanmalıdır.

Ancak bunu yüzlerce fiziksel PHP dosyasına aynı `require` satırını kopyalayarak yapma. Bu yapı zamanla dağılır ve unutulan dosyalar üretir.

Uygulanacak model:

1. Laravel'de tek bir `ContentPage` kaydı bulunur.
2. Her sayfanın benzersiz bir `page_key` ve canonical yolu vardır.
3. Sayfanın yayın durumu merkezi olarak tutulur.
4. Astro'da tek bir `PageGate` veya `PageRenderer` bütün sayfaların durumunu değerlendirir.
5. Sayfa `published` değilse ortak `UnderConstruction` bileşeni gösterilir.
6. Sayfa `published` olduğunda gerçek sayfa template'i ve içeriği gösterilir.
7. Bir sayfayı açmak için koddan component silinmez; yalnızca kontrollü yayın durumu değiştirilir.

Bu yaklaşım OOP kalıtımından çok composition, registry ve state machine yaklaşımıdır. Frontend bileşenlerinde kalıtım hiyerarşisi kurma.

## 4. Repository yapısı

Varsayılan monorepo yapısı:

```text
zabuno/
  apps/
    backend/
      app/
        Domain/Content/
        Domain/Localization/
        Domain/Media/
        Http/Controllers/Api/Public/
        Models/
        Services/
      config/
      database/migrations/
      routes/api.php
      tests/
    web/
      src/
        components/
          core/
          layout/
          page-state/
          sections/
          seo/
          media/
          forms/
          vendor/
        layouts/
        pages/
          [...slug].astro
        templates/
          product.astro
          feature.astro
          solution.astro
          integration.astro
          customer-story.astro
          resource.astro
          help.astro
          legal.astro
          corporate.astro
        lib/
          api/
          content/
          localization/
          seo/
          analytics/
        styles/
        content/
      tests/
      astro.config.mjs
      tailwind.config.ts
  packages/
    design-tokens/
    content-schema/
    page-registry/
  docs/
    adr/
    content/
    seo/
    design-system/
```

Kurallar:

- 414 route için 414 farklı layout veya kopya view üretme.
- Her canonical route için bir `ContentPage` kaydı üret.
- Sayfa türleri ortak template'leri paylaşmalıdır.
- Template içindeki içerik bölümleri bloklardan oluşmalıdır.
- Bileşen, template ve içerik kaydı birbirinden ayrılmalıdır.
- Vendor component kaynakları doğrudan genel component klasörüne karıştırılmamalıdır.

## 5. Sayfa kayıt modeli

Laravel'de en az aşağıdaki alanlara sahip bir `content_pages` tablosu oluştur:

```text
id
page_key
content_type
template_key
parent_id
default_locale
publication_status
visibility
canonical_path
navigation_visibility
sitemap_visibility
was_ever_published
published_at
unpublished_at
created_at
updated_at
```

Gerekli kurallar:

- `page_key` benzersizdir ve sonradan değiştirilmez.
- `canonical_path + locale` benzersizdir.
- Aynı canonical intent için ikinci sayfa kaydı oluşturulamaz.
- Silme varsayılan olarak soft delete olmalıdır.
- URL değişiklikleri ayrı redirect tablosunda tutulmalıdır.
- Durum değişiklikleri audit log'a yazılmalıdır.

## 6. Sayfa durum makinesi

Kullanılacak durumlar:

```text
planned
scaffolded
content_draft
content_review
design_review
seo_review
qa
approved
published
maintenance
retired
```

İzin verilen temel geçiş:

```text
planned
  -> scaffolded
  -> content_draft
  -> content_review
  -> design_review
  -> seo_review
  -> qa
  -> approved
  -> published
```

Ek geçişler:

- `published -> maintenance -> published`
- `published -> retired`
- QA başarısız olursa ilgili önceki aşamaya geri dön.
- `approved` durumu yayınlama değildir.
- `published` yalnızca bütün yayın kapıları geçildiğinde kullanılabilir.

## 7. PageGate davranışı

Tek bir merkezi karar fonksiyonu oluştur:

```ts
type PageRenderDecision = {
  mode: 'content' | 'construction' | 'not-found' | 'maintenance' | 'preview'
  statusCode: 200 | 404 | 503
  robots: 'index,follow' | 'noindex,follow' | 'noindex,nofollow'
  includeInSitemap: boolean
  includeInNavigation: boolean
}
```

Karar tablosu:

| Ortam ve durum | Görünüm | HTTP | Robots | Sitemap | Public menü |
| --- | --- | ---: | --- | --- | --- |
| Production + published | Gerçek içerik | 200 | index,follow | Evet | Evet |
| Production + yeni/planned/draft/review/qa | Yaratıcı hazırlanıyor görünümü | 404 | noindex,follow | Hayır | Hayır |
| Production + daha önce yayınlanmış maintenance | Bakım görünümü | 503 + Retry-After | İndeks kararı korunur | Geçici olarak korunabilir | Duruma göre |
| Authenticated preview + yayınlanmamış | Gerçek taslak veya hazırlanıyor görünümü | 200 | noindex,nofollow | Hayır | Preview menüsünde |
| Staging | Taslak içerik | 200 | noindex,nofollow + X-Robots-Tag | Hayır | Evet |
| Retired | Bulunamadı veya en yakın eşe 301 | 404/410/301 | noindex | Hayır | Hayır |

Önemli:

- Henüz hiç yayınlanmamış yüzlerce URL'ye `200 OK` dönen aynı hazırlanıyor metnini verme. Bu soft-404 ve kopya/thin content üretir.
- Tüm site uzun süre hazırlanıyorsa yalnızca ana sayfa gerçek ve faydalı bir coming-soon içeriğiyle `200` dönebilir.
- 503 yalnızca gerçekten daha önce çalışan sayfanın kısa süreli bakımı için kullanılmalıdır.
- robots.txt her zaman `200` dönmelidir; site kapalıyken robots.txt'ye 503 verme.
- `Retry-After` gerçekçi olmalı, uydurma tarih verilmemelidir.

## 8. Kreatif hazırlanıyor sayfası

### 8.1 Konsept adı

`Zabuno Service Pass`

Ana fikir: Restoran mutfağındaki sipariş fişi ile atmosfer/ozon katmanlarını birleştiren, sakin ama özgün bir sayfa.

### 8.2 Görsel dil

- Arka planda koyu lacivertten mora geçen, yavaş hareket eden yarı saydam atmosfer katmanları.
- Ortada bir restoran servis fişi veya mutfak pass kâğıdı hissi veren dikey panel.
- Panelin üstünde Zabuno kelime markası ve sayfanın gerçek başlığı.
- Katmanlar; içerik, tasarım, SEO ve kalite kontrol aşamalarını temsil eder.
- QR kodu taklit eden ancak taranabilir olmayan geometrik bir grid yavaşça düzenlenir.
- İkon kullanılmaz.
- Neon, oyun arayüzü veya aşırı parıltı kullanılmaz.
- Animasyonlar yavaş, düşük kontrastlı ve kurumsal olmalıdır.
- `prefers-reduced-motion` açık olduğunda bütün hareket durmalıdır.
- JavaScript çalışmasa bile metin ve CTA eksiksiz görünmelidir.

### 8.3 Metin sistemi

Ana başlık:

```text
Bu sayfa henüz servise çıkmadı.
```

Alt açıklama:

```text
İçerik, tasarım, arama görünürlüğü ve kalite kontrolü katman katman hazırlanıyor.
```

Sayfaya göre dinamik alanlar:

```text
Sayfa: {page_title}
Durum: {human_readable_stage}
Son güncelleme: {updated_at}
```

Durum metinleri:

| Teknik durum | Kullanıcıya gösterilecek metin |
| --- | --- |
| planned | Sıraya alındı |
| scaffolded | İskeleti hazırlandı |
| content_draft | Türkçe içeriği hazırlanıyor |
| content_review | İçeriği kontrol ediliyor |
| design_review | Görsel düzeni hazırlanıyor |
| seo_review | Arama görünürlüğü kontrol ediliyor |
| qa | Son kalite kontrolünde |
| approved | Servise çıkmayı bekliyor |
| maintenance | Kısa süreli bakımda |

CTA'lar:

- Ana sayfaya dön
- Çalışan sayfaları keşfet
- Demo iste
- İletişime geç

Sahte ilerleme yüzdesi veya gerçek olmayan geri sayım kullanma.

### 8.4 Component API

```ts
type UnderConstructionProps = {
  pageKey: string
  pageTitle: string
  stage: PagePublicationStatus
  updatedAt?: string
  expectedAt?: string
  locale: string
  fallbackLocale: string
  homeUrl: string
  exploreUrl?: string
  demoUrl?: string
  contactUrl?: string
  isPreview: boolean
}
```

Component tek instance mantığıyla kullanılmalı; sayfaya özel kopyaları oluşturulmamalıdır.

## 9. Laravel public content API

Astro'nun kullanacağı salt-okunur endpoint'ler:

```text
GET /api/public/v1/page?path={path}&locale={locale}
GET /api/public/v1/navigation?locale={locale}
GET /api/public/v1/sitemap?locale={locale}&type={type}
GET /api/public/v1/redirects
GET /api/public/v1/site-settings?locale={locale}
```

Page response en az şunları döndürmelidir:

```json
{
  "page_key": "product.qr-menu",
  "content_type": "product",
  "template_key": "product",
  "publication_status": "content_draft",
  "requested_locale": "en",
  "default_locale": "tr",
  "translation_status": "not_started",
  "canonical_path": "/tr/urun/qr-menu/",
  "localized_path": "/en/product/qr-menu/",
  "fields": {},
  "blocks": [],
  "seo": {},
  "media": [],
  "fallbacks_used": []
}
```

API kuralları:

- Public API taslak içeriği anonim kullanıcıya döndürmemelidir.
- Preview API imzalı ve süreli token istemelidir.
- ETag ve Last-Modified desteklenmelidir.
- Cache tag veya surrogate key ile sayfa bazlı invalidation yapılabilmelidir.
- Her response schema ile doğrulanmalıdır.

## 10. Türkçe öncelikli çeviri sistemi

### 10.1 İçerik tabloları

En az aşağıdaki yapıları oluştur:

```text
content_pages
content_page_localizations
content_blocks
content_block_localizations
media_assets
media_localizations
localized_routes
translation_glossary
translation_jobs
translation_audit_logs
```

`content_page_localizations` temel alanları:

```text
id
content_page_id
locale
translation_status
source_revision
translated_from_revision
title
slug
eyebrow
summary
body
seo_title
meta_description
og_title
og_description
structured_data_overrides
approved_by
approved_at
published_at
created_at
updated_at
```

`translation_status` değerleri:

```text
not_started
locked
draft
machine_draft
human_review
approved
published
stale
rejected
```

### 10.2 Çeviri kilidi

Üç katmanlı kilit kur:

1. Konfigürasyon kilidi

```text
TRANSLATION_GENERATION_ENABLED=false
```

2. Uygulama servis kilidi

- Translation service, kilit kapalıysa provider çağrısını reddetmelidir.
- Exception açık ve anlaşılır olmalıdır: `TranslationGenerationLocked`.

3. Queue worker kilidi

- Yanlışlıkla job oluşsa bile worker provider çağrısı yapmamalıdır.
- Job başarısız değil, `blocked_by_owner_policy` durumuna alınmalıdır.

Ek güvenlik:

- Yönetim panelinde çeviri başlatma butonu kilitli görünmelidir.
- API üzerinden toplu çeviri endpoint'i kilitliyken `423 Locked` dönmelidir.
- Scheduled task, event listener veya cron kendi kendine çeviri başlatmamalıdır.
- Türkçe kaynak güncellendiğinde yalnızca mevcut çeviri `stale` işaretlenir; otomatik yeniden çeviri yapılmaz.
- Kilidi yalnızca proje sahibinin açık talebiyle değiştir.

### 10.3 Field-level fallback

İstenen dilde bir alan boşsa sayfanın tamamını boş bırakma. Alan bazında Türkçeye geri dön:

```ts
function resolveLocalizedField(field, requestedLocale) {
  const localized = getApprovedValue(field, requestedLocale)

  if (localized !== null && localized !== '') {
    return {
      value: localized,
      contentLanguage: requestedLocale,
      isFallback: false
    }
  }

  return {
    value: getApprovedValue(field, 'tr'),
    contentLanguage: 'tr',
    isFallback: true
  }
}
```

Kurallar:

- İngilizce seçildiğinde çevrilmiş ve onaylanmış alan İngilizce görünür.
- Çevrilmemiş alan Türkçe görünür.
- Boş başlık, boş CTA, boş alt metin veya kırık medya oluşmaz.
- Fallback kullanılan HTML elemanında uygun `lang="tr"` işaretlemesi bulunmalıdır.
- Dil switcher seçilen route'u korur; eşleşen `page_key` üzerinden locale değiştirir.
- Fallback kullanıldığı kullanıcıya rahatsız edici uyarılarla gösterilmez; erişilebilirlik için semantik dil işaretlemesi yapılır.

### 10.4 Kısmi çevirinin SEO davranışı

Kısmen çevrilmiş veya tamamen Türkçe fallback kullanan `/en/` sayfa:

- `noindex,follow` olmalıdır.
- XML sitemap'e girmemelidir.
- `hreflang="en"` alternatifi olarak ilan edilmemelidir.
- İngilizce canonical sayfa olarak değerlendirilmemelidir.
- Gerekirse canonical Türkçe kaynak sayfayı göstermelidir.

Bir locale sayfası ancak aşağıdaki alanların tamamı çevrilmiş, gözden geçirilmiş ve onaylanmışsa indekslenebilir:

- URL slug
- Title
- H1
- Summary veya hero açıklaması
- Ana içerik blokları
- CTA metinleri
- Meta description
- Open Graph metinleri
- Görsel alt metinleri
- Zorunlu structured data metinleri
- Yasal olarak gerekli locale içeriği

Tamamlandığında:

- Self-canonical kullan.
- Karşılıklı hreflang ekle.
- `x-default` tanımla.
- Locale sitemap'e ekle.
- `<html lang>` değerini locale'e göre ayarla.

### 10.5 Medya ve video yerelleştirmesi

`media_assets` dil bağımsız dosyayı, `media_localizations` dile bağlı bilgiyi saklar.

Dil bazlı alanlar:

```text
alt_text
title
caption
description
transcript
subtitle_file
voiceover_file
poster_asset_id
localized_asset_id
```

Kurallar:

- Üzerinde yazı bulunmayan görsel aynı dosyayı kullanabilir; alt metin ve caption çevrilebilir.
- Görselin üzerinde Türkçe yazı varsa İngilizce locale için ayrı localized asset desteklenmelidir.
- Localized asset yoksa Türkçe görsel gösterilir ve elemanda doğru `lang`/açıklama kullanılır.
- Videoda locale bazlı WebVTT altyazı, transcript, poster ve isteğe bağlı seslendirme desteklenmelidir.
- Çevrilmiş altyazı yoksa Türkçe altyazı fallback olarak gösterilebilir.
- Video ve görsel için çeviri işi de genel çeviri kilidine tabidir.

## 11. URL politikası

1. Her arama niyeti için tek canonical URL.
2. URL'ler küçük harfli olmalıdır.
3. Kelimeler tire ile ayrılmalıdır.
4. Türkçe slug'larda ASCII karakter tercih edilmelidir: `coklu-sube`, `fiyatlandirma`.
5. Bütün public sayfalarda trailing slash politikası aynı olmalıdır.
6. Evergreen içerikte tarih slug'a eklenmemelidir.
7. Locale dizini açık olmalıdır: `/tr/` ve `/en/`.
8. Query string ayrı canonical sayfa üretmemelidir.
9. UTM parametreleri canonical'a dahil edilmemelidir.
10. Filtre, arama ve sıralama URL'leri varsayılan olarak indekslenmemelidir.
11. URL değişikliğinde tek atımlı 301 kullanılmalıdır; redirect chain oluşturulmamalıdır.
12. Geçici yönlendirme gerçekten geçiciyse 302/307 kullanılmalıdır.
13. Silinen ve eşdeğeri olmayan içerik 410 veya gerçek 404 döndürmelidir.
14. Aynı içeriğin kampanya, sektör, şehir veya anahtar kelime varyasyonlarını otomatik çoğaltma.
15. Entegrasyon URL'si yalnızca entegrasyon gerçekten mevcut ve belgelenmişse yayınlanmalıdır.
16. Canonical, hreflang, sitemap ve internal link aynı URL biçimini kullanmalıdır.
17. Slug geçmişi ve redirect kayıtları veritabanında tutulmalıdır.

## 12. Metadata ve SEO veri modeli

Her sayfada aşağıdaki alanlar yönetilebilir olmalıdır:

### Kimlik ve sınıflandırma

```text
page_key
content_type
template_key
parent_key
locale
audience
search_intent
funnel_stage
topic_cluster
primary_entity
related_entities
primary_query
supporting_queries
```

### Temel metadata

```text
seo_title
meta_description
canonical_url
robots_directive
og_title
og_description
og_image
og_type
twitter_card
breadcrumb_title
```

### İçerik güveni

```text
author_id
reviewer_id
fact_checked_by
source_references
first_published_at
last_reviewed_at
last_material_update_at
content_revision
content_freshness_status
```

### Yapay zekâ ve cevap sistemleri için içerik alanları

```text
direct_answer
short_definition
key_facts
steps
requirements
limitations
comparison_points
frequently_asked_questions
source_claim_map
entity_relationships
```

Kurallar:

- Meta keywords alanı oluşturma.
- Title, H1 ve URL aynı olmak zorunda değildir; aynı kullanıcı niyetini açıkça temsil etmelidir.
- Her sayfanın benzersiz title, H1 ve meta description'ı olmalıdır.
- Structured data sayfada görünmeyen veya doğrulanmamış bilgi içermemelidir.
- Schema üretimi template türüne göre merkezi yapılmalıdır.

## 13. Arama ve keşif uyumluluk modeli

Kullanıcının verdiği kavramların tamamı ayrı birer teknik standart değildir. Bir kısmı yerleşik disiplin, bir kısmı kanal, bir kısmı yeni pazarlama etiketi, bir kısmı ise riskli yöntemdir. Bunları 70 ayrı checkbox olarak uygulama. Aşağıdaki ortak sistemler üzerinden kapsa.

### 13.1 Crawl, render ve index temeli

Kapsadığı alanlar:

- SEO
- Technical SEO
- JavaScript SEO
- Headless SEO
- Edge SEO
- Mobile SEO
- Enterprise SEO

Gereksinimler:

- Ana içerik ilk HTML response içinde bulunmalıdır.
- Gerçek `<a href>` bağlantıları kullanılmalıdır.
- Doğru HTTP status code dönmelidir.
- robots.txt, XML sitemap, canonical ve redirect test edilmelidir.
- JavaScript olmadan temel içerik ve navigasyon çalışmalıdır.
- Pagination crawl edilebilir olmalıdır.
- Sonsuz scroll varsa paginated alternatif bulunmalıdır.
- CSS ve JavaScript fingerprint'li ve cache edilebilir olmalıdır.
- Staging ve preview ortamları auth veya noindex ile korunmalıdır.

### 13.2 On-page, semantic ve entity sistemi

Kapsadığı alanlar:

- On-Page SEO
- Semantic SEO
- Entity SEO
- Topical SEO
- Content SEO
- Editorial SEO
- Product SEO
- Category SEO
- Landing Page SEO
- KGO
- PEO
- LEO

Gereksinimler:

- Tek sayfa, tek ana niyet.
- Konu kümeleri ve hub/detail ilişkileri.
- Açık marka, ürün, özellik, sektör ve entegrasyon varlıkları.
- Breadcrumb ve anlamlı internal linkler.
- Tanım, fayda, işleyiş, gereksinim, sınırlama ve kanıt blokları.
- Yazar, editör, kaynak ve güncelleme bilgisi.
- Aynı anahtar kelime için birden fazla sayfa üretmeyi engelleyen cannibalization kontrolü.

PEO, LEO ve KGO için evrensel tek bir teknik standart varmış gibi davranma; bunları entity, yerel bağlam, uzmanlık ve bilgi grafiği gereksinimlerine eşle.

### 13.3 AEO, AI ve agentic search sistemi

Kapsadığı alanlar:

- AEO
- GEO
- LLMO
- AIO
- AISO
- AI SEO
- ASEO
- Search Everywhere Optimization
- Agentic Search Optimization
- Zero-Click SEO
- Featured Snippet SEO

Gereksinimler:

- Sayfanın başında kısa ve doğrudan cevap.
- Açık soru-cevap yapısı.
- Adım listeleri ve gereksinim tabloları.
- Kaynaklanabilir, tek anlamlı ve ölçülü iddialar.
- Marka ve ürün gerçeklerinin bütün sayfalarda tutarlı olması.
- Kullanılabilir HTML tabloları ve listeler.
- Server-rendered içerik.
- Gerektiğinde Organization, SoftwareApplication, Product, Article, BreadcrumbList, HowTo ve VideoObject structured data.
- Structured data sadece uygun olduğu gerçek sayfa türlerinde kullanılmalıdır.
- İçerikte iddia-kaynak eşlemesi tutulmalıdır.
- `llms.txt` yalnızca deneysel yardımcı dosya olarak değerlendirilebilir; robots.txt, sitemap, canonical veya gerçek içerik yerine kullanılamaz.

### 13.4 Programmatic ve ölçekli SEO

Kapsadığı alanlar:

- pSEO
- Programmatic SEO
- SaaS SEO
- B2B SEO
- B2C SEO
- Marketplace SEO
- E-commerce SEO
- Faceted Navigation SEO

Gereksinimler:

- Template kullanmak serbest, kopya değer önermesi üretmek yasaktır.
- Her programmatic sayfa gerçek veri, farklı kullanıcı problemi veya doğrulanabilir içerik taşımalıdır.
- Boş, az veri içeren veya yalnızca şehir/anahtar kelime değiştirilmiş sayfa yayınlanmamalıdır.
- Publish quality gate geçmeyen kayıt sitemap'e girmemelidir.
- Facet kombinasyonları allowlist ile yönetilmelidir.
- Entegrasyon, sektör, karşılaştırma ve sözlük sayfaları ayrı template ve kalite kurallarına sahip olmalıdır.
- Sitemap'ler içerik türü ve locale'e göre bölünmelidir.

### 13.5 Uluslararası ve yerel görünürlük

Kapsadığı alanlar:

- International SEO
- Multilingual SEO
- Multiregional SEO
- Local SEO
- Google Maps SEO

Gereksinimler:

- Locale bazlı URL.
- Tamamlanmış çevirilerde karşılıklı hreflang.
- x-default.
- Her locale için self-canonical.
- Fiyat, para birimi, mevzuat ve örneklerin yerelleştirilmesi.
- Türkçe fallback kullanan locale sayfalarının noindex olması.
- Kurumsal isim, adres, telefon ve destek bilgilerinin tutarlılığı.
- Fiziksel ofis varsa doğrulanmış LocalBusiness verisi; sanal adresle sahte yerel sayfa oluşturulmaması.

### 13.6 Görsel, video, ses ve uygulama keşfi

Kapsadığı alanlar:

- Image SEO
- Video SEO
- YouTube SEO
- Podcast SEO
- Voice Search SEO
- Visual Search SEO
- VSO
- ASO
- App Store Optimization

Gereksinimler:

- Görsel dosya adı, alt text, caption, width ve height.
- Responsive `srcset` ve `sizes`.
- AVIF/WebP ve uygun fallback.
- Özgün görseller için image sitemap.
- Video için transcript, caption, poster, süre ve VideoObject.
- YouTube videosu varsa web sayfasında benzersiz özet ve transcript bağlamı.
- Podcast varsa bölüm detay sayfası, transcript ve feed.
- Sesli arama için doğal soru ve kısa cevaplar.
- Görsel arama için metin içine gömülmeyen ürün fotoğrafı ve açıklayıcı çevre metni.
- ASO web SEO'nun alt türü değildir; mobil uygulama çıkarsa store listing, screenshot, preview video, localization ve deep-link işi ayrı backlog olmalıdır.

### 13.7 Dağıtım ve itibar

Kapsadığı alanlar:

- Off-Page SEO
- SMO
- SERM
- Barnacle SEO
- News SEO
- Google Discover SEO
- Academic SEO

Gereksinimler:

- Doğrulanmış sosyal profil ve Organization `sameAs` ilişkileri.
- Basın, müşteri hikayesi ve uzmanlık içeriği.
- Sahte yorum, sahte referans ve ücretli link şeması kullanılmaması.
- News veya Academic işaretleri yalnızca gerçekten bu içerik türü varsa uygulanmalıdır.
- Discover için büyük, kaliteli görseller ve people-first editoryal içerik.
- Barnacle yaklaşımı yalnızca meşru sektör dizini, partner profili ve doğrulanmış platform varlığıyla sınırlandırılmalıdır.

### 13.8 Yasaklanan yöntemler

| Terim | Karar |
| --- | --- |
| White-Hat SEO | Uygulanır |
| Gray-Hat SEO | Uygulanmaz; riskli deney olarak bile varsayılan plana girmez |
| Black-Hat SEO | Kesinlikle uygulanmaz |
| Negative SEO | Kesinlikle uygulanmaz |
| Parasite SEO | Otorite suistimali veya içerik kiralama biçiminde uygulanmaz |

Bu terimlere uyumluluk, bunları desteklemek değil; sistemin bu yöntemlere ihtiyaç duymadan çalışması ve kötüye kullanımı engellemesidir.

## 14. Structured data politikası

Merkezi bir schema builder oluştur. Sayfa türüne göre yalnızca geçerli schema üret:

| Sayfa türü | Uygun schema |
| --- | --- |
| Site geneli | Organization, WebSite |
| Ürün ve SaaS | SoftwareApplication, Product veya Service; gerçek modele göre biri seçilmeli |
| Rehber | Article, HowTo yalnızca gerçek adımlar varsa |
| Blog | Article veya BlogPosting |
| SSS | FAQPage yalnızca görünür ve uygun içerikte; rich result garantisi verilmez |
| Müşteri hikayesi | Article; Review yalnızca gerçek ve izinli değerlendirmede |
| Video | VideoObject |
| Sayfa hiyerarşisi | BreadcrumbList |
| İş ilanı | JobPosting |
| Etkinlik/webinar | Event |

Kurallar:

- Schema alanları görünür içerikle çelişemez.
- Sahte rating veya review üretilemez.
- JSON-LD server-rendered HTML içinde bulunmalıdır.
- Rich Results Test ve schema doğrulaması CI adımı olmalıdır.

## 15. İçerik şablonları

### Ürün veya özellik sayfası

1. Breadcrumb
2. Tek ve açık H1
3. Kısa doğrudan cevap
4. Kullanıcı problemi
5. Zabuno çözümü
6. Nasıl çalışır
7. Öne çıkan yetenekler
8. Ekran veya gerçek kullanım görseli
9. Sınırlamalar ve gereksinimler
10. Entegrasyonlar
11. İlgili müşteri kanıtı
12. SSS
13. Birincil CTA
14. İlgili sayfalar

### Sektör çözümü sayfası

1. Sektöre özel problem
2. İş akışı
3. İlgili ürün yetenekleri
4. Sektöre özel örnek menü
5. Gerçek müşteri hikayesi
6. Sonuç metrikleri
7. Kurulum yaklaşımı
8. SSS ve CTA

Yalnızca sektör adı değiştirilmiş kopya metin yayınlama.

### Entegrasyon sayfası

1. Entegrasyonun gerçek durumu
2. Aktarılan veriler
3. Veri yönü
4. Kurulum yöntemi
5. Ön koşullar
6. Sınırlamalar
7. Güvenlik ve izinler
8. Desteklenen sürümler
9. Kurulum dokümanı
10. Destek kanalı

### Karşılaştırma sayfası

1. Karşılaştırma tarihi
2. Kriter yöntemi
3. Doğrulanmış kaynaklar
4. Tarafsız özellik tablosu
5. Kimin için hangi ürün uygun
6. Zabuno'nun güçlü ve zayıf yönleri
7. Güncelleme geçmişi

### Blog ve rehber

1. Yazar ve uzmanlık
2. Son kontrol/güncelleme tarihi
3. Kısa cevap veya özet
4. İçindekiler
5. Kaynaklı ana içerik
6. Örnekler
7. Sık hatalar
8. Sonraki adım
9. İlgili ürün ve rehberler

## 16. Görsel ve video teknik gereksinimleri

- Görsel component'i merkezi olmalıdır.
- Width ve height zorunlu olmalıdır.
- Layout shift üretmemelidir.
- Hero görseli dışındaki görseller lazy-load edilmelidir.
- LCP görseli preload/fetchpriority ile kontrollü optimize edilmelidir.
- Alt text dekoratif görsellerde boş bırakılabilir; bilgi taşıyan görsellerde zorunludur.
- Görsel üzerinde metin kullanımı en aza indirilmelidir.
- Video otomatik sesli başlamamalıdır.
- Autoplay video varsa muted, playsinline ve durdurma kontrolü bulunmalıdır.
- Reduced motion ve düşük veri tercihleri dikkate alınmalıdır.
- Üçüncü taraf video embed'i consent ve performans bütçesine tabi olmalıdır.

## 17. MCP ve component kaynaklarının önceliği

### 17.1 Temel kural

MCP erişimi bir component'in otomatik olarak kaliteli, güvenli veya Zabuno'ya uygun olduğu anlamına gelmez. MCP yalnızca keşif ve kaynak kodu projeye alma hızını artırır.

Tek bir temel tasarım sistemi kullanılmalıdır:

```text
Tailwind tokens + shadcn-compatible registry + Zabuno-owned components
```

Diğer kaynaklar yalnızca seçili bileşen sağlayıcısıdır.

### 17.2 Önerilen sıra

| Öncelik | Kaynak | Kullanım kararı | Zabuno'daki rolü |
| ---: | --- | --- | --- |
| 0 | shadcn/ui registry | Temel altyapı | MCP, registry, component ownership ve ortak kurulum mekanizması |
| 1 | SmoothUI | Öncelikli efekt kaynağı | Rafine hero, metin hareketi, durum görselleştirmesi ve az sayıda premium etkileşim |
| 2 | Animate UI | Öncelikli işlevsel animasyon | Dialog, accordion, tabs, tooltip ve reduced-motion uyumlu gerçek UI davranışları |
| 3 | Magic UI | Yardımcı görsel efekt | Grid, beam, marquee ve hafif arka plan efektleri |
| 4 | Aceternity UI | Sınırlı imza bölümü | Bir veya iki güçlü hero, bento ya da spotlight deneyimi |
| 5 | React Bits | Deneysel ağır efekt | WebGL, shader veya parçacık yalnızca performans testini geçerse |
| 6 | 21st.dev | Keşif havuzu | İlham ve aday bulma; kod doğrudan güvenilir kabul edilmez |

### 17.3 SmoothUI kararı

SmoothUI öncelikli olabilir ve Astro + React island mimarisine uygundur. Ancak:

- SmoothUI temel tasarım sistemi değildir.
- React 19, Tailwind 4, Motion ve bazı bileşenlerde GSAP bağımlılığı dikkate alınmalıdır.
- Her sayfaya SmoothUI eklenmemelidir.
- Under construction sayfasının kritik metni SmoothUI veya JavaScript'e bağlı olmamalıdır.
- SmoothUI yalnızca görsel zenginleştirme katmanı olmalıdır.
- Bileşen kaynak kodu projeye alındıktan sonra Zabuno tokenlarıyla yeniden düzenlenmelidir.

### 17.4 MCP bileşeni kabul kapısı

Bir MCP kaynağından gelen component doğrudan production'a alınamaz. Aşağıdaki sırayı uygula:

1. Önizle.
2. Kaynak kodunu incele.
3. Lisansı doğrula.
4. Bağımlılıkları listele.
5. React/Astro uyumunu kontrol et.
6. Zabuno tasarım tokenlarına bağla.
7. İkonları kaldır.
8. Klavye ve screen reader davranışını test et.
9. `prefers-reduced-motion` desteği ekle.
10. Bundle etkisini ölç.
11. Mobil görünümü test et.
12. Storybook veya component playground kaydı oluştur.
13. Kaynak, lisans ve alınan sürümü component manifestine yaz.

### 17.5 Sayfa başına efekt bütçesi

- Bir sayfada en fazla bir baskın hareketli arka plan.
- Aynı viewport'ta birden fazla WebGL/shader çalıştırma.
- Hero dışında sürekli çalışan animasyon kullanma.
- Scroll-jacking kullanma.
- Cursor takip efektini zorunlu etkileşim hâline getirme.
- Mobilde ağır efektleri azalt veya kapat.
- Performans kapısını geçmeyen efekt yerine statik CSS varyantı kullan.

## 18. Tasarım sistemi gereksinimleri

### Token grupları

```text
color.brand.*
color.surface.*
color.text.*
color.border.*
color.state.*
font.family.*
font.size.*
font.weight.*
line.height.*
space.*
radius.*
shadow.*
motion.duration.*
motion.easing.*
container.*
breakpoint.*
z-index.*
```

Kurallar:

- Renkler component içinde hard-code edilmemelidir.
- Light/dark gereksinimi token katmanında çözülmelidir.
- Rastgele radius, spacing ve shadow değerleri kullanılmamalıdır.
- Component adları görünüm değil işlev anlatmalıdır.
- İkon-only button oluşturulmamalıdır.
- Focus state görünür olmalıdır.
- Minimum hedef WCAG 2.2 AA olmalıdır.

## 19. Performans ve kalite bütçeleri

Hedefler laboratuvar ölçümü değil gerçek kullanıcı deneyimi için izlenmelidir.

- LCP: iyi eşik hedefi.
- INP: iyi eşik hedefi.
- CLS: iyi eşik hedefi.
- Kritik metin server-rendered olmalıdır.
- Third-party scriptler envanter ve onay olmadan eklenmemelidir.
- Analytics consent sonrasında yüklenmelidir.
- Font sayısı ve ağırlığı sınırlanmalıdır.
- Route bazlı JavaScript ölçülmelidir.
- React yalnızca hydrate edilmesi gereken component'te kullanılmalıdır.
- Under construction ve maintenance görünümü statik HTML/CSS ile çalışmalıdır.

CI içinde:

```text
typecheck
lint
unit tests
integration tests
Playwright smoke tests
axe accessibility tests
SEO metadata tests
structured data validation
broken link scan
duplicate canonical scan
duplicate title/H1 scan
sitemap validation
translation lock tests
Lighthouse CI
```

## 20. Sayfa yayınlama kapısı

Bir sayfa `published` olabilmek için aşağıdakilerin tamamını geçmelidir:

### İçerik

- Türkçe içerik tamamlandı.
- İçerik sahibi tarafından onaylandı.
- Kopya veya cannibalization kontrolü geçti.
- İddialar kaynaklandı.
- Ürün gerçekten desteklemediği özelliği iddia etmiyor.

### Tasarım

- Desktop, tablet ve mobil kontrol edildi.
- Loading, empty, error ve long-content durumları kontrol edildi.
- İkon kullanılmadı.
- Reduced motion çalışıyor.

### SEO

- URL policy uygun.
- Title, H1 ve description benzersiz.
- Canonical doğru.
- Robots doğru.
- Breadcrumb doğru.
- Internal linkler çalışıyor.
- Structured data geçerli.
- Sitemap üyeliği doğru.

### Erişilebilirlik

- Klavye ile kullanılabiliyor.
- Focus sırası doğru.
- Landmark ve heading hiyerarşisi doğru.
- Form label ve error mesajları doğru.
- Kontrast yeterli.
- Medya alternatifleri mevcut.

### Teknik

- Gerçek HTTP status code doğru.
- Broken link yok.
- Console error yok.
- Performans bütçesi geçti.
- Testler geçti.
- Preview onayı alındı.

Kapı geçilmeden component'i silme, status'u elle zorlayarak published yapma veya sitemap'e ekleme.

## 21. Geliştirme sırası

### Faz 0 — Repository ve karar doğrulama

1. Repository yapısını incele.
2. Laravel backend ve mevcut public frontend'i tespit et.
3. Çalışan Blade public site yoksa Astro kararını uygula.
4. Mevcut değişiklikleri koru.
5. ADR oluştur:
   - ADR-001 Public frontend framework
   - ADR-002 Page registry and publication state
   - ADR-003 Localization fallback and translation lock
   - ADR-004 SEO rendering and indexing policy
   - ADR-005 Component registry governance

### Faz 1 — Sitemap'i registry'ye dönüştür

1. `zabuno-com-tam-site-haritasi.md` dosyasını parse et.
2. Canonical route listesini üret.
3. Duplicate route testi yaz.
4. Her route için `page_key`, `content_type`, `template_key`, `priority` ve başlangıç status'u üret.
5. Laravel seeder ve JSON registry çıktısı oluştur.
6. Fiziksel olarak yüzlerce kopya sayfa component'i üretme.

### Faz 2 — Page state ve hazırlanıyor sistemi

1. Migration'ları oluştur.
2. Page state enum ve transition policy yaz.
3. Public ve preview API'lerini oluştur.
4. Astro catch-all route ve PageGate'i oluştur.
5. UnderConstruction component'ini oluştur.
6. 404, 503, preview ve published davranışlarını test et.

### Faz 3 — Tasarım sistemi

1. Zabuno tokenlarını oluştur.
2. Typography ve container sistemini kur.
3. Header, footer, mega menü, breadcrumb, CTA, form ve content section component'lerini kur.
4. shadcn registry ve MCP yapılandırmasını kur.
5. SmoothUI ile yalnızca hazırlanıyor sayfasında veya ana hero'da tek bir kontrollü efekt prototipi oluştur.
6. Performans ve reduced-motion testlerinden sonra kabul et veya statik varyanta dön.

### Faz 4 — SEO çekirdeği

1. Metadata builder.
2. Canonical ve robots policy.
3. hreflang hazırlığı.
4. Structured data builder.
5. XML sitemap index ve alt sitemap'ler.
6. robots.txt.
7. redirects.
8. Open Graph ve sosyal preview.
9. RSS/feed gereksinimleri.
10. CI SEO testleri.

### Faz 5 — Çeviri altyapısı

1. Localization tabloları.
2. Field-level fallback.
3. Language switcher.
4. Partial translation noindex policy.
5. Media localization.
6. Translation lock.
7. Provider adapter interface; gerçek provider çağrısı kapalı.
8. Lock testleri.

Bu faz gerçek İngilizce içerik veya çeviri üretmez.

### Faz 6 — Türkçe P0 sayfaları

Önce şu sayfaları sırayla geliştir:

1. Ana sayfa.
2. QR Menü.
3. Menü Yönetimi.
4. Masa ve QR Yönetimi.
5. Tasarım ve Marka.
6. Görsel ve Medya.
7. Çoklu Dil ve Para Birimi.
8. Çoklu Şube.
9. Analitik.
10. Zabuno AI.
11. İşletme türleri ana sayfası.
12. Restoran.
13. Fast Food.
14. Kafe ve Pastane.
15. Entegrasyonlar ana sayfası.
16. Menü Örnekleri.
17. Müşteri Hikayeleri.
18. Fiyatlandırma.
19. Blog ve Rehberler ana sayfaları.
20. Yardım Merkezi.
21. Hakkımızda.
22. İletişim.
23. Güven Merkezi.
24. Zorunlu yasal sayfalar.

Her sayfa tamamlandığında yalnızca o sayfanın publication status'unu kalite kapısı üzerinden ilerlet.

### Faz 7 — Türkçe P1 ve P2

- Sitemap sırasına göre ilerle.
- Gerçek üründe olmayan entegrasyon ve özellikleri yayınlama.
- P2 sayfalarını ürün hazır olmadan yalnızca registry'de planned olarak tut.

### Faz 8 — Çeviriler

Bu fazı kendiliğinden başlatma.

Yalnızca kullanıcı açıkça aşağıdaki komutu verdiğinde yeni bir plan oluştur:

```text
ÇEVİRİLERE BAŞLA
```

Komut verilmeden Faz 8'e geçmek yasaktır.

## 22. Test senaryoları

### PageGate

- Planned production route 404 + noindex döndürür.
- Planned route public navigation ve sitemap'te görünmez.
- Preview token ile planned route 200 + noindex döndürür.
- Published route 200 + index döndürür.
- Maintenance route 503 + Retry-After döndürür.
- Daha önce yayınlanmamış route maintenance olamaz.

### Sitemap

- Yalnızca published ve sitemap-visible route'lar listelenir.
- Canonical URL ile sitemap URL aynı biçimdedir.
- 404, 503, redirect ve noindex URL sitemap'e girmez.
- Locale sitemap yalnızca tamamlanmış locale sayfalarını içerir.

### Translation lock

- Config kapalıyken provider çağrısı sıfır olmalıdır.
- UI, API, service ve worker kilidi ayrı ayrı test edilmelidir.
- Türkçe kaynak değiştiğinde İngilizce kayıt stale olur ama job oluşmaz.
- Eksik locale alanı Türkçe fallback döndürür.
- Tamamlanmamış locale sayfası noindex olur.
- Medya alt text, caption ve subtitle fallback'i çalışır.

### Duplicate prevention

- Aynı locale + canonical path ikinci kez eklenemez.
- Aynı page key ikinci kez eklenemez.
- Canonical conflict CI'ı durdurur.
- Aynı title ve H1 raporlanır.
- Aynı primary intent için editoryal uyarı üretilir.

### Component governance

- Vendor component manifest kaydı olmadan import edilemez.
- Reduced motion testi geçmeyen animasyon kabul edilmez.
- İkon içeren component kalite kapısından geçmez.
- Ağır effect mobil performans bütçesini aşarsa statik varyanta düşer.

## 23. Teslim edilmesi gereken çıktılar

Claude işi tamamladığında aşağıdakileri üretmelidir:

```text
docs/adr/ADR-001-public-frontend.md
docs/adr/ADR-002-page-registry.md
docs/adr/ADR-003-localization-lock.md
docs/adr/ADR-004-seo-indexing.md
docs/adr/ADR-005-component-governance.md
docs/content/page-status-workflow.md
docs/content/turkish-first-policy.md
docs/seo/url-policy.md
docs/seo/metadata-schema.md
docs/seo/structured-data-policy.md
docs/seo/programmatic-seo-guardrails.md
docs/design-system/tokens.md
docs/design-system/vendor-component-manifest.md
docs/qa/page-publish-checklist.md
```

Ayrıca:

- Çalışan Laravel migration ve modelleri.
- Sitemap'ten üretilmiş page registry ve seeder.
- Public content API.
- Astro PageGate ve catch-all renderer.
- UnderConstruction component.
- Translation fallback altyapısı.
- Kilitli translation provider interface.
- Language switcher.
- Metadata ve schema builder.
- Sitemap generator.
- Redirect manager.
- Testler ve CI kontrolleri.

## 24. Claude için çalışma davranışı

1. Önce repository'yi ve iki talimat dosyasını tamamen oku.
2. Mevcut kullanıcı değişikliklerini koru.
3. Uygulamadan önce kısa bir mevcut durum ve çakışma raporu çıkar.
4. Kritik mimari kararlarda ADR oluştur.
5. Test-first ilerle:
   - Önce kabul kriterini test olarak yaz.
   - Testin beklenen nedenle başarısız olduğunu doğrula.
   - En küçük doğru implementasyonu yap.
   - Testleri çalıştır.
   - Refactor et.
6. Her faz sonunda lint, typecheck ve ilgili testleri çalıştır.
7. Bir library component'ini kopyalayıp bırakma; Zabuno sistemine adapte et.
8. Ürün gerçeği bilinmiyorsa özellik veya entegrasyon iddiası uydurma.
9. Gerçek tarih, fiyat, müşteri, entegrasyon, performans veya kullanım sayısı uydurma.
10. Eksik bilgiyi açık TODO ve karar kaydı olarak bırak.
11. Çeviriye başlama izni isteme, çeviri önerisi üretme ve çeviri işini kendiliğinden sıraya koyma.
12. Kullanıcı `ÇEVİRİLERE BAŞLA` demedikçe yalnızca Türkçe içerikle ilerle.

## 25. Tamamlanma tanımı

Bu iş yalnızca hazırlanıyor ekranı görünür olduğunda tamamlanmış sayılmaz.

Tamamlanmış kabul edilebilmesi için:

- Sitemap'teki bütün canonical route'lar registry'de bulunmalıdır.
- Duplicate route bulunmamalıdır.
- Bütün yayınlanmamış route'lar merkezi PageGate ile yönetilmelidir.
- Her sayfa bağımsız olarak published yapılabilmelidir.
- Production HTTP ve indexing davranışı test edilmiş olmalıdır.
- Türkçe fallback hiçbir alanı boş bırakmamalıdır.
- Translation generation dört katmanda kilitli olmalıdır.
- Medya localization veri modeli hazır olmalıdır.
- SEO metadata, canonical, robots, hreflang hazırlığı, structured data ve sitemap merkezi çalışmalıdır.
- MCP'den alınan bütün component'ler governance kapısından geçmiş olmalıdır.
- Erişilebilirlik, performans, responsive ve reduced-motion testleri geçmiş olmalıdır.
- Türkçe P0 sayfaları için içerik geliştirme sırası uygulanabilir olmalıdır.

## 26. Resmî teknik referanslar

- Astro, server-first ve content-driven yaklaşım: https://astro.build/
- Astro React integration: https://docs.astro.build/en/guides/integrations-guide/react/
- Astro internationalization: https://docs.astro.build/en/guides/internationalization/
- Astro content collections: https://docs.astro.build/en/guides/content-collections/
- shadcn MCP: https://ui.shadcn.com/docs/mcp
- SmoothUI: https://smoothui.dev/
- Animate UI MCP: https://animate-ui.com/docs/mcp
- Animate UI accessibility: https://animate-ui.com/docs/accessibility
- Magic UI MCP: https://magicui.design/docs/mcp
- Aceternity registry ve MCP: https://ui.aceternity.com/components/cli
- React Bits MCP: https://reactbits.dev/get-started/mcp
- 21st.dev MCP: https://21st.dev/mcp
- Google JavaScript SEO: https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics
- Google crawling ve indexing: https://developers.google.com/search/docs/crawling-indexing
- Google geçici site kapatma politikası: https://developers.google.com/search/docs/crawling-indexing/pause-online-business
- Google localized versions ve hreflang: https://developers.google.com/search/docs/specialty/international/localized-versions
- Google structured data başlangıç: https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data
- Google people-first content: https://developers.google.com/search/docs/fundamentals/creating-helpful-content
- Google AI search features: https://developers.google.com/search/docs/appearance/ai-features
- Web Vitals: https://web.dev/articles/vitals

## 27. Son mimari özeti

```text
Laravel
  -> Sayfa registry
  -> Türkçe içerik
  -> Yayın durumu
  -> Medya
  -> Kilitli çeviri altyapısı
  -> Public content API

Astro
  -> Catch-all route
  -> PageGate
  -> Server-rendered template
  -> Metadata ve schema
  -> Sitemap ve internal links

React islands
  -> Yalnızca seçili etkileşimler
  -> SmoothUI / Animate UI / Magic UI
  -> Performans ve erişilebilirlik kapısı

Page status
  -> published ise gerçek sayfa
  -> yayınlanmamışsa doğru HTTP ile Zabuno Service Pass
  -> maintenance ise 503 + Retry-After
```

Son karar: Yeni public site için Astro + Laravel headless yapı kullanılmalı; bütün sayfalar merkezi registry ve PageGate üzerinden yönetilmeli; SmoothUI efekt kaynakları içinde birinci sıraya alınmalı fakat temel tasarım sistemi yapılmamalı; çeviri altyapısı kurulmalı fakat kullanıcı açıkça `ÇEVİRİLERE BAŞLA` demeden hiçbir gerçek çeviri aksiyonu çalışmamalıdır.
