# 50 — Shell mimarisi: fazlanmış geliştirme planı

**Durum:** Plan. Ölçüm yapıldı, kod yazılmadı.
**Requirement ID:** `SHELL-v2`
**İlgili:** `docs/36` (külliyat, MASTER), `docs/38` (URL), `docs/44`, `docs/46`,
`docs/47` (form), `docs/48` (320px), `docs/49` (medya)

---

## 1. Ana karar

> **Tek bir "masterpage" yok.** Ortak bir shell sistemi, farklı görevler için
> ayrı page layout'ları ve yalnız gerektiğinde açılan bağlamsal paneller.

Sebebi kapsam hiyerarşisidir:

```
Hesap → Organizasyon/Tenant → Workspace/Marka → Lokasyon → Nesne
Tolga  → müşteri şirketi     → Olga            → Kadıköy  → Ana Menü → Avokadolu Tost
```

Bu seviyeler karışırsa "Hesabım" menüsü bir çekmeceye dönüşür: profil, organizasyon,
fiyatlandırma, dokümantasyon, dil, faturalama ve tema aynı yere yığılır.

### Kavramlar aynı şey değildir

| Kavram | Sorumluluğu | Bizdeki karşılığı |
| --- | --- | --- |
| Document root | HTML, tema başlangıcı, mount | `resources/views/workspace-app.blade.php` |
| App root | Auth, i18n, tema, sağlayıcılar | `resources/js/workspace.tsx` + `ThemeRoot` |
| App shell | Header, ana gezinti, main | `catalog/layout/macro/AdminShell.tsx` |
| Page layout | Standart / editör / analytics / ayarlar | **YOK** — hepsi tek yerleşim |
| Page template | Liste / detay / form / sihirbaz | **YOK** |
| Context inspector | Seçili nesnenin ikincil ayarları | **YOK** |
| Drawer / dialog | Geçici görevler | `DrawerPanel` var |

---

## 2. Ölçüm — 19 kabul ölçütü karşısında bugün

| # | Ölçüt | Bugün | Kanıt |
| --- | --- | --- | --- |
| 1 | Hash gezintisi yok | ✅ | `docs/46`, 2026-08-27 |
| 2 | Tenant / platform / engineering shell ayrı | ⚠️ | Tenant ve platform ayrı; **engineering platformun içinde bir bölüm**, kendi shell'i yok |
| 3 | Aynı anda üç kalıcı sol rail yok | ✅ | Tek `<aside>` |
| 4 | Sidebar üstte workspace, altta account | ❌ | Account bir nav GRUBU; üstte switcher yok |
| 5 | Account yalnız kişisel + oturum | ❌ | Yalnız "Switch workspace" + "Log out"; profil, görünüm, dil yok |
| 6 | Org/plan/billing/team account'a gömülü değil | ⚠️ | Gömülü değil ama **Billing ve Brand ana menüde** — yönerge Settings altına istiyor |
| 7 | Çalışmayan search/notifications yok | ✅ | 2026-08-27 kaldırıldı |
| 8 | Command center: search / command / AI ayrı | ❌ | Yalnız "AI command center" var; deterministik arama ve komut yok |
| 9 | Editör: ana bilgi canvas, ileri ayar inspector | ❌ | Editör yerleşimi yok |
| 10 | Inspector mobilde ayrı sheet/route | ❌ | Inspector yok |
| 11 | Publication state / lifecycle / command ayrı | ❌ | `menus.state` tek sütun (`draft`) |
| 12 | Tenant'ta kalıcı telif footer yok | ❌ | `AdminFooter` her sayfada `© {yıl}` basıyor |
| 13 | Tema account tercihinde | ⚠️ | Sabitlenmesi kaldırıldı; account menüsüne **taşınmadı** |
| 14 | Sağlayıcı yoksa boş AI kartı yok | ❌ | **6 sayfada** "No real AI is connected yet" |
| 15 | AI aksiyon anatomisi tanımlı | ❌ | Yok |
| 16 | Tek tık yalnız düşük riskli/geri alınabilir | ⚠️ | Menü formu düzeldi; risk matrisi yok |
| 17 | Skip link, focus, landmark, klavye | ✅ | `SkipLink`, `main tabIndex=-1`, `aria-current` |
| 18 | Geliştirilmemiş özellik navigasyonda yok | ⚠️ | Düzeldi ama sözleşme yazılı değil |
| 19 | Sidebar/inspector/account/command için ayrı mobil davranış | ⚠️ | Çekmece var; alt gezinti, account sheet, tam ekran command yok |

