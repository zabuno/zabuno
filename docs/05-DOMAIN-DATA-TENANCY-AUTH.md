# 05 — Domain, Data, Tenancy & Auth

**PLANNING ONLY.**

## 1. Tenant modeli

```
Account / Workspace (tenant kökü)
    Brand
        Location
            Floor/Area → Section/Zone → Table → Seat
```

`Tenant = Restaurant` yapılmaz (bkz. `docs/02` §3, eski dokümandan doğrudan
korunmuş kritik mimari karar). Her tenant-aware kayıt en az şu alanları taşır:

```
workspace_id, brand_id (nullable → M1 çok-brand), location_id (nullable → M1 çok-şube),
created_by, updated_by
```

Backend **hiçbir zaman** yalnız istemciden gelen `workspace_id`'ye güvenmez;
tenant her istekte şu katmanların **kesişimiyle** çözülür: session, route context,
membership, policy, database scope. Bu beşi tek bir "tenant resolver" servisinde
toplanır (CORE-02, `modules/core-tenancy.md`).

## 2. Authorization: RBAC + ABAC + ReBAC, tek PDP

**Karar**: Yetki kararı tek bir Policy Decision Point (PDP) üzerinden verilir;
deny-by-default. Backend authoritative'dir — frontend yalnız *affordance*
(görünürlük ipucu) sağlar, hiçbir zaman tek güvenlik katmanı değildir.

Üç seviyeli kontrol:

```
Panel access → Module access → Record-level access
```

**Scope'lar**: platform / org(workspace) / brand / location / menu / asset / table.

**Karar mekaniği**:
- **RBAC** (baseline rol): Spatie Laravel Permission adayı — role/permission
  yapıları Laravel Gate katmanıyla bütünleşir, team-aware model destekler
  (koşullu — resmi paket dokümantasyonuna erişim doğrulandı, `docs/28`
  "Spatie Permission (wildcard)" satırı; production-proven sayılması için
  resmi kaynağın canlı doğrulaması + compatibility spike gerekir).
