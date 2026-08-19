# 10 — ECA, Workflow, Taxonomy & State

**PLANNING ONLY.**

## 1. Üç ayrı kavram, üç ayrı CORE modülü

Bu üçü sık karıştırılır; bu külliyat bilinçli olarak ayırır:

| Kavram | Ne için | CORE kodu |
|---|---|---|
| Workflow/State Machine | Domain lifecycle (bir varlığın durumdan duruma geçişi — örn. Menu: draft→ready→published) | CORE-10 |
| Taxonomy | Esnek vocabulary'ler (örn. floor/area isimleri, ürün etiketleri) | CORE-09 |
| ECA Rules | Event-Condition-Action otomasyonu (örn. "stok bittiğinde ürünü otomatik gizle") | CORE-11 |

**Kural**: currency, permission, lifecycle gibi **typed** (sabit, kod-tanımlı)
kavramlar Taxonomy'ye **taşınmaz** — Taxonomy yalnız kullanıcı tarafından
genişletilebilir, serbest biçimli listeler içindir.

## 2. Workflow/State motoru

Core motor tüm modüller için ortak state-transition altyapısını sağlar; **domain
lifecycle tanımları modül sahibinde** kalır (örn. Menu'nün durum makinesini
Menu Catalog modülü tanımlar, motor yalnız transition'ı yürütür/loglar). **Symfony
Workflow** adapter adayıdır (kanıtlanmış, PHP ekosisteminde yaygın).

Örnek durum makineleri (özet, `docs/05` ile çapraz bağlı):

```
Workspace:    onboarding → active → suspended → archived → deletion_pending → deleted
Subscription: trialing → active → past_due → grace → suspended → canceled → expired
Menu:         draft → ready → published → unpublished → archived
Publication:  pending → generating → published → failed → superseded
QR:           draft → active → disabled → archived → deleted
Product:      draft → visible → hidden → out_of_stock → archived
Invitation:   pending → accepted → expired → revoked
```

`hidden` ile `out_of_stock` ayrımı kritik: hidden'da müşteri ürünü **hiç görmez**;
out_of_stock'ta ürün **görünür ama mevcut değildir** (görsel olarak farklı
sunulur — örn. üzerinde çizgi + "tükendi" etiketi).

## 3. ECA engine + Automation Studio UI

Typed event → condition AST (Abstract Syntax Tree, serbest `eval` **yasaktır**) →
allowlisted action. Zorunlu güvenlik/operasyon kontrolleri:

- **Dry-run** modu (gerçek etkisi olmadan sonucu önizleme).
- **Version/approval**: bir kuralın yayına alınması onay gerektirir.
- **Idempotency**: aynı event iki kez tetiklense bile action iki kez uygulanmaz.
- **Retry** + **rate limit**.
- **Recursion/cycle guard**: bir action'ın kendi tetiklediği event'in aynı kuralı
  tekrar tetikleyip sonsuz döngü oluşturmasını engeller.
- **Audit**: her tetiklenme kayıt altına alınır.

Tüm modüller kendi event'lerini ve action'larını bu motora **register** eder
(cross-module iletişim, `docs/03` ADR-L05 ile uyumlu — doğrudan çağrı değil,
event-driven).

## 4. Admin CRUD varsayılanları