**Yeşil 4, sarı 7, kırmızı 8.**

---

## 3. Shell ailesi

```
AppRoot
├── PublicShell          → tanıtım, fiyat, yardım, QR menü      (bugün: public/layout.blade)
├── AuthShell            → giriş, kayıt, doğrulama              (bugün: auth.tsx)
├── OnboardingShell      → ilk workspace/lokasyon/menü          (bugün: TenantShell içinde)
├── TenantShell          → restoran paneli                      (bugün: AdminShell)
│   ├── StandardPageLayout
│   ├── CollectionLayout
│   ├── ListDetailLayout
│   ├── EditorLayout
│   ├── AnalyticsLayout
│   └── SettingsLayout
├── PlatformAdminShell   → tenant, plan, ödeme, destek          (bugün: PlatformApp)
└── EngineeringShell     → release readiness, güvenlik kanıtı   (bugün: platformun içinde)
```

Böylece restoran yöneticisi, Zabuno finans çalışanı ve geliştirici **aynı
masterpage içinde yaşamaz**.

---

## 4. Rail terminolojisi — karar

"Rail 1/2/3" evrensel kavram değildir; anlamlı ad verilir.

| Katman | Doğru adı | Zabuno kararı |
| --- | --- | --- |
| Rail 1 | Suite rail | **Kullanma.** Zabuno tek üründür; suite rail mimari gösteriş olur |
| Rail 2 | Primary workspace sidebar | **Kullan** — bugün var |
| Rail 3 | Local navigation pane | **Yalnız gerektiğinde**, sayfa içinde (tabs) |
| Sağ panel | Context inspector | **Editörlerde** |
| Sağ AI | Assistant panel | İsteğe bağlı |

**Üç kalıcı sol rail yok.** Carbon üç navigasyon katmanını desteklemez; daha
derin seviye için sayfa içi sekme önerir.

### Ölçüler (`docs/48` ile uyumlu — token olarak yazılır, ham piksel değil)

| Bölge | Hedef |
| --- | --- |
| Global header | 56–64 px |
| Primary sidebar | 248–272 px |
| Collapsed rail | 64–72 px |
| Context inspector | 336–400 px |
| Editör ana alanı | ≥ 640 px |
| Gezinti satırı | ≥ 44 px |

`docs/44` §3 gereği bunlar `--shell-*` semantic token olarak yayınlanır;
bileşen ham piksel bilmez.

---

## 5. Hedef bilgi mimarisi

```
Primary
  Home            (bugün: Dashboard)
  Menus           (bugün: Menu)
  QR codes        (bugün: Publication içinde)
  Insights        (bugün: Analytics)
Management
  Locations
  Media
  Team
Utility
  Settings        ← YENİ
  Account         ← sidebar altı trigger
```

### Ana sidebar'dan çıkacaklar

| Bugün | Yeni yeri |
| --- | --- |
| Brand | Settings → Brand |
| Publication | Menus → seçili menü → Preview & Publish |
| Billing | Settings → Plan & Billing |
| Switch workspace | Sidebar ÜSTÜ — workspace switcher |
| Log out | Account menüsü |

Billing günlük operasyon değildir; ana menüde kalıcı yer işgal etmez. Plan
sorunu varsa Home'da bağlamsal uyarı çıkar.

---

## 6. Fazlar

### Faz 1 — Temizlik (görünür, düşük risk)
1. **6 sayfadaki boş AI kartını kaldır.** Sağlayıcı yokken "No real AI is
   connected yet" göstermek, kullanıcıya değer değil geliştirilmemiş özellik
   gösterir
2. **Tenant footer'ını kaldır.** `AdminFooter` yalnız Public/Auth shell'de
   kalır; yasal bağlantı ve sürüm Account → About'a taşınır
3. **Tema seçiciyi Account menüsüne taşı** (bugün akışta duruyor, yeri değil)
4. Account'u nav grubu olmaktan çıkar → sidebar altı **trigger + popover**
   (Radix DropdownMenu, `side="top"`, `avoidCollisions`)
