# 34 — S1-WP02B Tenancy Isolation Delivery Contract

**DURUM: WP02B bounded CORE-02 baseline code/test-local-candidate-targeted-
green; public-promotion RED.** Bu dosya artık yalnız bir kapsam sözleşmesi
değildir — "Base ve blind-scope kanıtı"dan "Uygulama allowlist şekli"ne
kadarki bölümler (dondurulmuş kapsam/mimari-sınır/tehdit-modeli/RED-matrisi)
**tarihsel kayıt olarak korunur ve değiştirilmez**; §13a Final durum
(aşağıda) mevcut yerel/test gerçeğini kaydeder. Stage sayacı:
**0/8** (değişmedi). Public promotion ayrı ve halen **RED**.

## Base ve blind-scope kanıtı

- relevant-base SHA256: `398ebde53cdf55e4f7fdbe549fc278472d294a61d99a6956edaf84b4bbd802a2`
- Claude blind-scope SHA256: `939a913e77c0e093a64c1de74cd8fabc6b95cb71241c582c6c8bfa06b5ee3a53`
- Codex blind-scope SHA256: `a438a942566338fbc34bc773c47ee1af9956a762ee72589b67c4d014364c02de`

Bu üç hash, MASTER'ın seçeceği paket adayının donmuş temelini ve iki kör
scope-belirleme çıktısını sabitler. Bu dosya bu hash'leri **üretmez**, yalnız
kaydeder.

## once / simdi / fark

- **once**: Kullanıcı Identity/Session (WP02A) ile doğrulanabiliyor, ama hiçbir
  workspace (tenant kapsayıcı) kavramı yok; oturum "kimin hangi işletmesinde
  olduğunu" hiç bilmiyor. Bu belgenin ilk turu yalnız kabul kontratıydı, kod
  yoktu (aşağıdaki dondurulmuş kapsam kapanış marker'ından önceki tarihsel
  durum, bkz. §13a).
- **simdi**: Bounded CORE-02 baseline — workspace oluşturma+owner membership
  (tek transaction), yalnız çağıranın üyeliklerini listeleme, mevcut workspace
  bağlamını seçme/değiştirme, yabancı/var-olmayan/uygun-olmayan workspace'e
  enumeration-safe red — **code/test-local-candidate-targeted-green**'dir
  (bkz. §13a Final durum). Bu, dondurulmuş kontratın implementasyona
  taşındığı ve hedefli kanıtla desteklendiği anlamına gelir; **public
  promotion ayrı ve hâlâ RED**.
- **fark**: Sıfırdan tenancy izolasyonu tanımından (kontrat) → 23/23 hedefli
  test/72 assertion GREEN ile kanıtlanmış, bağımsız kapanış review'ından
  geçmiş, yerel/otomatik-test seviyesinde çalışan bounded bir workspace
  create/list/current/switch dikey dilimine.

## kullaniciYolculugu

Restoran zinciri sahibi Ayşe, e-postasını doğrulamış bir kullanıcı olarak
sisteme girer. "Zeytin Restoranları" adında bir workspace oluşturur — bu tek
işlemde hem workspace hem de Ayşe'nin "owner" üyeliği aynı anda var olur; arada
sahipsiz bir workspace anı yoktur. Ayşe workspace listesini açtığında yalnız
kendi üye olduğu workspace'leri görür — başka bir zincirin (örn. "Deniz Kebap")
workspace'i listede yer almaz ve id'sini bilse bile ona geçiş yapamaz, 404
döner (var olup olmadığı sızdırılmaz). Ayşe birden fazla workspace'e üye
olabilir (örn. danışmanlık verdiği başka bir zincir), çünkü kullanıcı hiçbir
zaman tek bir tenant'a kilitlenmez — Workspace, Restaurant değildir. Ayşe
"mevcut workspace"ini seçer; oturumdaki bu seçim tek başına yetkili değildir,
her istek yeniden doğrulanmış kullanıcı + üyelik + workspace durumu kesişimiyle
kontrol edilir. Workspace "onboarding" durumunda başlar; askıya alınmış/arşivli/
silinmeye programlanmış/silinmiş bir workspace mevcut bağlam olarak seçilemez.

