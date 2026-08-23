# 26 — Milestone / Work Package Matrix

**PLANNING ONLY.** Bu doküman, modül × stage × edition × dependency × owner ×
status matrisinin **ve** stage×WP milestone/work-package registry'sinin **tek
kanonik sahibidir**. Diğer dokümanlar buraya link verir, kendi matrislerini
tutmaz. Bu dosya WP'lerin **ölçülebilir outcome/scope**'unu ve **kanıt
bağını** taşır — her stage'in kendi anlatısı (`kullaniciYolculugu`, `once/
simdi/fark` vb.) yalnız `docs/18`–`docs/25`'te yaşar, burada **kopyalanmaz**.

## 0. İlerleme sayacı (değişmez)

Sabit payda **0/8** — `docs/17` §4, `README.md` §İlerleme ile birebir. Bu
dosyadaki WP registry'nin genişletilmesi sayacı **değiştirmez**; sayaç yalnız
bir stage'in Exit Gate'i kanıtla GO aldığında artar.

## 1. Modül × Stage giriş matrisi (tam sekiz stage)

| Modül | Stage 1 MVP | Stage 2 Post-MVP | Stage 3 GTM | Stage 4 PMF | Stage 5 Growth | Stage 6 Enterprise | Stage 7 Maturity | Stage 8 Exit Ready |
|---|---|---|---|---|---|---|---|---|
| CORE-01..07, 10, 12..16 (CORE-08/09/11 ayrı satırda) | ✅ MVP-required baseline dilim | — | — | — | — | — | operasyonel süreç katmanı (docs/15 observability + CORE-07 + CORE-15 üzerine, `docs/24`) | belge/kanıt envanteri derlenir (`docs/25`) |
| CORE-08 Localization | altı katalog scaffold+wiring+pipeline (en/tr/de/fr/ar/ru), en complete/default | ✅ tam çeviri completeness (tr/de/fr/ar/ru) + RTL görsel completeness | — | — | — | — | — | — |
| CORE-09 Taxonomy | temel | ✅ tam | — | — | — | — | — | — |
| CORE-11 ECA | minimum (yalnız kritik yol için typed event/action registry + kritik deterministic kurallar + dry-run güvenlik temeli, `docs/18` §Module increments) | ✅ tam (full Automation Studio authoring/catalog UI + kural motoru) | — | — | — | — | — | — |
| Onboarding | ✅ | polish | — | — | — | — | — | — |
| Menu Catalog | ✅ | — | — | — | — | — | — | — |
| Publication | ✅ (senkron snapshot) | scheduled publish (OPT-11) | — | — | — | — | — | — |
| QR Destination | ✅ | — | — | — | — | — | — | — |
| QR Print Export | baseline (PNG/SVG/PDF/bulk, 6 tema) | advanced tema designer + matbaa/vendor (bleed/crop/CMYK/EPS, OPT-05) | — | — | — | — | — | — |
| PWA baseline (diner, `docs/15` §5a — modül değil, yatay teslimat) | manifest+service-worker+salt-okunur offline-fallback+bounded analytics queue çekirdeği | versioned cache/update-prompt olgunlaşması (diner) + non-authoritative local form-draft recovery UX (admin/staff, service worker değil) | — | — | — | — | — | — |
| Themes/Brand | temel | — | — | — | Custom Branding (OPT-08) | — | — | — |
| Page Composition | temel | — | — | — | — | — | — | — |
| Content/Frontpages | temel | — | ✅ tam | — | — | — | — | — |
| Pricing/Subscription/Billing | manuel+sandbox | — | ✅ canlı | — | — | özel sözleşme/limit | — | — |
| Iyzico Payment | sandbox | — | ✅ live | — | — | — | — | — |
| Analytics/Consent/Tagging | temel (scan sayaçları) | — | — | ✅ cohort/retention (asıl işlev) | GA4/Yandex Metrica inbound reporting (`docs/12` §5a) | — | — | — |
| SEO/Search & Discovery | — | — | ✅ temel | — | pSEO ölçek | — | — | — |
| Mini CRM | — | ✅ | — | — | genişletilmiş (OPT-18) | — | — | — |
| Helpdesk/Tickets | — | ✅ | — | — | — | SLA sözleşmeli | — | — |
| AI Platform (yatay plane) | mimari pre-wired (kapalı) | temel (routing+kill switch aktif) | — | — | — | — | — | — |
| AI Provider Account Vault | mimari pre-wired (kapalı) | temel (tek platform-owned hesap) | çok-hesap/BYOK | — | — | — | — | — |
| Integration Hub | temel (yalnız Iyzico webhook altyapısı) | — | — | — | — | genişletilmiş (SSO/SCIM + API/webhook) | — | — |
| Feedback/NPS (OPT-25) | — | — | — | ✅ | — | — | — | — |
| DS-00 Continuous Design System Workstream (yatay, modül değil) | ✅ paralel yaşar (Wave0 dondurulmuş sözleşme + Wave1–3 uygulama, `docs/35`) | ✅ paralel yaşar | ✅ paralel yaşar | ✅ paralel yaşar | ✅ paralel yaşar | ✅ paralel yaşar | ✅ paralel yaşar | ✅ paralel yaşar |

Boş hücre = o stage'de o modülde artımlı bir değişiklik planlanmadı (önceki
seviye korunur). Stage 7 ve Stage 8, yeni "modül" eklemez — mevcut modüllerin
üzerine operasyonel/kanıt katmanı ekler (`docs/24`, `docs/25` §Module
increments ile tutarlı).

## 2. Modül × Edition (plan tier) matrisi

| Modül | Free | Starter | Growth (edition) | Enterprise (edition) |
|---|---|---|---|---|
| Menu Catalog (1 menü) | ✅ | ✅ | ✅ | ✅ |
| Multiple Menus (OPT-03) | ❌ | ❌ | ✅ | ✅ |
| Advanced QR Designer (OPT-05) | ❌ | ✅ | ✅ | ✅ |
| Custom Domain (OPT-09) | ❌ | ❌ | ✅ | ✅ |
| Multi-branch (OPT-10) | ❌ | ❌ | ✅ | ✅ |
| SSO/SCIM | ❌ | ❌ | ❌ | ✅ |
| Advanced Analytics (OPT-06) | ❌ | ✅ | ✅ | ✅ |

Not: "Edition" burada `docs/09` §4'teki entitlement envanterinin ticari
paketlenmesidir — kesin fiyat/limit sayıları bu matrisin sorumluluğu değildir,
`docs/09`'a link verilir.

## 3. Milestone / Work Package registry (S1–S8, kanonik)

Kimlik biçimi: `S<stage>-WP<sıra>` (örn. `S1-WP01`), her kimlik **benzersizdir**
ve bu külliyatta yalnız bu registry'de üretilir. Kimliğin `S<n>` öneki **zaten
stage field'ıdır** — WP hangi stage'e ait olduğunu kimliğinden taşır ve aşağıda
o stage'in kendi `### Stage N` bölüm başlığı altında listelenir; bu yüzden
ayrı bir "Stage" sütunu **gerekmez** (kimlik + bölüm başlığı çapraz
doğrulanabilir, tekrar bir alan bu doğrulamayı güçlendirmez, yalnız
tekrar eder — `AGENTS.md` §2 tek kanonik sahiplik ilkesiyle tutarlı).