5. Sidebar üstüne **workspace switcher**; tek workspace varken sahte seçim
   ekranı gösterme

**Kabul:** Tenant kabuğunda telif footer yok, boş AI kartı yok, account
popover'da profil/görünüm/dil/çıkış var.

### Faz 2 — Navigation registry (tek kaynak)
Bugün `WorkspaceSectionRegistry` yalnız sidebar'ı besliyor. Genişletilir:

```
id, labelKey, route, scope, group, permission, entitlement,
featureFlag, prerequisites, commandKeywords, mobilePlacement
```

Aynı registry: sidebar + mobil gezinti + command palette + breadcrumb +
yetki filtresi. **Görünürlük UX'tir; nihai yetki Laravel policy/gate'tedir.**

**Kabul:** Bir hedefin adı, yolu ve görünürlüğü tek dosyada tanımlı.

### Faz 3 — Sidebar görünürlük sözleşmesi
| Özellik durumu | Davranış |
| --- | --- |
| Geliştirilmedi | Gizle |
| Geliştiriliyor | Yalnız dev/staging |
| Beta | Feature flag (Laravel Pennant) |
| Yetki yok | Gizle |
| Planda yok, satın alınabilir | Plan etiketiyle göster |
| Ön koşul eksik | Göster + yönlendirici boş durum |
| Veri yok | Göster + doğru boş durum |

**"Yakında" ve devre dışı gezinti öğesi yasak.**

### Faz 4 — Page layout ve template kataloğu
Her ekranı sıfırdan tasarlamak yerine:

| Template | Kullanım | Inspector |
| --- | --- | --- |
| Overview | Home | Yok |
| Collection | Menus, Locations, Team | Seçime göre drawer |
| List-detail | Media, Products | Detay paneli |
| Editor | Product, Menu | Var |
| Settings | Brand, Billing, Preferences | Local settings nav |
| Analytics | Insights | Yok |
| Task flow | Onboarding, import | Yok |
| Review | Publish, toplu değişiklik | Değişiklik özeti |