## kalanEngel

- RBAC/ABAC/ReBAC yetkilendirme motoru yok (yalnız owner ilişkisi var,
  CORE-03 not-started).
- Workspace durum geçiş uç noktaları (suspend/archive/delete) bu kapsamda değil.
- Davet, Brand/Location/işletme profili, billing, menü, QR, medya, UI, AI
  çağrıları bu kapsamda değil.
- BIZ-02 (workspace-count cap) çözülmemiş varsayım olarak açık kalır.
- CORE-06 Settings/Secrets, admin panel iskeleti hâlâ not-started.
- Non-blocking P3: 6 karakterlik random slug collision DB-korumalı ve
  precheck'lidir, ancak gerçek bir eşzamanlı (concurrent) collision şu an
  retry yerine yakalanmamış 500 döner — dar kapsamlı bir takip riski olarak
  kaydedilir, blocker değildir, tamamlanmış da değildir.
- Persistent developer DB bu paket kapsamında henüz migrate edilmedi; tarayıcı/
  UI workspace journey'si yoktur — kanıt yalnız API kodu + izole test
  koşusudur (bkz. §13a).
- İki public blocker (composer license/legal owner kararı;
  `AGENTS.md`/`docs/31` public-safe çelişkisi) açık kalır — public promotion
  RED.

## capability_delta

- Önce: 0 workspace capability'si.
- Sonra (bounded, code/test-local-candidate-targeted-green — **public
  promotion ve tarayıcı/manuel E2E değil**): doğrulanmış kullanıcı
  `POST /api/workspaces` ile workspace oluşturabilir (owner membership aynı
  transaction'da), `GET /api/workspaces` ile yalnız kendi üyeliklerini
  listeleyebilir, `PUT`/`GET /api/workspace-context` ile mevcut bağlamı
  seçebilir/değiştirebilir/görebilir; yabancı/var-olmayan/uygun-olmayan
  workspace'e enumeration-safe biçimde erişemez; bayat oturum bağlamı
  sessizce temizlenir. CORE-02'nin geri kalanı (state geçişleri, davet,
  Brand/Location/işletme profili, RBAC/ABAC/ReBAC) hâlâ **yoktur**.
- **Şu an çalıştırılabilir iddia**: Yerel API kodu + izole hedefli test
  kanıtı (23/23, 72 assertion) düzeyinde **candidate**; tarayıcıda görünür
  bir workspace UI'ı veya manuel gerçek-hesap E2E **yoktur**, persistent
  developer DB migrate edilmedi. Stage sayacı **0/8**, değişmedi.

## Kanonik değişmezler (invariants)

1. Kullanıcı tenant-bağımsız kalır, birden çok üyeliği olabilir; Workspace ≠
   Restaurant.
2. Workspace oluşturma ve owner üyeliği **tek DB transaction**'ıdır.
3. `workspace_memberships` üzerinde `unique(workspace_id, user_id)`.
4. `workspaces`: `name`, benzersiz üretilmiş `slug`, `state`, `created_by`,
   timestamps.
5. Sahiplik `owner_user_id` ile tekrarlanmaz; sahiplik Membership ilişkisidir,
   `created_by` yalnız audit provenance'tır.
6. Workspace `onboarding` durumunda başlar. Mevcut-bağlam çözümlemesi yalnız
   `onboarding` ve `active` durumlarına izin verir; `suspended` /
   `archived` / `deletion_pending` / `deleted` reddedilir. Durum geçiş uç
   noktaları bu paketin dışındadır.
7. Oturumdaki workspace id tek başına yetkili değildir. Her current/switch
   isteği doğrulanmış+verified kullanıcı ∩ üyelik ∩ workspace durumu
   kesişimini kontrol eder. Yabancı/üye-olunmayan workspace enumeration-safe
   404 döner. Seçili bağlam yoksa 409 (sabit hata kodu).
