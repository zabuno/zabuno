# 03 — Architecture Decisions

**PLANNING ONLY.** Bu doküman bağlayıcı ADR'lerin özet indeksidir; her karar için
tam ADR `templates/ADR.md` şablonuyla genişletilebilir (M1'de gerekirse ayrı
dosyalara bölünür — bkz. `AGENTS.md` §2 tek kanonik sahiplik kuralı).

## ADR-L01: Laravel modular monolith, finite Kernel

**Karar**: Laravel tam modular monolith. Kernel sonlu ve sabittir (CORE-01..16,
bkz. `docs/04`); zorunlu Core modüller kaldırılamaz; business modülleri tak-çıkar.
**Gerekçe**: Tek deployment birimi, shared-host uyumu, modül yaşam döngüsü
(install/enable/disable/upgrade/uninstall/data-retention) tek registry üzerinden
yönetilebilir. Mikroservise geçiş bu aşamada gerekçesizdir (kanıt yok →
`docs/16` ARCH-03, "ne zaman mikroservis?" sorusu açık bırakılmıştır).
**Durum**: koşullu — Laravel'in modüler paket/discovery mekanizmasına resmi
kaynak erişimi doğrulanmıştır (bkz. `docs/28-SOURCE-REGISTER.md`, "Laravel —
providers/packages" satırı), ancak bu erişim doğrulaması tek başına
**kanıtlanmış**a yükseltmez; production-proven sayılması resmi kaynağın canlı
doğrulaması + bir compatibility spike sonucunu gerektirir (`docs/28` §sınıf
tanımı).

## ADR-L02: Onion dependency yönü + MVC delivery pattern

**Karar**: `Domain ← Application ← Infrastructure/Adapters ← Delivery`. Delivery
katmanı **MVC**'dir, MVVM **seçilmemiştir**. React, View katmanının istemci tarafı
render motoru olarak konumlanır; Laravel controller yalnızca HTTP isteğini bir
use-case'e adapte eden ince bir katmandır.
**Gerekçe**: MVVM'nin two-way binding/ViewModel state senkronizasyonu server-side
Laravel + client-side React ayrımında karşılığı yoktur; MVC + Onion, controller'ı
"fat" yapmadan (iş kuralı Domain'de) React tarafını "akıllı View" olarak tutar.
**Sınır**: Domain katmanında Laravel bağımlılığı **yasaktır** (Eloquent, Facade,
Request nesnesi Domain'e sızmaz — Infrastructure/Adapters'ta adaptörlenir). Fat
controller/fat model yasak. React'te iş kuralı yasak (React yalnız sunum + client
state, `docs/10` ECA motorunun kararlarını *uygular*, kendi kural motoru içermez).

## ADR-L03: Strict OOP

**Karar**: `declare(strict_types=1)`, value object'ler (Money, Slug, EmailAddress
vb.), interface/port'lar, aggregate root'lar, sınıflar varsayılan olarak `final`.
**Gerekçe**: Domain modelinin kazara genişletilmesini/override edilmesini önler;
port/adapter sınırını netleştirir (test edilebilirlik + modül izolasyonu).

## ADR-L04: Modül registry/manifest + lifecycle

**Karar**: Her modül bir manifest sunar (isim, versiyon, bağımlılıklar, izinler,
route'lar, migration'lar, frontend entry, ayarlar, entitlement, event'ler, job'lar,
health check). Yaşam döngüsü: install → enable → disable → upgrade → uninstall,
ayrı olarak data-retention politikası.
**Kritik ayrım**: **deployment-level install** (kod sunucuda var mı) ile
**tenant-level entitlement/enable** (bu tenant bu modülü kullanabiliyor mu) iki
farklı katmandır — biri DevOps/release kararı, diğeri plan/entitlement kararı
(`docs/09`). Disable veri **silmez**; yalnız route/menü/API/job üretimini durdurur,
veriyi arşivlenmiş halde korur. `Disable` ile `Purge` aynı işlem değildir.
**Kütüphane adayı**: `nwidart/laravel-modules` yalnız **opsiyonel scaffolding**
adayıdır (klasörleme kolaylığı) — mimarinin temeli **değildir**; temel, Laravel'in
kendi service provider + package discovery mekanizmasıdır. Compatibility spike
gerekmeden pinlenmez (koşullu sınıf).

