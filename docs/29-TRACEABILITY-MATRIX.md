# 29 — Traceability Matrix

**PLANNING ONLY.** Bu doküman, görev talimatının (Codex Desktop MASTER'ın
verdiği paket talimatı) her maddesinin bu külliyatta nerede karşılandığının
**çift yönlü** kanıtıdır: talimat maddesi → kanonik dosya/bölüm, ve kanonik
dosya → hangi talimat maddesini karşıladığı.

## A — Arşivleme, ön kanıt ve rollback

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Preflight ve taşıma-öncesi ham envanter kaydı | `evidence/` (yalnız yerel ham kanıt, public'e dahil değil — sanitize edilmiş özet `evidence/PUBLIC-ARCHIVE-ATTESTATION.md`) |
| Kesin istisnalar (.git, old, laravelv01, worktrees) | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md`, uygulanmış hal `docs/00` §3 |
| node_modules symlink byte-korumalı taşıma | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §3 |
| Bağlı yardımcı geliştirme çalışma alanlarının taşınması + onarımı | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §4 |
| Bu paket (arşivleme) kapsamında Git add/commit/push/merge yok — Claude worker'ın kendisi hiçbir zaman Git mutasyonu yapmaz; bağımsız public `zabuno/zabuno` init/commit/push, yalnız bağımsız review GREEN + owner'ın açık yayın talebiyle Codex Desktop MASTER tarafından ayrı bir yetkilendirilmiş adımda yapılır | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §5, `AGENTS.md` §6, `docs/31` §1a |
| Taşıma-sonrası bütünlük doğrulaması (99/99) | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §2 |
| Rollback manifest-driven prosedürü | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §8 (tam prosedür detayı yalnız yerel) |

## B — Yeni kanonik doküman ağacı

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Türkçe yazım, İngilizce ürün UI dili | Tüm `docs/*` dosyaları + `README.md` |
| laravelv01/README.md (0/8, plan-onayı) | `README.md` |
| laravelv01/AGENTS.md, CLAUDE.md | `AGENTS.md`, `CLAUDE.md` |
| docs/00–32 (33 dosya) | `docs/00-PROVENANCE-ARCHIVE.md` … `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` |
| templates/MODULE-SPEC.md, ADR.md, MILESTONE-GATE.md, SKILL-SPEC.md, AI-CAPABILITY-MANIFEST.md | `templates/` |

## C — Ürün felsefesi ve yolculuklar

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Beauty for everyone hiyerarşisi | `docs/01` §2 |
| Self-service, tenant izolasyonu, modülerlik, AI-off determinizm, dynamic QR permanence, edit/publish ayrımı | `docs/01` §3 |
| Değer zinciri (Capability→Module→Feature→Flow→Story→Acceptance→Test) | `docs/01` §4 |
| Strict MVP boundary, audit/export/operations/entitlement/evidence gates | `docs/01` §5, §6 |
| Eski teknoloji taşınmaması | `docs/00` §4, `docs/01` §7 |
| Platform + tenant + public rol detayları | `docs/02` §1 |
| Yetki sınırları / görev ayrımı | `docs/02` §2 |
| Restoran hiyerarşisi (account→brand→location→floor→section→table→seat) | `docs/02` §3, `docs/05` §1 |
| Onboarding + bulk QR akışı | `docs/02` §4 |
| Restoran kurumsal işletme profili (kimlik/iletişim/operasyon/erişilebilirlik/external-social-delivery provider registry) — alan sahipliği | `modules/core-tenancy.md` §Business profile contract |
| Brand asset (logo/cover/avatar/favicon/app-icon/OG) + renk rolleri (primary/secondary/accent/neutral) + tipografi — alan sahipliği | `modules/themes-brand.md` §Brand asset ve renk sözleşmesi |
| İşletme profili/brand asset slotlarının medya pipeline tarafı (yalnız asset/recipe, veri değil) | `docs/07` §6 |
| İşletme profilinin onboarding'deki minimum required/optional ayrımı + one-click defaults | `modules/onboarding.md` §UX one-click journey |
| Growth/Marketing Operator rolü (dar kapsamlı: SEO/content/analytics/campaign, tenant içerik/billing/authz/secrets yetkisi yok) | `docs/02` §1.1, `docs/26` §5 |

## D — Mimari kararlar

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Laravel modular monolith, finite Kernel | `docs/03` ADR-L01, `docs/04` §2 |
| Onion + MVC (MVVM değil) | `docs/03` ADR-L02 |
| Strict OOP | `docs/03` ADR-L03 |
| Modül registry/lifecycle, install≠entitlement, disable≠purge | `docs/03` ADR-L04, `docs/04` §2, §5 |
| Cross-module yalnız contract/event/outbox | `docs/03` ADR-L05 |
| nwidart/laravel-modules opsiyonel | `docs/03` ADR-L04 |
| React+Vite, Flowbite first, shadcn source-owned, Next.js yasak | `docs/03` ADR-L06 |
| Public SEO envelope (SSR shell + progressive enhancement) | `docs/03` ADR-L07 |
| No Docker, shared-host default, kapasite matrisi | `docs/03` ADR-L08, `docs/15` §4 |
| 5 tema domeni | `docs/03` ADR-L09, `docs/06` §1 |

## E — Modül kataloğu

| Talimat maddesi | Karşılandığı yer |
|---|---|
| CORE-01..16 tam liste | `docs/04` §2, `modules/core-*.md` |
| Required product modülleri | `docs/04` §3, `modules/*.md` |
| Optional katalog (OPT-01..29) | `docs/04` §4 |
| Modül matrisi (stage/edition/dependency/owner/status) | `docs/26` |
| "Core"u sınırsız büyütmeme | `docs/04` §5 |
| CORE-11 ECA Stage 1 minimum (typed event/action registry + kritik deterministic kurallar + dry-run güvenlik) vs. Stage 2 full Automation Studio | `docs/26` §1, `docs/18` §Module increments |
| CORE-14 = Notifications (Observability değil); Stage 7 Maturity operasyonel katmanı docs/15 yatay observability + CORE-07 + CORE-15 üzerine kurulur | `docs/24` §Module increments, `docs/04` §2 |
| Required product sınıfı = hedef ürünün zorunlu iş/kabiliyet seti (MVP'de bulunur ile eş anlamlı değil), stage-teslimatı `docs/26` ile kademeli; AI Platform yatay Capability Plane olarak ayrık | `docs/04` §1, §3 |
| ADR-L01 (Laravel modular monolith) ve Spatie/Sanctum kararları koşullu sınıfa düzeltildi (erişim doğrulandı ≠ production-proven) | `docs/03` ADR-L01, `docs/05` §2, §3 |

## F — Özel alan kararları

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Media/File Manager (Uppy/Spatie/Intervention/Flysystem/Optimizer, fingerprint, WebP/AVIF, WebM/MP4, güvenlik pipeline, slotlar, UX) | `docs/07` (tüm bölümler) |
| Imageoptimization upstream snapshot + provenance + port/no-port | `research/upstream/imageoptimization/UPSTREAM.md`, `docs/07` §9 |
| QR (endroid/qr-code, mPDF, ISO 216, scannability, bulk wizard, immutable ID) | `docs/08` (tüm bölümler) |
| Money/Pricing/Iyzico (brick/money, ledger, entitlement, iyzipay-php, webhook) | `docs/09` (tüm bölümler) |
| Authorization (RBAC+ABAC+ReBAC, PDP, scopes, explainable, Spatie/OpenFGA) | `docs/05` §2 |
| Communications (mail default+adapters, SMS Netgsm/Twilio, mini CRM+helpdesk) | `docs/11` (tüm bölümler) |
| Analytics/SEO/Content (event ledger, GA4/Yandex/Metabase, Page Composition vs Content, tek SEO capability map, AI arama netleştirmesi) | `docs/12` (tüm bölümler) |
| i18n/state/ECA (Gettext PO canonical, Workflow/Taxonomy ayrımı, ECA engine) | `docs/13`, `docs/10` §1–§4 |
| Altı katalog (en/tr/de/fr/ar/ru) Stage 1 scaffold+pipeline / Stage 2 içerik-completeness+plural/context+RTL görsel completeness sözleşmesi (kanonik kaynak) | `docs/13` §2a — `modules/core-localization.md` §Phase delivery/§Acceptance ve `docs/26` §1 CORE-08 satırı yalnız uygular |
| Frontend state kararı (local/URL/form/server-cache/offline-draft ayrımı, TanStack Query/RHF/Zustand koşullu, Redux varsayılan değil, optimistic update disiplini) | `docs/10` §5, `modules/core-workflow-state.md` §Bounded context (yalnız link) |
| AI-first optional (AI-off determinizm, provider registry, güvenlik katmanı, Laravel AI SDK/Boost, MCP/skills sınırı) | `docs/14` (tüm bölümler) |
| Security/performance/shared-host/mobile (Fortify+Sanctum, brute-force, native vs Cloudflare dürüstlüğü, cache hiyerarşisi, kapasite matrisi, mobil strateji, standartlar) | `docs/15` (tüm bölümler) |

## G — UX

| Talimat maddesi | Karşılandığı yer |
|---|---|
| 3 tık→1 tık ölçülebilir hedef | `docs/06` §3 |
| Defaults/bulk/autosave/preview/command palette vb. | `docs/06` §5 |
| Flowbite/shadcn/Radix erişilebilir adapter kararı | `docs/06` §2, `docs/03` ADR-L06 |
| AI destructive action onay kuralı | `docs/06` §7, `docs/14` §4 |
| Admin CRUD gelişmiş UX filtresi, backend authoritative | `docs/06` §5 |

## H — Waterfall aşamaları

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Her stage'in zorunlu alanları | `docs/17` §3, uygulanmış hali `docs/18`–`docs/25` |
| 8 aşama içerikleri (MVP…Exit Ready) | `docs/18`, `docs/19`, `docs/20`, `docs/21`, `docs/22`, `docs/23`, `docs/24`, `docs/25` |
| Evidence-based çıkış, sayaç kuralı, scope değişince yeni plan | `docs/17` §4, `README.md` §İlerleme |

## I — Gap / unknown-unknowns

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Tüm 21 sınıf (product-market … vendor concentration/exit diligence) | `docs/16` §A–§T |
| Kayıt alanları (ID, sınıf, varsayım, etki, sinyal, test, sahip, tetikleyici, containment, gate) | `docs/16` (her tablo satırı) |
| ANL-02 (unique scan penceresi/fingerprint/consent) | `docs/16` §J, `docs/12` §3, `modules/analytics-consent-tagging.md` |
| QR-03 (revoke edilen destination restore/rotate semantiği) | `docs/16` §E, `modules/qr-destination.md` |
| OPS-04 (Kitchen rolü approval/yayın sınırının pilot doğrulaması) | `docs/16` §B, `docs/02` §1.2 |
| LEG-04 (restricted/minor içerik mevzuat/UX kararı) | `docs/16` §C, `docs/02` §1.3 |
| AI-03 (çok-dilli AI translation kalite eval seti) | `docs/16` §O, `modules/opt-22-ai-translation.md` |
| AIV-09 (external secret vault adapter + shared-host feasibility) | `docs/16` §W, `modules/ai-provider-account-vault.md` |
| ARCH-03 (modular monolith → servis extraction tetikleyicileri) | `docs/16` §U, `docs/03` ADR-L01 gerekçe |
| OPT-COMM-01 (mevcut ID, opt-15'teki stale metin bu ID'ye bağlandı, yeni gap açılmadı) | `docs/16` §U2, `modules/opt-15-restaurant-payment.md` |

## J — Skills planları

| Talimat maddesi | Karşılandığı yer |
|---|---|
| 22 skill listesi (18 orijinal + 4 AI Capability Plane) | `skills/*.md` |
| Her plan alanları (trigger, inputs, authority, permitted/forbidden, output, evidence, approval, failure/rollback, eval, phase) | `templates/SKILL-SPEC.md`, uygulanmış hali her `skills/*.md` |

## K — Resmi kaynak kaydı

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Tüm kaynak listesi + erişim tarihi + karar + güven + adapter notu | `docs/28` |
| OpenAI Admin API (projects/usage/admin_api_keys/debugging) — erişim doğrulandı, adaptör kararı koşullu | `docs/28` §"OpenAI Admin API (developers.openai.com) — erişim doğrulandı", `modules/ai-provider-account-vault.md` §Observability, §Permissions, §Security |
| GA4 Data API + Yandex Metrica Reporting API + PWA update/service-worker lifecycle — erişim doğrulandı, Zabuno adaptör/production kararı koşullu | `docs/28` §"GA4 / Yandex Metrica / PWA — inbound ve update lifecycle kaynakları", `docs/12` §5a, `docs/15` §5a |
| brick/money / endroid/qr-code / mPDF — capability-verified, adoption koşullu (sürüm/PHP uyumluluğu netleşmeden pinlenmez) | `docs/08` §1, `docs/09` §1, `docs/28`, `docs/16` DEP-01 |

## L — QA, vibecoding, traceability

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Waterfall QA disiplini, AI-generated change kuralları | `docs/27` |
| Requirements→module→journey→stage→WP→acceptance→test→rollback | Bu doküman (`docs/29`) + `docs/27` §2 |
| No runtime tests (docs-only, N/A gerekçeli) | `docs/27` §6 |
| Kullanıcı talebinin tüm maddelerinin eşlenmesi | Bu doküman — tamamı |

## N — AI Capability Plane (yatay ekleme, mimari sentez maddeleri)

| Talimat maddesi | Karşılandığı yer |
|---|---|
| AI yatay Capability Plane, CORE-01..16 korunur, `ai-platform` runtime-support olarak yeniden sınıflandırılır | `modules/ai-platform.md` §Sınıf, `docs/32` giriş |
| Provider Account Vault ayrı bileşen spec'i, CORE-17 yaratılmaz | `modules/ai-provider-account-vault.md` §Sınıf |
| 61 modülün her biri için AI capability manifest, mode/capabilities/AI-off path/data class/tools/forbidden authority/human approval/feature policy/budget/eval/phase | `docs/32`, her `modules/*.md` §AI Capability Manifest, `templates/AI-CAPABILITY-MANIFEST.md` |
| İki eksen: deterministic_baseline (sabit) + ai_posture (advisory/assistive/automated_guarded/agentic_guarded/none) — "AI-off determinizm" ile "AI kabiliyeti yok" karıştırılmaz | `templates/AI-CAPABILITY-MANIFEST.md` §İki eksen, `docs/32` §Temel ilke |
| Her modülde en az bir opsiyonel, provider-nötr AI kullanım örneği veya gerekçeli `none` | `docs/32` §A/B/C tabloları, her `modules/*.md` §Optional AI use case(ler) |
| N adet yetkili bağlantı, hard-code 1/2/3 yok, platform-owned vs tenant BYOK ayrımı | `modules/ai-provider-account-vault.md` §Feature × provider/model × account × policy × tenant/residency routing, §Tenant isolation |
| Tüketici Pro/Max hesabı production credential değildir, kota/rate-limit evasion yasak | `modules/ai-provider-account-vault.md` §Tüketici abonelik yasağı, `skills/ai-account-routing.md` §Forbidden actions |
| Feature × provider/model × account × policy × tenant/residency routing; priority/weighted/cost/latency/health; session affinity; idempotency; concurrency; circuit breaker/retry budget; consent; audit | `modules/ai-provider-account-vault.md` §Feature × provider/model × account × policy × tenant/residency routing, `skills/ai-account-routing.md` |
| AI credit ledger: reserve→invoke→actual debit/reconcile/release/refund, idempotent, immutable audit, provider maliyeti ile iç kredi ayrımı | `modules/ai-provider-account-vault.md` §Budget/credit behavior, CORE-12 bağımlılığı |
| AI unavailable durumları (disabled/no-credential/no-credit/quota/429/outage/residency/safety/invalid-schema) — veri kaybı yok, degraded UX, gizli ücret yok | `modules/ai-platform.md` §AI Capability Manifest, `skills/ai-no-credit-degradation.md` |
| Shared-hosting secret custody, encrypted-at-rest, master key webroot dışında | `modules/ai-provider-account-vault.md` §Security / privacy |
| AI ürün-runtime, AI-destekli geliştirme, AI operasyon yönetişimi ayrımı | `templates/AI-CAPABILITY-MANIFEST.md` §Değişmez kurallar (üç katman notu) |

## O — Postmortem, başarı modeli, public repo governance, Zabuno rebrand

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Django/FastAPI vibecoding postmortem, kök neden framework değil disiplin | `docs/30` §2, §3 |
| Tek kritik dikey dilim başarı modeli, billing/Iyzico MVP exit gate içinde | `docs/30` §4 |
| Acceptance before code, mocks test sınırında, one writer, targeted RED, iki tam QA, independent review, rollback | `docs/30` §4 (bağlayıcı disiplin maddeleri) |
| Laravel sihirli çözüm değildir | `docs/30` §5 |
| Public repo `zabuno/zabuno`, LICENSE eklenmez, `.gitignore` sözleşmesi | `docs/31`, `.gitignore` |
| imageoptimization snapshot silinmez/değiştirilmez, yalnız ignore | `docs/31` §6, `.gitignore` |
| Sanitizasyon: mutlak yol/attachment UUID/iç orkestrasyon aracı detayı yok | `docs/31` §7, `README.md`, `docs/00`, `AGENTS.md`, `CLAUDE.md` |
| Public archive attestation, path/symlink sızdırmadan 99/99 özet | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` |
| Zabuno ürün adı; legacy ürün adı owner talimatı gereği hiçbir bağlamda (tarihsel dahil) yazılmaz — yalnız "legacy QR-menü projesi/denemesi" ifadesi kullanılır | `README.md`, `docs/00`, `docs/31` §7, `modules/opt-08-custom-branding.md` |
| Marka/legacy-token doğrulama kapısı | `docs/31` §9, `skills/public-repository-gate.md` §Zorunlu marka kontrolü |

## M — Self-check ve teslim

| Talimat maddesi | Karşılandığı yer |
|---|---|
| Root top-level yalnız .git/old/laravelv01/worktrees | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §7, doğrulanmış (tam ham kanıt yalnız yerel `evidence/`de) |
| Archive manifest/inode/symlink/çalışma-alanı onarım doğrulaması | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §2, §4 |
| old içeriği edit edilmedi, moved-path caveat | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §3 (symlink caveat) |
| Tüm dosyalar var, thin/placeholder yok | Bu paketin final raporunda özetlenir |
| Broken link sıfır hedefi | Final raporda link-check sonucu raporlanır |
| 8 stage sırası doğru, atlama yok | `docs/17` §1, `docs/18`–`docs/25` |
| Next.js/Docker yalnız yasak bağlamında | `docs/03` ADR-L06, ADR-L08 — bu doküman tarafından doğrulanabilir (grep ile) |
| PLANNING ONLY + runtime-not-runnable her girişte | Her `docs/*` ve `modules/*` dosyasının başlığı |
| Bu düzeltme paketi kapsamında Claude worker Git stage/commit/push yapmadı — bu, "public depo asla var olmayacak" anlamına **gelmez**; public repo init/commit/push kendi başına ayrı, önceden yetkilendirilmiş bir yayın adımıdır (`docs/31` §1a) | `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §5, `AGENTS.md` §6 (ham git öncesi/sonrası kaydı yalnız yerel `evidence/`de) |
| Son rapor (değişen/taşınan dosyalar, checksum, self-check, riskler, rollback) | Konuşma özetinde + bu paketin final mesajında |

## Kanonik sahiplik

Bu izlenebilirlik matrisi tek kanonik kaynaktır; hiçbir başka doküman bu
eşlemeyi tekrar üretmez.
