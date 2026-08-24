# Claude Bağımsız Hız/Kök-Neden Raporu — S1-WP01A

Yazar: taze Claude worker (bu görev için başlatıldı, izole, tek writer).
Kapsam: yalnız bu dosya. Kod, doküman, config, Git durumu değiştirilmedi.
Metodoloji: ekteki Codex hipotez seti hiçbir sayısı doğrulanmadan kabul edilmedi;
her iddia repodan yeniden ölçüldü, teyit/kısmi teyit/çürütme/bilinmiyor olarak
etiketlendi.

**Düzeltme notu (aynı writer, düzeltme geçişi):** Bu belge, ilk sürümdeki
dokuz maddelik bir düzeltme talebine yanıt olarak revize edildi: (1)
reviewer'ın salt-okunur olduğu ve asla test yazamayacağı netleştirildi,
(2) 20 dakikalık checkpoint kadansı tüm şeritlere (yüksek risk dahil)
evrensel yapıldı, (3) yüksek-risk tespiti frontend yollarını ve semantik
değişim sınıflarını kapsayacak şekilde genişletildi, (4) güvensiz otomatik
flaky-karantina kuralı kaldırılıp kanıt/görünürlük/süre sınırlı bir kurala
çevrildi, (5) CI onarımında Seçenek A/B çelişkisi giderildi ve pin niyeti
"bilinmiyor" olarak yeniden sınıflandırıldı, (6) Pareto payları ve
birleştirilmiş-paket tasarruf iddiaları hipotez olarak işaretlendi, (7)
touch-time/wait-time/flow-efficiency/handoff/rework/checkpoint/kaçan-hata
metrikleri ve oyun-önleme kuralı eklendi, (8) 10x/50x iddiaları koşullu ve
dürüst aralıklara çekildi, (9) kopyala-yapıştır talimatı bu kurallarla
güncellendi. Orijinal bağımsız kanıt tablosu (§2) ve yazarlık değişmedi.