Her template için ayrı tasarlanacak durumlar: loading, empty, populated,
partial, error, permission denied, **plan restricted**, prerequisite blocked,
success. (Plan-restricted bu turda Analytics'te kuruldu — desen oradan alınır.)

### Faz 5 — İki katmanlı header
**Global header** (her sayfada): sidebar toggle, ürün kimliği, workspace +
lokasyon bağlamı, omnibox, Create, Help, Account.
**Page header** (route'a göre): breadcrumb (yalnız gerçek hiyerarşi varsa),
başlık, durum, açıklama, birincil eylem, filtreler/sekmeler.

Bugün ikisi karışık: breadcrumb üst düzey ekranlarda bile `Olga / Media`
gösteriyor — workspace zaten header'da.

### Faz 6 — Omnibox: search, command, AI ayrı modlar
```
Cmd/Ctrl + K → Search | Go to | Create | Run command | Ask Zabuno
```
**Varsayılan mod deterministiktir.** Arama sorgusu sessizce AI prompt'una
dönüşmez. Üstte kapsam görünür: `Olga / Kadıköy / Ana Menü / 8 seçili ürün`.

Riskli komutlar (publish, delete, toplu fiyat, yetki, faturalama) palette'ten
**doğrudan çalışmaz** — inceleme yüzeyine gider.

### Faz 7 — Editör yerleşimi ve context inspector
```
Global header
─────────────────────────────────────────────────
Primary sidebar │ Main content / editor │ Inspector
─────────────────────────────────────────────────
              Contextual save/status bar
```
Ürün editörü — **canvas:** ad, fiyat, açıklama, birincil görsel, varyant,
alerjen, uygunluk. **Inspector:** durum ve görünürlük, kategori, lokasyon,
dil, SEO, zamanlama, revizyon, AI önerileri.

**Inspector zorunlu değildir**: temel iş ona bağımlı olamaz (mobilde gizli).
Birincil görsel yalnız inspector'da bırakılmaz.

### Faz 8 — Durum ve komut ayrımı
```
Publication state : draft in_review approved scheduled published archived
Lifecycle state   : active trashed deleted
Commands          : Save draft, Submit, Approve, Publish now, Schedule,
                    Unpublish, Archive, Duplicate, New version, Trash,
                    Restore, Delete permanently
```
Bugün `menus.state` tek sütun. Kullanıcı "version 2"yi bir form alanına
yazmaz; sistem yayın geçmişini yönetir. (`docs/49` Faz 5 ile aynı omurga.)

### Faz 9 — Responsive shell
| Genişlik | Davranış |
| --- | --- |
| ≥1440 | Açık sidebar, geniş main, kalıcı inspector |
| 1024–1439 | Katlanabilir sidebar, inspector overlay |
| 768–1023 | Rail veya drawer, inspector overlay |
| ≤767 | Üst app bar, **alt gezinti (3–5 hedef)**, account bottom sheet, command tam ekran, inspector tam ekran sheet |

`docs/48` gereği eşikler kapsayıcı sorgusuyla; ham breakpoint yalnız
gerçekten ekrana ait kararlarda ve gerekçesiyle.

### Faz 10 — AI-first shell
Beş yüzey, beşi de bağlamlı:

| Yüzey | Ne zaman | Zabuno örneği |
| --- | --- | --- |
| Inline AI action | Küçük, açık görev | Ürün açıklaması yaz |
| Command center | Global niyet | Menüdeki eksikleri bul |
| Assistant sidebar | Süren bağlamsal çalışma | Seçili menüyü analiz et |
| Full-page AI create | Büyük üretim | PDF'den menü oluştur |
| Proactive suggestion | **Gerçek sinyal varsa** | 8 eksik çeviri bulundu |

**AI aksiyon anatomisi** (`docs/47` Kural 10 ile aynı):
```
Niyet → yorum → kullanılan bağlam → önerilen plan → etkilenen kayıtlar
→ önizleme/diff → onay → uygulama → sonuç → denetim → geri alma
```

Sağlayıcı yoksa: **temel görev çalışmaya devam eder, AI giriş noktası
gizlenir.** AI rengi/rozeti dekorasyon olarak kullanılmaz.

---

## 7. Bu plan neyi UYGULAMAYACAK

```
Suite rail
Üç kalıcı sol navigation katmanı
Tenant'ta global footer
Global sabit tema seçici
Her sayfada AI assistant kartı
Bitmemiş özellik için devre dışı navigasyon
Publication için ayrı global modül
Tenant içinde engineering readiness
Account menüsünde bütün tenant ayarları
```

---

## 8. "Üç tıktan bir tıka" — doğru okuma

"Her iş tek tık olmalı" bir standart değildir. Doğru hedef:

> Gereksiz navigasyonu, tekrarlanan kararları, yeniden veri girişini ve
> beklemeyi kaldır; **riskli işlemlerde gerekli sürtünmeyi koru.**

Tek tık kapısı — hepsi sağlanmalı: bağlam açık, işlem sık, sonuç
öngörülebilir, risk düşük, geri alınabilir, yetki belli, geri bildirim hızlı.

| İşlem | Akış |
| --- | --- |
| Ürünü gizle / stok değiştir | Tek tık + Undo |
| Tek üründe fiyat | Inline edit + autosave |
| Menüyü yayımla | Otomatik preflight + açık publish |
| 40 üründe fiyat | Değişiklik özeti + onay |
| Rol değiştir / ödeme / kalıcı silme | Açık inceleme, gerekirse re-auth |

---

## 9. Sahibinin kararı gereken noktalar

| # | Karar | Neden |
| --- | --- | --- |
| 1 | Organizasyon ile Workspace ayrı kavram mı? | Veri modelinde aynı değilse UI'da tek switcher altında belirsiz bırakılamaz |
| 2 | QR codes ayrı ana menü mü, Menus altında mı? | Bilgi mimarisi; bugün Publication içinde |
| 3 | EngineeringShell ayrı rota mı (`/engineering/*`), platformun içinde mi? | Yetki ve deployment yüzeyi |
| 4 | Mobilde alt gezinti hangi 3–5 hedef? | Faz 9 |
| 5 | AI sağlayıcı ne zaman bağlanacak? | Faz 10 tetikleyicisi |

Faz 1–4 bu kararları beklemez; Faz 5 sonrası bekler.