8. Oluşturma için doğrulanmış-kullanıcı başına 5/dk abuse throttle vardır;
   workspace-sayısı üst sınırı **yoktur** (BIZ-02 açık varsayım).
9. AI bağımlılığı/çağrısı yok; AI-off/no-credit davranışı özdeştir.
10. Shared-host güvenli; senkron DB/session yolu, Redis/queue/worker
    zorunluluğu yok.

## Minimum HTTP kontratı (JSON, `auth:sanctum` + `verified`)

| Uç nokta | Davranış |
|---|---|
| `POST /api/workspaces` `{name}` | 201 — `onboarding` workspace + owner membership tek transaction'da oluşturulur, mevcut bağlam olur. |
| `GET /api/workspaces` | 200 — yalnız çağıranın üyelikleri. |
| `PUT /api/workspace-context` `{workspace_id}` | 200 mevcut özet; üye-olunmayan/geçersiz/uygun-olmayan → 404. |
| `GET /api/workspace-context` | 200 mevcut özet; yoksa → 409 `workspace_context_required`; bayat/yabancı/uygun-olmayan oturum id'si var olduğunu sızdırmadan temizlenip reddedilir. |

Bu dilimde frontend/UI yoktur.

## Mimari sınır

Strict OOP, `final` concrete class'lar, `strict_types`. Onion katmanları:

```
Domain  <-  Application (ports/use cases)  <-  Infrastructure (Eloquent/session adapters)  <-  Delivery (MVC controllers)
```

- Domain katmanında Laravel import'u yok.
- Global/client-controlled `workspace_id` güvenilmez — her istekte
  sunucu-taraflı üyelik+durum kesişimiyle yeniden doğrulanır.
- Yeni bağımlılık yok.

## Tehdit modeli

| Tehdit | Karşı önlem |
|---|---|
| Tenant escape: client `workspace_id`'yi manipüle ederek yabancı workspace'e erişir | Her istekte sunucu-taraflı üyelik kesişimi; yabancı id → enumeration-safe 404 (var/yok ayrımı sızdırılmaz). |
| Oturumda kalan bayat/yabancı workspace id (üyelikten çıkarılmış/state değişmiş) | current/switch her çağrıda üyelik+state yeniden doğrulanır; geçersizse oturum bağlamı sessizce temizlenir. |
| Workspace oluşturma DoS / brute enumeration | Doğrulanmış-kullanıcı başına 5/dk throttle. |
| Yarı-oluşmuş workspace (owner'sız) durumu | Create ve owner-membership tek DB transaction; rollback atomik. |
| Duplicate/çakışan üyelik | `unique(workspace_id,user_id)` DB kısıtı. |
| Non-member workspace varlığının keşfi (enumeration) | 404 hem "yok" hem "yabancı" için aynı; existence sızdırılmaz. |
| Bağlam yokluğunun ayırt edilememesi | `GET /api/workspace-context` bağlam yoksa sabit 409 `workspace_context_required` kodu döner (400/500 karışıklığı yok). |

## RED→GREEN beklentileri

Aşağıdaki test ID'leri implementasyon başlamadan önce RED (yazılmış ama
başarısız veya henüz yazılmamış, açıkça RED olarak işaretli) olmalı, sonra
implementasyonla GREEN'e geçmelidir. Her ID tek bir invariant/kontrat maddesini
doğrular; hiçbiri implementasyon detayına göre gevşetilemez.