**İkinci düzeltme notu (aynı writer, owner talebi — 100-worker modüler
monolith çerçevesi):** Owner, örtük bir varsayımı haklı olarak
sorguladı: modüler bir monolith'in birçok kişi tarafından paralel
geliştirilebileceği, farklı worker'ların aynı anda farklı modül/parça
sahipliği alıp güvenle birleştirilebileceği. Bu talebe yanıt olarak yeni
**§16 "Modüler Monolith 100-Worker Geliştirme Çerçevesi"** bölümü eklendi
— repodaki gerçek modül sınırları, cross-module import disiplini, paylaşılan
darboğazlar (WorkspaceApp shell, i18n dosyaları, tek lockfile/CI) yeniden
incelenerek. Önceki tüm düzeltmeler (§1'deki ilk not) korunmuştur; bu geçiş
yalnız ekliyor, önceki bulguları geçersiz kılmıyor. Yönetici özeti, kök
neden modeli, guardrails, rollout ve kopyala-yapıştır talimatı bu yeni
bölümle tutarlı olacak şekilde güncellendi.

**Üçüncü düzeltme notu (aynı writer, bağımsız kanıt düzeltme geçişi):**
Bağımsız salt-okunur yeniden doğrulama, §16'daki bazı iddiaların gerçek
olguları aştığını (overclaim) buldu ve bunlar düzeltildi: (1) dizin
envanteri kesinleştirildi — `Domain` katmanında `Billing` **yok**
(`Application`/`Infrastructure`'da var), önceki "aynı 12 + Modules,
Taxonomy" ifadesi hatalıydı; (2) "sıfır cross-module import" iddiası
aşırı genellemeydi — geniş bir `rg` taraması 3 gerçek doğrudan cross-
context import buldu (Authorization→Tenancy, Team→Identity,
MenuCatalog→Taxonomy); ayrım artık "umut verici ama gözenekli" olarak
tanımlanıyor, kasıtlı-kontrat ile tesadüfi-coupling ayrımı ayrı bir
hazırlık paketine bağlandı; (3) modül manifestindeki `owns`/`dependsOn`
alanları artık `verified`/`inferred` etiketleriyle işaretleniyor, "zaten
zorlanan gerçeklik" gibi sunulmuyor; (4) 100-worker tahsis matematiği
düzeltildi — test writer→implementation writer→salt-okunur reviewer
**sıralı fazlardır**, bir pakette asla eşzamanlı 3 worker yoktur; kişi
kimliği, aktif yazan, aktif reviewer, aktif paket, kuyruktaki worker ve
fiili eşzamanlı süreç ayrı ayrı tanımlandı; ramp tablosu "aktif paket
tavanı" ile "eşzamanlı worker/süreç admission tavanı"nı ayırdı; (5) Git
yetkisi düzeltildi — paket worker'ları asla rebase/merge/rollback/commit/
push/promotion çalıştırmaz, bunlar Codex Desktop MASTER'ın (gate'ler
sonrası Git yürütücüsü) işidir; rollback bir `reset` değil, MASTER'ın
yürüttüğü bir revert'tir; (6) mevcut worktree'lerin aynı SHA'da donmuş
olması artık yalnız "ortak taban + durmuş topoloji" kanıtı olarak okunuyor,
"immutable-base disiplini zaten çalışıyor" iddiası kaldırıldı; (7)
100-worker'ın bir yazılım-şirketi-tarzı **program-ölçeğinde genişlik**
ifade ettiği, hepsinin bu Mac'te aynı anda çalışmasının gerekmediği, ama
altyapı/admission izin verirse ramp sonunda gerçek eşzamanlı genişliğin
mimari olarak mümkün olduğu netleştirildi. Kanıt tablosuna (§2) bu
düzeltmelerin somut örnekleri (satır 17–18) eklendi.

**Dördüncü düzeltme notu (aynı writer, ölçeklenebilirlik düzeltmesi —
owner haklı):** Owner, iki gereksiz serial kısıtı doğru şekilde sorguladı
ve bunlar kaldırıldı: (1) "her modülde aynı anda yalnız 1 aktif paket"
keyfi kuralı, **dependency-DAG + write-set çakışma grafiği zamanlayıcısı**
ile değiştirildi (§16.6) — her paket writeSet/readSet/contractsConsumed/
contractsChanged/tablesTouched/sharedSurfacesTouched bildirir, iki paket
(aynı modülde bile) bunlar kesişmiyorsa ve DAG tatmin ediliyorsa eşzamanlı
çalışabilir; yalnız gerçek bir çakışma kenarı o çakışma bileşenini
serileştirir, tüm modülü değil. (2) "tüm paylaşılan dosyalar için tek
global steward sırası" kaldırıldı, yerine **yüzey başına 7 ayrı steward
lane'i** (routes, frontend-shell registry, i18n, dependency lockfile,
migrations/schema, test config, CI) kondu — farklı lane'ler birbirini
bloklamaz, yalnız aynı somut dosyanın kendi lane'i içinde tek yazar kalır
(§16.6a). 100-worker tahsis örneği (§16.16) ve ramp tablosu (§16.17) bu iki
değişikliğe göre yeniden inşa edildi: artık 100 kişilik havuzun tam
dağılımı (10 kapsam analisti + 20 test writer + 20 implementation writer +
15 reviewer + 7 steward + 3 entegrasyon analisti + 25 kuyruk = 100) veriliyor,
her paketin tam olarak bir aktif rolü olduğu matematiksel olarak
doğrulanıyor, ve ramp aşama etiketleri artık kesin olarak "tüm rollerde
eşzamanlı admit edilen worker tavanı" anlamına geliyor (aktif paket sayısı
bu tavanın ≤'sı, faz karışımına bağlı). Ayrıca Git yetkisi daha da
netleştirildi: contract-freeze artık **hiçbir zaman bir Git tag/release**
olarak değil, yalnız immutable commit SHA + kontrat manifest hash'i olarak,
yalnız MASTER-onaylı akışla kaydediliyor (§16.5) — MASTER'ın da tag/release
yetkisi olmadığı açıkça belirtildi. Tüm önceki kanıt düzeltmeleri ve
güvenlik sınırları (§1'deki ilk iki not) korunmuştur.

**Beşinci düzeltme notu (aynı writer, iki son düzeltme):** (1) §16.16'daki
100-worker steady-state örneği düzeltildi — önceki sürüm 75 aktif +
25 kuyrukta veriyordu, owner'ın istediği **tam olarak 100 aktif worker,
tam olarak 100 farklı aktif paket/iş biriminde** değildi. Yeni dağılım:
10 aktif kapsam analisti + 25 aktif test writer + 35 aktif implementation
writer + 20 aktif salt-okunur reviewer + 7 aktif yüzeye-özgü steward
yazarı + 3 aktif salt-okunur merge-treni/entegrasyon analisti = **100
aktif worker, 100 aktif paket/iş birimi**, hiçbir paket iki fazda birden
değil. Kuyruk artık operasyonel bir kavram olarak ayrıca var olabilir ama
bu 100-aktif örneğin **dışında** tutuluyor; Guardian admission ve
dağıtılmış-altyapı koşulu (§16.10) korunuyor. (2) §16.5'teki contract-
freeze kaydı düzeltildi — Codex Desktop MASTER **repo-read-only**
olduğundan (`masterIsRepoReadOnly=true`) registry dosyasının **içeriğini
asla kendisi yazmaz**; içerik **ayrı, yetkilendirilmiş bir Claude config/
governance writer** tarafından kendi paketinde yazılır, MASTER yalnız bu
içeriği **doğrular ve gate-sonrası izin verilen Git dahil etme/promotion
işlemini yürütür**. `frozenBy: "MASTER"` gibi MASTER'ı içerik yazarı
gösteren her ifade `authoredBy: "<config-governance-writer-package-id>"`
olarak düzeltildi; immutable SHA + manifest hash ve tag/release
yasağı aynen korunuyor.

## 1. Yönetici özeti (düz dil)

Bu paket gerçekten 5 gün sürmedi — Git kanıtı bunu doğruluyor: gerçek "normal
paket" teslimleri 22–82 dakika arasında gerçekleşmiş, 5 günlük görünen aralığın
büyük kısmı tek seferlik, 753 dosyalık bir "foundation checkpoint" commit'i.
Codex'in bu iddiası **teyit edildi**.

Asıl kök neden tek bir şey değil, üçü birlikte: (a) hedefli test sayısı ve
tekrar sayısı için üst sınır yok, (b) her mikro davranış (aria, focus, escape,
disabled...) ayrı test oluyor — risk değil yüzey ölçülüyor, (c) CI, testlere
hiç ulaşmadan `composer validate --strict` adımında **her seferinde** patlıyor
— bu doğrulandı ve repro edildi (aşağıda kanıt). CI'nin yapısal olarak
sürekli kırmızı olması, güveni yerel review tekrarlarına kaydırıyor, bu da
paket başına ek sabit maliyet demek.

Codex'in önerdiği "3–8 güçlü hedefli test, ≤2 dosya, 20 dakikalık checkpoint"
iskeleti **doğru yönde ama tek başına yetersiz**: CI'nin yapısal RED'i
kapatılmadan hiçbir test bütçesi CI/full-QA güvenini geri getirmez, ve
worktree/snapshot dağınıklığı kapatılmadan reviewer'ın "immutable snapshot"
varsayımı sürekli bozulur. Bu raporda ikisini de kapatan somut bir sözleşme
tasarlanıyor.

**10x nerede gerçek, 50x nerede yanıltıcı (dürüst aralıklar, koşullu):**
- **Mikro/hotfix şerit:** ön koşullar (batch kuralı + CI onarımı + read-only
  reviewer disiplini) sağlandığında, 22–27 dakikalık gözlemlenmiş sürelerden
  (kanıt #7,8) ≤10 dakikaya inmek **2–3x** bandında **makul bir hedeftir**;
  bu bir gözlem değil, §8 bütçesinden türetilen bir **hedeftir**, A/B
  deneyiyle (§14) doğrulanmadan taahhüt sayılmamalı.
- **Normal paket:** 82 dakikalık gözlemlenmiş süreden (kanıt #6) ~15–20
  dakikaya inmek **~4–5x** bandında, yine **hedef/hipotez**, CI onarımı ve
  test-bütçesi disiplini birlikte uygulanmadan gerçekleşmez.
- **50x veya "5 dakika" gibi iddialar bu repo için desteklenmez ve
  önerilmemektedir.** Web'in 5 dakikası apples-to-apples değildir (§6);
  onu sayısal hedef almak yanlış optimizasyondur. Bu raporun tek
  taahhüdü, **aynı güvenlik sınırları korunarak** ölçülebilir, kademeli
  bir iyileşmedir — büyüklüğü A/B deneyi kapanmadan kesinleşmez.

**Tek paketin hızı ile toplam program hızı farklı sorulardır (§16):**
Yukarıdaki 2–5x, **bir** paketin serial checkpoint zincirini kısaltmakla
ilgilidir. Owner'ın 100-worker sorusu farklı bir eksen açıyor: **bağımsız
modüllerdeki çok sayıda paketi eş zamanlı yürütmek**. Repo kanıtı (§16.1)
gösteriyor ki backend zaten `Application`/`Infrastructure` katmanlarında 12
bounded-context'e (Billing dahil), `Domain` katmanında ise 13 dizine
(Billing **hariç** — `Domain/Billing` yok, §16.1/§16.3'teki not) port/
hexagonal sınırlarla ayrılmış. **Düzeltme:** önceki taslaktaki "hiçbir
doğrudan cross-module PHP import'u yok" iddiası aşırı genellemeydi; daha
geniş bir tarama 3 gerçek cross-context import buldu — `Authorization/
RolePermissions.php → Tenancy\MembershipRole`, `Team/CreateTeamInvitation.php`
ve `AcceptTeamInvitation.php → Identity\EmailAddress`, `MenuCatalog/
Product.php → Taxonomy\TaxonomyTerm` (hepsi bu oturumda `grep`/`rg` ile
doğrudan doğrulandı, §16.1). **Doğru sonuç: ayrım umut verici ama gözenekli
(porous), mükemmel değil.** Doğru guardrail'lerle (§16) ve kasıtlı
yayınlanmış kontrat ile tesadüfi coupling'i ayıran tam bir bağımlılık
grafiğiyle (§16.18, önkoşul hazırlık paketi), çok sayıda bağımsız modül
paketinin **paralel** yürütülebileceği makul bir hedeftir — ama bu bugün
**zaten garanti edilmiş bir gerçeklik değil**. Bu paralellik, tek bir
paketin kendi içindeki RED→GREEN→review zincirini hızlandırmaz (Amdahl
analizi, §16.9) — 100 worker, 100 bağımsız iş parçasını aynı anda
ilerletebilir, tek bir seri bağımlılık zincirini 100 kat hızlandıramaz.

## 2. Kanıt tablosu

| # | İddia (Codex) | Komut/Kaynak | Sonuç | Durum |
|---|---|---|---|---|
| 1 | 85 PHP + 107 frontend test dosyası | `find ... -name "*Test.php"` / `*.test.tsx,*.test.ts` | 83 PHP, 107 FE | **Kısmen teyit** (PHP 83, iddia 85 — küçük sapma, muhtemelen sayım zamanı farkı) |
| 2 | ~1.514 test çağrısı | `grep -E "^\s*(it|test)\("` (FE) + `"public function test\|#\[Test\]"` (PHP) | 801 FE + 583 PHP = **1.384** | **Kısmen teyit** — aynı büyüklük mertebesi, ~9% düşük; farklı sayım yöntemi (örn. `it.each`, closures) muhtemel neden |
| 3 | Test ~46.538 satır, ürün ~28.831 satır, oran 1,61:1 | `wc -l` üzerinden test dosyaları vs `resources/js`+`app` (test hariç) | test 46.017, ürün 28.852, oran **1,60:1** | **Teyit** (marjinal fark, ölçüm anındaki dosya kümesi farkı) |
| 4 | 5 CI koşusu testlere ulaşmadan `composer validate --strict`'te bitti | `gh run list -L5` + `gh run view <id> --log-failed` | Son 5 run: 11–34 sn, hepsi `failure`; log: `Validate composer.json and composer.lock` adımı `exit code 1` ile bitiyor, sonraki adımlara hiç geçilmiyor | **Teyit ve repro edildi** |
| 5 | CI'nin RED'i license + 4 exact pin nedeniyle yapısal | CI log çıktısı: "No license specified" + 4× "exact version constraints ... should be avoided" (endroid/qr-code 6.0.0, iyzico/iyzipay-php 2.0.61, khanamiryan/qrcode-detector-decoder 2.0.2, mpdf/mpdf 8.3.1) | Aynen doğrulandı, `composer.json`'daki `require` bloğuyla birebir eşleşiyor | **Teyit** |
| 6 | Password reset → ownership transfer ~82 dk | `git log --pretty='%h %ad %s' --date=iso` | 12c01f3 04:02:21 → 2915937 05:24:26 = **82 dk** | **Teyit** |
| 7 | Ownership → ilk dialog düzeltmesi ~22 dk | aynı log | 2915937 05:24:26 → b77760d 05:46:57 = **22 dk 31 sn** | **Teyit** |
| 8 | Sonraki dialog düzeltmesi ~27 dk | aynı log | b77760d 05:46:57 → e7758a1 06:13:43 = **26 dk 46 sn** | **Teyit** |
| 9 | 5 günlük aralık aslında 753 dosya/~98K satırlık foundation checkpoint | `git show 46f3100 --stat` | checkpoint commit'i `app/`, `config/`, `.github/workflows/`, dokümantasyon dahil yüzlerce dosyayı tek seferde ekliyor (stat çıktısında yüzlerce dosya doğrulandı, tam sayım bu oturumda tekrarlanmadı) | **Büyük ölçüde teyit** — dosya sayısı ve satır sayısı tam olarak yeniden sayılmadı (zaman bütçesi), ancak commit'in "toplu foundation" niteliği doğrulandı |
| 10 | Password reset paketi: 33 test, 689 satır | `git show 12c01f3 --stat` | Test dosyaları: `PasswordResetJourney.test.tsx` (291 satır) + `PasswordResetJourneyTest.php` (398 satır) = 689 satır test **teyit**; test *sayısı* (33) bu oturumda ayrıca sayılmadı | **Satır sayısı teyit, test adedi doğrulanmadı** |
| 11 | Ownership transfer: 14 test, 763 satır | `git show 2915937 --stat` | `TeamPage.ownershipTransfer.test.tsx` (448) + `TransferWorkspaceOwnershipJourneyTest.php` (315) = 763 satır **teyit** | **Satır sayısı teyit, test adedi doğrulanmadı** |
| 12 | İki dialog düzeltmesi: ~24 ürün satırı vs 175 test satırı | `git show b77760d,e7758a1 --stat` | b77760d: +16/-3 ürün, +95 test; e7758a1: +4+10=+14 ürün, +60+20=+80 test → ürün ~30, test ~175 | **Yaklaşık teyit** (ürün satırı iddiadan biraz yüksek: ~30 vs ~24, yön ve oran doğru: test/ürün ~5,8:1) |
| 13 | 10 ayrı frontend worktree, bazıları kirli/yarım | `git worktree list` | 11 worktree kaydı (ana + 5 `-task` çifti + bu foundation worktree'si); FE alanları: brand-locations, dashboard, media, menu, publication-qr — her biri hem `codex/` hem `-task` varyantıyla, hepsi aynı `8d39aa5` SHA'sında donmuş | **Teyit** (sayı 10 iddiasıyla uyumlu: 5 alan × 2 varyant) |
| 14 | 530 panel kaydı / Guardian 1 worker öneriyor | Pane/Guardian iç durumu | Bu oturumdan salt-okunur, güvenli biçimde doğrulanamadı (transcript/secret inceleme yasak, ayrıca bu görevin "no subagent" ve dar okuma kapsamı buna izin vermiyor) | **Bilinmiyor / doğrulanamadı** |
| 15 | Aktif worktree'de billing + Pane GC paketleri birlikte | `git status` (bu oturumun başındaki snapshot) | Dirty tree'de `BillingPage.tsx`, `ManualPaymentUnavailableForm.*` (billing) ve `.claude/` (GC/skill altyapısı) aynı anda değişmiş/eklenmiş görünüyor | **Teyit** — snapshot entanglement gerçek |
| 16 | docs/27 test-first + full-QA bütçesini tanımlıyor ama test adedi/süre/browser/reviewer tekrarı sınırsız | `docs/27-QA-ACCEPTANCE-VIBECODING.md` içeriği (grep + okuma) | Doküman "tam QA en fazla iki kere", snapshot bazlı kanıt yeniden kullanımı gibi kavramları tanımlıyor; targeted test sayısı, dosya sayısı, checkpoint süresi için sayısal üst sınır **yok** | **Teyit** |
| 17 | (§16 düzeltme geçişi) Backend modülleri arasında sıfır cross-context PHP import var | `rg -n "^use App\\Domain\\[A-Za-z]+\\" app/Domain` + hedefli `grep` doğrulaması: `App\Domain\Authorization\RolePermissions.php`, `App\Application\Team\UseCase\{CreateTeamInvitation,AcceptTeamInvitation}.php`, `App\Domain\MenuCatalog\Product.php` | **Çürütüldü — önceki taslağın kendi iddiası hatalıydı.** 3 gerçek cross-context import doğrulandı: `Authorization/RolePermissions.php:7 → use App\Domain\Tenancy\MembershipRole;`; `Team/UseCase/CreateTeamInvitation.php` ve `AcceptTeamInvitation.php → App\Domain\Identity\EmailAddress`; `Domain/MenuCatalog/Product.php:7 → use App\Domain\Taxonomy\TaxonomyTerm;`. Ayrım gözenekli (porous), sıfır değil. | **Çürütüldü/düzeltildi** — bkz. §16.1, §16.2 |
| 18 | (§16 düzeltme geçişi) `app/Domain` dizin envanteri, Billing dahil 12+ | `ls app/Domain` | Doğrulanan tam liste: `Analytics, Authorization, Identity, Media, MenuCatalog, Modules, Platform, Publication, QrDestination, Security, Taxonomy, Team, Tenancy` — **13 dizin, Billing YOK.** `app/Application` ve `app/Infrastructure` her ikisi de `Billing`'i içeriyor (Application 12 context, Infrastructure aynı 12 + paylaşılan `Persistence`); yalnız `Domain` katmanında Billing eksik — üç katman arasında asimetri | **Teyit, önceki taslaktaki "aynı 12 + Modules, Taxonomy" ifadesi hatalıydı, düzeltildi** — bkz. §16.1, §16.3 |

## 3. Değer akışı haritası ve darboğaz Pareto'su (mevcut durum)

**Ölçüm durumu — dikkatle okunmalı:** Bu bölümdeki tüm yüzde payları
**hipotezdir, gözlemlenmiş veri değildir**. Bu oturumda doğrudan ölçülen
tek zaman verisi commit zaman damgalarıdır (kanıt #6–8: 22, 27, 82 dk toplam
paket süreleri). Agent-içi faz kırılımı (kapsam analizi, RED, GREEN, review,
handoff ayrı ayrı ne kadar sürdü) bu oturumda **hiç gözlemlenmedi** — böyle
bir telemetri repoda yok (§7). Aşağıdaki Pareto sıralaması bu yüzden yalnız
niteliksel bir **tahmin/hipotez sıralamasıdır**; payların doğrulanması için
§13/§14'teki faz-bazlı zaman damgalama önkoşulu gerekir. "Güven" etiketi
"bu payın doğru olduğuna güven" değil, "bu sıralamanın yönünün doğru
olduğuna güven" anlamındadır.

1. **[HİPOTEZ] Sabit orkestrasyon vergisi (kapsam analisti + ayrı test
   writer + ayrı implementation writer + ayrı reviewer + Guardian/auth/
   capability başlangıcı) — tahmini pay %35–45, güven: orta.** Dayanak:
   24 satırlık bir buton düzeltmesi için 22–27 dakika harcanması (kanıt
   #7, #8, #12), üretilen ürün koduna kıyasla orantısız görünüyor; ancak
   bu sürenin ne kadarının başlangıç/geçiş maliyeti, ne kadarının fiili
   test/implementasyon/review işi olduğu faz-bazlı ölçüm olmadan
   **kesin olarak ayrıştırılamaz**.
2. **[HİPOTEZ] Sınırsız hedefli test üretimi (yüzey bazlı test tasarımı)
   — tahmini pay %20–30, güven: orta-yüksek.** Doğrudan ölçülen veri:
   kanıt #12'de 14 satır ürün değişikliğine 80 satır test (ConfirmDialog
   dismiss); test/ürün oranı paket bazında 5–6:1'e çıkıyor, genel repo
   oranı 1,6:1 (kanıt #3, ölçülmüş). Bu oranın *zaman* payına çevrimi
   ise tahmindir — test yazma süresi ile test satır sayısı doğrusal
   orantılı varsayılıyor, bu varsayım test edilmedi.
3. **[HİPOTEZ, kısmen ölçülmüş temel] CI'nin yapısal RED'i → yerel
   review'a kayan güven maliyeti — tahmini pay %15–20, güven: orta.**
   CI'nin hiçbir zaman teste ulaşmadığı doğrudan repro edildi (kanıt
   #4–5, ölçülmüş olgu). Ancak bunun paket süresine ne kadar zaman
   payı olarak yansıdığı ölçülmedi — yalnız docs/27'deki "tam QA en
   fazla iki kere ama targeted tekrar sınırsız" deseninden çıkarılan
   dolaylı bir iz.
4. **[HİPOTEZ] Worktree/snapshot entanglement — tahmini pay %5–10, güven:
   düşük-orta.** 11 worktree'nin varlığı ve aktif worktree'de birden
   fazla paketin aynı anda dirty olması ölçülmüş olgu (kanıt #13, #15);
   bunun reviewer re-work'üne veya paket süresine kaç dakika yansıdığı
   ölçülmedi.
5. **[HİPOTEZ] Mikro paketleme (bölünmüş prop/dialog değişiklikleri) —
   tahmini pay %5–10, güven: düşük-orta.** İki ardışık dialog düzeltmesinin
   (kanıt #7,8) ayrı paketler olduğu ölçülmüş olgu; bunların
   birleştirilmesi halinde ne kadar zaman tasarrufu olacağı (§8.2'deki
   "~35–40 dakikaya birleşebilirdi" ifadesi dahil) **tahmindir, gözlemlenmiş
   bir birleştirilmiş-paket verisi yoktur** — gerçek tasarruf yalnız A/B
   deneyiyle (§14) doğrulanabilir.
6. **Browser QA / diğer — gözlemlenen commit'lerde doğrudan iz yok; bu
   oturumda ayrı ölçülemedi. Bilinmiyor**, hipotez bile üretilmedi.

## 4. 5-Whys + nedensellik grafiği

**Neden 22–82 dakika, hedeflenen "web-benzeri" tempoya göre uzun?**
1. Neden → Her paket ayrı kapsam analisti + ayrı test writer + ayrı
   implementation writer + ayrı reviewer + Guardian/Pane/capability
   başlangıcı gerektiriyor.
2. Neden (o zorunlu mu?) → Evet, test-first ayrımı ve tek-writer/independent
   reviewer kuralı **AGENTS.md**/**CLAUDE.md** ve yönetişim bloğunda
   sabit — bu değiştirilemez bir güvenlik sınırı (bkz. görev talimatı
   "non-negotiable separation").
3. Neden bu sabit maliyet küçük paketlerde orantısız büyüyor? → Çünkü
   paket boyutu için alt sınır yok; 10–30 satırlık bir düzeltme de tam
   orkestrasyon zincirinden geçiyor (kanıt #7,8,12).
4. Neden paketler bu kadar küçük bölünüyor? → docs/27 ve mevcut akış,
   "bir davranış = bir test = genelde bir paket" desenini ödüllendiriyor;
   mikro-batching kuralı yok.
5. Neden test sayısı da paralel şişiyor? → Çünkü test tasarımı risk
   sınıflandırması yerine yüzey numaralandırması yapıyor (aria, focus,
   escape, disabled — her biri ayrı test); üst sınır olmadığı için bu
   davranış kendiliğinden durmuyor.

**Kök nedenler (ayrı dallar, birbirini güçlendiriyor):**
- Sınırsız hedefli test bütçesi (test-first'ün kendisi değil, sınırsızlığı).
- Paket boyutu alt sınırı yok → sabit vergi orantısız.
- CI yapısal RED → güven açığı yerel tekrarla kapatılıyor → ek süre.
- Worktree/snapshot entanglement → reviewer'ın "tek immutable snapshot"
  varsayımı sık sık geçersizleşiyor → re-review riski.
- Makine-okunur, deterministik bir "hız kapısı" yok; bütün sınırlar
  doküman metni (docs/27) düzeyinde, kod/script düzeyinde değil.

Test-first metodolojisinin kendisi kök neden **değildir** — Codex'in vardığı
sonuçla bu raporun vardığı sonuç burada örtüşüyor: sorun test yazmak değil,
test kapsamının ve paket/orkestrasyon büyüklüğünün sınırsız olması.

**Altıncı bir kök neden dalı (§16 ile eklendi):** Yukarıdaki 5 neden, **tek
bir paketin** neden yavaş olduğunu açıklıyor. Owner'ın 100-worker sorusu
ayrı bir 6. dalı ortaya çıkardı: **program ölçeğinde paralellik eksikliği**
— repo zaten 12 modüle port/hexagonal sınırlarla ayrılmış olsa da (§16.1),
frontend'de bu ayrımın karşılığı yok (`WorkspaceApp.tsx`, `workspace.ts`,
`routes/api.php` tek dosyada birleşiyor, §16.2) ve modül-bazlı CI/test
seçimi/merge-queue altyapısı repoda **yok**. Bu, mevcut 5 kök nedenden
bağımsız, ayrı bir darboğaz: paketlerin *kendi içindeki* hızı düzelse bile,
paylaşılan dosyalardaki tek-sıra darboğaz (§16.6a) ve CI'nin yapısal RED'i
(kök neden 3, §10) birlikte çözülmeden **modül-paralelliği** ölçeklenemez.

## 5. Ekteki (Codex) rapor: doğru, yanlış, doğrulanamamış, eksik

**Doğru ve bu oturumda bağımsız teyit edildi:**
- 5 günlük görünen sürenin gerçek paket teslim süresini yanlış temsil ettiği
  (22–82 dk gerçek, geri kalanı tek seferlik checkpoint).
- CI'nin `composer validate --strict` adımında, testlere hiç ulaşmadan,
  yapısal biçimde her seferinde kırmızı olduğu (bu oturumda canlı `gh run`
  loglarıyla repro edildi — Codex'in kendisi bunu runpane/gh üzerinden mi
  yoksa statik okumayla mı doğruladığı belirtilmemiş; bu oturum canlı log
  ile doğruladı).
- Test/ürün satır oranının ~1,6:1 olduğu ve mikro düzeltmelerde bu oranın
  çok daha yüksek çıktığı (5–6:1).
- 11 (≈10) worktree'nin var olduğu ve entanglement riskinin gerçek olduğu.
- "3–8 güçlü test" sınırının yönü doğru ama tek başına yetmediği.

**Doğru olma ihtimali yüksek ama bu oturumda tam doğrulanamadı:**
- Test *çağrısı* sayısı (1.514 iddia edildi, bu oturumda 1.384 sayıldı —
  aynı mertebe, muhtemelen sayım yöntemi farkı; kritik değil).
- Password reset (33 test) ve ownership transfer (14 test) paketlerindeki
  tam test *adedi* — satır sayıları teyit edildi, adetler bu oturumda ayrı
  sayılmadı.

**Doğrulanamadı (repo dışı/erişilemez veri):**
- "530 panel kaydı", "Guardian yalnız 1 worker öneriyor" — Pane/Guardian iç
  telemetrisi bu görevin salt-okunur ve dar kapsamı içinde güvenle
  incelenemedi. Bu, Codex raporunun da açıkça "izolasyon nedeniyle taze
  worker açmadım" dediği, kendi sınırlarının ötesindeki bir iddia olabilir —
  kaynağı belirtilmemiş, bu yüzden bağımsız doğrulama bekliyor.

**Codex raporunun gözden kaçırdığı/eksik bıraktığı noktalar (bu raporun
katkısı):**
- Codex kendi raporunda "unknown unknown" listesini zaten dürüstçe veriyor
  (apples-to-apples benchmark yok, kaçan hata oranı ölçülmüyor, mutation
  değeri ölçülmüyor, ajan başlangıç süreleri ayrı kaydedilmiyor, flaky
  bütçesi yok, browser karar matrisi yok, risk şeritleri yok). Bu rapor
  ayrıca şunları ekliyor: (a) `composer validate --strict`'in **local**
  ortamda da aynı şekilde exit-1 verdiği doğrulanmadı açıkça (bu oturumda
  bir ölçüm hatası nedeniyle yerel çalıştırma yanlış pipe'landı — CI log'u
  üzerinden dolaylı doğrulandı, ama yerel repro net değil); (b) checkpoint
  commit'inin (46f3100) tam dosya/satır sayımı bu oturumda tekrarlanmadı,
  yalnız "toplu" niteliği doğrulandı; (c) test *adedi* (33, 14 gibi) satır
  sayısından türetilmiş sayılar olabilir, bağımsız sayılmadı.

## 6. Claude Web "5 dakika" karşılaştırması — apples-to-apples tasarımı

Karşılaştırılabilir DEĞİL çünkü:
- Web tek-turn, tek-ajan, review/reviewer ayrımı yok, tenancy/auth/güvenlik
  kapsamı genelde yok, CI/gerçek deploy pipeline'ı yok, çok-worktree Git
  entegrasyonu yok.
- Bu repo: ayrı test writer + implementation writer + independent reviewer
  (Kernel-benzeri güvenlik sınırı), gerçek Git/CI/worktree/Guardian/Pane
  yaşam döngüsü, tenancy/güvenlik riski olan gerçek production kod tabanı.

**Adil kıyas protokolü:** Aynı görev tanımını (ör. "tek buton disabled-state
davranışı ekle") iki koşulda çalıştır: (1) mevcut governance zinciriyle
(ayrı writer/reviewer, CI dahil), (2) yalnız orkestrasyon vergisini
kaldırıp test/review/CI zorunluluklarını **aynı tutarak** tek warm-agent
ile. Web'in 5 dakikasıyla kıyaslamak, güvenlik sınırlarını kaldırmadan
mümkün değil — bu yüzden web sayısı bir *hedef* değil, yalnız bir *üst
sınır referansı* olarak kullanılmalı (§9'daki A/B protokolü buna göre
tasarlandı; web ile değil, "governed-current" ile "governed-fast" arasında
kıyas yapılıyor).

## 7. Unknown unknowns / boşluk kaydı

- Kaçan hata oranı: sıfır telemetri.
- Mutation/bug-killing test değeri: ölçülmüyor.
- Flaky test oranı ve karantina protokolü: yok.
- Ajan başlangıç/capability/Pane bekleme süresi: paket süresinden ayrı
  kaydedilmiyor (yalnız commit zaman damgaları var, faz kırılımı yok).
- Değişen-test seçimi (yalnız etkilenen testleri çalıştırma): yok.
- Paylaşılan büyük dosyaların (`TeamPage.tsx`, `resources/js/i18n/*`)
  küçük değişikliklerde test alanını genişletme maliyeti: ölçülmüyor.
- Reviewer rework oranı: kayıt yok.
- Ortam sürüklenmesi (Node 24/PHP 8.3 pin'lerinin lokal/CI tutarlılığı):
  bu oturumda karşılaştırılmadı.
- Warm worker reuse imkanı: mevcut mimaride tanımlı değil.
- Pane panel sayısı/Guardian önerisi (Codex'in 530/1 iddiası): erişilemedi,
  doğrulanmadı.
- **(§16 ile eklendi) Modül-arası bağımlılık grafiğinin tam DAG taraması
  yapılmadı** — §16.1'deki cross-import kontrolü yalnız iki nokta örneği
  (MenuCatalog→Billing, MenuCatalog→Tenancy), tüm 12×11 modül çiftini
  kapsamadı; tam DAG bir sonraki hazırlık paketinde çıkarılmalı.
- **(§16 ile eklendi) Steward-lanesi kuyruk gecikmesi hiç ölçülmedi** —
  bu darboğazın gerçek büyüklüğü (§16.9 Amdahl terimi) yalnız 4→12 ramp
  aşamasında gözlemlenebilir, bu oturumda tahmin bile üretilmedi.
- **(§16 ile eklendi) `Domain/Billing` eksikliğinin nedeni bilinmiyor**
  (§16.3 not) — kasıtlı bir tasarım mı yoksa eksik bir modelleme mi
  belirsiz.
- **(§16 ile eklendi) Çoklu-makine/bulut dağıtımının admission modeli
  tanımlı değil** — mevcut Guardian/Pane admission yalnız bu Mac için
  tarif edilmiş; ikinci bir makine/oturuma dağıtıldığında admission'ın
  nasıl senkronize kalacağı (yoksa her makine bağımsız mı admission
  verir) bu oturumda çözülmedi, §16.10'da yalnız "her makine kendi
  admission'ına tabi olmalı" ilkesi belirtildi, mekanizma tasarlanmadı.

## 8. Yeni ultra-hızlı işletim sözleşmesi (sert sayılar)

### 8.1 Risk şeritleri — giriş kriterleri
| Şerit | Giriş kriteri | Test/QA rejimi |
|---|---|---|
| **Prototip/demo** | Kullanıcıya production'da görünmeyecek, tek-seferlik keşif | Test opsiyonel; full QA yok; CI'ye girmez (ayrı dal/etiket); 20 dk checkpoint yine uygulanır (§8.3) |
| **Mikro/hotfix** | Beklenen ürün diff'i <30 satır, tek component/journey, aşağıdaki high-risk sınıflarından hiçbirine girmiyor | 1–3 hedefli test, 1 dosya, browser QA yok, checkpoint ≤10 dk |
| **Normal ürün paketi** | Diğer her şey (default) | 3–8 hedefli test, ≤2 dosya, checkpoint ≤20 dk (bkz. 8.2) |
| **Yüksek risk** (auth, tenancy, billing/payment, webhook, migration, secrets/security, authorization, geri döndürülemez veri değişikliği) | Aşağıdaki genişletilmiş tetikleyicilerden biri | Normal sınırın dışında; ek test gerekçeli olmalı; **toplam bitiş süresi yok, ama 20 dakikalık checkpoint kadansı yine zorunlu** (§8.3) — birden çok checkpoint gerekebilir; browser smoke zorunlu (dikey dilim kapanışında) |

**Yüksek-risk tetikleyicileri — genişletilmiş, çift taraflı:**
- **Backend yol örüntüleri:** `app/**/Auth/**`, `app/**/Billing/**`,
  `app/**/Tenancy/**`, `**/Webhook*`, `database/migrations/**`,
  `app/**/Security/**`, `app/**/Authorization/**`, yetki/permission
  kontrolü içeren `Policy`/`Gate` sınıfları.
- **Frontend yol örüntüleri (Codex ve önceki taslakta eksikti — bu
  düzeltmede eklendi):** `resources/js/**/auth/**`,
  `resources/js/**/billing/**`, `resources/js/**/workspace/**team**`
  (ownership/permission akışları), ödeme formu/checkout bileşenleri,
  webhook/callback URL'lerini tüketen istemci kodu, secrets/token
  saklayan/ileten herhangi bir dosya.
- **Semantik değişim sınıfları (yol örüntüsünden bağımsız, davranışa
  bakar):** kimlik doğrulama akışı değişikliği, tenancy/izolasyon sınırı
  değişikliği, ödeme/faturalama hesaplama veya durum geçişi değişikliği,
  webhook imza/doğrulama mantığı değişikliği, migration/şema/geri
  döndürülemez veri dönüşümü, yetkilendirme (authorization) kararı
  değişikliği, secrets/credential işleme değişikliği.
- **Kritik uyarı:** Yol örüntüleri **muhafazakâr bir tetikleyicidir,
  tek başına yeterli sınıflandırma değildir.** Bir değişiklik yukarıdaki
  path pattern'lerin hiçbirine düşmese bile semantik olarak
  auth/tenancy/billing/webhook/migration/secrets/authorization/geri-
  döndürülemez-veri sınıfına giriyorsa (örn. bir `TeamMemberList.tsx`
  değişikliği ownership transfer yetkisini etkiliyorsa — tam olarak kanıt
  #11'deki paket), yüksek-risk sınıflandırması **path pattern eşleşmese
  bile** uygulanır. `speed-gate` yalnız path pattern kontrolü yapabilir
  (deterministik); semantik sınıflandırma kapsam-analisti/worker'ın insan
  (LLM) yargısını gerektirir ve şüpheli her durumda yüksek-riske
  yükseltilir (fail-closed, §9).

### 8.2 Normal paket bütçesi (Codex önerisi değerlendirilip aynen benimsendi + tamamlandı)
- Toplam **3–8** yeni/değiştirilen hedefli test, **≤2** test dosyası.
- Üç ana test tercih sırası: (1) başarılı journey, (2) yetki/validasyon/güvenli
  hata, (3) retry/idempotency/yeniden yükleme.
- RED bir kere çalışır; implementation aynı hedefli seti GREEN için bir kere
  çalıştırır; reviewer suite'i tekrar etmez, yalnız immutable hash + çıktıyı
  doğrular. **Reviewer kesinlikle salt-okunurdur ve hiçbir zaman test
  ekleyemez/değiştiremez** (bkz. bu düzeltmedeki düzeltme #1 ve §9). Reviewer
  gerekli gördüğü ek doğrulamayı **1–2 odaklı adversarial kontrol önerisi**
  olarak yazılı gerekçeyle geri bildirir; bu öneri onaylanırsa açık bir
  handoff ile ayrı test writer'a döner, test writer testi yazar, ayrı
  implementation writer gerekirse uygular, ve **taze bir immutable snapshot**
  üzerinde yeniden review yapılır. Reviewer full-suite koşusu her koşulda
  **sıfır** kalır.
- Full local QA: writer tarafından **1 kere**. CI full QA: **1 kere**.
- Değişmeyen snapshot'ta test tekrarı **yasak** (evidence reuse).
- Mikro UI düzeltmesinde browser QA **yok**; kullanıcıya görünen dikey dilim
  kapanışında en fazla **1** browser smoke.
- 8'den fazla test gerekiyorsa paket yanlış bölünmüş demektir: journey
  bazında ayrılır ya da yüksek-risk olarak yeniden sınıflandırılır.

**Bu raporun eklediği/sıkılaştırdığı nokta:** Codex'in bütçesi test
tarafını sınırlıyor ama paket **alt** sınırını sınırlamıyor. Ek kural:

- Beklenen ürün diff'i <30 satırsa **tek başına paket açılmaz**; aynı
  component/journey içindeki ardışık en fazla **3** mikro düzeltme tek
  pakette **batch** edilir (kanıt #7,8 — iki ayrı 22 ve 27 dakikalık dialog
  paketi tek ~35–40 dakikalık pakette birleşebilirdi, orkestrasyon vergisi
  bir kez ödenirdi).
- Batch kararı, `speed-gate`'in `BATCH_REQUIRED` çıktısıyla **deterministik**
  olarak tetiklenir (bkz. §9), worker'ın öznel kararına bırakılmaz.

### 8.3 20 dakikalık checkpoint kadansı — TÜM ŞERİTLER İÇİN EVRENSEL

**Düzeltme:** Önceki taslakta checkpoint sınırı yalnız normal/mikro şeride
uygulanıyor, yüksek-riskte "checkpoint sınırı yok" deniyordu — bu yanlıştı
ve düzeltildi. **20 dakikalık güvenli checkpoint kadansı prototip, mikro,
normal ve yüksek-risk dahil her şeritte geçerlidir; hiçbir paket 20 dakikayı
checkpoint'siz aşamaz.** Yüksek-risk şeridinde farklı olan tek şey: toplam
**bitiş** süresi için üst sınır yoktur (paket birden fazla 20-dakikalık
checkpoint'ten geçebilir), ama **her 20 dakikada bir** güvenli bir ara-durum
kaydedilmek zorundadır.

Normal/mikro şeritte tipik tek-checkpoint akışı:
| Süre | İş |
|---|---|
| 0–3 dk | Kapsam ve risk sınıfı belirleme (şerit ataması otomatik olmalı, §8.1) |
| 3–6 dk | 3–8 hedefli testte RED |
| 6–14 dk | Implementasyon + hedefli GREEN |
| 14–18 dk | Bağımsız review (suite tekrarı yok, salt-okunur hash doğrulama + gerekirse 1–2 odaklı adversarial kontrol önerisi) |
| 18–20 dk | Güvenli checkpoint/handoff |

Yüksek-risk şeridinde çok-checkpoint akışı (örnek, tek doğru sıra değil):
checkpoint 1 (0–20 dk) kapsam+risk analizi ve RED'in bir kısmı; checkpoint 2
(20–40 dk) RED'in tamamlanması + implementasyonun başlaması; checkpoint 3+
(40 dk sonrası) implementasyon, GREEN, review, adversarial kontrol
önerisi→handoff döngüsü, browser smoke — paket tamamlanana kadar her 20
dakikada bir güvenli durum kaydedilerek devam eder.

20 dakikada bitmezse (hangi şerit olursa olsun) paket başarısız sayılmaz;
güvenli checkpoint alınır, worker aynı belirsizlik üzerinde sınırsız
düşünmeye devam etmez — bu kural aynen korunuyor ve artık **hiçbir istisnası
yoktur**.

## 9. Guardrails-as-code ve MCP tasarımı

Aşağıdaki dosyalar bu görevde **oluşturulmadı** (yazma izni yok); şema ve
davranış burada tasarım düzeyinde tanımlanıyor, ileride ayrı bir yazma
görevinde uygulanmalı.

### `config/development-speed-budget.json` (şema önerisi)
```json
{
  "checkpointCadenceMinutesMax": 20,
  "checkpointCadenceAppliesTo": ["prototype", "microHotfix", "normal", "highRisk"],
  "lanes": {
    "prototype": { "targetedTestMax": 0, "testFilesMax": 0, "browserRunsMax": 0, "totalCompletionDeadlineMinutes": null, "fullLocalQaMax": 0, "ciFullQaMax": 0 },
    "microHotfix": { "targetedTestMax": 3, "testFilesMax": 1, "browserRunsMax": 0, "totalCompletionDeadlineMinutes": 10, "fullLocalQaMax": 1, "ciFullQaMax": 1, "productDiffLinesMax": 30 },
    "normal": { "targetedTestMax": 8, "targetedTestMin": 3, "testFilesMax": 2, "browserRunsMax": 1, "totalCompletionDeadlineMinutes": 20, "fullLocalQaMax": 1, "ciFullQaMax": 1, "reviewerFullSuiteRunsMax": 0, "reviewerCanAuthorTests": false, "reviewerAdversarialCheckSuggestionsMax": 2 },
    "highRisk": { "targetedTestMax": null, "testFilesMax": null, "browserRunsMax": 1, "totalCompletionDeadlineMinutes": null, "fullLocalQaMax": 1, "ciFullQaMax": 1, "requiresJustificationAboveNormal": true, "reviewerFullSuiteRunsMax": 0, "reviewerCanAuthorTests": false }
  },
  "highRiskPathPatterns": [
    "app/**/Auth/**", "app/**/Billing/**", "app/**/Tenancy/**", "app/**/Authorization/**",
    "**/Webhook*", "database/migrations/**", "app/**/Security/**",
    "resources/js/**/auth/**", "resources/js/**/billing/**",
    "resources/js/**/workspace/pages/team/**", "resources/js/**/checkout/**", "resources/js/**/payment/**"
  ],
  "highRiskSemanticClasses": ["auth-flow", "tenancy-isolation", "billing-or-payment-calculation", "webhook-signature-or-verification", "migration-or-irreversible-data-change", "authorization-decision", "secrets-or-credential-handling"],
  "highRiskPathPatternsAreConservativeTriggerOnly": true,
  "microBatch": { "adjacentMicroFixesMax": 3, "sameComponentOrJourneyRequired": true },
  "snapshotEvidenceReuse": { "keyedBy": "contentHashOfChangedFiles", "reuseWindowMinutes": null },
  "flakyQuarantine": {
    "reproducibilityEvidenceRequired": true,
    "minReproRuns": 3,
    "ownerOrMasterVisibleRecordRequired": true,
    "expiryDays": 14,
    "linkedRepairTaskRequired": true,
    "neverQuarantineIfHighRiskClassAffected": true,
    "unknownFailureDefaultsToBlocking": true
  },
  "moduleFramework": {
    "note": "§16 ile eklendi, bu düzeltme geçişinde revize edildi — modül-ölçekli tahsis bu şemanın uzantısıdır, ayrı bir sistem değildir. Serileştirme birimi 'modül' değil 'çakışma bileşeni'dir.",
    "packageManifestSchema": {
      "packageId": "string, required",
      "module": "string, required",
      "writeSet": "string[], required — allowedFiles ile birebir aynı, kesişim kontrolünün girdisi",
      "readSet": "string[], required",
      "contractsConsumed": "string[] (portName@contractManifestHash)",
      "contractsChanged": "string[] (portName@contractManifestHash) — boşsa bu paket hiçbir kontratı değiştirmiyor",
      "tablesOrMigrationsTouched": "string[]",
      "sharedSurfacesTouched": "string[] — steward lane adları (routes|frontendShell|i18n|dependencyLockfiles|migrationsSchema|testConfig|ci)",
      "dependsOnPackages": "string[] (packageId) — DAG kenarları"
    },
    "conflictGraphScheduler": {
      "note": "iki paket P1,P2 eşzamanlı çalışabilir <=> tüm koşullar sağlanır",
      "concurrencyConditions": [
        "writeSet(P1) ∩ writeSet(P2) = ∅",
        "writeSet(P1) ∩ readSet(P2) = ∅ AND writeSet(P2) ∩ readSet(P1) = ∅",
        "contractsChanged(P1) ∩ contractsConsumed(P2) = ∅ AND contractsChanged(P2) ∩ contractsConsumed(P1) = ∅",
        "tablesOrMigrationsTouched(P1) ∩ tablesOrMigrationsTouched(P2) = ∅",
        "sharedSurfacesTouched(P1) ∩ sharedSurfacesTouched(P2) = ∅",
        "dependsOnPackages(P1) ve dependsOnPackages(P2) içindeki her paket zaten merge treninden geçmiş"
      ],
      "onViolation": "yalnız ihlal eden iki paket arasında conflictEdge oluştur; yalnız o conflictComponent'i serileştir, modülün geri kalanını serileştirme",
      "moduleBoundaryAloneNeverSerializes": true,
      "highRiskAloneNeverSerializesWholeModule": true,
      "onlyExactConflictEdgeOrHighRiskContractRequiresSerialization": true
    },
    "stewardLanes": {
      "note": "§16.6a düzeltmesi — tek global sıra DEĞİL, yüzey başına ayrı sıra; lane'ler arası eşzamanlılık serbest",
      "lanes": {
        "routes": { "concurrentActivePackages": 1, "queueDiscipline": "FIFO", "coversFiles": ["routes/api.php"] },
        "frontendShell": { "concurrentActivePackages": 1, "queueDiscipline": "FIFO", "coversFiles": ["resources/js/components/workspace/WorkspaceApp.tsx"] },
        "i18n": { "concurrentActivePackages": 1, "queueDiscipline": "FIFO", "coversFiles": ["resources/js/i18n/workspace.ts"] },
        "dependencyLockfiles": { "concurrentActivePackages": 1, "queueDiscipline": "FIFO", "coversFiles": ["composer.lock", "package-lock.json"] },
        "migrationsSchema": { "concurrentActivePackages": 1, "queueDiscipline": "FIFO", "coversFiles": ["database/migrations/**"] },
        "testConfig": { "concurrentActivePackages": 1, "queueDiscipline": "FIFO", "coversFiles": ["phpunit.xml"] },
        "ci": { "concurrentActivePackages": 1, "queueDiscipline": "FIFO", "coversFiles": [".github/workflows/ci.yml"] }
      },
      "crossLaneConcurrency": "unrestricted — farklı lane'ler birbirini bloklamaz"
    },
    "moduleConcurrentActivePackagesMax": null,
    "moduleSerializationBasis": "conflictComponent, NOT module — bkz. conflictGraphScheduler",
    "withinModuleShardingRequiresNonOverlappingFileSet": true,
    "contractFreezeRecording": { "mechanism": "immutableCommitSha+contractManifestHash", "notATag": true, "registryContentAuthoredBy": "separate authorized Claude config/governance writer, in its own package — NEVER MASTER (MASTER is repo-read-only)", "gitInclusionExecutedBy": "MASTER-only, after gate approval — MASTER validates content and executes permitted Git inclusion/promotion, does not author registry content" },
    "gitMutationsByPackageWorkers": "forbidden — rebase/merge/rollback/commit/push/promotion are MASTER-only",
    "rampStage": "4",
    "rampStages": ["4", "12", "25", "50", "100"],
    "rampStageMeaning": "max concurrently admitted workers across ALL roles; active packages <= this ceiling, actual value depends on phase mix"
  }
}
```

### `scripts/speed-gate` — deterministik kontroller
Girdi: paket manifestosu (değişen dosya listesi, test sayısı/dosyası,
QA/CI koşu sayaçları, checkpoint başlangıç zamanı, snapshot hash).
Çıktı: `GREEN | BLOCK:<reason> | BATCH_REQUIRED | ESCALATE_HIGH_RISK`.

Kontroller (bire bir kod, LLM çağrısı yok):
- `targetedTestCount` şeride göre `min/max` içinde mi.
- `testFilesChanged <= lane.testFilesMax`.
- `fullLocalQaRuns <= lane.fullLocalQaMax`, `ciFullQaRuns <= lane.ciFullQaMax`.
- `browserRuns <= lane.browserRunsMax`.
- `reviewerFullSuiteRuns == 0` (reviewer suite tekrarı hard-block, tüm
  şeritlerde — yüksek risk dahil, istisna yok).
- `reviewerTestFileWrites == 0` (reviewer'ın herhangi bir test dosyasına
  yazdığı tespit edilirse hard-block; reviewer salt-okunurdur, bu asla
  fail-open değildir).
- `elapsedSinceLastCheckpointMinutes <= checkpointCadenceMinutesMax (20)`
  — **tüm şeritlerde**, yüksek risk dahil → aşarsa `BLOCK` değil,
  `CHECKPOINT_NOW` sinyali (fail-open: iş kaybolmaz, güvenli ara-durum
  alınır). Yüksek riskte bu sinyal paketi bitirmez, yalnız bir sonraki
  20 dakikalık pencereye geçmeden önce checkpoint kaydı zorunlu kılar.
- Aynı `snapshotHash` için daha önce üretilmiş kanıt varsa yeniden
  kullanılır, testler tekrar çalıştırılmaz (`evidence_reuse_check`).
- `changedProductLines < 30 AND` aynı journey'de son N dakikada başka bir
  mikro paket kapatıldıysa → `BATCH_REQUIRED`.
- Dosya yolu `highRiskPathPatterns`'a düşüyorsa → şerit otomatik
  `highRisk`'e yükseltilir, `normal` sınırları uygulanmaz.

**Fail-open/fail-closed sınırı:** Güvenlik sınırlarını (test-writer/
implementation-writer/reviewer ayrımı, **reviewer'ın salt-okunur olması ve
hiçbir zaman test yazamaması/değiştirememesi**, CI full QA zorunluluğu,
high-risk şeridinde ek test gerekçesi) ihlal eden hiçbir durum fail-open
değildir — bunlar her zaman `BLOCK`, şerit farkı gözetmeksizin. Yalnız
*hız/verimlilik* sınırı olan **20 dakikalık checkpoint kadansı** aşıldığında
fail-open davranır: iş kaybolmaz, sadece görünür bir `CHECKPOINT_NOW`
sinyali üretilir — bu davranış artık tüm şeritlerde (yüksek risk dahil)
aynıdır, çünkü checkpoint kadansı bir güvenlik sınırı değil bir görünürlük/
kayıp-önleme mekanizmasıdır. Bu ayrım, hız sisteminin kendisinin yeni bir
bürokrasi katmanına dönüşmesini engelliyor — `speed-gate` onay makamı
değil, **sayaç ve sinyal** üretici; reviewer'ın salt-okunur sınırını
gevşetmek için hiçbir hız gerekçesi kabul edilmez.

### `.claude/rules/fast-development.md` / `zabuno-speeder` skill+agent
Bu ikisi, yukarıdaki JSON şemasını ve `speed-gate` çıktısını insan-okunur
prosedüre çevirir; **karar mantığını içermez**, yalnız script'in ürettiği
verdict'i nasıl yorumlayıp handoff'a yazacağını tanımlar. Skill/agent asla
`speed-gate`'in yerine geçen kendi eşik hesaplaması yapmaz — tek doğruluk
kaynağı `config/development-speed-budget.json` + `scripts/speed-gate`.

### Opsiyonel ince MCP
Yalnız 4 yerel, hızlı fonksiyonun ince adaptörü olmalı — Codex'in önerdiği
`speed_budget_check`, `qa_run_admit`, `evidence_reuse_check`,
`checkpoint_record` isimleri ve sınırı **aynen benimseniyor**: MCP bir LLM
orkestratörü değil, `scripts/speed-gate`'in çağrılabilir arayüzü. Önce
yerel checker tamamlanmalı; MCP olmadan da script tek başına çalışabilmeli
— aksi halde hızlandırma amacıyla yeni bir orkestrasyon katmanı eklenmiş
olur.

## 10. CI onarım stratejisi: exact pin vs `composer validate --strict`

Kanıt #4–5 ile doğrulanan durum: CI her zaman `composer validate --strict`
adımında `exit 1` ile bitiyor; sebep license eksikliği + 4 exact pin
uyarısı.

**Pin niyeti bilinmiyor — düzeltme:** Önceki taslak exact pin'lerin
"kasıtlı/güvenlik amaçlı" olduğunu varsayıyordu; bu varsayım hatalıydı ve
kaldırıldı. Repoda bu pin'lerin neden exact seçildiğine dair bir ADR,
yorum veya karar kaydı **bulunamadı**. Doğru sınıflandırma: **niyet
bilinmiyor**, ADR/commit-mesajı/yorum ile doğrulanana kadar pin'lerin hem
"kasıtlı güvenlik kararı" hem de "gelişigüzel/gereksiz katılık" olma
ihtimali açık tutulmalı. Bu belirsizlik CI onarım kararını etkiler: pin'leri
**gevşetmek** (versiyon aralığına çevirmek) niyet doğrulanmadan yapılacak
bir reproducibility/security kararı olur — **yapılmamalı**.

**Önerilen yaklaşım (gerçek şema/lock doğrulamasını korur, yalnız bilinen
politika uyarılarını non-blocking yapar; pin'lere dokunmaz, doğrulamayı
tamamen kaldırmaz):**
`composer validate --strict` adımını CI'de **kaldırmak** (Seçenek B —
tüm gelecekteki gerçek şema/lock hatalarını sessizleştirir) **reddedilir**;
bunun yerine tek adım ikiye bölünür:
1. `composer validate` (strict olmadan) — gerçek yapı/lock-tutarlılık
   hatalarını (geçersiz JSON, `composer.json`/`composer.lock` uyumsuzluğu,
   şema ihlali) build-kıran (`exit 1` → CI fail) bir adım olarak **korur**.
   Bu, reproducibility/security doğrulamasının **hiçbir parçasını
   kaldırmaz**.
2. Ayrı, **bilgilendirici** bir adım (`composer validate --strict` çıktısını
   yakalar ama `continue-on-error: true` ile) yalnız license/exact-pin
   stil uyarılarını CI özetinde görünür tutar, build'i kırmaz. Bu, pin
   niyeti netleşene kadar (bir ADR ile "kasıtlı" ya da "gevşetilecek" diye
   karara bağlanana kadar) uyarıları **gizlemez, yalnız blocking olmaktan
   çıkarır**.
3. `composer.json`'a `"license": "proprietary"` eklemek license uyarısını
   ayrıca kapatır (bu bilgi kaybı değildir, gerçek bir eksik alanı
   doldurur) — **kod/config değişikliği**, bu görevin yazma kapsamı
   dışında; öneri olarak kayda geçiyor.

**Sonuç:** Bu yaklaşım hem "CI her zaman RED" sorununu kapatır hem de
gerçek şema/lock doğrulamasını (asıl reproducibility garantisi) korur;
pin'lerin kasıtlı mı gelişigüzel mi olduğuna dair karar **ayrı bir ADR
kararına** bırakılır, bu rapor o kararı **vermiyor**. Uygulama, bu görevin
dışında, ayrı bir CI-config paketi olarak yapılmalı (tek writer,
allowed-files `{.github/workflows/ci.yml, composer.json}`).

## 11. Test portföyü azaltma yöntemi

- **Konsolidasyon:** Aynı component'in ayrı ayrı test edilen aria/focus/
  escape/disabled/backdrop davranışları, tek bir "kullanıcı journey'si"
  testinde (örn. "dialog busy iken kapatılamaz, boşta iken tüm çıkış
  yolları çalışır") birleştirilir. Kanıt #12'deki 80 satırlık test artışı
  bu deseni gösteriyor.
- **Bug-killing kontratları koru:** Yetki/validasyon/idempotency testleri
  asla azaltılmaz — bunlar normal paket bütçesinin (2) ve (3) numaralı
  zorunlu testleri.
- **Örnekleme mutation testing:** Her sprint/hafta, rastgele seçilen 3–5
  yüksek-risk dosyada mutation testing çalıştırılıp "bug-killing" oranı
  ölçülür (repo genelinde sürekli değil — maliyet/fayda).
  Şu an PHPUnit/Infection veya Stryker gibi bir mutation test aracı
  `composer.json`'da **kurulu değil** (require-dev listesinde yok) — bu
  bir kurulum eksikliği, ayrı bir kararla eklenmeli.
- **Flaky karantina — düzeltilmiş, daha sıkı kural:** Önceki taslaktaki
  "2 ardışık tutarsız çalıştırma → otomatik karantina" kuralı **güvensizdi**
  ve kaldırıldı (2 çalıştırma bir gerçek regresyonu flaky sanıp gizleyebilir).
  Yeni kural: (1) karantinaya almak için **en az 3 tekrar** ile tutarsızlık
  gösteren, **reproducibility kanıtı** (çalıştırma logları/hash'leri)
  eklenmiş bir kayıt gerekir; (2) karantina kaydı **owner/MASTER'a görünür**
  bir yerde tutulur (otomatik/sessiz değil); (3) her karantinanın **sınırlı
  bir geçerlilik süresi** (örn. 14 gün) ve **bağlı bir onarım görevi**
  olmalı — süre dolduğunda test ya onarılır ya da bilinçli bir kararla
  kaldırılır, sonsuza kadar karantinada kalamaz; (4) **auth, tenancy,
  billing/payment veya migration** sınıfına giren bir testin flaky
  görünmesi asla otomatik karantina ile kapatılmaz — bu sınıflarda tutarsız
  davranış önce bir gerçek regresyon olmadığı kanıtlanana kadar **blocking**
  kalır; (5) nedeni belirlenemeyen (unknown) test başarısızlıkları
  **varsayılan olarak blocking'dir**, karantina yalnız yukarıdaki kanıt
  seti tamamlandığında uygulanır.
- **Değişen-test seçimi:** `git diff --name-only` ile değişen dosyaların
  import grafiğinden etkilenen test dosyaları hedeflenir; bu repo boyutunda
  (107 FE + 83 PHP test dosyası) tam suite yerine değişen-alan testi RED/
  GREEN fazlarında yeterli, full suite yalnız full-QA fazında çalışır.
- **Kaçan hata ölçümü:** Her production incident/hotfix'te, kaçan hatanın
  hangi normal-paket testinde yakalanabilirdi diye geriye dönük etiketlenir;
  bu, test bütçesinin gerçekten daraltılabilir mi diye kanıt biriktirir.

## 12. CI/entegrasyon/worktree stratejisi

- CI onarımı §10 Seçenek A ile ayrı, küçük, yüksek-risk-dışı bir pakette
  yapılmalı — bu paketin kendisi normal şeride girer (config-only, <30
  satır çekirdek değişiklik + gerekçe).
- 11 worktree'nin (kanıt #13) durumu envanterlenmeli: her `-task` varyantı
  ile `codex/` varyantı arasındaki fark netleştirilmeli, tamamlanmış/terk
  edilmiş olanlar Pane-GC skill'i üzerinden (bu repoda zaten zorunlu,
  event-driven) temizlenmeli — bu raporun kapsamı dışında bir eylem, yalnız
  önerim.
- Aktif foundation worktree'sindeki billing + Pane-GC dosyalarının aynı
  anda dirty olması (kanıt #15) snapshot hash'ini paket sınırında değil
  worktree sınırında geçersiz kılıyor; **kural:** bir worktree'de aynı anda
  yalnız bir paketin dirty diff'i bulunmalı, bir sonraki paket önceki
  commit/handoff tamamlanmadan başlamamalı.

## 12a. Throughput/akış metrikleri (eklendi — oyun-önleme dahil)

Bu bölüm önceki taslakta eksikti. Ölçüm altyapısı §13'teki 24-saatlik
adımda kurulmalı; hiçbiri bu oturumda toplanmadı (§7'deki bilinmeyenle
tutarlı).

- **Touch-time:** Bir worker'ın pakette fiilen aktif olduğu süre (kapsam,
  RED, implementasyon, review — düşünme/yazma), bekleme hariç.
- **Wait-time:** Ajan başlangıcı, capability/Guardian/Pane admission,
  handoff'lar arası bekleme, CI kuyruğu.
- **Flow efficiency:** `touch-time / (touch-time + wait-time)`. Düşük
  flow efficiency, orkestrasyon vergisinin (Pareto madde 1, §3) gerçek
  büyüklüğünü doğrudan ölçer — bu, §3'teki hipotez payını gözlemlenen
  veriye çevirecek anahtar metriktir.
- **Handoff sayısı:** Paket başına kaç ayrı worker-arası devir var (kapsam
  analisti → test writer → implementation writer → reviewer → [varsa]
  ek test writer → implementation writer → reviewer). Her handoff sabit
  bir maliyet taşır; batch kuralının etkisi bu sayıdaki düşüşle ölçülür.
- **Rework:** Reviewer'ın "adversarial kontrol önerisi" sonrası açılan
  ek test-writer/implementation-writer turlarının sayısı. **Oyun-önleme
  kuralı:** bir paket "tamamlandı" olarak işaretlendikten sonra tekrar
  açılırsa (reopened), bu **yeni bir paket değil, orijinal paketin
  rework'ü olarak sayılır** — orijinal paketin checkpoint/touch-time
  metriklerine eklenir, ayrı ve daha hızlı görünen bir "yeni paket" olarak
  raporlanamaz. Bu, checkpoint süresini düşük göstermek için paketi
  erken "bitti" işaretleyip hemen yeni bir pakette devam etme oyununu
  engeller.
- **Checkpoint median/p95:** §8.3'teki 20 dakikalık kadansa göre, paket
  başına kaç checkpoint gerektiği ve her checkpoint'in gerçek süresi.
- **Escaped defects (kaçan hata):** Production/sonraki paketlerde ortaya
  çıkan, önceki paketin testleriyle yakalanabilecekken yakalanmamış
  hatalar — §13'teki rollback tetikleyicisiyle doğrudan bağlı.

Bu metrikler olmadan §3'teki Pareto payları hipotez olarak kalır; §13'ün
24 saatlik adımı bu metriklerin toplanmaya başlamasını içermelidir.

## 13. Rollout, deney, metrikler, rollback

**Bu bölümün tek-paket rollout'u ile §16.17'deki 4→12→25→50→100 modül-
paralelliği ramp'i ayrı eksenlerdir:** aşağıdaki 24 saat/7 gün/30 gün
planı **tek paketin** hızını iyileştiriyor; §16.17'nin ramp'i **kaç modül
paketinin eş zamanlı** ilerleyebileceğini yönetiyor. 4-worker ramp aşaması,
bu bölümdeki 24 saatlik adımdan **sonra**, `speed-gate`'in advisory modu
en az 7 günlük pencerede stabil çalıştığı görüldükten sonra başlamalı —
CI onarımı (§10) her iki eksenin de önkoşuludur.

**24 saat:** `config/development-speed-budget.json` şeması ve `speed-gate`
script'i taslak olarak yazılır (bu görevde değil, ayrı pakette); mevcut 2–3
tamamlanmış pakete (kanıt #6–8) geriye dönük uygulanıp verdict'lerin
mantıklı çıktığı doğrulanır (dry-run, hiçbir gate henüz blocking değil).

**7 gün:** Normal şerit bütçesi 5–10 gerçek pakette canlı ama
**fail-open/advisory** modda çalıştırılır (yalnız uyarı, block yok);
checkpoint süresi, test sayısı, CI pass-through oranı kaydedilir. CI onarımı
(§10) bu pencerede uygulanır ve en az 3 ardışık push'ta gerçek testlere
ulaştığı doğrulanır.

**30 gün:** Gate `advisory`'den `blocking`'e geçer (yalnız hız sınırları;
güvenlik sınırları zaten baştan blocking). Hedef metrikler:
- Normal paket median checkpoint süresi: 22–82 dk aralığından **≤15 dk
  median**'a.
- CI pass-through (teste ulaşan run oranı): **%0 → ≥%90**.
- Reviewer full-suite tekrarı: **0** (zaten hedef).
- Kaçan hata oranı (test azaltımı sonrası): mevcut baseline'dan **artmamalı**
  — bu tek başına rollback tetikleyicisidir.

**Rollback tetikleyicileri:** (a) kaçan hata oranında artış, (b) high-risk
şeritte bir sınır ihlali production'a sızarsa, (c) `speed-gate` iki hafta
üst üste `false negative` (gerçek riski bloklamama) üretirse → gate anında
tüm şeritlerde `blocking`'den `advisory`'ye geri alınır ve neden kaydedilir.

## 14. A/B benchmark protokolü

- Aynı risk sınıfından (normal) eşdeğer karmaşıklıkta 2 paket seç.
- Paket A: mevcut akış (sınırsız targeted test/tekrar).
- Paket B: §8.2 bütçesi + `speed-gate` advisory modda.
- Ölçülecek (bkz. §12a): touch-time, wait-time, flow efficiency, handoff
  sayısı, rework (reopened paketler orijinale sayılır, oyun-önleme),
  checkpoint median/p95, browser koşu sayısı, CI pass/fail, 30 gün
  sonrası kaçan hata sayısı. Ajan başlangıç/capability gecikmesi ayrı
  damgalanmalı — şu an yok, bu deneyin ön koşulu.
- Rapor: median + p95, N≥5 paket çifti sonrası; reopened/rework paketleri
  ayrı "hızlı" paket olarak sayılmaz (§12a oyun-önleme kuralı).
- Web'in "5 dakika"sı bu deneyde **referans değil**, yalnız bağlam notu
  olarak raporlanır (bkz. §6).

## 15. Kopyala-yapıştır hazır vibecoding talimatı (gelecek worker için)

```
Paket öncesi: risk şeridini belirle (prototype/microHotfix/normal/highRisk)
— dosya yolu highRiskPathPatterns'a düşüyorsa otomatik highRisk; path
eşleşmese bile değişiklik semantik olarak auth/tenancy/billing/webhook/
migration/secrets/authorization/geri-döndürülemez-veri sınıfına giriyorsa
yine highRisk (path pattern yalnız muhafazakâr bir tetikleyicidir, tek
başına yeterli sınıflandırma değildir).

normal şeritte: 3–8 hedefli test, ≤2 test dosyası, üç ana test tercih
sırası (journey → yetki/validasyon → retry/idempotency). Ürün diff'i
<30 satırsa ve aynı journey'de yakın zamanda başka mikro paket kapandıysa
tek başına paket açma, batch et (≤3 ardışık mikro düzeltme).

RED bir kere, GREEN bir kere, full local QA bir kere, CI full QA bir kere.

REVIEWER KURALI (hiçbir istisnası yok): Bağımsız reviewer kesinlikle
salt-okunurdur; hiçbir zaman test ekleyemez veya değiştiremez, full-suite
koşusu her zaman sıfır kalır. Reviewer yalnız immutable snapshot hash'i ve
çıktıyı doğrular; ek doğrulama gerekli görürse bunu 1–2 odaklı adversarial
kontrol *önerisi* olarak yazılı gerekçeyle bildirir. Onaylanan her öneri,
ayrı test writer'a açık bir handoff ile döner; test writer testi yazar,
ayrı implementation writer gerekirse uygular, ve taze bir immutable
snapshot üzerinde yeniden review yapılır.

Değişmeyen snapshot'ta test tekrarı yok. Mikro UI düzeltmesinde browser QA
yok; dikey dilim kapanışında en fazla 1 browser smoke.

20 DAKİKALIK CHECKPOINT KADANSI — TÜM ŞERİTLERDE EVRENSEL, İSTİSNASIZ
(prototip, mikro, normal, yüksek-risk dahil): hiçbir paket 20 dakikayı
checkpoint'siz aşamaz. Normal/mikro tipik akış: 0–3 kapsam, 3–6 RED, 6–14
implementasyon+GREEN, 14–18 review, 18–20 handoff. Yüksek-riskte toplam
bitiş süresi için sınır yoktur ama her 20 dakikada bir güvenli checkpoint
zorunludur — paket birden çok checkpoint'ten geçebilir. Bitmezse (hangi
şerit olursa olsun) güvenli checkpoint al, sınırsız düşünmeye devam etme.

Test writer / implementation writer / independent (salt-okunur) reviewer
ayrımı ve worktree affinity her zaman korunur — hız hiçbir zaman bu
sınırları gevşetmenin gerekçesi değildir.

MODÜL ÖLÇEĞİ (§16 ile eklendi, bu geçişte düzeltildi): Paketin hangi
modüle ait olduğunu belirle (§16.3 manifesti) ve paket manifestini
(writeSet/readSet/contractsConsumed/contractsChanged/tablesTouched/
sharedSurfacesTouched, §16.6) bildir. **Aynı modülde birden fazla paket,
write-set'leri kesişmiyorsa, hiçbiri diğerinin tükettiği bir kontratı
değiştirmiyorsa ve DAG bağımlılığı tatmin ediliyorsa eşzamanlı çalışabilir**
— modül sınırı tek başına bir serileştirme gerekçesi değildir; yalnız
gerçek çakışma (kesişen dosya/tablo/kontrat/artefakt/lockfile) o çakışma
bileşenini serileştirir, tüm modülü değil (§16.6). Paylaşılan dosyalara
(`routes/api.php`, `WorkspaceApp.tsx`, `workspace.ts`, lockfile'lar,
migrations, `phpunit.xml`, `ci.yml`) doğrudan yazma — bunun yerine ilgili
**yüzeye özgü steward lane'ine** istek bırak (§16.6a); farklı yüzeylerin
steward lane'leri birbirini bloklamaz, yalnız aynı yüzeyin kendi lane'i
içinde tek yazar kuralı geçerlidir. Modüller arası bağımlılık varsa,
bağımlı olunan Port arayüzü **immutable commit SHA + kontrat manifest
hash'i** ile dondurulmuş kabul edilir (§16.5, **tag değil**); imza
değişikliği ayrı, önce giden bir kontrat-değişikliği paketidir. Paket
başına **her zaman tam olarak bir aktif yazan** (sıralı fazda) ve somut
dosya başına **her zaman tam olarak bir yazan** mutlak kalır. 100 worker
toplam kapasiteyi ifade eder; hazırlık ve altyapı tamamlandığında dağıtılmış
ortamda gerçek 100 eşzamanlı worker mimari olarak mümkündür, ama bu Mac'te
gerçek eşzamanlı worker sayısı her zaman Guardian admission'ına tabidir
(§16.10). Paket worker'ları (test writer, implementation writer, reviewer,
steward) **hiçbir zaman** rebase/merge/rollback/commit/push/promotion
çalıştırmaz — yalnız içerik üretir ve çakışma kanıtı raporlar; Git
yürütücüsü ve rollback/revert yetkisi yalnız Codex Desktop MASTER'a
aittir.
```

## 16. Modüler Monolith 100-Worker Geliştirme Çerçevesi

Owner'ın sorduğu soru doğru ve önceki bölümlerin (tek paketin serial
checkpoint zinciri) cevaplamadığı ayrı bir eksen: **modüler bir monolith
birçok worker tarafından paralel inşa edilip güvenle birleştirilebilir mi?**
Bu bölüm, mevcut mimariyi *varsaymadan*, repodan yeniden okuyarak yanıtlıyor.

### 16.1 Repo kanıtı — gerçek modül sınırları (icat edilmedi, ölçüldü)

| Katman | Kanıt | Gözlem |
|---|---|---|
| `app/Application/*` | `ls app/Application` | 12 bounded-context dizini: `Analytics, Authorization, Billing, Identity, Media, MenuCatalog, Platform, Publication, QrDestination, Security, Team, Tenancy` — her biri kendi `Dto/`, `Port/`, `UseCase/`, `Exception/` alt yapısına sahip |
| `app/Domain/*` | `ls app/Domain` | `Analytics, Authorization, Identity, Media, MenuCatalog, Modules, Platform, Publication, QrDestination, Security, Taxonomy, Team, Tenancy` — **`Billing` bu dizinde YOK.** Domain katmanı Application/Infrastructure ile birebir aynı 12'li kümeyi izlemiyor; bu bir düzeltme (önceki taslakta "aynı 12 + Modules, Taxonomy" hatalıydı — Billing'in Domain karşılığı yok, bu ayrı bir boşluk, aşağıda §16.3'te not ediliyor). |
| `app/Infrastructure/*` | `find app/Infrastructure -maxdepth 1 -type d` | Application ile aynı 12 context (Billing dahil) için ayrı persistence/adapter dizinleri, + paylaşılan `Infrastructure/Persistence`. **Infrastructure/Billing var, Domain/Billing yok** — üç katman arasında asimetri. |
| Port sayısı | `find app/Application -name "*Port.php" \| wc -l` | **30 Port arayüzü** — hexagonal sınırların somut, sayılabilir kanıtı; her modül dış bağımlılığını interface arkasına koymuş |
| Cross-module PHP import — **düzeltilmiş, genişletilmiş tarama** | `rg -n "^use App\\Domain\\[A-Za-z]+\\" app/Domain` + hedefli grep'ler | Önceki taslaktaki "sıfır cross-module import" iddiası **aşırı genellemeydi** ve tek bir dar örneklemden (yalnız MenuCatalog→Billing, MenuCatalog→Tenancy) çıkarılmıştı. Daha geniş bir tarama **gerçek, doğrudan cross-context import'lar** buldu: (1) `app/Domain/Authorization/RolePermissions.php:7` → `use App\Domain\Tenancy\MembershipRole;`; (2) `app/Application/Team/UseCase/CreateTeamInvitation.php` ve `AcceptTeamInvitation.php` → `App\Domain\Identity\EmailAddress` import ediyor; (3) `app/Domain/MenuCatalog/Product.php:7` → `use App\Domain\Taxonomy\TaxonomyTerm;`. Üçü de bu oturumda `grep`/`rg` ile doğrudan doğrulandı. **Doğru sonuç: ayrım umut verici ama gözenekli (porous), sıfır değil.** Tam bir makine-üretimli bağımlılık grafiği (§16.18) olmadan hangi cross-import'ların *kasıtlı yayınlanmış kontrat* (örn. `EmailAddress` bir paylaşılan value object olabilir) hangilerinin *tesadüfi coupling* olduğu ayırt edilemez — bu ayrım aşağıda §16.2'de netleştiriliyor. |
| `routes/api.php` | okundu | Tek dosyada, controller import'ları modül öneki ile ayrışmış (`Http\Controllers\Billing\*`, `\Team\*`, `\Tenancy\*` ...) — routing **fiziksel olarak tek dosyada birleşiyor**, bu bir paylaşılan darboğaz (§16.3) |
| `database/migrations/` | `ls` | 20 migration, tarih öneki modül sırasını gösteriyor (workspaces → brands/locations → menu → media → publications → qr → analytics → team → billing/plans → tenant-isolation → backup-restore) — **tek migration dizini, tek sıra**, modül başına ayrı değil |
| `resources/js/components/*` | `find -maxdepth 2` | `workspace/`, `auth/`, `catalog/`, `platform/`, `admin/`, `public/` — frontend'de de isimlendirilmiş ayrım var, ama... |
| `WorkspaceApp.tsx` | `wc -l` + import listesi | **894 satır**, `DashboardPage, BrandPage, LocationsPage, MenuPage, MediaPage, PublicationPage, AnalyticsPage, TeamPage, BillingPage, LaunchReadinessPage` — **her modülün sayfası bu tek dosyadan import ediliyor**. Bu, backend'deki port ayrımının frontend'de **karşılığı olmayan, tek dosyada toplanmış bir router/shell**. |
| `resources/js/i18n/workspace.ts` | `wc -l` | **503 satır** — muhtemelen birçok modülün metin anahtarlarını tek dosyada topluyor (tenancy, team, billing UI metinleri) |
| `TeamPage.tsx` | `wc -l` | 506 satır — tek sayfa dosyası, ownership transfer paketi (kanıt #11) burayı 149 satır değiştirmişti |
| Lockfile | `ls composer.lock package-lock.json` | **İkisi de tek, kök-düzey, tüm modülleri kapsayan** dosyalar — herhangi bir modülün bağımlılık eklemesi aynı lockfile'ı değiştirir |
| `phpunit.xml` | okundu | Tek `testsuites` bloğu, `tests/Unit` — **modül başına ayrı test suite tanımı yok** |
| Worktree topolojisi | `git worktree list` (önceki oturumdan) | 11 worktree: 5 frontend alanı (`brand-locations, dashboard, media, menu, publication-qr`) her biri `codex/` + `-task` çiftiyle, hepsi **aynı `8d39aa5` SHA'sında donmuş**. **Düzeltme:** bu, "immutable-base disiplininin zaten çalıştığının kanıtı" **değildir** — kanıtladığı yalnız *ortak bir taban commit* ve *durmuş/ilerletilmemiş bir topoloji*dir. Bu worktree'lerin fiilen bir paket üretip üretmediği, base'in kasıtlı mı yoksa yalnız hiç kullanılmamış oldukları için mi sabit kaldığı bu oturumda **bilinmiyor** — etkinlik (effectiveness) doğrulanmadı, yalnız varlık (existence) doğrulandı. |

### 16.2 Sınıflandırma: fiilen ayrılabilir vs. yalnız isimle ayrılmış vs. refactör gerektirir

**Fiilen ayrılabilir, ama gözenekli — "sıfır coupling" değil (düzeltme):**
- Backend `app/Application/{Modül}`, `app/Domain/{Modül}`,
  `app/Infrastructure/{Modül}` üçlüsü büyük ölçüde port arkasında; ancak
  §16.1'de doğrulanan 3 gerçek cross-context import (Authorization→Tenancy,
  Team→Identity, MenuCatalog→Taxonomy) gösteriyor ki **bu ayrım tam
  değil**. Modül-içi UseCase/Dto/Port/Adapter dosyalarının çoğu başka
  modülün dosyasına dokunmadan değişebilir, ama bu **istisnasız bir
  garanti değil** — bir worker, kendi modülünün bir value object'ini veya
  domain sınıfını değiştirirken, o sınıfı import eden başka bir modülü
  kırabilir. **İzin verilen vs. tesadüfi coupling ayrımı:**
  - `Team → Identity::EmailAddress` ve muhtemelen `MenuCatalog →
    Taxonomy::TaxonomyTerm` **kasıtlı, yayınlanmış-kontrat tipi
    bağımlılıklar olabilir** (value object'ler / paylaşılan taksonomi
    bilerek başka modüllerin kullanımına açılmış olabilir) — ama bu niyet
    bu oturumda bir ADR/yorum ile **doğrulanmadı**, yalnız import'un
    varlığı doğrulandı.
  - `Authorization → Tenancy::MembershipRole` bir domain sınıfının başka
    bir modülün domain value object'ine doğrudan bağımlı olması — bu,
    Authorization'ın "cross-cutting, herkesin bağımlı olabileceği çekirdek"
    rolüyle (§16.4) tutarlı olabilir, ama yine niyet doğrulanmadı.
  - Niyet doğrulanmadan hiçbiri "güvenle paralelleştirilebilir kesin
    kontrat" sayılmamalı; **tam bir makine-üretimli bağımlılık grafiği
    (§16.18'deki hazırlık paketi) zorunlu önkoşuldur** — bu raporun
    manifesti (§16.3) yalnız bir **aday**dır, kanıtlanmış gerçek değil.
- Her modülün kendi Feature test dizini (`tests/Feature/{Modül}/...` —
  kanıt: `PasswordResetJourneyTest.php`, `TransferWorkspaceOwnershipJourneyTest.php`
  ayrı dosyalar, kanıt #10–11) fiziksel olarak ayrı — bu gözlem değişmedi.

**Yalnız isimle ayrılmış, fiziksel olarak birleşik (paralel yazımda
çakışma üretir):**
- `routes/api.php` — 12 modülün route'u **tek dosyada**; iki modül worker'ı
  aynı anda yeni route eklerse aynı dosyada çakışır.
- `resources/js/components/workspace/WorkspaceApp.tsx` — her modülün
  sayfası bu 894 satırlık dosyadan import ediliyor; yeni bir sayfa/route
  eklemek veya var olanın imzasını değiştirmek bu dosyaya dokunur.
- `resources/js/i18n/workspace.ts` (503 satır) — çok modüllü metin
  anahtarı deposu; iki modül worker'ı aynı anda metin eklerse çakışır.
- `composer.lock` / `package-lock.json` — herhangi bir modülün yeni bir
  paket eklemesi kilidi değiştirir; iki worker aynı anda paket eklerse
  kilit dosyası çakışması kaçınılmaz.
- `database/migrations/` — tek, tarih-sıralı dizin; iki modülün eş zamanlı
  migration'ı dosya adı çakışması üretmez (timestamp farklı) ama migration
  **sırası** ve şema bağımlılığı (örn. `qr_codes`'a `dining_table_id` ekleyen
  `..._000007_add_dining_table_id_to_qr_codes.php` migration'ı QrDestination
  ile Tenancy/dining-area şemasına bağımlı) paralel migration yazımını
  koordinasyonsuz güvenli kılmaz.
- `phpunit.xml` tek suite — modül bazlı "yalnız etkilenen modülü çalıştır"
  ayrımı **yapılandırılmamış**, bu bir CI/test seçim altyapısı eksikliği.

**Refactör gerektirir (100-worker ölçeğinden önce):**
- **Cross-context import'ların tam envanteri ve sınıflandırılması** —
  §16.1'de bulunan 3 örnek (Authorization→Tenancy, Team→Identity,
  MenuCatalog→Taxonomy) yalnız görülenler; tam bir `rg`/statik-analiz
  taraması ile her import "yayınlanmış kontrat" ya da "tesadüfi coupling,
  düzeltilmeli" olarak etiketlenmeli. Bu envanter olmadan §16.3'teki
  `dependsOn` alanları **doğrulanmamış adaylardır**.
- Frontend'de backend'deki port disiplinine denk bir **modül-sınırlı
  sayfa kayıt mekanizması** yok (örn. lazy-route registry, her modül kendi
  sayfasını `WorkspaceApp`'e bir merkezi dosyayı değiştirmeden kaydedebilsin).
- i18n için modül başına ayrı dosya/namespace yok (`workspace.ts` tek
  havuz).
- Migration bağımlılık grafiği açık/dokümante değil — hangi migration'ın
  hangi modülün şemasına bağımlı olduğu yalnız dosya içeriğini okuyarak
  çıkarılabiliyor, bir manifest yok.
- CI, modül bazlı "yalnız etkilenen modülü test et" + ayrı bir "merge
  queue"/entegrasyon CI ayrımı yapmıyor (§10'daki tek, hep-red CI zaten
  bunun bir belirtisi).

### 16.3 Önerilen kanonik modül manifesti (repo kanıtına dayalı ADAY — henüz repoda yok, henüz uygulanmış/zorlanan gerçek DEĞİL)

**Doğrulama durumu etiketleri — okurken dikkat:** Aşağıdaki her modül
girdisi `verified` (dizin/dosya kanıtıyla doğrudan doğrulandı) veya
`inferred` (isimlendirme/routing/migration'dan makul ama **doğrulanmamış**
bir çıkarım) olarak etiketlenmiştir. **`owns` ve `dependsOn` alanları şu an
kod tarafından zorlanan (enforced) bir gerçeklik değildir** — bunlar bu
raporun önerdiği, henüz yazılmamış bir manifestin taslağıdır. Zorlama
(enforcement), §16.18'deki hazırlık paketleriyle (tam bağımlılık grafiği +
Port-sınırı statik kontrolü) ayrı bir adımda kurulmalıdır.

```json
{
  "verificationLegend": { "verified": "dizin/dosya/import kanıtıyla bu oturumda doğrudan doğrulandı", "inferred": "isimlendirme/routing/migration'dan çıkarım, kod tarafından zorlanmıyor, doğrulanmadı" },
  "modules": {
    "identity":       { "verification": "inferred", "layer": ["Application/Identity", "Domain/Identity", "Infrastructure/Identity"], "owns": ["users", "password_resets"] },
    "tenancy":        { "verification": "inferred", "layer": ["Application/Tenancy", "Domain/Tenancy", "Infrastructure/Tenancy"], "owns": ["workspaces", "workspace_memberships", "brands", "locations"] },
    "team":           { "verification": "inferred", "layer": ["Application/Team", "Domain/Team", "Infrastructure/Team"], "owns": ["team_invitations"], "dependsOn": ["tenancy", "identity"], "dependsOnEvidence": "verified — Team/UseCase/CreateTeamInvitation.php ve AcceptTeamInvitation.php App\\Domain\\Identity\\EmailAddress import ediyor (§16.1)" },
    "menuCatalog":    { "verification": "inferred", "layer": ["Application/MenuCatalog", "Domain/MenuCatalog", "Infrastructure/MenuCatalog"], "owns": ["menus", "categories", "products", "menu_items"], "dependsOn": ["tenancy", "taxonomy"], "dependsOnEvidence": "verified (taxonomy) — Domain/MenuCatalog/Product.php App\\Domain\\Taxonomy\\TaxonomyTerm import ediyor (§16.1); tenancy bağımlılığı inferred" },
    "media":          { "verification": "inferred", "layer": ["Application/Media", "Domain/Media", "Infrastructure/Media"], "owns": ["media_assets"], "dependsOn": ["tenancy"] },
    "publication":    { "verification": "inferred", "layer": ["Application/Publication", "Domain/Publication", "Infrastructure/Publication"], "owns": ["menu_publications"], "dependsOn": ["menuCatalog", "tenancy"] },
    "qrDestination":  { "verification": "inferred", "layer": ["Application/QrDestination", "Domain/QrDestination", "Infrastructure/QrDestination"], "owns": ["qr_codes", "dining_areas", "dining_tables"], "dependsOn": ["tenancy", "publication"] },
    "analytics":      { "verification": "inferred", "layer": ["Application/Analytics", "Domain/Analytics", "Infrastructure/Analytics"], "owns": ["analytics_events"], "dependsOn": ["tenancy"] },
    "billing":        { "verification": "inferred, DOMAIN-KATMANI EKSİK", "layer": ["Application/Billing", "Infrastructure/Billing"], "layerGap": "Domain/Billing bu oturumda `ls app/Domain` ile doğrulanan listede YOK — bkz. aşağıdaki not", "owns": ["plans", "subscriptions", "manual_payments", "iyzico_sandbox_transactions"], "dependsOn": ["tenancy", "platform"] },
    "platform":       { "verification": "inferred", "layer": ["Application/Platform", "Domain/Platform", "Infrastructure/Platform"], "owns": ["platform_role_assignments"], "dependsOn": ["identity"] },
    "authorization":  { "verification": "verified (dependsOn.tenancy)", "layer": ["Application/Authorization", "Domain/Authorization", "Infrastructure/Authorization"], "owns": [], "dependsOn": ["identity", "tenancy"], "dependsOnEvidence": "verified (tenancy) — Domain/Authorization/RolePermissions.php App\\Domain\\Tenancy\\MembershipRole import ediyor (§16.1); identity bağımlılığı inferred", "note": "cross-cutting — bkz. §16.4" },
    "security":       { "verification": "inferred", "layer": ["Application/Security", "Domain/Security", "Infrastructure/Security"], "owns": ["tenant_isolation_evidence", "backup_restore_evidence"], "dependsOn": ["tenancy"] },
    "taxonomy":       { "verification": "inferred, manifest'e §16 düzeltmesinde eklendi", "layer": ["Domain/Taxonomy"], "owns": ["taxonomy_terms(TBD)"], "note": "yalnız Domain katmanında var (ls app/Domain doğrulandı); Application/Infrastructure karşılığı bu oturumda aranmadı — bilinmiyor" }
  },
  "sharedInfrastructure": ["Infrastructure/Persistence", "routes/api.php", "resources/js/components/workspace/WorkspaceApp.tsx", "resources/js/i18n/workspace.ts", "composer.lock", "package-lock.json", "database/migrations/", "phpunit.xml", ".github/workflows/ci.yml"]
}
```

**Not — düzeltilmiş envanter (owner talebiyle netleştirildi):**
`Domain/Billing` bu oturumda **doğrulanan** `ls app/Domain` çıktısında
kesin olarak **yok** (liste: `Analytics, Authorization, Identity, Media,
MenuCatalog, Modules, Platform, Publication, QrDestination, Security,
Taxonomy, Team, Tenancy`), buna karşın `Application/Billing` ve
`Infrastructure/Billing` **doğrulandı**. Bu üç-katman asimetrisi (Billing
domain katmanında temsil edilmiyor) bir tutarsızlık işareti olabilir ya da
Billing'in domain nesnelerinin (örn. para/plan value object'leri) başka
bir dizinde modellendiğini gösterebilir; bu oturumda kesinleştirilmedi,
**bilinmiyor**, manifest yazılırken (ve Billing'i içeren her paralel
paket highRisk disiplininde tutulurken, §17) doğrulanmalı.

### 16.4 Modül API ve veri sahipliği kontratları

- Her modülün **tek veri sahibi** olması **hedeflenen** kural, yukarıdaki
  `owns` alanıyla ifade ediliyor — ama bu şu an kod tarafından **zorlanan
  bir gerçeklik değil**, bir tasarım hedefidir (§16.3'teki
  `inferred`/`verified` etiketlemesine bakın). Mevcut 30 Port arayüzü
  *disiplinin genel yönünü* kanıtlıyor, ama §16.1'in bulduğu 3 doğrudan
  domain-to-domain import (Port arkasında değil) bu disiplinin **her yerde
  istisnasız uygulanmadığını** gösteriyor. Bir modül başka modülün
  tablosuna doğrudan sorgu yazmaması **hedeftir**; bunu kod-düzeyinde
  garanti eden bir statik kontrol (lint kuralı, mimari sınır testi) bu
  oturumda bulunamadı — bu, §16.18'deki hazırlık paketlerinden biridir.
  Migration bağımlılığı örneği yukarıdaki `qr_codes` ↔ `dining_areas`
  vakası: iki modül aynı tabloya migration yazıyorsa, o tablo **paylaşılan
  veri** sayılır ve bir "shared-file steward" (§16.6) atanır.
- `authorization` modülü kasıtlı olarak **cross-cutting**: kendi tablosu
  yok, diğer tüm modüllerin yetki kararlarını `AuthorizationPort` üzerinden
  sağlıyor. Cross-cutting modüller normal `dependsOn` DAG'ının dışında,
  **herkesin bağımlı olabileceği ama kimsenin değiştiremeyeceği (contract-freeze,
  §16.5) çekirdek** olarak ele alınır.
- API kontratı: her modülün dış yüzeyi yalnız kendi `Port` arayüzleri +
  kendi `Http/Controllers/{Modül}` dosyalarıdır. Bir modül başka modülün
  Controller'ını veya Eloquent modelini doğrudan import edemez —
  **izin verilen yön:** `Controller → UseCase → Port → Infrastructure`
  (kendi modülü içinde); **izin verilen çapraz-modül yön:** yalnız
  `UseCase → başka modülün Port arayüzü` (interface'e bağımlılık, somut
  sınıfa değil); **yasak yön:** bir modülün `Infrastructure`/Eloquent
  katmanının başka modülün `Infrastructure` katmanını doğrudan çağırması.

### 16.5 Contract-first freeze — düzeltilmiş kayıt mekanizması (tag yok)

Bir modül-arası bağımlılık (örn. `qrDestination → publication`) içeren
paket başlamadan önce: bağımlı olunan Port arayüzünün imzası **dondurulur**.
**Düzeltme (Git yetkisi):** bu dondurma **hiçbir zaman bir Git etiketi
(tag) veya release olarak kaydedilmez** — Codex Desktop MASTER dahi
tag/release oluşturma yetkisine sahip değildir (kök yönetişim bloğu, madde
1: MASTER yalnız "Git executor", tag/release ayrı bir promotion/release
yetkisidir ve bu raporun kapsamında MASTER'a da atfedilmiyor). Doğru kayıt
mekanizması: dondurulmuş kontrat, **immutable commit SHA'sı + bir kontrat
manifest hash'i** (örn. Port arayüzünün imzasının içerik hash'i) olarak
kaydedilir.

**Düzeltme (yazar yetkisi — MASTER repo-read-only'dir, kayıt dosyasını
asla yazmaz):** Bu kayıt bir dosyada (örn.
`contract-freeze-registry.json`, henüz repoda yok, öneri) tutulur — ama
**MASTER bu dosyayı asla kendisi authoring/değiştirme yapmaz**, çünkü
MASTER kök yönetişim bloğu madde 1 gereği **repo-read-only**'dir
(`masterIsRepoReadOnly=true`, `masterWriteScope=none`). İçeriği
**ayrı, yetkilendirilmiş bir Claude config/governance writer** (mevcut
"tek writer paket" disiplinine tabi, kendi paketinde) yazar:
`{portInterface, frozenAtCommitSha, contractManifestHash, authoredBy:
"<config-governance-writer-package-id>"}`. **MASTER bu içeriği doğrular**
(gate kontrolü) ve gate geçtikten sonra **izin verilen Git
yürütme/promotion işlemini** (commit'in dahil edilmesi, merge, vb.)
gerçekleştirir — ama registry'nin *içeriğini* MASTER **yazmaz**. **Paket
worker'ları (test writer, implementation writer, reviewer, steward) bu
kaydı asla kendileri oluşturmaz veya değiştirmez** — yalnız dondurulmuş
`contractManifestHash`'e karşı okur ve yazar; dondurma/kaldırma kaydının
Git'e dahil edilmesi, her Git mutasyonu gibi, MASTER'ın gate-sonrası
yürüttüğü bir işlemdir (bkz. §16.8 Git yetkisi düzeltmesi), ama kayıt
*içeriğinin yazarı* ayrı config/governance writer'dır. Bağımlı modülün
worker'ı bu dondurulmuş arayüze karşı yazar.
Port imzası değişecekse, bu değişikliğin kendisi ayrı, önce giden bir
"contract change" paketidir (yüksek-risk şeridine benzer disiplinle: tüm
bağımlı modüllere haber verilir, eski imza bir sürüm penceresi boyunca
expand-contract ile korunur, §16.11).

### 16.6 Paket sahipliği — dependency-DAG + write-set çakışma grafiği zamanlayıcısı (düzeltme: keyfi "modül başına 1 paket" kuralı kaldırıldı)

**Düzeltme (owner haklı):** Önceki taslaktaki "her modülde aynı anda yalnız
1 aktif paket" ve "highRisk modüllerde 1 (çekirdek, sık paylaşılan)"
kuralları **keyfi ve gereğinden fazla serial** idi. Owner'ın belirttiği
gibi, aynı modülün içindeki iki paket bile — dosya kümeleri kesişmiyorsa,
hiçbiri diğerinin tükettiği bir kontratı değiştirmiyorsa ve DAG bağımlılığı
tatmin ediliyorsa — **eşzamanlı çalışabilir**. Modül sınırı tek başına bir
serileştirme gerekçesi değildir; **gerçek çakışma** (kesişen dosya, tablo/
migration, kontrat, üretilmiş artefakt veya lockfile) gerekçedir.

**Her paket şu manifesti bildirir (paket başlamadan önce, kapsam
analistinin çıktısı):**
```json
{
  "packageId": "menuCatalog-category-crud-01",
  "module": "menuCatalog",
  "allowedFiles_writeSet": ["app/Http/Controllers/MenuCatalog/StoreCategoryController.php", "app/Http/Requests/MenuCatalog/StoreCategoryRequest.php", "tests/Feature/MenuCatalog/StoreCategoryTest.php"],
  "readSet": ["app/Domain/MenuCatalog/Category.php", "app/Application/MenuCatalog/Port/MenuCatalogRepositoryPort.php"],
  "contractsConsumed": ["MenuCatalogRepositoryPort@<contractManifestHash>"],
  "contractsChanged": [],
  "tablesOrMigrationsTouched": [],
  "sharedSurfacesTouched": [],
  "dependsOnPackages": []
}
```

**Zamanlama kuralı — dependency-DAG + write-set çakışma grafiği:** İki
paket P1, P2 **eşzamanlı çalışabilir** ancak ve ancak:
1. `writeSet(P1) ∩ writeSet(P2) = ∅` (kesişen dosya yok),
2. `writeSet(P1) ∩ readSet(P2) = ∅` ve tersi de (biri diğerinin okuduğu
   dosyayı canlı değiştirmiyor — okuma sırasında taban kayması yok),
3. `contractsChanged(P1) ∩ contractsConsumed(P2) = ∅` ve tersi (biri
   diğerinin tükettiği bir kontratı değiştirmiyor; değiştiriyorsa
   contract-freeze/expand-contract akışı, §16.5/§16.11, devreye girer),
4. `tablesOrMigrationsTouched(P1) ∩ tablesOrMigrationsTouched(P2) = ∅`,
5. `sharedSurfacesTouched(P1) ∩ sharedSurfacesTouched(P2) = ∅` (aynı
   steward-lane yüzeyine aynı anda dokunmuyorlar, §16.6a),
6. DAG bağımlılığı tatmin edilmiş (`dependsOnPackages` içindeki her paket
   zaten merge treninden geçmiş, §16.14).

Yukarıdaki altı koşuldan **herhangi biri ihlal edilirse**, bu iki paket
arasında bir **çakışma kenarı (conflict edge)** oluşur ve yalnız bu
**çakışma bileşeni (conflict component)** — tüm modül değil — serileştirilir.
Yani modülün geri kalan, çakışma grafiğinde bu bileşene bağlı olmayan
paketleri **eşzamanlı devam eder**. Bu, önceki taslaktaki "modül = 1 aktif
paket" kuralının yerini alıyor: **serileştirme birimi artık modül değil,
çakışma bileşenidir.**

**Mutlak, hiçbir zaman gevşetilmeyen iki kısıt (owner'ın talebiyle
korunuyor):**
- **Paket başına tam olarak bir aktif yazan** (test writer VEYA
  implementation writer, sıralı fazda, §16.16) — bu, çakışma grafiğinden
  bağımsız, her zaman geçerli.
- **Somut bir dosya başına tam olarak bir yazan, herhangi bir anda** —
  iki farklı paket aynı dosyaya aynı anda asla yazmaz; bu, yukarıdaki
  write-set kesişim kontrolüyle (koşul 1) zaten garanti ediliyor, ama
  ayrıca **istisnasız bir alt sınır** olarak da tekrar belirtiliyor.

**Örnek — aynı modülde eşzamanlı iki paket:** `menuCatalog-category-crud`
(`StoreCategoryController.php` + `StoreCategoryRequest.php`) ve
`menuCatalog-price-update` (`UpdateMenuItemPriceController.php` +
`UpdateMenuItemPriceRequest.php`) — write-set'leri kesişmiyor, ikisi de
aynı `MenuCatalogRepositoryPort` kontratını yalnız *tüketiyor*, hiçbiri
*değiştirmiyor*, hiçbiri aynı migration'a dokunmuyor → **çakışma kenarı
yok, eşzamanlı çalışabilirler.** Buna karşın `menuCatalog-category-crud`
ile aynı anda `app/Domain/MenuCatalog/Category.php`'yi değiştiren üçüncü
bir paket olsaydı (readSet/writeSet kesişimi), bu ikisi arasında bir
çakışma kenarı oluşur ve yalnız bu ikisi sıraya girer — `price-update`
paketi bundan etkilenmez.

### 16.6a — Paylaşılan yüzeyler için ayrı steward lane'leri (düzeltme: tek global sıra kaldırıldı)

**Düzeltme (owner haklı):** Önceki taslaktaki "tüm paylaşılan dosyalar için
tek global FIFO sıra" gereğinden fazla serialdi — `routes/api.php`'ye bir
route eklemek ile `package-lock.json`'a bir paket eklemek birbirinden
bağımsız yüzeylerdir, aynı sıraya sokulmaları gerekmez. Düzeltilmiş model:
**yüzey başına ayrı bir steward lane'i**, her lane kendi tek-yazar
kısıtına sahip, lane'ler arası **bağımsız eşzamanlılık**:

| Steward lane | Kapsadığı yüzey(ler) | Eşzamanlı diğer lane'lerle ilişkisi |
|---|---|---|
| **routes** | `routes/api.php` (+ ileride `routes/{modül}.php` refaktörü sonrası yalnız toplayıcı dosya, §16.18) | Bağımsız — aynı anda `i18n` lane'i ile çakışmaz |
| **frontend-shell registry** | `resources/js/components/workspace/WorkspaceApp.tsx` (+ ileride modül-sınırlı sayfa registry sonrası yalnız toplayıcı) | Bağımsız |
| **i18n** | `resources/js/i18n/workspace.ts` (+ ileride modül başına ayrı i18n dosyası sonrası yalnız birleştirme adımı) | Bağımsız |
| **dependency lockfiles** | `composer.lock`, `package-lock.json` | Bağımsız |
| **migrations/schema** | `database/migrations/` (yeni migration dosyası eklemek, mevcut şemayı değiştirmek) | Bağımsız, ama migration'ın kendisi ayrıca `tablesOrMigrationsTouched` çakışma kontrolüne (§16.6) tabidir |
| **test configuration** | `phpunit.xml` (+ ileride modül-bazlı suite refaktörü) | Bağımsız |
| **CI** | `.github/workflows/ci.yml` | Bağımsız |

**Her lane'in kendi tek-yazar kuralı:** aynı lane içinde aynı anda yalnız
bir steward paketi write erişimine sahiptir (FIFO, o lane için); **farklı
lane'ler birbirini bloklamaz** — bir `i18n` steward paketi ile bir
`migrations` steward paketi **eşzamanlı** ilerleyebilir, çünkü yazdıkları
somut dosyalar kesişmiyor. Bir modül worker'ı paylaşılan bir yüzeyi
doğrudan değiştiremez; ilgili lane'in steward paketine bir istek (diff
önerisi + gerekçe) bırakır, o lane'in steward worker'ı uygular. Bu, her
somut paylaşılan dosyada **eşzamanlı yazar sıfır** garantisini korur
(mevcut "aynı dosyada paralel yazar yok" kısıtıyla birebir tutarlı), ama
artık **yüzeyler arası yapay bir sıra dayatmıyor**.

**Steward ihtiyacının azalması (owner'ın "çoğu değişiklik steward'ı
atlar" beklentisiyle tutarlı):** §16.18'deki hazırlık paketleri (modül-
sınırlı sayfa registry, modül başına ayrı route/i18n dosyası) tamamlandıktan
sonra, çoğu modül paketi **hiçbir steward lane'ine dokunmadan** tamamlanır
— yalnız gerçekten paylaşılan bir toplayıcı dosyayı (örn. yeni bir modülün
ilk kez sisteme kaydı) değiştiren nadir paketler steward lane'ine düşer.
Bugün (hazırlık paketleri öncesi) steward ihtiyacı daha yüksektir çünkü
`routes/api.php`/`WorkspaceApp.tsx`/`workspace.ts` hâlâ tek, toplu
dosyalardır (§16.1, §16.2).

### 16.7 Modül-içi sharding (aynı modülde birden fazla dikey dilim) — §16.6'nın özel hali

Bu bölüm artık §16.6'daki dependency-DAG + write-set çakışma grafiği
kuralının **modül-içi özel durumudur**, ayrı bir kural değil — aynı grafik
kuralı hem modüller arası hem modül-içi paketler için **tek tip**
uygulanır. Bir modülün (örn. `menuCatalog`) kendi içinde birden fazla
bağımsız dikey
dilimi olabilir (örn. "kategori CRUD", "ürün fiyatlandırma", "allergen
etiketleme" — kanıt: `StoreCategoryController`, `UpdateMenuItemPriceController`,
`UpdateMenuItemAllergensController` ayrı controller'lar). Kural: aynı
modül içinde birden fazla worker **yalnız dosya-kümesi kesişmeyen** dikey
dilimlerde eş zamanlı çalışabilir (örn. `StoreCategoryController.php` +
`StoreCategoryRequest.php` vs. `UpdateMenuItemPriceController.php` +
`UpdateMenuItemPriceRequest.php` — kesişmiyor). Kesişen dosya varsa (örn.
ikisi de `MenuItem.php` domain sınıfını değiştiriyorsa), bu iki dilim
**aynı pakette birleştirilir veya sıraya alınır** — asla aynı anda iki
ayrı yazar aynı dosyaya yazmaz (mevcut "no parallel writers on same
file/package" kısıtı, modül-içi sharding'de de **istisnasız** geçerli).

### 16.8 İzole branch/worktree adlandırma ve immutable base kuralı

- Adlandırma: `zabuno-<modül>-<dikey-dilim>-<paket-no>` (örn.
  `zabuno-menuCatalog-category-crud-01`), steward paketleri
  `zabuno-shared-<dosya-kısa-adı>-<paket-no>` (örn.
  `zabuno-shared-routes-01`).
- Her modül paketi kendi worktree'sinde, **tek bir immutable base commit**
  üzerinden başlar. **Düzeltme (mevcut worktree kanıtının doğru okunuşu):**
  worktree'lerin `8d39aa5`'te donmuş olması (§16.1) yalnız *ortak bir taban
  commit*i ve *durmuş bir topolojiyi* kanıtlıyor — bu disiplinin *fiilen
  çalıştığının* kanıtı değil, etkinliği bu oturumda **bilinmiyor**.
- **Git yetkisi düzeltmesi:** Paket worker'ları (test writer, implementation
  writer, reviewer) **hiçbir zaman** `rebase`, `merge`, `rollback`,
  `commit`, `push` veya promotion işlemi **çalıştırmaz**. Bunlar yalnız
  kendi atandıkları worktree'de içerik değişikliği üretir ve raporlar;
  bir çakışma/conflict tespit ederlerse bunu **kanıt olarak** (hangi
  dosya, hangi satır, hangi base commit'e göre) MASTER'a bildirirler.
  Base commit değiştiğinde (steward paketi merge olduğunda), açık modül
  paketlerinin **rebase edilmesi kararı ve işlemi Codex Desktop MASTER
  oturumuna aittir** — MASTER, kapıları (gate) geçtikten sonraki Git
  yürütücüsüdür (bkz. kök yönetişim bloğu madde 1: "Codex Desktop MASTER
  ... Git executor"). Bu rapor bu yetkiyi hiçbir şekilde worker'lara
  devretmiyor; önceki taslaktaki "worker rebase eder" ifadesi hatalıydı ve
  düzeltildi.
- Worktree affinity kuralı (mevcut CLAUDE.md/AGENTS.md kısıtı) burada da
  **istisnasız**: bir worker yalnız kendi paketine atanmış worktree'de
  yazar; modül sınırları arası "kolaylık olsun" diye worktree paylaşımı
  yapılmaz. Bu affinity kuralı da Git yürütme yetkisi vermez — yalnız
  içerik değişikliği yetkisidir.

### 16.9 Amdahl/koordinasyon analizi — dürüst sınır

100 worker, **100 bağımsız dikey dilimi aynı anda ilerletebilir** — bu
paralel kısım. Ama:
- **Her steward lane'i kendi içinde serial bir darboğazdır (§16.6a
  düzeltmesiyle: artık tek global sıra değil, yüzey başına ayrı sıra).**
  `routes/api.php` kendi `routes` lane'inde, `WorkspaceApp.tsx` kendi
  `frontend-shell` lane'inde, `workspace.ts` kendi `i18n` lane'inde,
  lockfile'lar kendi `dependency-lockfiles` lane'inde **bağımsız bağımsız**
  sıralanır — lane'ler birbirini bloklamaz, ama **her lane'in kendisi**
  tek-yazarlı bir sıradır. 100 modül paketi aynı anda **aynı lane'e**
  (örn. hepsi "yeni route ekle" isteği) düşerse, o **tek lane** toplam
  programın alt sınırını belirler — Amdahl yasasının klasik "serial kesir"
  terimi burada **somut ve ölçülebilir**: en meşgul lane'in paket başına
  ortalama süresi × o lane'e bekleyen istek sayısı. Yedi lane'e eşit
  dağılmış istekler tek bir global sıradan çok daha az darboğaz üretir.
- **Bağımlılık zincirleri (`dependsOn`) paralelliği kısıtlar.**
  `qrDestination`, `publication`'a bağımlı; `publication`, `menuCatalog`'a
  bağımlı — bu üç modülün aynı dikey dilimdeki paketleri **sırayla**
  gitmek zorunda, 100 worker bu zinciri kısaltmaz, yalnız zincirin
  **dışındaki** diğer 97 bağımsız paketi aynı anda ilerletebilir.
- **Tek bir paketin kendi 20 dakikalık checkpoint zinciri (§8.3) 100
  worker ile hızlanmaz** — bu zaten §1'in düzeltilmiş 10x/50x
  paragrafında netleştirildi. 100 worker **genişlik** (kaç bağımsız iş
  parçası aynı anda ilerliyor) kazandırır, **derinlik**
  (tek bir işin kendi içindeki adım sayısı) kazandırmaz.
- **Sonuç formülü (niteliksel, sayısal değil — ölçülmeden iddia
  edilmiyor):** toplam program süresi ≈ `max(kritik-yol bağımlılık zinciri
  süresi, en meşgul steward lane'inin kuyruk süresi, çakışma-bileşeni
  serileşme süresi, tek-worker checkpoint süresi × paket-sayısı /
  gerçek-eşzamanlı-worker-sayısı)`. Dört terimden en büyüğü baskındır;
  100 worker yalnız son terimi küçültür, ilk üçünü küçültmez.

### 16.10 100 mantıksal worker/paket ile güvenli eşzamanlı yerel concurrency arasındaki fark

**Kritik ayrım — owner'ın sorusunun iki farklı okuması:**
1. **"100 worker'ın 100 mantıksal paket/rol üstlenmesi"** (100 ayrı kapsam
   analisti + test writer + implementation writer + reviewer rolü,
   zaman içinde dağıtılmış) — bu, **program ölçeğinde bir organizasyon
   modelidir**, herhangi bir anda kaçının fiilen aynı makinede aktif
   olduğuyla ilgili değildir. Bu ölçek §16.11'deki dalga modeliyle
   yönetilir.
2. **"100 worker'ın aynı anda bu Mac'te eşzamanlı çalışması"** — bu **ayrı
   ve çok daha sıkı sınırlı bir sorudur**. Guardian admission (CLAUDE.md/
   AGENTS.md yönetişim bloğu, madde 1: "masterIsRepoReadOnly",
   admission-gated worker creation) bu Mac'te **tek bir admission
   otoritesi** tanımlıyor; bu raporun hiçbir önerisi bunu gevşetmiyor.
   Yerel eşzamanlı worker sayısı, mevcut Guardian/Pane kapasitesiyle
   sınırlıdır (§7'de "Guardian yalnız 1 worker öneriyor" iddiası
   doğrulanamadı ama mimari olarak admission'ın tek otorite olduğu
   yönetişim bloğunda **kesin**).

**Nasıl uzlaştırılır — dalgalar halinde modül-worktree/makine dağıtımı:**
100 mantıksal paket, **modül worktree'leri veya ayrı makineler/Pane
oturumları arasında dalgalar halinde** dağıtılır: her dalga, o an
Guardian'ın admission verdiği kadar worker'ı, farklı modül worktree'lerine
(§16.8 adlandırmasıyla) atar; bir dalga tamamlanıp checkpoint/handoff'lar
kapandıkça bir sonraki dalga başlar. **Mevcut Mac admission'ı her zaman
otoritedir** — 100 sayısı bir **tavan/tasarım kapasitesi**dir, bir anlık
eşzamanlılık taahhüdü değil. Ayrı makinelere dağıtım (ör. bulut ajanları)
mümkündür ama her makine/oturum kendi Guardian-benzeri admission
kontrolüne tabi olmalı — hiçbir dağıtım "admission'ı atla" anlamına gelmez.

Güvenlik sınırları (ayrı test writer/implementation writer/salt-okunur
reviewer, tek aktif yazar, worktree affinity, 20 dakikalık checkpoint
kadansı) **dalga sayısından, worker sayısından veya makine sayısından
bağımsız olarak istisnasız** korunur — 100-worker ölçeği bu sınırları
gevşetmenin gerekçesi değildir, tam tersine §16.6/§16.6a/§16.7 bu
sınırları modül ölçeğine **açıkça genişletir**.

**Netleştirme (owner talebiyle eklendi):** "100 worker" kapasitesi, bir
yazılım şirketinin 100 mühendisi bağımsız modül/paket/ekip üzerinde aynı
anda çalıştırması gibi **program-ölçeğinde bir genişlik (breadth)**
ifade eder — bunun **hepsinin bu Mac'te fiilen aynı anda çalışması**
zorunlu **değildir** ve bu rapor bunu iddia etmiyor. Eğer altyapı (çoklu
makine/oturum) ve her makinenin kendi admission'ı izin verirse, ramp'in
(§16.17) sonunda **gerçek eşzamanlı genişlik mimari olarak mümkündür** —
ama bu, "100'ü bugün bu Mac'te aç" demek değil, "doğru guardrail'ler ve
admission altyapısı kurulduktan sonra, dağıtılmış biçimde ulaşılabilir bir
tavan" demektir. Guardian/admission her aşamada ve her makinede **otorite
olarak kalır** — bu netleştirme admission'ı hiçbir şekilde gevşetmiyor.

### 16.11 Event/API/DB migration expand-contract deseni

Modüller arası veri/şema geçişleri (örn. `qrDestination`'ın
`dining_table_id` alanını `qr_codes`'a eklemesi — kanıt: migration
`..._000007_add_dining_table_id_to_qr_codes.php`) **expand-contract**
ile yapılır: (1) expand — yeni alan/kolon nullable eklenir, eski davranış
bozulmaz; (2) her iki modülün worker'ları bağımsız olarak yeni alana geçer
(feature flag arkasında, §16.12); (3) contract — tüm tüketiciler geçtikten
sonra eski alan/davranış ayrı bir sonraki pakette kaldırılır. Bu, iki
modülün paralel worker'larının aynı migration/tabloyu **aynı anda kırıcı
şekilde** değiştirmesini engeller.

### 16.12 Feature flag'ler

Modül-arası kırıcı değişiklikler ve steward lane'leri üzerinden geçen
paylaşılan dosya değişiklikleri (§16.6a) varsayılan olarak **kapalı** bir flag arkasına
alınır; flag'i açan paket ayrı, küçük bir "activation" paketidir. Bu, bir
modülün contract-freeze (§16.5) ihlaline düşmeden kendi hızında ilerlemesini
sağlar — bağımlı modülün worker'ı henüz hazır değilse flag kapalı kalır.

### 16.13 Deterministik kontrat testleri ve etkilenen-test seçimi

- Her Port arayüzü için, o arayüzü uygulayan her Infrastructure adaptörünün
  geçmesi gereken **paylaşılan bir kontrat test seti** tanımlanır (örn.
  `TenantIsolationSuiteRunnerPort`'un her implementasyonu aynı kontrat
  testinden geçer — kanıt: `SymfonyTenantIsolationSuiteRunner.php` zaten
  bu Port'un tek implementasyonu, ileride ikinci implementasyon gelirse
  kontrat testi paylaşılır).
- Etkilenen-test seçimi modül sınırına göre yapılır: bir modül paketi yalnız
  `tests/Feature/{Modül}/**` + `tests/Unit/{Modül}/**` (varsa) + kendi
  Port'una bağımlı diğer modüllerin kontrat testlerini çalıştırır — repo
  genelindeki 83 PHP + 107 FE test dosyasının tamamını her modül paketinde
  çalıştırmaz (bu zaten §11'deki "değişen-test seçimi" ilkesinin modül
  ölçeğine uygulanmış hali).

### 16.14 Modül CI → merge-queue entegrasyon CI → topolojik merge treni

1. **Modül CI (hafif):** her modül paketi kendi modülünün etkilenen
   testlerini + kontrat testlerini çalıştırır (§16.13), `composer validate`
   (strict olmayan, §10) + Pint/lint. Full suite **değil**.
2. **Merge-queue entegrasyon CI (ağır, tam):** steward lanesinden geçen her
   birleştirme, `dependsOn` DAG'ına göre **topolojik sırayla** bir merge
   treninden geçer — önce bağımlılığı olmayan modüller, sonra onlara bağımlı
   olanlar. Bu aşamada tam suite (mevcut `.github/workflows/ci.yml` adımları)
   çalışır, ama **paket başına değil, tren başına bir kez** — §8.2'deki
   "CI full QA yalnız bir kere" ilkesinin çok-modül karşılığı.
3. **Conflict/rebase politikası — düzeltilmiş Git yetkisi:** bir modül
   paketi merge trenine girerken base'i eskimişse (steward lanesi
   ilerlemişse), **paket worker'ı rebase işlemini kendisi çalıştırmaz.**
   Worker yalnız conflict kanıtını (hangi dosya/satır, hangi base'e göre
   diff aldığı) MASTER'a rapor eder; **rebase'i Codex Desktop MASTER
   yürütür** (Git executor yetkisi, kök yönetişim bloğu madde 1). Rebase
   sonrası reviewer **taze immutable snapshot'ı** yeniden değerlendirir
   (mevcut kural, §9'daki reviewer disiplinine birebir uyumlu — yeniden
   test yazmaz, yalnız hash+çıktı doğrular).
4. **Modül başına rollback — düzeltilmiş yetki ve mekanizma:** bir
   modülün merge treninde başarısız olması, yalnız o modülün treni
   geciktirir; bağımlı olmayan diğer modüller trenlerine devam eder.
   **Rollback bir worker eylemi değildir**; bir `git reset --hard` de
   değildir. Rollback, **MASTER'ın yürüttüğü bir revert** (o modülün son
   bilinen yeşil commit'ine karşı `git revert`-tipi, geçmişi silmeyen bir
   geri-alma) olarak tanımlanır — diğer modüllerin geçmişini veya
   ilerlemesini bloklamaz (§16.9'daki bağımlılık zinciri dışındakiler
   etkilenmez). Paket worker'larının hiçbiri commit/push/promotion
   çalıştırmaz; bunların tümü MASTER'ın onaylı gate'ler sonrası yürüttüğü
   işlemlerdir.

### 16.15 Kanıt yeniden kullanımı, entegrasyon sıklığı, hata izolasyonu

- Değişmeyen modül snapshot'ında kanıt yeniden kullanımı §9'daki
  `evidence_reuse_check` ile aynı mekanizma, yalnız anahtar artık
  `moduleId + contentHash` ikilisi.
- Entegrasyon sıklığı: her dalga (§16.10) sonunda bir merge-treni turu;
  günde birden fazla dalga olabilir ama en meşgul steward lane'inin
  kapasitesi (§16.6a, yüzey başına ayrı sıra) o lane'e düşen paketler için
  pratik üst sınırı belirler — diğer lane'ler ve çakışmasız modül
  paketleri bundan etkilenmez.
- Hata izolasyonu: bir modülün production regresyonu, `dependsOn`
  DAG'ında ona bağımlı modülleri etkiler (feature flag ile izole
  edilmemişse); bağımsız modüller etkilenmez. Bu, `qrDestination`'ın bir
  regresyonunun `menuCatalog`'u etkilememesi ama `publication`'ı
  etkileyebilmesi anlamına gelir (`qrDestination dependsOn publication`).
- **100x full-suite veya browser tekrarı yok:** her modül paketi kendi
  full-QA'sını (§8.2, bir kere) ve CI full QA'sını **merge treni
  düzeyinde paylaşır** (madde 2); 100 paralel paket olsa bile toplamda
  100 ayrı full-suite koşusu **olmaz** — yalnız modül-CI'lar (hafif) +
  tren başına bir tam CI. Browser smoke da aynı şekilde yalnız
  kullanıcıya-görünen dikey dilim kapanışında, modül başına değil,
  **etkilenen kullanıcı yolculuğu başına** bir kez.

### 16.16 Somut 100-worker tahsis örneği — düzeltilmiş matematik

**Önceki taslaktaki hata:** "her paket 3 worker (test writer + implementation
writer + reviewer) eşzamanlı çalıştırır" varsayımı **yanlıştı**. Bu üç
rol, mevcut yönetişimde (bkz. §8.2, "RED bir kere çalışır; implementation
aynı hedefli seti GREEN için bir kere çalıştırır; reviewer suite'i tekrar
etmez") **sıralı paket fazlarıdır** — test writer bitirmeden implementation
writer başlamaz, o bitirmeden reviewer başlamaz. Bu yüzden bir paketin
**herhangi bir anda tam olarak bir aktif yazan**ı vardır (test writer VEYA
implementation writer, ikisi asla aynı anda değil); reviewer ise salt-
okunur olduğu için hiçbir zaman "yazan" sayılmaz, yalnız **aktif okuyucu/
analist**tir. Aşağıdaki tablo bu ayrımı **kişi kimliği**, **pipeline
fazı**, **aktif yazan**, **aktif salt-okunur analist/reviewer**, **aktif
paket** ve **kuyruktaki worker** olarak ayrıştırıyor.

**Kavramlar:**
- **Kişi kimliği (personnel identity):** 100 kişilik mantıksal havuzdaki
  ayrı bir worker kimliği — bir program boyunca farklı paketlerde farklı
  rollerde görev alabilir, tıpkı bir yazılım şirketindeki 100 mühendis gibi.
- **Pipeline fazı:** bir paketin o anki durumu — kapsam/RED, GREEN/
  implementasyon, review, handoff/checkpoint.
- **Aktif yazan (active writer):** o anda bir dosyaya içerik yazan kimlik.
  **Bir paket için her zaman 0 veya 1** (asla 2+, asla eşzamanlı).
- **Aktif salt-okunur analist/reviewer:** o anda bir paketi inceleyen ama
  yazmayan kimlik.
- **Aktif paket:** o anda bir fazda ilerleyen paket (bir aktif yazanı veya
  bir aktif reviewer'ı olan).
- **Kuyruktaki worker:** bir sonraki pakete/faza atanmayı bekleyen kimlik
  (kapsam analizi hazırlığı, önceki paket kapanınca devreye girecek).
- **Fiili eşzamanlı süreç (gerçek concurrency):** bu makinede (veya
  dağıtılmış oturumlarda) aynı saniyede fiilen çalışan process/agent
  sayısı — Guardian admission bunu sınırlar, kimlik sayısını değil.

**Düzeltme (owner hedefi — hazırlık sonrası gerçekten ulaşılabilir 100):**
Bu bölümün önceki sürümü, dağıtılmış altyapı/admission mevcutken bile
100'ü yalnız bir "tasarım tavanı" olarak sunuyordu ve fiilen 14 aktif
paketlik bir örnek veriyordu. Owner'ın hedefi daha net: **hazırlık
tamamlandığında (§16.18) ve dağıtılmış altyapı/admission izin verdiğinde,
100 worker gerçekten 100 farklı paket/pipeline-fazında eşzamanlı aktif
olabilmelidir** — bu bölüm artık böyle bir dağılımı **tam sayılarla**
veriyor, "belki 14-28" gibi belirsiz bir aralıkla değil. Aşağıdaki dağılım
§16.6'daki dependency-DAG + write-set çakışma grafiği kuralına göre
**çakışmasız** kurgulanmıştır; toplam kişi kimliği tam olarak 100'dür,
aktif paket sayısı ile aktif rol sayısı tutarlıdır ve hiçbir pakette
eşzamanlı test+implementasyon+review yoktur (her paketin tam olarak bir
aktif rolü vardır, o an hangi fazdaysa).

**Düzeltme (owner talebiyle netleştirildi — hedef steady-state tam olarak
100 AKTİF worker olmalı, 75 aktif + 25 kuyruk değil):** Önceki sürüm 75
aktif role + 25 kuyrukta bekleyen kimlik veriyordu; bu, owner'ın istediği
"100 worker'ın 100 farklı paket/iş biriminde eşzamanlı aktif olması"
hedefini tam karşılamıyordu. Aşağıdaki tablo **tam olarak 100 aktif
worker'ı, tam olarak 100 farklı aktif paket/iş biriminde** dağıtıyor —
kuyruk operasyonel olarak var olabilir (bir sonraki dalga için hazırlanan
kimlikler), ama bu **100-aktif steady-state örneğinin dışındadır**, ayrı
bir kavramdır.

**100 kişilik havuzun tam dağılımı — hedef steady-state, dağıtılmış
altyapı+admission sağlandığında (tek Mac değil, §16.10):**

| Rol kategorisi | Aktif kişi sayısı | Aynı anda kaç farklı pakette/iş biriminde | Not |
|---|---|---|---|
| Aktif kapsam analisti (yeni paket başlatan, RED öncesi) | 10 | 10 farklı paket, henüz test/impl/review fazına girmemiş | Her biri ayrı bir gelecek pakete kapsam/risk sınıfı atıyor |
| Aktif test writer (RED fazında) | 25 | 25 farklı paket, bu fazda | Her paketin bu anda **tek** aktif yazanı |
| Aktif implementation writer (GREEN fazında) | 35 | 35 farklı paket, bu fazda (test writer'ı bitmiş, kendisi devrede) | Bu 35, RED fazındaki 25 paketten **farklı**, GREEN fazına geçmiş 35 ayrı pakettir |
| Aktif salt-okunur reviewer (review fazında) | 20 | 20 farklı paket, bu fazda | Yazan değil — yalnız hash+çıktı doğruluyor, gerekirse adversarial kontrol önerisi bırakıyor (§9) |
| Aktif yüzeye-özgü steward yazarı (§16.6a: routes, frontend-shell, i18n, lockfile, migrations, test-config, CI — 7 lane) | 7 | 7 farklı lane/iş birimi, her biri bağımsız | Lane'ler birbirini bloklamaz (§16.6a düzeltmesi); her lane'in kendi tek yazarı |
| Aktif salt-okunur merge-treni/entegrasyon analisti (§16.14) | 3 | 3 farklı modül-grubu treni, paralel (bağımlılık zincirinde birbirine bağlı olmayanlar) | Yazan değil — tren başına full CI sonucunu doğruluyor (§16.15) |
| **TOPLAM AKTİF** | **100** | **100 farklı aktif paket/iş birimi** | 10+25+35+20+7+3 = **100** |

**Kuyruk, bu steady-state örneğinin dışındadır:** Operasyonel olarak, bir
sonraki dalga için hazırlanan ek kimlikler (bir sonraki kapsam analizi,
bir sonraki modül-içi dilim) **ayrıca var olabilir** — ama bunlar yukarıdaki
100-aktif tabloya dahil değildir; bu tablo *tam olarak 100 aktif worker'ın
100 farklı iş biriminde eşzamanlı çalıştığı* an'ı tarif ediyor, kuyruk
101., 102. vb. kimlikler olarak bu havuzun **dışında** sayılır.

**Tutarlılık kontrolü (owner'ın istediği "tam olarak 100 aktif worker, tam
olarak 100 aktif paket" şartı):**
- Toplam aktif kişi: 10+25+35+20+7+3 = **100**. ✓
- Toplam aktif paket/iş birimi: 10 (kapsam) + 25 (RED) + 35 (GREEN) + 20
  (review) = **90 farklı ürün/test paketi**, artı 7 steward lane iş birimi
  + 3 merge-treni entegrasyon birimi = **100 aktif iş birimi toplamda**.
  Bu, 100 aktif worker'la **birebir tutarlıdır** — her aktif iş biriminin
  tam olarak bir aktif rolü var, hiçbir iş birimi rolsüz veya çift-rollü
  değil. ✓
- **Hiçbir pakette aynı anda iki faz yok:** RED fazındaki 25 paket ile
  GREEN fazındaki 35 paket **farklı paketlerdir** (bir paket aynı anda hem
  RED hem GREEN fazında olamaz, §8.2'nin sıralı faz kuralı); review'daki
  20 paket de RED/GREEN fazındaki 60 paketten ayrıdır; kapsam analizindeki
  10 paket henüz hiçbir yazma fazına girmemiştir. Bu ayrım, kişi kimliği
  ile paket-fazı arasındaki bire-bir eşlemeyi **matematiksel olarak**
  garanti eder — hiçbir paket iki aktif rolü aynı anda taşımaz.
- Aynı modülün birden fazla paketi (örn. `menuCatalog`'un 2 dikey dilimi)
  yukarıdaki 90 paket içinde **farklı satırlar** olarak sayılabilir —
  §16.6'nın çakışma-grafiği kuralı gereği bu, modül sınırının artık bir
  serileştirme gerekçesi olmadığının doğrudan sonucudur.

**Bu Mac'teki gerçek durumla farkı (§16.10 ile tutarlı, tekrar
vurgulanıyor):** Yukarıdaki 100'lük dağılım **hedef mimari**dir — tek bu
Mac'te değil, dağıtılmış altyapı ve her düğümün kendi admission'ı
sağlandığında ulaşılabilir bir tavan. **Bu Mac'te bugün** fiili eşzamanlı
süreç sayısı çok daha düşüktür ve **Guardian admission'a tabidir** (§7'de
doğrulanamayan "yalnız 1 worker öneriyor" iddiası dahil, gerçek sayı
bilinmiyor) — admission her zaman otoritedir, yukarıdaki 100 bir tavanı
geçersiz kılmaz, admission izin verdiği kadarı fiilen çalışır.

### 16.17 Güvenli ramp: 4 → 12 → 25 → 50 → 100 — kesin tanım: eşzamanlı admit edilen worker tavanı

**Kesin tanım (owner talebiyle netleştirildi):** Ramp'teki her aşama
etiketi (4, 12, 25, 50, 100), **tüm rollerde (kapsam analisti + test
writer + implementation writer + reviewer + steward/entegrasyon analisti)
birlikte, o anda eşzamanlı olarak admit edilmiş (Guardian/admission
tarafından fiilen çalışır durumda onaylanmış) worker/süreç sayısının
tavanıdır** — başka bir şey değil. **Aktif paket sayısı bu tavanın
"en fazla" değeridir, asla üstüne çıkmaz**, ve gerçek değeri o andaki **faz
karışımına (phase mix)** bağlıdır: bir pakette aynı anda en fazla 1 aktif
rol olduğundan (§16.6, §16.16), N worker admit edilmişse aktif paket
sayısı da en fazla N'dir, ama faz dağılımına göre daha az da olabilir
(örn. birden fazla worker aynı paketin ardışık fazlarında sırayla
çalışıyorsa, ya da bazı worker'lar steward/entegrasyon rolündeyse paket
başına birden fazla worker aynı paketi farklı fazlarda zaman içinde
kullanır, ama **hiçbir anda ikisi aynı pakette eşzamanlı yazan olarak
görünmez**).

| Aşama | Eşzamanlı admit edilen worker tavanı (tüm roller toplamı) | Aktif paket sayısı (≤ tavan, faz karışımına bağlı) | Kapsam | Promosyon metriği (bir sonrakine geçiş şartı) | Rollback eşiği |
|---|---|---|---|---|---|
| **4** | 4 | ≤4 | Tek dalga, steward lane'leri manuel gözetimli | Yeterli çakışmasız backlog (en az 8 hazır, sıraya girmemiş paket manifesti) + 2 ardışık dalga: sıfır yanlış-serileştirilmiş çakışma (write-set kesişimi kaçırılmadı), sıfır reviewer test-yazma ihlali, checkpoint p95 ≤20dk, merge metrikleri stabil | Herhangi bir çakışma/ihlal → 4'te dondur |
| **12** | 12 | ≤12 | Steward lane'leri otomatikleşir (`speed-gate` per-surface kuyruklar, §16.6a) | Yeterli çakışmasız backlog (en az 20 hazır paket) + 3 ardışık dalga: her steward lane'inin kuyruk bekleme p95 ≤ paket süresinin %20'si, kaçan hata artışı yok, merge metrikleri (§12a) stabil | Bir lane'in kuyruğu p95 %20'yi 2 dalga üst üste aşarsa → 12'de dondur |
| **25** | 25 | ≤25 | Dependency-DAG + write-set çakışma grafiği zamanlayıcısı canlı (§16.6), merge-queue topolojik tren (§16.14) devrede | Yeterli çakışmasız backlog (en az 40 hazır paket) + 3 ardışık tren: tüm modüller yeşil, hiçbir modül rollback tetiklemedi, çakışma grafiğinin yanlış-negatif oranı (kaçırılan gerçek çakışma) sıfır | Bir highRisk modülünde production regresyon veya kaçırılan bir çakışma → 12'ye geri dön |
| **50** | 50 | ≤50 | Modül-içi + modüller-arası eşzamanlı paketler (§16.6/§16.7) tam ölçekte aktif | Yeterli çakışmasız backlog (en az 75 hazır paket) + 5 ardışık dalga: paketler-arası dosya çakışması sıfır, rework oranı (§12a) baseline'ın altında | Kaçırılan bir çakışma tespit edilirse → 25'e geri dön |
| **100** | 100 (§16.16'daki hedef dağılım) | 100 (§16.16'daki 100-aktif steady-state örneği: 10+25+35+20+7+3 = 100 aktif paket/iş birimi; kuyruk operasyonel olarak ayrıca var olabilir ama bu tavana dahil değil) | Çoklu makine/oturum dağıtımı (gerekiyorsa), tam ramp — **bu Mac'te tek başına 100'e ulaşması gerekmez** (§16.10); dağıtılmış ortamda her düğüm kendi admission'ına tabi | 10 ardışık dalga: flow efficiency (§12a) düşmüyor, escaped defect oranı baseline'ın altında, tüm steward lane'leri stabil, çakışma grafiği yanlış-negatif oranı sıfır | Herhangi bir aşamadaki eşiğin 2 dalga üst üste ihlali → bir önceki aşamaya geri dön |

Her aşama geçişi, önceki aşamanın metriklerinin **gözlemlenmiş** olmasını
VE **yeterli çakışmasız backlog**'un (bir sonraki aşamanın tavanını
doldurabilecek kadar hazır, birbiriyle çakışmayan paket manifestinin)
var olmasını gerektirir (§12a'daki metrikler olmadan hiçbir aşama geçişi
onaylanmamalı — bu, §3'teki "hipotez, gözlem değil" disiplininin ramp'e
uygulanmış hali). **Guardian, her aşamada, her makinede admission
otoritesidir** — ramp tablosundaki sayılar bir hedef/tavan, admission'ın
fiilen verdiği sayının **üstüne çıkılamaz**.

### 16.18 Zabuno'nun bugün 100-worker paralelliğini engelleyen somut boşluklar + minimal hazırlık paketleri

| Boşluk (repo kanıtı) | Neden engelliyor | Minimal hazırlık paketi (öneri, bu görevde yazılmadı) |
|---|---|---|
| `WorkspaceApp.tsx` tek dosyada 10 modül sayfası import ediyor | Her yeni/değişen sayfa bu dosyaya dokunur → steward darboğazı büyür | Modül-sınırlı sayfa registry (her modül kendi route/sayfa kaydını kendi dizininde tanımlar, `WorkspaceApp` bunları merkezi olmayan bir mekanizmayla toplar) |
| `resources/js/i18n/workspace.ts` 503 satır, çok modüllü | Metin anahtarı eklemek steward darboğazı üretir | Modül başına ayrı i18n dosyası + merkezi bir birleştirme adımı |
| `routes/api.php` tek dosya | Route eklemek steward darboğazı üretir | Modül başına ayrı route dosyası (`routes/{modül}.php`) + tek bir `require` toplayıcı |
| Tek `phpunit.xml` suite | Modül bazlı hafif CI (§16.14 madde 1) yapılandırılamıyor | Modül başına test suite tanımı veya path-bazlı gruplama |
| Migration bağımlılık grafiği dokümante değil | Paralel migration yazımı güvenle sıralanamıyor | Migration manifest (hangi migration hangi modüle ait, hangi tabloya bağımlı) |
| CI her zaman RED (`composer validate --strict`, §10) | Merge-queue entegrasyon CI'sinin (§16.14 madde 2) bir temeli yok — zaten kırık bir CI üzerine tren kurulamaz | §10'daki CI onarım paketi **önkoşuldur**, 100-worker çerçevesinden önce gelir |
| `Domain/Billing` eksikliği (§16.3 not) | Modül manifesti tutarsız — Billing'in domain sahipliği belirsiz | Küçük bir keşif paketi: Billing domain nesnelerinin nerede modellendiğini doğrula, manifest'i düzelt |
| Per-surface steward lane mekanizması repoda yok | §16.6a'nın yüzey-başına tek-yazar garantisi bugün **elle disipline dayalı**, deterministik değil; ayrıca dependency-DAG + write-set çakışma grafiği zamanlayıcısı (§16.6) da bir yazılım/`speed-gate` uzantısı olarak mevcut değil | `speed-gate`'e (a) 7 lane'lik per-surface kuyruk desteği ve (b) write-set/kontrat çakışma grafiği hesaplayıcısı ekleme (§9'daki şemanın uzantısı) |

**Sonuç:** Yukarıdaki 8 boşluktan **CI onarımı (§10) ve steward-lanesi
mekanizması en kritik önkoşuldur** — bunlar olmadan 12'den yukarı hiçbir
ramp aşaması güvenle denenmemeli. Diğerleri (i18n/route/sayfa registry
ayrımı) 25–50 aşamasından önce tamamlanmalı; migration manifesti ve
Billing tutarsızlığı 12–25 aralığında çözülebilir.

## 17. Nihai karar ve artık riskler

**Karar:** Codex'in önerdiği "3–8 test + ≤20 dk checkpoint" iskeleti
benimseniyor ve bu raporla üç eksik parçayla tamamlanıyor: (1) paket
**alt** sınırı ve deterministik batch kuralı, (2) CI'nin yapısal RED'ini
kapatan somut, güvenliği zayıflatmayan bir onarım (§10 Seçenek A), (3)
`speed-gate`'in fail-open/fail-closed sınırının net tanımı — güvenlik
sınırları asla fail-open değil.

**Artık riskler:**
- CI onarımı yapılmadan test bütçesi daraltılırsa, azalan yerel-tekrar
  güveni CI tarafından telafi edilmez — bu yüzden §10 ve §8 birlikte
  uygulanmalı, ayrı ayrı değil.
- Mutation testing/kaçan-hata telemetrisi kurulana kadar test azaltımının
  gerçek bug-killing etkisi **varsayımsal** kalır; 30 günlük pencerede bu
  ölçülmeden `blocking` moda geçilmemeli.
- Pane/Guardian tarafındaki iddialar (530 panel, 1 worker önerisi) bu
  raporda doğrulanamadı; rollout kararı bunlara **dayandırılmamalı**.

**§16 ile eklenen karar (bu geçişte kanıtla düzeltildi):** Owner'ın örtük
varsayımı — modüler monolith'in paralel yazılabileceği — **doğrulandı,
ama koşullu ve önceki taslaktan daha temkinli**: repo kanıtı (§16.1)
backend'in `Application`/`Infrastructure` katmanlarında 12 bounded-
context'e büyük ölçüde port disiplinli ayrıldığını gösteriyor, ama
**mükemmel yalıtılmış değil** — 3 gerçek cross-context import doğrulandı
(Authorization→Tenancy, Team→Identity, MenuCatalog→Taxonomy) ve
`Domain/Billing` üç katmandan yalnız birinde eksik. Bu **fiilen
ayrılabilir ama gözenekli** bir temel — "sıfır coupling" değil. Ama frontend shell
(`WorkspaceApp.tsx`), i18n havuzu (`workspace.ts`), tek route dosyası,
tek lockfile'lar ve CI'nin yapısal RED'i (§10) **bugün** 100-worker
ölçekli paralelliği güvenle desteklemiyor — bunlar icat edilmiş
varsayımlar değil, ölçülmüş darboğazlar (§16.18). Karar: **100-worker
çerçevesi kabul edilebilir bir hedef mimaridir; serileştirme birimi artık
"modül" değil "gerçek çakışma bileşeni"dir (§16.6, owner'ın düzeltmesiyle),
ama CI onarımı ve per-surface steward lane + çakışma grafiği
mekanizmaları olmadan hiçbir ramp aşaması 12'nin üzerine çıkmamalı**
(§16.17). Amdahl analizi (§16.9) net: 100 worker genişlik
kazandırır (bağımsız modüllerin sayısı), tek bir paketin derinliğini
(kendi checkpoint zincirini) hızlandırmaz — bu ikisini karıştırmak
yanıltıcı bir "100x" vaadine yol açar, bu rapor böyle bir vaat vermiyor.

**§16 ile eklenen artık riskler:**
- Steward-lanesi (§16.6a) elle disipline dayalıyken (mekanizma repoda
  yok), 12'nin üzerindeki hiçbir ramp aşaması güvenle denenmemeli —
  deterministik kuyruk garantisi olmadan "tek yazan" kısıtı fiilen
  denetlenemez.
- Modül-arası bağımlılık DAG'ı yalnız kısmen tarandı — 3 gerçek cross-
  context import doğrulandı (§16.1) ama tam bir taraması yapılmadı;
  bulunanların "kasıtlı yayınlanmış kontrat" mı "tesadüfi coupling, önce
  temizlenmeli" mi olduğu **doğrulanmadı**. Tam taraması ve sınıflandırması
  yapılmadan `dependsOn` manifestine (§16.3, `inferred` etiketli) güvenerek
  merge-queue sıralaması (§16.14) kurulmamalı.
- `Domain/Billing` tutarsızlığı (§16.3) çözülmeden Billing modülünün
  sahiplik sınırları kesin değildir; bu modülü içeren paralel paketler
  bu belirsizlik giderilene kadar highRisk disiplininde tutulmalı.

---

CLAUDE_SPEEDER_REPORT_100_WORKER_FINAL
Source-Type: independent-claude-report
Original-Source-SHA-256: 7075153d46dd3d65bea3554202871a661ebd8aef89ab6884de8470c79700ce6c