Default CRUD table/form her yeni modül için hazır gelir (advanced filters, saved
views dahil) — bu, `docs/06` §5'teki veri listeleme sözleşmesinin ECA/Taxonomy
yönetim ekranlarına da uygulanmasıdır (Automation Studio ve Taxonomy yönetimi
kendi özel UI'larını *ayrıca* alır, ama liste/CRUD iskeleti ortaktır).

## 5. Frontend state kararı

Frontend state **bir Core/domain modülü değildir** — CORE-10 (§2) yalnız
**domain lifecycle** (sunucu tarafı state machine) sahibidir; aşağıdaki
ayrım React tarafının **kendi** state kategorilerini kanoniklaştırır, yeni bir
CORE kodu açmaz (`docs/04` §5 "Core'u sınırsız büyütmeme" testinden geçmez —
bu bir mimari sözleşme notudur, modül değildir).

**Beş ayrı state kategorisi, karıştırılmaz**:

| Kategori | Örnek | Sahip/mekanizma |
|---|---|---|
| Local ephemeral UI state | Açık/kapalı dropdown, aktif tab | React local state/context — **first-class default** |
| URL/shareable filter state | Liste filtresi, sayfa numarası, arama sorgusu | URL query param (paylaşılabilir/geri-alınabilir link) |
| Form state | Menü/ürün düzenleme formu | React Hook Form (koşullu aday) |
| Server/async state | API'den gelen liste/detay verisi, cache | TanStack Query (koşullu aday) — **server state cache'tir, domain state değildir** |
| Offline state (iki ayrı alt-durum, birbirine karıştırılmaz) | (a) Diner: bağlantı yokken salt-okunur son yayın snapshot'ı + yalnız idempotent/zararsız analytics event kuyruğu (**içerik düzenleme taslağı değildir** — diner içerik düzenlemez). (b) Admin/staff: bağlantı koparsa yalnız non-authoritative **local form-draft recovery** (güvenli form girdisi için; ödeme/secret/izin verisi asla yerelde tutulmaz, kritik komut hiç kuyruklanmaz) | `docs/15` §5a (diner: bounded analytics queue; admin: local form-draft recovery, service worker **değildir**) |

**Laravel API, domain gerçeğinin sahibidir**; React hiçbir zaman kendi
kopyasını "gerçek" sayıp sunucuyla çelişen bir karar vermez — CORE-10 (§2)
domain state machine'in **tek** yürütücüsüdür, React yalnız o kararın sunum
tarafını yansıtır (`docs/03` ADR-L02 "React iş kuralı içermez" ile tutarlı).

**Koşullu adaylar (kanıt/ADR olmadan varsayılan sayılmaz)**:
- **TanStack Query** — server state/cache için koşullu aday (`docs/28`).
- **React Hook Form** — form state için koşullu aday (`docs/28`).
- **Local React state/context** — cross-page paylaşım gerekmeyen her durumda
  **ilk tercih** (first-class default), ek kütüphane gerektirmez.
- **Zustand** — yalnız **kanıtlanmış** bir cross-page/cross-component UI state
  ihtiyacı ölçüldüğünde (örn. birden fazla sayfa arasında paylaşılan bir sepet/
  seçim state'i) devreye girer; varsayılan olarak kurulmaz.
- **Redux** — **varsayılan değildir**; yalnız ölçülen bir ihtiyaç + ayrı bir
  ADR (`templates/ADR.md`) ile gerekçelendirilirse değerlendirilir.

**Optimistic update disiplini**: yalnız **idempotent + reversible** işlemlerde
kullanılır (örn. bir ürünü gizle/göster — tekrar tetiklense de aynı sonuca
gider ve geri alınabilir); ödeme/yayınlama/izin değişikliği gibi geri
alınamaz işlemlerde optimistic update **yapılmaz**. Query key'ler
**tenant-scoped**'dur (bir tenant'ın cache'i başka tenant'ın verisiyle asla
karışmaz); bir mutation sonrası ilgili query'ler **explicit invalidation**
ile güncellenir (sessiz/otomatik "arka planda bir gün güncellenir" varsayımı
yoktur); bir mutation başarısız olursa **mutation rollback** ile UI önceki
tutarlı duruma döner ve kullanıcıya **stale-data UX** (verinin güncel
olmayabileceği görünür bir işaret) gösterilir.

## 6. Kanonik sahiplik

ECA/Workflow/Taxonomy ayrımı, motor sözleşmesi ve frontend state kararı
burada kanoniktir. Her modülün kendi state machine'i ve ECA hook'ları
`modules/*.md` dosyalarının ilgili alanlarında (states, ECA hooks) tanımlanır
— burada yalnız motor davranışı ve frontend state ayrımı tanımlanır, modül-özel
durumlar burada tekrar edilmez. `modules/core-workflow-state.md` (CORE-10)
yalnız **domain** state machine motoruna sahiptir, frontend state kararı bu
dosyaya link verir, kendi tanımını tutmaz.