Her satır: **outcome/scope** (ölçülebilir, "ne biter" — anlatı değil),
**predecessor** (aynı stage içi veya önceki stage'e bağımlılık — critical
path), **owner**, **acceptance evidence / exit-gate bağı** (ilgili stage
dokümanının Acceptance evidence bölümüne veya `docs/27`'ye link), **status**
(bu paket üretimi sırasında hepsi `not-started`, §6 sözleşmesiyle).

**Owner sütununun iki ayrı sınıfı vardır, karıştırılmaz**:

1. **Runtime platform/tenant personası** — `docs/02`'deki gerçek bir rol adı
   (örn. "Growth/Marketing Operator", "Finance Operator", "Owner"); bu, o WP'nin
   ürettiği **kalıcı özelliği kimin runtime'da kullanacağı/yöneteceği**
   ile ilişkilidir ve `docs/02` §1'e **bağımlıdır** — `docs/02`'de bir rol
   yoksa bu sütunda o rol adı **kullanılmaz**.
2. **Proje delivery accountability function'ı** — "Architecture", "Engineering",
   "Design", "Product", "Security", "Hukuk danışmanlığı" gibi adlar bir
   **runtime yetki rolü değildir**; bunlar bu planlama külliyatının WP'yi
   **kimin teslim etmekten sorumlu olacağını** belirten delivery-accountability
   etiketleridir (ADR/spec/kanıt sahipliği anlamında — `docs/03`, `docs/27`
   disipliniyle tutarlı). Bunlar `docs/02` rol taksonomisinde **karşılığı
   olması gerekmeyen**, tamamen ayrı bir isimlendirme uzayıdır.

Bir WP satırının Owner hücresi bu iki sınıftan birini veya ikisinin
kombinasyonunu taşıyabilir (örn. "Growth/Marketing Operator + Engineering" —
ilki runtime persona, ikincisi delivery function). Bu ayrım, hiçbir WP
satırının içeriğini (outcome/scope/predecessor/status) **değiştirmez** —
yalnız Owner sütununun nasıl okunacağını netleştirir.

### Stage 1 — MVP (`docs/18`)

| WP | Outcome / Scope | Predecessor | Owner | Acceptance evidence / exit-gate | Status |
|---|---|---|---|---|---|
| S1-WP01 | Foundation/preflight: modül registry/manifest bootstrap (CORE-05), env/config katmanlama (dev/staging/prod), temel CI iskeleti (build/lint/test), OWASP ASVS temel checklist bağlanır | — (kök WP) | Architecture + Engineering | `docs/03` ADR uygunluk kontrolü, `docs/27` genel disiplin | in-progress (alt paket S1-WP01A foundation iskeleti implementation-in-progress — hedefli preflight/PHPUnit/Vitest/build kanıtı; FULL_QA_LOCAL_1 bir kez çalıştı (8/10, Pint hedefli düzeltildi); ikinci tam QA bütçesi yalnız CI için rezerve; iki bağımsız review de INDEPENDENT_REVIEW_RED sonucu verdi — ilki iki P1 blocker buldu (composer license metadata + AGENTS.md/docs/31 public-governance çelişkisi) ve iki P2 hedefli RED→GREEN düzeltildi, ikincisi (209 dosyalık dondurulmuş snapshot) bu iki P2 kapanışını GREEN doğruladı ve üçüncü bir blocker bulmadı, aynı iki P1 hâlâ açık (bkz. `docs/27` §6); WP'nin tamamı henüz `done-with-evidence` değildir) |
| S1-WP02 | Identity+Tenant+Permission+Admin shell: CORE-01 Identity/Sessions, CORE-02 Tenancy, CORE-03 Authorization (RBAC baseline), CORE-06 Settings/Secrets, admin panel iskeleti | S1-WP01 | Architecture + Engineering | Tenant escape testi (`docs/16` AUTH-02), IDOR testi (`docs/05` §6) | in-progress (alt paket **S1-WP02A** — CORE-01-only register→verification-pending→signed/expiring email verification→authenticated cookie session→logout dikey dilimi — yerel çalıştırılabilir bir implementation candidate'tır: hedefli evidence GREEN (Vitest 23/23, odaklı PHP closure review 5 test/6 assertion, lint/build/Pint/`git diff --check` GREEN, bağımsız closure review FINAL_INDEPENDENT_REVIEW_GREEN — P0/P1/P2 yok, bkz. `docs/33` §Final durum); durum **WP02A local-candidate-targeted-green**, **public-promotion RED**. Alt paket **S1-WP02B** — bounded CORE-02 Tenancy baseline (workspace create+owner-membership tek transaction, üyelik-scope'lu liste, current/switch context, enumeration-safe tenant escape reddi) — artık **code/test-local-candidate-targeted-green**'dir: 23/23 hedefli test/72 assertion GREEN, düzeltilmiş kod üzerinde bağımsız kapanış review'ı S1_WP02B_CLOSURE_REVIEW_GREEN (P0/P1/P2 yok), yalnız API kodu + izole test kanıtı — persistent DB migrate edilmedi, workspace UI/manuel E2E yok (bkz. `docs/34` §13a). CORE-02'nin geri kalanı (state geçişleri, davet, Brand/Location/işletme profili) ve CORE-03 Authorization, CORE-06 Settings/Secrets, admin panel iskeleti bu iki bounded dilimin **dışındadır**, hâlâ not-started; bu yüzden S1-WP02 bütünü **in-progress** olarak işaretlidir; hiçbiri **done-with-evidence** değildir) |
| S1-WP03 | Menu+Media: Menu Catalog (kategori/ürün/alerjen/görsel/fiyat/görünürlük), CORE-13 Media baseline (upload/validate/derivative **+ quarantine/security-scan zorunluluğu**, `docs/07` §5, `modules/core-file-media.md` §Phase delivery), CORE-09 Taxonomy temel, CORE-08 altı katalog scaffold+PO→MO→JSON pipeline (`docs/13` §2a) | S1-WP02 | Engineering + Design | Medya golden-file testi, quarantine/security-scan testi (`docs/16` MED-03, taranmamış asset public olamaz), i18n pipeline tutarlılık testi (6 katalog) | not-started |
| S1-WP04 | Publication+QR Resolve+Bulk Print: Publication (draft/preview/publish/snapshot), QR Destination resolver, QR Print Export baseline (PNG/SVG/PDF, bulk wizard, 6 tema), diner PWA baseline (manifest+service-worker+salt-okunur offline-fallback+bounded analytics queue, `docs/15` §5a) | S1-WP03 | Engineering + Design | QR fiziksel scan testi (`docs/16` QR-02), PWA installability + salt-okunur offline-fallback testi | not-started |
| S1-WP05 | Analytics+Team+Pricing entitlements: Analytics/Consent/Tagging temel (scan sayaçları, QR Resolve vs Confirmed Menu Open), Team (Owner+Editor davet), CORE-04 Entitlements, Pricing/Subscription/Billing (plan katalogu + manuel ödeme) | S1-WP02 (S1-WP03/04 ile paralel) | Engineering + Finance Operator | Consent-gated tag testi (`docs/12` §1), entitlement uygulama testi | not-started |
| S1-WP06 | Iyzico sandbox/webhook: sandbox checkout/3DS, server-side tutar doğrulama, imzalı webhook doğrulama + replay protection, idempotency, CORE-12 Money/Ledger | S1-WP05 | Finance Operator + Engineering | Webhook imza doğrulama testi, idempotency testi (`docs/09` §6) | not-started |
| S1-WP07 | Security/shared-host/backup-restore/exit evidence: OWASP ASVS denetimi, shared-host kapasite probe'u (`docs/16` MED-01), backup/restore drill (`docs/16` DR-02), RPO/RTO kararı (`docs/16` DR-01), MVP Exit Gate kanıt paketi derlemesi | S1-WP02, S1-WP04, S1-WP06 | Engineering + Security + Owner | E2E kritik yol kaydı + restore drill kaydı (`docs/18` §Acceptance evidence), Exit Gate GO/NO-GO kararı | not-started |

### Stage 2 — Post-MVP (`docs/19`)

| WP | Outcome / Scope | Predecessor | Owner | Acceptance evidence / exit-gate | Status |
|---|---|---|---|---|---|
| S2-WP01 | Production hardening: MVP kırılganlık noktaları giderilir, modül registry tam lifecycle (install/enable/disable/upgrade/uninstall), CORE-09 Taxonomy tam, PWA versioned cache/update-prompt olgunlaşması (diner) + non-authoritative local form-draft recovery UX (admin/staff, `docs/15` §5a) | S1-WP07 (MVP Exit Gate GO) | Engineering | `docs/27` genel disiplin + PWA update-prompt testi + form-draft recovery testi (kritik alan asla yerelde tutulmadığının doğrulaması) | not-started |
| S2-WP02 | Medya pipeline/registry olgunlaşması: derivative pipeline tam (WebP/AVIF, decompression-bomb/SVG-sanitize hardened), asset kullanım grafiği, replace-without-broken-reference, derivative kalıtımlı görünürlük politikasının (draft→private/signed, published→public) tam kapsamlı uygulanması | S2-WP01 | Engineering | Medya güvenlik pipeline testi + quarantine/security-scan regresyon testi (`docs/07` §5, `docs/16` MED-03), derivative signed-URL erişim testi | not-started |
| S2-WP03 | Full ECA Automation Studio: CORE-11 tam (authoring UI, katalog, dry-run, version/approval, recursion/cycle guard, audit) | S2-WP01 | Engineering | ECA recursion/cycle guard testi (`docs/19` §Acceptance evidence) | not-started |
| S2-WP04 | Altı dil/RTL içerik tamamlanması: tr/de/fr/ar/ru içerik-completeness (PO üzerinden), plural/context completeness, RTL görsel regresyon tam kapsam (`docs/13` §2a Stage 2 sözleşmesi) | S1-WP03 | Engineering + Design | RTL görsel regresyon testi, PO→MO→JSON tutarlılık testi (`docs/13` §2a, `modules/core-localization.md` §Acceptance) | not-started |
| S2-WP05 | Mini CRM: contact/consent/timeline/segment | S1-WP05 | Growth/Marketing Operator + Engineering | `docs/27` genel disiplin | not-started |
| S2-WP06 | Helpdesk/Tickets: queue/status/SLA/escalation temel | S2-WP05 (opsiyonel ilişki, `docs/26` §4 dependency graph) | Engineering | `docs/27` genel disiplin | not-started |

### Stage 3 — Go-to-Market (`docs/20`)

| WP | Outcome / Scope | Predecessor | Owner | Acceptance evidence / exit-gate | Status |
|---|---|---|---|---|---|
| S3-WP01 | Canlı billing/Iyzico: sandbox→live geçiş kapısı, canlı checkout, reconciliation job'ları | S1-WP06, S2-WP01 (Post-MVP Exit Gate GO) | Finance Operator + Engineering | Sandbox→live geçiş kapısı kaydı, webhook imza testi (`docs/20` §Acceptance evidence) | not-started |
| S3-WP02 | Legal/consent: Legal Records tam (şartlar/aydınlatma/rıza/doküman versiyonları) | S3-WP01 ile paralel | Owner + Hukuk danışmanlığı | Hukuk review kaydı | not-started |
| S3-WP03 | SEO/Frontpages canlı: SEO/Search & Discovery temel (technical+local facet), Content/Frontpages tam | S2-WP01 | Growth/Marketing Operator + Engineering | pSEO/technical SEO kalite kapısı testi (`docs/12` §8) | not-started |
| S3-WP04 | Operasyonel GTM hazırlığı: onboarding/support runbook, monitoring, prod-benzeri restore doğrulaması, prod yük/güvenlik/release kapıları | S3-WP01, S3-WP02, S3-WP03 | Engineering + Owner | Restore drill (prod-benzeri ortam), production yük testi (`docs/20` §Acceptance evidence) | not-started |

### Stage 4 — Product-Market Fit (`docs/21`)

| WP | Outcome / Scope | Predecessor | Owner | Acceptance evidence / exit-gate | Status |
|---|---|---|---|---|---|
| S4-WP01 | First-party veri kalitesi: event ledger bütünlüğü, bot filtreleme doğrulaması, QR Resolve vs Confirmed Menu Open doğruluk denetimi | S3-WP04 (GTM Exit Gate GO) | Engineering | Bot-trafik ayrıştırma testi (`docs/21` §Rollback trigger ile ilişkili) | not-started |
| S4-WP02 | Cohort/retention: cohort analizi, D7/D30 retention dashboard, churn hesaplama | S4-WP01 | Growth/Marketing Operator + Engineering | Cohort hesaplama doğruluk testi | not-started |
| S4-WP03 | Feedback/deneyim + experiment altyapısı: Feedback/NPS (OPT-25), temel A/B deney altyapısı | S4-WP01 | Product + Engineering | `docs/27` genel disiplin | not-started |
| S4-WP04 | PMF kanıt kapısı: owner-onaylı retention baseline dokümanı, Exit Gate GO/NO-GO/CONDITIONAL kararı | S4-WP02, S4-WP03 | Owner | Owner-onaylı retention baseline dokümanı (`docs/21` §Acceptance evidence — keyfi sayı uydurma yasak) | not-started |

### Stage 5 — Growth (`docs/22`)

| WP | Outcome / Scope | Predecessor | Owner | Acceptance evidence / exit-gate | Status |
|---|---|---|---|---|---|
| S5-WP01 | Kapasite planlaması: yük testi, ölçülmüş ihtiyaçla opsiyonel Redis/S3 aktivasyonu | S4-WP04 (PMF Exit Gate GO + retention kanıtı) | Engineering | Yük testi sonuçları (`docs/22` §Acceptance evidence) | not-started |
| S5-WP02 | Multi-branch: OPT-10 Multi-branch Management (`docs/05` §1'deki 1:N model genişlemesi) | S5-WP01 | Architecture + Engineering | Multi-branch tenant-isolation testi | not-started |
| S5-WP03 | Growth entegrasyonları: OPT-17 Loyalty, OPT-18 CRM genişletilmiş, OPT-19/20 marketing automation/campaign, OPT-27 Marketplace (erken), OPT-28 Metabase Embed, GA4/Yandex Metrica inbound reporting adaptörü (`docs/12` §5a, `docs/16` ANL-03) | S5-WP02 | Growth/Marketing Operator + Engineering | Inbound adaptör mapping/discrepancy-indicator testi | not-started |
| S5-WP04 | Opsiyonel native shell kanıtı: Capacitor native shell substantive-value değerlendirmesi (yalnız ölçülmüş ihtiyaçta), App Store/Play submission denemesi (onay garanti edilmez) | S5-WP01 | Engineering | Erken TestFlight/Play submission kaydı (`docs/16` APP-01) | not-started |

### Stage 6 — Enterprise Level (`docs/23`)

| WP | Outcome / Scope | Predecessor | Owner | Acceptance evidence / exit-gate | Status |
|---|---|---|---|---|---|
| S6-WP01 | SSO/SCIM: CORE-03 üzerine SAML/OIDC adaptörleri, SCIM provisioning | S5-WP02, S5-WP03 (Growth Exit Gate GO) | Architecture + Engineering + Security | SSO/SCIM entegrasyon testi (`docs/23` §Acceptance evidence) | not-started |
| S6-WP02 | Veri residency/SLA: residency seçenekleri, SLA/DR/HA sözleşmeleri | S6-WP01 | Engineering + Owner | Residency altyapı karşılığının doğrulanması | not-started |
| S6-WP03 | Governed API/audit/enterprise kontroller: Integration Hub genişletilmiş (API/webhook), CORE-07 kurumsal audit export formatı, approval workflow, özel sözleşme/limit desteği | S6-WP01 | Engineering + Finance Operator | Kurumsal audit export'un sözleşme gereksinimini karşıladığının doğrulaması | not-started |

### Stage 7 — Maturity Level (`docs/24`)

| WP | Outcome / Scope | Predecessor | Owner | Acceptance evidence / exit-gate | Status |
|---|---|---|---|---|---|
| S7-WP01 | SRE/SLO/DORA: SLI/SLO tanımları, DORA dörtlüsü izleme hattı (`docs/15` §6 OpenTelemetry üzerine) | S6-WP01, S6-WP02, S6-WP03 (Enterprise Exit Gate GO) | Engineering | Tanımlı SLO'lara karşı gerçek uyum verisi (`docs/24` §Acceptance evidence) | not-started |
| S7-WP02 | Cost/vendor/deprecation: unit economics şeffaflığı, deprecation/compatibility politikası, vendor risk değerlendirmesi | S7-WP01 | Finance Operator + Engineering | Vendor risk değerlendirme kaydı | not-started |
| S7-WP03 | DR/restore operasyonel olgunluk: düzenli incident/DR tatbikatları, runbook kütüphanesi | S7-WP01 | Engineering | Gerçekleştirilmiş DR tatbikatı raporu | not-started |
| S7-WP04 | Security/privacy program: resmi sahiplik + periyodik review takvimi, observability operasyonel mükemmeliyet | S7-WP01 | Security + Engineering | Privacy/security program review kaydı | not-started |

### Stage 8 — Exit Ready (`docs/25`)

| WP | Outcome / Scope | Predecessor | Owner | Acceptance evidence / exit-gate | Status |
|---|---|---|---|---|---|
| S8-WP01 | Data room derlemesi: due-diligence merkezi belge deposu | S7-WP01..WP04 (Maturity Exit Gate GO) | Owner + Engineering | Bağımsız/simüle edilmiş due-diligence checklist sonucu (`docs/25` §Acceptance evidence) | not-started |
| S8-WP02 | IP/lisans/SBOM envanteri: SBOM+lisans+dependency pinning denetimi, `docs/16` LIC-01/LIC-02 kapanışı | S8-WP01 | Engineering + Owner | SBOM/lisans denetim raporu | not-started |
| S8-WP03 | Finansal/metrik lineage: `docs/28`/`docs/29` omurgasıyla metrik/finansal geçmişin izlenebilirliği | S8-WP01 | Finance Operator | İzlenebilirlik zinciri doğrulaması | not-started |
| S8-WP04 | Müşteri/vendor concentration analizi: `docs/16` EXIT-01 ve müşteri concentration incelemesi | S8-WP01 | Owner | Concentration analiz raporu | not-started |
| S8-WP05 | Reproducible restore + mimari/veri envanteri: deploy/restore tekrarlanabilirlik testi, güncel mimari/veri envanteri | S7-WP03 | Engineering | Reproducible deploy başarı oranı (`docs/25` §Metrics) | not-started |
| S8-WP06 | Key-person/transition hazırlığı: key-person bağımlılık skoru, transition playbook | S8-WP01 | Owner + Engineering | Key-person bağımlılık skoru + yazılı transition playbook | not-started |

## 4. Dependency graph (metin tablosu)

| Modül | Bağımlı olduğu |
|---|---|
| Onboarding | CORE-01, CORE-02, CORE-05 |
| Menu Catalog | CORE-02, CORE-13 (medya) |
| Publication | Menu Catalog, CORE-13 |
| QR Destination | Publication, CORE-02 |
| QR Print Export | QR Destination, CORE-13 |
| PWA baseline (diner) | Publication, QR Destination (`docs/15` §5a) |
| Pricing/Subscription/Billing | CORE-04, CORE-12 |
| Iyzico Payment | Pricing/Subscription/Billing |
| Mini CRM | CORE-02, CORE-11 (ECA event register) |
| Helpdesk/Tickets | CORE-02, Mini CRM (opsiyonel ilişki) |
| Analytics/Consent/Tagging (GA4/Yandex inbound) | Integration Hub (secret custody/bağlantı kaydı) |
| AI Platform | CORE-03, CORE-04, CORE-06, CORE-07, CORE-12, AI Provider Account Vault |
| AI Provider Account Vault | CORE-06, CORE-07, CORE-12 |

Tam DAG doğrulaması (döngü kontrolü) `docs/04` §6'daki semantic-compatibility
resolver seçimine bağlıdır (henüz seçilmedi, `docs/16` MOD-01). Yukarıdaki §3
WP registry'sindeki predecessor sütunu, bu modül bağımlılık grafiğiyle ve
`docs/17` sekiz-stage sırasıyla **tutarlıdır** — bir WP'nin predecessor'ı asla
sonraki bir stage'de veya bağımlı olmadığı bir modülde olamaz.

## 5. Owner atama

| Alan | Owner rolü (bkz. `docs/02`) |
|---|---|
| CORE modülleri | Architecture + Engineering |
| Money/Billing/Iyzico | Finance Operator + Engineering |
| SEO/Analytics (inbound GA4/Yandex dahil) | Growth/Marketing Operator (`docs/02` §1.1) + Engineering |
| AI Platform | Engineering + Security (birlikte) |
| AI Provider Account Vault | Engineering + Security (custody), Finance Operator (credit ledger) |
| Legal Records | Owner + Hukuk danışmanlığı |
| Stage 7/8 operasyonel/due-diligence WP'leri | Engineering + Security + Owner (Finance Operator finansal lineage için) |

Bu tablo, §3'teki WP registry'sinin "Owner" sütunundaki **delivery
accountability function'larıyla** (Architecture/Engineering/Finance
Operator/Security/Hukuk danışmanlığı gibi) ve orada geçen **runtime
personalarıyla** (Growth/Marketing Operator) **çelişmez** — ancak bu, her
alanın §3'te birebir aynı kelimelerle tekrarlandığı anlamına **gelmez**; bu
tablo alan-bazlı bir **özet** eşlemedir, WP-bazlı ayrıntı yalnız §3'te
yaşar (tek kanonik sahiplik, `AGENTS.md` §2). Owner sütununun iki sınıfının
(runtime persona vs. delivery function) tanımı §3 başında yapılmıştır, burada
tekrar edilmez.

## 6. Status alanı sözleşmesi

Her satır (§1 matrisi ve §3 WP registry'si) için status: `not-started` (bu
külliyattaki her satırın **varsayılan mevcut durumu**), `planned`,
`in-progress`, `blocked`, `done-with-evidence`. Şu an külliyattaki bu
varsayılanın **iki** istisnası vardır: **S1-WP01** (`in-progress` — alt
paket S1-WP01A foundation iskeleti implementation-in-progress, §3 Stage 1
satırı) ve **S1-WP02** (`in-progress` — alt paket S1-WP02A CORE-01-only
dikey dilimi local-candidate-targeted-green, public-promotion RED
(`docs/33`); alt paket S1-WP02B bounded CORE-02 Tenancy baseline
code/test-local-candidate-targeted-green, public-promotion RED (`docs/34`
§13a); WP02'nin geri kalanı (CORE-02 remainder, CORE-03, CORE-06, admin
shell) hâlâ not-started, §3 Stage 1 satırı). Geri kalan
**her** modül/stage/WP kombinasyonu hâlâ `not-started` durumundadır — plan
üretimi (doküman genişletmesi) bu durumu değiştirmez, yalnız gerçek
implementasyon ilerlemesi değiştirir. `docs/17` §4'teki sabit **0/8** sayaç
kuralı bu WP registry'sinden **bağımsızdır** — bir stage'in tüm WP'leri
`done-with-evidence` olsa bile, sayaç yalnız o stage'in Exit Gate'i **kanıtla**
GO aldığında artar (owner kararı, otomatik değil).

## 7. Kanonik sahiplik

Bu dosya modül×stage×edition×dependency×owner×status matrisinin **ve**
stage×WP milestone/work-package registry'sinin tek kanonik kaynağıdır. Stage
dokümanları (`docs/18`–`docs/25`) kendi anlatısını (`once/simdi/fark`,
`kullaniciYolculugu` vb.) taşır ve bu registry'ye link verir; bu registry
stage anlatısını **tekrar üretmez**, yalnız outcome/scope/kanıt bağını taşır.
