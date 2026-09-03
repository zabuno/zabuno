# 100 — Kamu sayfaları (frontpages) ve masterpage planı

**Durum:** Faz 1 ✅ (FF-72, 2026-09-04). Sayaç: **1/4 tamamlandı, 2/4 aktif.**
**Kapsam:** kaydolmamış ziyaretçinin gördüğü her sayfa: `/`, `/pricing`,
`/help`, `/contact`, `/terms`, `/privacy`, `/kvkk`. Misafir menüsü (`/menu/*`)
**bu planın dışındadır** — orası restoranın yüzeyi, burası ürünün yüzeyi
(`docs/85`).
**Kanonik komşular:** URL/SEO motoru `docs/38`, ek plan `docs/39`; sayfa
kimliği `docs/89`; fiyat/iletişim `docs/88`; shell (uygulama içi) `docs/50`;
üst-yönetici estetiği `docs/99`; program sayacı `docs/98`.

---

## 0. Neden bu belge — ölçülen boşluk

`docs/98` Tur 6: "5 Blade sayfası var; plan/maturity belgesi yok; header/footer
masterpage sözleşmesi yok; Flowbite yalnız uygulama içinde." Ölçüm doğruydu:

| Ölçüm (2026-09-04, FF-72 öncesi) | Değer |
| --- | --- |
| Kamu sayfası | 7 rota, 5 şablon (`home`, `pricing`, `help`, `contact`, `legal`) |
| Ortak masterpage | `public/layout.blade.php` — header ve footer **şablonun içinde**, parça değil |
| Header'daki gezinti | 5 bağlantı; 4'ü ana sayfa çıpası, `/pricing` ve `/help` gerçek sayfa olduğu hâlde gezintide yok |
| Footer | yasal 3 bağlantı + "Contact"; Help yok |
| Gezinti metni | Blade'e gömülü İngilizce (`layout.blade.php` çevrilemez borç: 14) |
| Mühendislik satırı | "16/16 modules registered" **ziyaretçiye görünür** paragraf |
| Sayfa kimliği | ✅ `<title>`, description, canonical, og (`docs/89` kapısı) |
| Akışkan düzen | ✅ kırılma noktası jetonu yok (`HOME-FLUID-04`) |
| React paketi | ✅ yüklenmez (`docs/38` §16) |

Bu belge o boşluğu iki şeyle kapatır: bir **sözleşme** (masterpage) ve bir
**olgunluk cetveli** (L0–L4), her sayfanın bugün hangi seviyede olduğu
yazılı.

---

## 1. Bilgi mimarisi

```
/                 Ana sayfa — ürün nedir, kime, nasıl çalışır, fiyat, SSS, iletişim
/pricing          Fiyat — plan kataloğundan (docs/88), kaydolmadan görülür
/help             Yardım — ilk 15 dakika makalesi; okuyucunun dilinde (docs/89)
/contact          İletişim — mesaj saklanır, teyit ekranda (docs/88)
/terms /privacy /kvkk   Yasal — hukuk incelemesi bekliyor; bunu SÖYLER, taklit etmez
/login /register  Hesap — auth şablonları (bu planın dışında, docs/70)
/app              Uygulama — React shell (docs/50)
```

**Kurallar**

1. **Available before visible** (`docs/64` §4): gezintide yalnız arkasında
   gerçek içerik olan sayfa durur. Help gezintiye bugün giriyor, çünkü
   `resources/help/{en,tr}/first-15-minutes` gerçek bir makale. `docs/61` A12
   bu yüzden ⛔ → ✅ olur.
2. **Çıpa ≠ sayfa.** Ana sayfa bölümleri (`#features`, `#how-it-works`,
   `#pricing`, `#faq`, `#contact`) aynı belgedeki gerçek başlıklardır —
   fragment'ın meşru kullanımı (`docs/38` §4). Başka sayfadan ana sayfa
   bölümüne bağlantı `/#faq` biçimindedir. Gerçek sayfası olan şeye
   (Pricing, Help, Contact) gezinti **sayfayı** gösterir, çıpayı değil.
3. **Tek H1**, tek `<main id="main-content">`, atlama bağlantısı — her sayfada
   (`PublicPageIdentityTest`, `PublicHomeContractTest`).
4. **Sunucuda üretilir, React yüklemez** (`docs/38` §16). Etkileşim yalnız
   formda (iletişim) ve tema anahtarında.