| Test ID | Doğruladığı |
|---|---|
| `S1WP02B-AUTH-01` | Tüm workspace uç noktaları `auth:sanctum`+`verified` gerektirir. |
| `S1WP02B-CREATE-01` | `POST /api/workspaces` 201 + onboarding state + slug üretimi. |
| `S1WP02B-CREATE-TXN-01` | Create+owner-membership atomik; ara hata → hiçbir kayıt kalmaz. |
| `S1WP02B-MEMBERSHIP-UNIQUE-01` | `unique(workspace_id,user_id)` DB kısıtı ihlali reddedilir. |
| `S1WP02B-LIST-01` | `GET /api/workspaces` yalnız çağıranın üyeliklerini döner. |
| `S1WP02B-SWITCH-01` | `PUT /api/workspace-context` geçerli üyelik için 200. |
| `S1WP02B-CURRENT-01` | `GET /api/workspace-context` mevcut bağlamı döner; yoksa 409. |
| `S1WP02B-TENANT-ESCAPE-01` | Üye olunmayan workspace id → 404 (switch). |
| `S1WP02B-TENANT-ESCAPE-02` | Var olmayan workspace id ile aynı 404 imzası (enumeration-safe). |
| `S1WP02B-SESSION-STATE-01` | Bayat/geçersiz oturum bağlamı sessizce temizlenir, sızdırmaz. |
| `S1WP02B-STATE-01` | `suspended`/`archived`/`deletion_pending`/`deleted` current-context'e izin vermez. |
| `S1WP02B-USER-NOT-TENANT-BOUND-01` | Bir kullanıcı >1 workspace'e üye olabilir. |
| `S1WP02B-RATE-01` | 5/dk throttle create üzerinde çalışır. |
| `S1WP02B-AI-01` | AI-off/no-credit davranışı ile davranış özdeş (AI çağrısı yok). |
| `S1WP02B-ONION-01` | Domain katmanında Laravel import'u yoktur (mimari test). |

## Giriş/çıkış kapıları

- **Giriş kapısı (fiilen izlenen yol)**: Bu belge donduktan sonra ayrı bir
  Claude implementasyon writer'ı, aynı immutable base snapshot üzerinden,
  yukarıdaki allowlist dışına çıkmadan RED testleri yazdı/doğruladı, sonra
  implementasyonu yazdı (bkz. §13a).
- **Çıkış kapısı (durum)**: §11'deki 15 RED matris ID'sinin tamamı hedefli
  kanıtla GREEN'e taşınmıştır (23/23 test, 72 assertion) ve bağımsız kapanış
  review'ı (P2 düzeltmesi sonrası) GREEN'dir (bkz. §13a). Ancak bu, WP02B'nin
  resmi **GO** kararına eşdeğer değildir: yalnız bir yerel tam (full) QA
  koşusu bu paket kapsamında çalıştırılmış ve harcanmıştır — P2 düzeltmesi
  sonrası ikinci bir yerel tam koşu yapılmadı, yalnız targeted 23/23/72 +
  targeted Pint/php-l/closure review taze kabul edilir; §3 bütçesindeki
  ikinci tam QA kalemi yalnız sonraki CI/full QA koşusu için rezervedir. Stage
  sayacı bu belge kapsamında **değiştirilmedi** (0/8).

## QA bütçesi

- Paket-özel: **1 local full QA** (Claude implementasyon writer'ı, hedefli
  RED→GREEN sonrası) + **1 CI full QA** (zorunlu).