## ADR-L05: Cross-module iletişim yalnız contract/port/event

**Karar**: Bir modül başka bir modülün iç tablosuna veya sınıfına **doğrudan
erişemez**. İzin verilen tek iletişim yolları: public contract/port (interface),
domain event + event outbox, read model (projeksiyon). **Anti-pattern**: Modül A'nın
Eloquent modelini Modül B'nin doğrudan import edip query'lemesi.
**Gerekçe**: Modül disable/uninstall edildiğinde diğer modüllerin kırılmaması;
bağımlılık grafiğinin (`docs/04` §Dependency Graph) doğrulanabilir kalması.

## ADR-L06: Frontend — React + Vite, Flowbite first, Next.js kesin yasak

**Karar**: React + TypeScript + Vite. Flowbite React birincil bileşen kütüphanesi;
shadcn/ui **source-owned** (kod projeye kopyalanır, npm bağımlılığı değil) ikincil;
Radix/headless yalnız Flowbite/shadcn'de eksik erişilebilir primitive'ler için
adapter katmanından kullanılır. **Next.js hiçbir amaçla kullanılmaz** (App Router,
Node runtime, SSR/ISR dahil) — bu proje Node runtime'a bağımlı bir prod dağıtım
istemez (shared-host uyumu, ADR-L08).
**Duplicate önleme politikası**: Aynı UI primitive'i (örn. Dialog) iki farklı
kütüphaneden kurulmaz; token/CSS çakışması tespit edilirse adapter katmanında
normalize edilir, iki paralel tasarım sistemi oluşmasına izin verilmez.
**Filament kullanılmaz** — restoran paneli için ikinci bir UI stack'i yaratmak
yasaktır; superadmin dahil tüm paneller React üzerindedir (eski dokümandaki
Filament önerisi bilinçli olarak terk edilmiştir, bkz. `docs/00` §4 taşınmayanlar).

## ADR-L07: Public SEO envelope — Laravel SSR shell + React progressive enhancement