---

## 2. Masterpage sözleşmesi — header / footer

Masterpage üç parçadır: `public/layout.blade.php` (belge iskeleti, `<head>`,
atlama bağlantısı, `@yield`), `public/partials/header.blade.php`,
`public/partials/footer.blade.php`. Sayfa şablonları yalnız `@section`
doldurur; header/footer'a **dokunamaz**.

### Header

| Bölge | İçerik | Kural |
| --- | --- | --- |
| Marka | "Zabuno" → `/` | Metin logo; görsel logo geldiğinde `alt` = marka adı |
| Birincil gezinti (`nav[aria-label=Primary]`) | Features `#`, How it works `#`, Pricing `/pricing`, Help `/help`, Contact `/contact` | Çıpalar yalnız ana sayfada çıpa, diğer sayfalarda `/#…`; gerçek sayfalar her yerde gerçek yol |
| Hesap eylemleri | Log in `/login`, Create account `/register` | Ana sayfada ayrıca "Open workspace app" `/app` (mevcut sözleşme) |
| Dil | Tarayıcı dili (`Accept-Language`) | Görünür dil seçici L2'de (bkz. §4) |

### Footer

| Bölge | İçerik |
| --- | --- |
| Ürün (`nav[aria-label=Product]`) | Pricing, Help, Contact |
| Yasal (`nav[aria-label=Legal]`) | Terms, Privacy, KVKK |
| Marka satırı | © yıl Zabuno + tek cümlelik slogan (katalogdan) |

### Sözleşme maddeleri (test: `PublicMasterpageContractTest`)

- **MP-01** Her kamu sayfasında tam bir `<header>` ve tam bir `<footer>` var.
- **MP-02** Header/footer HTML'i sayfalar arasında **aynıdır** (çıpa öneki
  dışında) — masterpage tek kaynaktır.
- **MP-03** Gezinti metinleri **katalogdan** gelir (`site.nav.*`,
  `site.footer.*`); Türkçe tarayıcı Türkçe okur.
- **MP-04** Mühendislik satırı ("N/16 modules registered") ziyaretçiye
  **görünmez**; `<meta name="zabuno-build">` olarak kalır (kayıt sözleşmesi
  `FoundationStatusDeliveryArchitectureTest` bozulmaz — esnetilen kural,
  `docs/98` §6).
- **MP-05** Hiçbir kamu sayfasında kırılma noktası jetonu yok (`sm:`…);
  düzen 320 px'ten akışkan.
- **MP-06** Gezintideki her yol 200 döner (ölü bağlantı yasağı, `docs/64`).

---

## 3. Flowbite eşlemesi — Blade tarafında ne demek

ADR-L06 "Flowbite React birincil kütüphane" kuralı **React yüzeyleri** için
(`docs/38` §16). Kamu sayfaları React yüklemez; Flowbite burada **sınıf
sözlüğü ve token** olarak kullanılır — aynı Tailwind katmanı
(`resources/css/app.css` → `flowbite-react/plugin/tailwindcss`), aynı
semantik token'lar (`bg-surface`, `text-fg`, `border-border`, `bg-action`).

| Uygulama içi (Flowbite React) | Kamu sayfası karşılığı | Not |
| --- | --- | --- |
| `Navbar` | `partials/header.blade.php` | JS yok; menü daralmaz, sarar (`flex-wrap`) |
| `Footer` + `Footer.LinkGroup` | `partials/footer.blade.php` | iki `nav` + marka satırı |
| `Button` (`color="blue"`) | `a.rounded.bg-action.text-white` | aynı token, aynı ölçü |
| `Card` | `section.rounded-lg.border.border-border.bg-surface` | fiyat kartları |
| `Accordion` | `<details>/<summary>` | SSS — JS'siz, erişilebilir |
| `TextInput`/`Textarea` | iletişim formu alanları | aynı odak halkası token'ı |
| `Alert` | `p[role=status]` / `p[role=alert]` | teyit ve hata |

**Kural:** kamu sayfasına Flowbite JS **eklenmez**. Etkileşim gerekiyorsa
önce `<details>`, sonra `<form>`; o da yetmiyorsa o sayfa artık kamu sayfası
değildir, uygulamaya taşınır.

---

## 4. Olgunluk seviyeleri (maturity) — sayfa başına

