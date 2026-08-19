# 17 — Waterfall Lifecycle Master

**PLANNING ONLY.** Bu doküman sekiz aşamalı sabit sıranın **tek kanonik
tanımıdır**; her stage dokümanı (`docs/18`–`docs/25`) buraya link verir, sırayı
tekrar tanımlamaz.

## 1. Sabit sıra (değişmez)

```
1. MVP
2. Post-MVP
3. Go-to-Market (GTM)
4. Product-Market Fit (PMF)
5. Growth
6. Enterprise Level
7. Maturity Level
8. Exit Ready
```

Faz atlama, takvimle otomatik terfi ve kanıtsız "tamamlandı" iddiası **yasaktır**.
Bir sonraki stage'e geçiş yalnız önceki stage'in **Exit Gate**'i kanıtla (evidence)
GO/CONDITIONAL-GO alındığında olur. **Stage 1 dışındaki her stage, bir önceki
stage'in Exit Gate'i GO/CONDITIONAL-GO almadan başlayamaz** — bu §3a'daki her
stage panosunun "Entry gate" satırında somutlanır. **Exit Ready (Stage 8) son
stage'dir**; ondan sonra "next-stage admission" yoktur.

## 2. Enterprise yönetişim ≠ Stage 6 "Enterprise Level" — kritik ayrım

| | Enterprise sınıfı waterfall yönetişimi | Stage 6 "Enterprise Level" |
|---|---|---|
| Ne | Dokümantasyon disiplini: ADR, requirements→acceptance→test izlenebilirliği, evidence gates | Ürün/operasyon kabiliyeti: SSO/SAML/OIDC, SCIM, data residency, SLA/DR/HA |
| Ne zaman | **Gün 1'den** geçerli | Stage 5 (Growth) tamamlanıp kanıtla GO alındıktan **sonra** |
| Bu iki kavram karıştırılırsa | "Yönetişim disiplinimiz var, o yüzden Enterprise stage'deyiz" gibi **yanlış** bir sonuç çıkar | — |