**Karar**: Public menü ve kurumsal sayfalar için Laravel, semantik HTML +
meta/JSON-LD içeren bir SSR "shell" üretir; React bunun üzerine progressive
enhancement uygular (yayın snapshot'ından hidrate). **Node runtime prod'da yok**,
Next.js yok.
**Gerekçe**: SEO/AEO/GEO gereksinimleri (`docs/12`) sunucu tarafı ilk render
gerektirir; ancak tam bir Node SSR sunucusu shared-host kısıtına (ADR-L08) aykırıdır.

## ADR-L08: No Docker, shared-host default

**Karar**: Varsayılan dağıtım container'sız, shared-host uyumludur: yerel
dosya sistemi, DB tabanlı cache/queue, cron scheduler, önceden derlenmiş (prebuilt)
frontend asset'leri. Redis ve S3 **opsiyonel adaptörlerdir**, varsayılan değildir.
**Kapasite matrisi**: Imagick/ffmpeg/`exec`/`proc_open`/symlink/worker
yeteneklerinin var olup olmadığı host'a göre değişir; her özellik bu yetenekler
yokken **zarif biçimde düşer** (graceful degradation) — hard-fail yasak
(`docs/15` §Shared-Host Capability Matrix, `skills/shared-host-capability`).

## ADR-L08a: Docker ek dağıtım profilidir (ADR-L08'i daraltır, 2026-08-27)

**Karar (owner)**: Birincil dağıtım hedefi **netcup VPS, AMD EPYC, Docker
Compose, PostgreSQL** olur. ADR-L08 iptal EDİLMEZ: shared-host profili
desteklenmeye devam eder ve varsayılan olarak kalır.

**Neden kayda geçti**: ADR-L08'in metni Docker'ı yasaklamıyordu — "varsayılan
dağıtım container'sız, shared-host uyumludur" diyordu. Ama onu zorlayan
ön kontrol, Docker dosyalarının VARLIĞINI yasağa çevirmişti. Sahibi VPS
dağıtımına karar verdiğinde kapı meşru bir kararı engelledi; üstelik korumak
için var olduğu şeyi ölçmüyordu.

**Korunan şey**: shared-host yolunun açık kalması. Kapı artık bunu ölçüyor —
varsayılan profil Redis'e ya da bir container servis adına bağlanamaz.
Docker dosyasının varlığı serbest; uygulamanın Docker olmadan çalışamaz hâle
gelmesi yasak.

**Sonuç**: iki profil birlikte yaşar.

| Profil | Hedef | Veritabanı | Durum |
| --- | --- | --- | --- |
| Container | netcup / Hetzner VPS | PostgreSQL | Birincil, `docs/42` |
| Container'sız | Turhost / Natro / Güzel Hosting | SQLite | Varsayılan, ADR-L08 |

İkisinin de çalıştığı CI'da kanıtlanır: süit her iki veritabanı motorunda
koşar (2026-08-27'de eklendi; o gün 18 test yalnız PostgreSQL'de düşüyordu).

## ADR-L09: Tema domenleri ayrık

**Karar**: Storefront/marketing, public menu, restaurant admin, superadmin, QR
print — beş ayrı tema domeni. Ortak design token seti paylaşılır; her domenin
kendi layout kısıtları vardır; draft/preview/publish/rollback döngüsü her tema
domeni için ayrı ayrı geçerlidir (`docs/06`).

## ADR-L10: Dual-renderer hazırlığı, Stage 1'de tek canonical renderer

**Karar**: Zabuno dual-renderer-**ready** mimariyle kurulur — aynı semantic
domain/view-model contract'ı (`docs/35` §5 UI state union'ları, §6 port/
use-case sözleşmesi) birden fazla renderer tarafından tüketilebilecek
şekilde tasarlanır (örn. `renderer-zabuno` ana özel tasarım sistemi,
ileride `renderer-enterprise-adapter` gibi Flowbite/AntD tabanlı bir
adapter). Renderer'lar birbirini import etmez, business logic taşımaz,
aynı semantic token'ları (`docs/06` §11) ve aynı accessibility/interaction
sözleşmesini (`docs/35` §8) tüketir; aynı ekranın iki bağımsız business
implementasyonu oluşturulmaz.
**Stage 1 sınırı**: yalnız **tek** canonical production renderer kullanılır
(`docs/06` ADR-L06'daki Flowbite-first/shadcn-source-owned bileşen kararı).
İkinci renderer'ın **implementasyonu** yalnız gerçek enterprise CRUD,
migration veya white-label ihtiyacı ölçülüp kanıtlandığında (veya bir
feature flag/theme profile kararıyla) aktive edilir — Stage 1'de ikinci bir
renderer paketi **açılmaz**.
**Gerekçe**: Hazırlık (contract ayrımı, port/adapter disiplini) mimari
borcu önler; ikinci renderer'ın erken inşası ise kanıtsız kapasite israfı
sayılır (`docs/16` gap disipliniyle tutarlı — "ne zaman ikinci renderer?"
sorusu kanıt gerektirir).
**Durum**: deneysel/koşullu — henüz hiçbir ikinci renderer paketi
başlamamıştır; bu karar yalnız **hazırlık** ilkesidir, bir implementasyon
taahhüdü değildir.

## Kanıt/sınıf notu

Her karar `docs/28-SOURCE-REGISTER.md`'de birincil kaynağına bağlanır ve
**kanıtlanmış / koşullu / deneysel** sınıflarından biriyle etiketlenir. Bu
dokümandaki hiçbir karar "kurulmuş" olarak sunulmaz — hepsi plan aşamasındadır.

## Kanonik sahiplik

Mimari kararların tek kanonik sahibi bu dosyadır. Modül-spesifik teknoloji
seçimleri (Money kütüphanesi, QR kütüphanesi vb.) ilgili domain dokümanında
(`docs/07`–`docs/14`) detaylandırılır ama üst-seviye mimari ilke burada kalır.