| Seviye | Tanım | Ölçü |
| --- | --- | --- |
| **L0 Statik** | Sunucuda üretilir, kimliği tam (title/description/canonical/og), tek H1, akışkan | `PublicPageIdentityTest`, `HOME-FLUID-04` |
| **L1 Masterpage** | Header/footer parçadan, metin katalogdan, ölü bağlantı yok | `PublicMasterpageContractTest` |
| **L2 Yerelleştirilmiş** | Sayfa gövdesi de katalogdan; görünür dil seçici; `hreflang` | `lang/untranslatable-debt.json` o dosya için 0; `docs/39` Faz 1 |
| **L3 Ölçülen** | Sayfa başına dönüşüm olayı (`register_started`, `contact_sent`, `pricing_viewed`) tenant-bağımsız GA4/Metrica'da; LCP bütçesi | `partials/analytics` bağlamı + `docs/42` bütçe |
| **L4 Kişiselleştirilmiş** | Oturum açmış ziyaretçiye "Open workspace"; ülkeye göre para birimi; A/B (Pennant) | FF-74 (Pennant) sonrası |

### Bugün (FF-72 sonrası)

| Sayfa | L0 | L1 | L2 | L3 | L4 |
| --- | --- | --- | --- | --- | --- |
| `/` | ✅ | ✅ | 🔶 gövde İngilizce gömülü (borç 29) | ⬜ | ⬜ |
| `/pricing` | ✅ | ✅ | ✅ (katalog, `docs/88`) | ⬜ | ⬜ |
| `/help` | ✅ | ✅ | ✅ (dosya başına dil, `docs/89`) | ⬜ | ⬜ |
| `/contact` | ✅ | ✅ | ✅ | 🔶 `contact_sent` yok | ⬜ |
| `/terms` `/privacy` `/kvkk` | ✅ | ✅ | ⬜ hukuk metni yok (bekliyor) | — | — |

---

## 5. Fazlar

### Faz 1 — Masterpage ✅ (FF-72)
Header/footer parça; katalog anahtarları (`site.nav.*`, `site.footer.*`,
`site.skipToContent`, `site.footer.tagline`); Help ve Pricing gezintide;
mühendislik satırı meta'ya; sözleşme testi. `layout.blade.php` çevrilemez
borcu 14 → 1 (yalnız marka adı).

### Faz 2 — Ana sayfa gövdesi katalogdan (L2)
`home.blade.php` 29 dize → `site.home.*`; görünür dil seçici (header,
`?lang=` **değil** — `docs/38` §6: dil, sorgu parametresi değil, yol ya da
tercih); `hreflang` (`docs/39` Faz 1). Kapı: `byFile['public/home.blade.php'] = 0`.

### Faz 3 — Ölçüm (L3)
Her kamu sayfasına `zabuno_page` bağlamı ve üç dönüşüm olayı; LCP bütçesi
kamu sayfaları için `docs/42`'ye eklenir. Kapı: GA4 DebugView'da üç olay.

### Faz 4 — Kişiselleştirme (L4)
Oturum tespiti (yalnız çerez var/yok — kimlik okunmaz), ülke → para birimi
(fiyat kataloğu zaten çoklu para birimi taşıyor), Pennant bayrakları
(FF-74). Kapı: aynı sayfa iki ziyaretçiye iki dürüst hâl gösterir, ikisi de
sunucuda üretilir.

---

## 6. Bu plan neyi YAPMAZ

- Misafir menüsünü değiştirmez (`docs/85`, `docs/38` §4b).
- Blog/changelog/status sayfası **eklemez** — arkasında içerik yok; ilk
  gerçek içerik günü gezintiye girer (`docs/64` §4).
- Yasal metin yazmaz — hukuk incelemesi sahibin dış kararı.
- Uygulama shell'ine (`/app`) dokunmaz — o `docs/50`.

## 7. Kullanıcı yolculuğu

Adana'dan bir kebapçı telefonunda `zabuno.com`'u açar → üstte "Fiyat" ve
"Yardım" görür (Türkçe, çünkü telefonu Türkçe) → Fiyat'a basar, kaydolmadan
rakamı okur → Yardım'a basar, "ilk 15 dakika"yı Türkçe okur → altta
"İletişim"e basar, soru yazar, ekranda "gönderildi" görür. Hiçbir adımda
kaydolması istenmedi; hiçbir bağlantı ölü değildi; hiçbir sayfa "16/16
modules registered" gibi anlamadığı bir satır göstermedi.