- **ABAC** (öznitelik tabanlı, örn. "kendi workspace'inde bile Owner'a özel kritik
  işlemi yapamaz"): policy sınıflarında öznitelik kontrolü.
- **ReBAC** (ilişki tabanlı, örn. "bu kullanıcı bu asset'in owner'ı mı"): OpenFGA
  **opsiyonel adapter** — shared-hosting için **varsayılan değildir** (koşullu
  sınıf; ek servis/altyapı gerektirir).
- Her karar **explainable** olmalı ve audit'e yazılmalı (CORE-07 ile entegre):
  "kim, neye, neden erişemedi" sorgulanabilir olmalı.

**Segregation of duties**: `docs/02` §2'de tanımlanan rol ayrımları PDP seviyesinde
zorunlu kılınır; iki çelişen rolün (örn. Auditor + Platform Admin) aynı kullanıcıya
otomatik atanmasına izin verilmez (uygulama detayı: policy-level constraint,
`modules/core-authorization.md`).

## 3. Kimlik doğrulama

- **Fortify headless** + **Sanctum first-party session cookie** (SPA aynı
  organizasyona ait olduğundan token değil, cookie+CSRF — koşullu, resmi
  Sanctum dokümantasyonuna erişim doğrulandı, `docs/28` "Laravel Fortify"
  satırı; production-proven sayılması için resmi kaynağın canlı doğrulaması +
  compatibility spike gerekir).
- CSRF koruması, e-posta doğrulama, opsiyonel OTP/passkey **roadmap** (MVP dışı).
- Brute-force koruması katmanlı: rate limit + credential-stuffing/breached-password
  kontrolü + generic hata mesajları (kullanıcı var/yok bilgisi sızdırılmaz) +
  session/device risk skorlama + lockout-abuse önleme (bir saldırganın hedef
  hesabı kilitleyerek DoS yapmasını önleyen üst sınır).

## 4. Impersonation

Dört zorunlu şart (bkz. `docs/02` §2): sebep girilir, süreli, ekranda banner,
tüm işlemler audit'e yazılır. Kritik billing/silme işlemleri impersonation
sırasında **engellenir** (policy-level hard block, bypass edilemez).

## 5. Veri modeli — özet varlık ilişkileri

```
Workspace 1:N Brand 1:N Location 1:N Menu
Menu 1:N MenuCategory, 1:N MenuItem, 1:N MenuPublication
Product 1:N MenuItem (bir ürün birden fazla menü/kategoride farklı fiyat/görünürlükle yer alabilir)
Product 1:N ProductAllergen
Location 1:N QRCode; QRCode belongsTo Destination; Destination pointsTo MenuPublication|Menu|URL|...
MenuPublication belongsTo Menu, immutable snapshot içerir
```

Kritik karar: **Product ≠ MenuItem**. Ürünün temel bilgisi (Product) ile onun
belirli bir menü/kategorideki yerleşimi + fiyatı + görünürlüğü (MenuItem) ayrıdır
— bu ayrım aynı ürünün farklı menülerde farklı fiyatla, bir menüde görünür diğerinde
gizli olarak yer almasını mümkün kılar (`docs/29`'da traceable, kaynağı eski
dokümanın MOD-R03 bölümü).

## 6. Panel access değil, gerçek isolation testi

Tenant izolasyonu yalnız UI route guard ile değil, her ORM sorgusunda zorunlu
tenant-scope ile sağlanır (global scope / query builder macro seviyesinde,
uygulama detayı `modules/core-tenancy.md`). IDOR (Insecure Direct Object
Reference) testleri her modülün acceptance kriterine dahildir (`docs/27`).

## 6a. Client-heavy frontend / server-authoritative iş bölümü

Genel yön: Restaurant Admin ve Superadmin client-rendered SPA/PWA'dır;
Public Menu/Marketing static/cacheable shell + versioned publication
snapshot'tır; per-request SSR mümkün olduğunca azaltılır (`docs/03` ADR-L07
ile tutarlı). Bu, §2'deki tek-PDP/deny-by-default kararını **gevşetmez** —
yalnız hangi işin nerede çalıştığını ayırır:

**Client'a taşınabilir** (yetki/veri kararı taşımaz): UI rendering, local
view state, bounded data filtering/sorting, safe preview, local form
validation, reversible optimistic UI (yalnız idempotent işlemlerde, `docs/10`
§5 optimistic-update disipliniyle tutarlı), draft state, client cache,
command palette search, layout adaptation, public menu presentation, image
preview/güvenli pre-processing, offline-safe draft (`docs/10` §5 offline
state ayrımıyla tutarlı).

**Server'da kalması zorunlu** (authoritative, bypass edilemez): authentication,
authorization/PDP kararı (§2), tenant isolation (§1, §6), authoritative
validation, permission mutation, publication, payment, audit, ECA execution,
AI secrets/tool execution (`docs/14` §3), signed media access, immutable
snapshots, QR destination authority, rate limiting, idempotency, final
business rules.

Client'ın gönderdiği `role`/`workspace_id`/`permission`/`price`/
`publication state`/AI sonucu **tek başına güvenilmez** — server her
istekte kimlik, tenant, resource relationship, attribute ve permission'ı
yeniden doğrular (§2, §6 ile aynı ilke). Server yükünü azaltmak için
coarse-grained typed API, pagination, server-driven filter, ETag/
Cache-Control, versioned publication snapshot, CDN, queue/background job,
gerektiğinde SSE/WebSocket, incremental sync, request dedup, client query
cache, pre-signed bounded upload kullanılabilir — bunların hiçbiri güvenlik
kuralını client'a taşımaz.

## 7. Kanonik sahiplik

Tenant modeli, authorization mekaniği ve auth akışlarının tek kanonik sahibi bu
dosyadır. CORE-02/CORE-03 modül spec'leri (`modules/`) buradaki kararları
*uygulamaya* döker, yeniden tanımlamaz.