Bu külliyatın kendisi (ADR'ler, izlenebilirlik matrisi, gap register) Enterprise
sınıfı yönetişimin **bir örneğidir** — ama bu, ürünün Stage 6'da olduğu anlamına
**gelmez**. Ürün hâlâ **0/8**'dedir (bkz. `README.md` §İlerleme).

## 3a. Stage özet panoları (tek bakış)

> Numaralandırma notu: bu bölüm §2 ile §3 (Her stage dokümanının zorunlu
> alanları) arasına, mevcut bölüm numaralarını **kaydırmadan** eklenmiştir —
> `docs/26`, `docs/22`, `docs/23`, `docs/30` ve `templates/MILESTONE-GATE.md`
> bu dosyanın §2/§3/§4/§5'ine sabit numarayla referans verir; o referanslar
> bu ekleme ile **bozulmaz**.

Her panoda beş alan vardır: **Amaç/capability delta**, **Entry gate**,
**Sıralı alt fazlar**, **Exit gate/evidence özeti**, **Stage-detail**. Bu
panolar §3'teki zorunlu alanların **kısaltılmış özetidir** — tam anlatı
(`once/simdi/fark`, `kullaniciYolculugu`, scope/non-goals, metrics, security/
a11y/performance/i18n, rollback trigger vb.) yalnız `docs/18`–`docs/25`'te
yaşar, burada **tekrar edilmez**.

Alt fazların (WP) **kimliği, sırası ve sayısı** `docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md`
§3 ile **birebir aynıdır** (toplam 38 WP: S1=7, S2=6, S3=4, S4=4, S5=4, S6=3,
S7=4, S8=6) — bu panolarda yalnız kısa, anlamlı bir ad eklenmiştir; her WP'nin
**ölçülebilir outcome/scope, predecessor, owner, acceptance-evidence bağı ve
status**'unun kanonik sahibi `docs/26` §3'tür, burada **kopyalanmaz**. Her
WP'nin **acceptance/journey** ayrıntısının kanonik sahibi ilgili stage
dokümanıdır (`docs/18`–`docs/25`).

### Stage 1 — MVP

- **Amaç / capability delta**: Tek dikey kritik yolun gerçekten çalışır
  (runnable) olduğu, kanıtla doğrulanmış bir ilk sürüm. `0 → tam dikey kritik
  yol` (kayıt→menü→yayın→QR→fiyat güncellemesinin anlık yansıması).
- **Entry gate**: Külliyatın (docs/00–32, modules/, skills/, templates/)
  plan-onayı alması + `docs/16`'daki MVP-kritik açık maddelerin en az
  "containment ile ilerle" kararıyla kapanması.
- **Sıralı alt fazlar** (ayrıntı `docs/26` §3 "Stage 1"):
  1. S1-WP01 — Foundation & preflight
  2. S1-WP02 — Identity/Tenant/Auth shell
  3. S1-WP03 — Menu & Media
  4. S1-WP04 — Publication & QR Print
  5. S1-WP05 — Analytics & Entitlements
  6. S1-WP06 — Iyzico sandbox
  7. S1-WP07 — Security & Exit evidence
- **Exit gate / evidence özeti**: E2E kritik yol kaydı + tenant escape testi +
  QR fiziksel scan testi + restore drill. Exit GO/NO-GO: **henüz
  değerlendirilmedi** (stage başlamadı).
- **Stage-detail**: [`docs/18-STAGE-01-MVP.md`](18-STAGE-01-MVP.md)

### Stage 2 — Post-MVP

- **Amaç / capability delta**: MVP'nin kırılgan noktalarını gidermek ve
  opsiyonel-modül temelini hazırlamak. `MVP dikey yol → +medya olgunluğu +ECA
  +CRM/Helpdesk +çoklu dil +production hardening`.
- **Entry gate**: MVP Exit Gate GO/CONDITIONAL-GO.
- **Sıralı alt fazlar** (ayrıntı `docs/26` §3 "Stage 2"):
  1. S2-WP01 — Production hardening
  2. S2-WP02 — Medya pipeline olgunlaşması
  3. S2-WP03 — Full ECA Automation Studio
  4. S2-WP04 — Altı dil/RTL tamamlama
  5. S2-WP05 — Mini CRM
  6. S2-WP06 — Helpdesk/Tickets
- **Exit gate / evidence özeti**: `docs/27` genel disiplini + medya
  golden-file testleri + ECA recursion/cycle guard testi. Exit GO/NO-GO:
  **henüz değerlendirilmedi**.
- **Stage-detail**: [`docs/19-STAGE-02-POST-MVP.md`](19-STAGE-02-POST-MVP.md)

### Stage 3 — Go-to-Market (GTM)

- **Amaç / capability delta**: Gerçek para hareket ettiren ilk müşterilere
  güvenle açılmak. `sandbox ödeme → canlı ödeme + yasal/SEO/operasyon
  hazırlığı`.
- **Entry gate**: Post-MVP Exit Gate GO.
- **Sıralı alt fazlar** (ayrıntı `docs/26` §3 "Stage 3"):
  1. S3-WP01 — Canlı billing/Iyzico
  2. S3-WP02 — Legal/consent
  3. S3-WP03 — SEO/Frontpages canlı
  4. S3-WP04 — Operasyonel GTM hazırlığı
- **Exit gate / evidence özeti**: Sandbox→live geçiş kapısı kaydı + webhook
  imza doğrulama testi + prod-benzeri restore drill. Exit GO/NO-GO: **henüz
  değerlendirilmedi**.
- **Stage-detail**: [`docs/20-STAGE-03-GO-TO-MARKET.md`](20-STAGE-03-GO-TO-MARKET.md)

### Stage 4 — Product-Market Fit (PMF)

- **Amaç / capability delta**: Retention/use kanıtı olmadan Growth'a
  geçilmeyeceğini garanti eden bir ölçüm disiplini kurmak. `canlı ödeme →
  sistematik davranış/tutunma ölçümü`.
- **Entry gate**: GTM Exit Gate GO + owner tarafından belirlenen minimum
  ödeyen müşteri sayısı (`docs/16` BIZ-03/04).
- **Sıralı alt fazlar** (ayrıntı `docs/26` §3 "Stage 4"):
  1. S4-WP01 — First-party veri kalitesi
  2. S4-WP02 — Cohort/retention
  3. S4-WP03 — Feedback & experiment altyapısı
  4. S4-WP04 — PMF kanıt kapısı
- **Exit gate / evidence özeti**: Owner-onaylı retention baseline dokümanı —
  **owner onayı olmadan keyfi sayı uydurma yasaktır**. Exit GO/NO-GO: **henüz
  değerlendirilmedi**.
- **Stage-detail**: [`docs/21-STAGE-04-PRODUCT-MARKET-FIT.md`](21-STAGE-04-PRODUCT-MARKET-FIT.md)

### Stage 5 — Growth

- **Amaç / capability delta**: Ölçülmüş ihtiyaca göre kapasite ve çok-şube
  desteğini genişletmek. `tek-şube → çok-şube/zincir + ölçek altyapısı`.
- **Entry gate**: PMF Exit Gate GO + owner-onaylı retention/use kanıtı
  (`docs/21`).
- **Sıralı alt fazlar** (ayrıntı `docs/26` §3 "Stage 5"):
  1. S5-WP01 — Kapasite planlaması
  2. S5-WP02 — Multi-branch
  3. S5-WP03 — Growth entegrasyonları
  4. S5-WP04 — Opsiyonel native shell kanıtı
- **Exit gate / evidence özeti**: Yük testi sonuçları + multi-branch
  tenant-isolation testi. Exit GO/NO-GO: **henüz değerlendirilmedi**.
- **Stage-detail**: [`docs/22-STAGE-05-GROWTH.md`](22-STAGE-05-GROWTH.md)

### Stage 6 — Enterprise Level

> Ürün/operasyon kabiliyet seviyesi — gün 1'den beri uygulanan Enterprise
> sınıfı waterfall **yönetişimi** ile karıştırılmaz (§2).

- **Amaç / capability delta**: Kurumsal müşteri gereksinimlerini modüler
  monolit mimariyi bozmadan karşılamak. `self-service SaaS → kurumsal
  entegrasyon + sözleşmeli garanti seviyesi`.
- **Entry gate**: Growth Exit Gate GO.
- **Sıralı alt fazlar** (ayrıntı `docs/26` §3 "Stage 6"):
  1. S6-WP01 — SSO/SCIM
  2. S6-WP02 — Veri residency/SLA
  3. S6-WP03 — Governed API/audit/enterprise kontroller
- **Exit gate / evidence özeti**: SSO/SCIM entegrasyon testi + kurumsal audit
  export'un sözleşme gereksinimini karşıladığının doğrulaması. Exit GO/NO-GO:
  **henüz değerlendirilmedi**.
- **Stage-detail**: [`docs/23-STAGE-06-ENTERPRISE.md`](23-STAGE-06-ENTERPRISE.md)

### Stage 7 — Maturity Level

- **Amaç / capability delta**: Dışa dönük özellik eklemeden, platformun kendi
  operasyonel dayanıklılığını kurumsallaştırmak. `kurumsal entegrasyon → iç
  operasyonel sağlamlaştırma`.
- **Entry gate**: Enterprise Level Exit Gate GO.
- **Sıralı alt fazlar** (ayrıntı `docs/26` §3 "Stage 7"):
  1. S7-WP01 — SRE/SLO/DORA
  2. S7-WP02 — Cost/vendor/deprecation
  3. S7-WP03 — DR/restore operasyonel olgunluk
  4. S7-WP04 — Security/privacy programı
- **Exit gate / evidence özeti**: Gerçekleştirilmiş DR tatbikatı raporu +
  tanımlı SLO'lara karşı gerçek uyum verisi + vendor risk değerlendirme kaydı.
  Exit GO/NO-GO: **henüz değerlendirilmedi**.
- **Stage-detail**: [`docs/24-STAGE-07-MATURITY.md`](24-STAGE-07-MATURITY.md)

### Stage 8 — Exit Ready

- **Amaç / capability delta**: Dış due diligence sürecini ürün ekibinin
  sürekli müdahalesi olmadan geçirebilecek bir kanıt/envanter paketi
  oluşturmak. `iç operasyonel olgunluk → dış işlem hazırlığı`.
- **Entry gate**: Maturity Level Exit Gate GO.
- **Sıralı alt fazlar** (ayrıntı `docs/26` §3 "Stage 8"):
  1. S8-WP01 — Data room derlemesi
  2. S8-WP02 — IP/lisans/SBOM envanteri
  3. S8-WP03 — Finansal/metrik lineage
  4. S8-WP04 — Müşteri/vendor concentration analizi
  5. S8-WP05 — Reproducible restore & mimari/veri envanteri
  6. S8-WP06 — Key-person/transition hazırlığı
- **Exit gate / evidence özeti**: Bağımsız/simüle edilmiş bir due-diligence
  checklist'inin data room'u eksiksiz bulması. Exit GO/NO-GO: **henüz
  değerlendirilmedi** (son stage — sonraki bir "next-stage admission" yoktur).
- **Stage-detail**: [`docs/25-STAGE-08-EXIT-READY.md`](25-STAGE-08-EXIT-READY.md)

## 3. Her stage dokümanının zorunlu alanları

Her `docs/18`–`docs/25` dosyası aşağıdaki alanları **eksiksiz** taşır (owner
persona uyumu için Türkçe alan adları korunmuştur); §3a'daki panolar bu
alanların yalnız bir alt kümesinin özetidir:

```
once, simdi, fark, kullaniciYolculugu, kalanEngel, capability_delta,
şu-an-çalıştırılabilir/çalıştırılamaz iddiası,
amaç, scope/non-goals, entry gate, milestone/WP, module increments,
dependency/critical path, acceptance evidence, metrics,
security/a11y/performance/i18n, rollback trigger,
exit GO/NO-GO/CONDITIONAL, next-stage admission
```

## 4. İlerleme sayacı kuralı

Sabit payda **0/8**; sayaç yalnız bir stage'in Exit Gate'i **kanıtla** GO aldığında
artar. Kapsam değişirse önceki sayaç geriye yazılmaz — yeni adlandırılmış
plan/versiyon açılır (örn. "MVP v2 — genişletilmiş kapsam"), eski ilerleme kaydı
korunur.

## 5. Bu külliyatın kendi durumu

Bu plan korpusunun üretilmiş olması (bu §3a stage panoları dahil) **hiçbir
stage'i tamamlamaz** — bu, PLANNING ONLY bir doküman genişletmesidir, ürün
ilerlemesi değildir. Şu an:

```
0/8 tamamlandı, aktif aşama: plan-onayı (Stage 1 MVP henüz başlamadı)
```

## 6. Kanonik sahiplik

Sekiz aşamalı sıra, stage özet panoları (§3a), stage-doküman şablonu (§3) ve
ilerleme sayacı kuralı (§4) burada kanoniktir. Her stage'in kendi içeriği
(`docs/18`–`docs/25`) bu kuralları **uygular**, yeniden tanımlamaz; WP'lerin
outcome/scope/predecessor/owner/status ayrıntısı `docs/26`'da kanoniktir.