- Hedefli RED tespiti (RED'i kurma) bu bütçeden düşülmez.
- Bağımsız reviewer zorunlu; yazan kişi kendi paketini review edemez.
- Bu bütçe GREEN olduktan sonra bespoke ek probe/self-verifier/router-evidence
  script icat edilmez; ek tam koşu belgelenmiş gerekçe ister.

## Rollback

- Migration `down()` sırası: önce `workspace_memberships`, sonra `workspaces`
  (FK bağımlılığı bu yönde çözülür) — implementasyon artık mevcuttur, bu sıra
  paketin gerçek revert/migration-down yoludur. Rollback bu sırayı çalıştırıp
  paketin şemasını geri alır; ilişkisiz uncommitted iş korunur, üzerine
  yazılmaz. Bu paket kapsamında rollback fiilen **çalıştırılmaz** — yalnız
  yolu burada kayıtlıdır.
- Worktree kirliyse (uncommitted iş varsa) korunur, üzerine yazılmaz.
- Hiçbir yıkıcı Git komutu (force-push, reset --hard, branch -D vb.) bu paket
  kapsamında çalıştırılmaz.

## Kanıt-senkron kapanışları

Aşağıdakiler bu evidence-sync paketinde çözüldü:

- `AGENTS.md`/`modules` Identity bölümü güncel gerçeği yansıtacak şekilde
  düzeltildi.
- `docs/27-QA-ACCEPTANCE-VIBECODING.md` içindeki WP01A/WP02A/WP02B attribution
  ve rejim sayısı düzeltildi.
- `docs/` dosya sayısı referansları (35, docs/00–34) güncellendi.
- `docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md` içindeki WP02 projeksiyonu bu
  paketi yansıtacak şekilde ele alındı.

## Kapsam dışı (explicit non-goals)

RBAC/ABAC/ReBAC yetkilendirme motoru; owner ilişkisi ötesinde roller; owner
transfer/son-owner mutasyonu; davet; Brand/Location/işletme profili; workspace
durum geçiş uç noktaları; Settings/Secrets; admin shell; billing; menü; QR;
medya; UI; AI çağrıları; Docker/Next.js/Filament; kota/entitlement.

## Uygulama allowlist şekli (olası, bağlayıcı değil — implementasyon paketi kendi allowlist'ini ayrıca dondurur)

```
app/Domain/Tenancy/**
app/Application/Tenancy/**
app/Infrastructure/Tenancy/**
app/Models/Workspace.php
app/Models/WorkspaceMembership.php
app/Http/Controllers/Tenancy/**
database/migrations/*create_workspaces_and_memberships*
app/Providers/AppServiceProvider.php
routes/api.php
tests/Feature/Tenancy/**
tests/Architecture/Tenancy/**
```

Mevcut auth testlerine dokunulmaz — yalnız ayrıca onaylanmış, nesnel olarak
zorunlu bir uyumluluk düzeltmesi varsa istisna olur.

## Sonraki non-goal paket sınırı

Bu paketin doğal devamı, workspace durum geçişleri (suspend/archive/delete)
ve davet akışı olacaktır; bunlar ayrı, bu belgeyle aynı disiplinde donmuş bir
kabul kontratı gerektirir ve bu paket kapsamına şimdi dahil edilmez.

---

**S1_WP02B_CONTRACT_FROZEN_GREEN** — yukarıdaki dondurulmuş kapsam/mimari-
sınır/tehdit-modeli/RED-matrisinin ("Base ve blind-scope kanıtı"dan
"Uygulama allowlist şekli"ne kadar) tarihsel kapanış marker'ıdır. Bu marker,
donmuş kabul baseline'ını (kapsam/mimari-sınır/tehdit-modeli/RED-matrisi
metni, hash'ler, invariant'lar, test ID'leri) korur — bu içerik implementasyon
sonrası değiştirilmemiştir (`AGENTS.md` §2 tek kanonik sahiplik, kabul
kuralları sonradan yeniden yazılmaz). Bu, dosyanın tamamının byte-immutable
kaldığı anlamına **gelmez**: bu evidence-sync paketi §Rollback ve "Kanıt-
senkron kapanışları" bölümlerinin durum/anlatı metnini kasıtlı olarak
güncellemiştir, kabul kriterlerini zayıflatmadan.

## 13a. Final durum (evidence sync — implementasyon sonrası)

WP02B'nin bounded CORE-02 baseline'ı artık **code/test-local-candidate-
targeted-green**'dir: doğrulanmış kullanıcı `POST`/`GET /api/workspaces` ve
`PUT`/`GET /api/workspace-context` uçlarını kullanabilir; create+owner
membership tek DB transaction'ıdır; kullanıcı tenant-bağımsız kalır; liste
yalnız üyelik-scope'ludur; current/switch her istekte üyelik+uygun-olma
durumunu yeniden doğrular; bayat oturum bağlamı sessizce temizlenir;
yabancı/var-olmayan/uygun-olmayan workspace enumeration yapmaz; create
üzerinde doğrulanmış-kullanıcı başına 5/dk throttle vardır; hiçbir
AI/network/Redis/queue/worker gereksinimi yoktur (§Kanonik değişmezler madde
9–10 ile tutarlı).

**MASTER + bağımsız reviewer hedefli kanıtı**: 23/23 test, 72 assertion
GREEN. Dört JSON route (`/api/workspaces` POST/GET, `/api/workspace-context`
PUT/GET) `auth:sanctum`+`verified` taşır; yalnız POST `throttle:5,1` taşır
(`routes/api.php`). Paket-hedefli Pint ve `php -l` GREEN.

**İlk bağımsız review ve düzeltme**: Dondurulmuş agregat snapshot
`0cb9d7...` üzerindeki ilk bağımsız review **RED** sonucu verdi — tek bir P2:
domain eligibility policy (workspace mevcut-bağlam için "uygun" sayılan
durum değerleri) repository katmanında yinelenmişti. Düzeltme: uygun durum
değerleri `WorkspaceState`'in predicate'inin arkasına taşındı (tek kanonik
sahiplik). Düzeltilmiş kod üzerinde taze bir kapanış review'ı çalıştırıldı:
**S1_WP02B_CLOSURE_REVIEW_GREEN** — P0 yok, P1 yok, P2 kapandı.

**Non-blocking P3 (ertelenmiş takip)**: 6 karakterlik random slug collision
DB-korumalı ve precheck'lidir, ancak gerçek bir eşzamanlı collision durumunda
retry yerine yakalanmamış bir 500 döner. Bu dar kapsamlı bir takip riski
olarak kayıtlıdır — blocker değildir, bu paket kapsamında düzeltilmedi.

**QA bütçesi durumu**: WP02B için bir yerel tam (full) QA sırası bu paket
kapsamında **bir kez** çalıştırıldı — implementasyon writer'ı tam PHP suite
74/74 GREEN ve build GREEN raporladı; `composer validate` yalnız mevcut
license-eksikliği owner kararı yüzünden RED kaldı; repository-genelinde
style gate'leri WP02B production allowlist'i dışındaki mevcut sapmayı
raporladı. P2 düzeltmesinden **sonra** ikinci bir yerel tam suite koşusu
çalıştırılmadı — yalnız targeted 23/23 test/72 assertion + targeted Pint/
`php -l`/kapanış review'ı düzeltilmiş snapshot için tazedir. CI/full QA,
yetkili bir commit/push/promotion olmadığı için çalıştırılmadı.

**Kalan public blocker'lar (değişmedi)**: composer license/legal owner
kararı ve managed `AGENTS.md` yetki bloğu ile `docs/31` public-safe yasağı
arasındaki çelişki açık kalır (`docs/27` §6). Public promotion **RED**
kalır — bu iki blocker düzeltilmedi/hafifletilmedi.

**Persistent developer DB ve UI durumu**: Bu paket kapsamında persistent
developer veritabanı WP02B için henüz **migrate edilmedi**; hiçbir workspace
UI/tarayıcı journey'si yoktur. Bu yüzden iddia yalnız API kodu +
izole-test-koşusu seviyesindedir — görünür bir localhost workspace UI'ı veya
manuel gerçek-hesap E2E iddia **edilmez**.

**Kapsam dışı kalanlar (değişmedi)**: BIZ-02 (workspace-count cap) açık
varsayım olarak kalır; Authorization CORE-03, Settings CORE-06, admin shell,
Brand/Location/işletme profili, state-transition uç noktaları, davetler ve
RBAC/ABAC/ReBAC hâlâ not-started/kapsam dışıdır.

Stage sayacı bu evidence-sync turunda **değiştirilmedi**: **0/8**, Stage 1
aktif.

**S1_WP02B_LOCAL_CANDIDATE_TARGETED_GREEN**
