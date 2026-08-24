# Fast Development — SP-01 speed genome (Claude rule)

Amaç: aynı test-first/tek-writer/independent-reviewer güvenlik sınırlarını
koruyarak paket başına orkestrasyon vergisini ve sınırsız hedefli test
üretimini kesmek. Kaynak analiz: `claude_speeder_report.md`,
`codex_speeder_report.md`. Sayısal politika: **yalnız**
`config/development-speed-budget.json` — bu dosya hiçbir eşiği tekrar
etmez, yalnız o dosyaya işaret eder.

## Zorunlu akış

1. Her paket başında risk şeridi belirlenir: `prototype` / `microHotfix` /
   `normal` / `highRisk` (giriş kriterleri ve tetikleyiciler
   `config/development-speed-budget.json`'da; yol örüntüsü tetikleyicileri
   deterministiktir, semantik sınıflandırma worker/kapsam-analisti yargısı
   gerektirir ve şüpheli durum yüksek-riske yükseltilir).
2. Her paket, RED→implementasyon→GREEN→independent review→checkpoint
   adımlarından önce ve her checkpoint'te `scripts/speed-gate check
   --manifest <manifest.json> --config config/development-speed-budget.json`
   çağrılır. Verdict `PASS` değilse (`BATCH_REQUIRED`, `HIGH_RISK`,
   `CHECKPOINT_BLOCKED`) o verdict'e göre davranılır — paket bölünür,
   yüksek-risk rejimine geçilir veya güvenli checkpoint alınır.
3. Ayrı test writer, ayrı implementation writer, ayrı salt-okunur reviewer
   ayrımı korunur (bkz. kök `CLAUDE.md`, `AGENTS.md` §6). Reviewer asla test
   yazmaz/full-suite tekrar etmez; reviewer full-suite bütçesi her şeritte
   `config/development-speed-budget.json`'daki `reviewerFullSuiteRunsMax`
   alanı kadardır (bu dosya o sayıyı tekrar etmez).
4. Docs (`docs/26`, `docs/27` dahil) sayısal eşik **tekrar etmez** —
   `scripts/speed-gate docs-scan --docs-root docs` ile doğrulanır.
5. Değişmeyen snapshot'ta kanıt yeniden kullanılır (`speed-gate` bunu
   `snapshotHash` üzerinden otomatik uygular); tam local QA/CI-full-QA
   bütçesi `config/development-speed-budget.json`'daki `fullLocalQaMax`/
   `ciFullQaMax` alanlarıyla sınırlıdır (bu dosya o sayıları tekrar etmez).

## Programın hızı, tek paketin hızı değildir

Bu paketin taahhüdü **risk-ayarlı PROGRAM throughput'udur** (kabul edilen
dikey dilim sayısı / birim program zamanı, kaçan-hata ve rework oranı
guardrail'leriyle birlikte) — tek bir paket için desteklenmeyen bir süre
taahhüdü **değildir**. Kanonik tanım, hedef bandı ve guardrail'ler
`config/development-speed-budget.json#programThroughputObjective`'de
sahiplenilir (`singlePackageDurationSlaClaimAllowed: false`); bu dosya o
bandı tekrar etmez.

## Program-ölçekli overlay (ayrı sayaç)

`config/development-speed-budget.json#fastDeliveryGenomeOverlay` mevcut
`docs/17` §4 ürün roadmap sayacından **bağımsız**, ayrı bir denominatördür
— tamamlanan/aktif madde sayısı ve madde listesi yalnız o JSON alanında
sahiplenilir (bkz. JSON `totalItems`/`completedItems`/`activeItem`/
`dependsOn`); bu dosya o sayıları tekrar etmez.

## Owner'a sorulacak sorular, sınırlı

Owner'a yalnız ürün/marka kapsamı, geri döndürülemez etki, dış maliyet
veya güvenlik risk iştahı sorulur. Geri döndürülebilir teknik kararlar
(risk şeridi eşikleri, checkpoint kadansı, test bütçesi gibi) Codex
Desktop MASTER'da kalır — kök `CLAUDE.md`'deki yönetişim bloğunun "Owner
load" maddesiyle tutarlı, onu daraltmaz veya genişletmez.

## Kapsam sınırı

Bu kural runtime/product/Billing/Auth/Tenancy kodunu kapsamaz ve CI'yi
onarmaz; SP-01 kapsamı yalnız genome+gate artefaktlarıdır
(`config/development-speed-budget.json#fastDeliveryGenomeOverlay`'deki
ilgili madde).
