# AGENTS.md — bu repo (zabuno/zabuno) çalışma kuralları

Bu dosya bu depoda (`zabuno/zabuno` — bu külliyatın public GitHub kökü)
çalışan tüm ajanlar (insan veya AI) için geçerlidir. Kök dizindeki genel
Codex/Claude yönlendirme talimatları bu dosyanın **üzerinde** kalır; çelişki
halinde kök talimatlar kazanır.

## 1. Bu depo bir planlama paketi + başlayan bir Stage 1 runtime foundation'dır

- Bu depo artık iki katmanlıdır: (a) `docs/`, `modules/`, `skills/`,
  `templates/` altındaki plan korpusu — stage dokümanları ve bir modülün
  henüz implement edilmemiş geri kalanı PLANNING ONLY etiketini taşımaya
  devam eder, kendi stage'i/modülü uçtan uca implement edilmeden bu etiket
  kaldırılmaz; yalnız açıkça kanıtla desteklenmiş bounded runtime dilimleri
  (örn. CORE-01/CORE-02'nin bu belgede tanımlı bounded alt dilimleri) kendi
  scoped durumunu (local-candidate-targeted-green vb.) taşıyabilir — bu tek
  başına ilgili modülün veya stage'in bütününü terfi ettirmez; (b) S1-WP01A
  foundation iskeleti (`app/`, `config/`,
  `routes/`, `resources/`, `tests/`, `composer.json`/`package.json` ve
  kilit dosyaları, `.github/workflows/ci.yml`, `security/`) — bu, kurulu
  bağımlılıklar ve hedefli kontrollerle doğrulanmış bir `/up` health route'u
  + foundation-status ekranı taşır (`docs/26` S1-WP01). Bu paket
  **implementation-in-progress**tir: test handoff tamamdır ve
  FULL_QA_LOCAL_1 bir kez çalıştı (8/10; Pint hedefli düzeltme sonrası
  mevcut snapshot'ta GREEN), ikinci tam QA bütçesi yalnız sonraki CI/full
  QA koşusu için rezervedir (ikinci yerel tam koşu planlanmaz). İki
  bağımsız review de INDEPENDENT_REVIEW_RED sonucu verdi; ikincisi P2
  kapanışlarını GREEN doğruladı ve üçüncü bir blocker bulmadı — yayın,
  hâlâ açık iki P1 owner-kararı blocker'ı (composer license metadata +
  AGENTS.md/docs/31 public-governance çelişkisi) kapanana kadar
  "implemented"/"complete" olarak sunulmaz (bkz. `docs/27` §6).
  Bu ayrım stage dokümanlarının ve bir modülün implement edilmemiş geri
  kalanının kendi "PLANNING ONLY / implement edilmedi" durumunu
  **değiştirmez** — yalnız CORE-05 registry bootstrap, env/config
  katmanlama, temel CI ve OWASP ASVS baseline'ı için implementation-in-
  progress bir durum vardır. S1-WP02'nin
  iki bounded alt dilimi de local/hedefli-test seviyesinde candidate'tır:
  S1-WP02A (CORE-01 kimlik/oturum, docs/33) ve S1-WP02B (bounded CORE-02
  tenancy baseline: workspace create+owner-membership, üyelik-scope'lu liste,
  current/switch context, `docs/34` §13a). Geri kalan ürün/iş
  kabiliyeti (menü, QR, ödeme, CORE-02 remainder, CORE-03, CORE-06 vb.)
  hâlâ **yoktur** (`docs/17`
  §4 sayaç kuralı, `README.md` §İlerleme).
- Bu depo (`zabuno/zabuno`) **hiçbir `old/` arşiv dizini içermez** — güncel
  kökte böyle bir dizin yoktur. Bu külliyatın süzüldüğü tarihsel legacy
  QR-menü projesi/denemesi tamamen **bu deponun dışında**, ayrı bir dış
  arşivde tutulur; hiçbir ajan bu depodan o dış arşive yazmaz, onu bu depoya
  taşımaz veya onun Git geçmişini bu depoya dahil etmez (bkz. §Workspace/repo
  preflight). Arşivin daha önceki, artık geçerli olmayan bir çalışma
  düzenindeki konumu yalnız `docs/00-PROVENANCE-ARCHIVE.md`'nin tarihsel
  kaydında yaşar (bkz. o dosyanın güncel-topoloji notu); oradan yalnız ürün
  felsefesi/journey/iş kuralı/kapsam dersleri süzülüp yeni dille yazılmıştır.
  Eski teknoloji seçimi (Django, FastAPI, MVVM, Filament, Astro vb.) yeni
  karar gibi sunulamaz.

## 2. Tek kanonik sahip, projeksiyon yaklaşımı

- Bir bilgi yalnız bir dosyada "sahiplenilir"; başka dosyalar ona bağlantı verir
  (göreli bir Markdown linkiyle — örn. `metin -> relative-path.md`, gerçek bir
  link hedefi değil, yalnız biçim anlatımı), tekrar etmez. Örn: modül matrisi
  `docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md`'de sahiplenilir; başka dosyalar oraya
  link verir.
- Yeni kanonik belge gerekiyorsa oluşturulabilir, ancak önce mevcut 35 dosyadan
  birinin genişletilip genişletilemeyeceği değerlendirilir.

## 3. Faz disiplini

- Sekiz aşamalı sıra (`docs/17-WATERFALL-LIFECYCLE-MASTER.md`) değişmez: faz atlama,
  takvimle otomatik terfi, kanıtsız "tamamlandı" iddiası yasaktır.
- Enterprise sınıfı **yönetişim** (waterfall disiplini, ADR, izlenebilirlik) gün 1'den
  itibaren geçerlidir; Stage 6 "Enterprise Level" **ürün kabiliyeti** ile karıştırılmaz.
- İlerleme sayacı sabit paydalı (`X/8`) ve tek yönlüdür. Kapsam değişirse yeni
  adlandırılmış plan/versiyon açılır; eski sayaç geriye yazılmaz.

## 4. Kanıt disiplini

- "Vibe says done" kabul edilmez. Her iddia (mimari karar, teknoloji seçimi, aşama
  çıkışı) ya birincil kaynağa (`docs/28-SOURCE-REGISTER.md`) ya da açık bir
  varsayım/karar kaydına (`docs/16-GAP-UNKNOWN-UNKNOWNS.md`) bağlanır.
- Teknoloji "kanıtlanmış / koşullu / deneysel" sınıflarından biriyle etiketlenir.
  Sınıf yükseltmek için gerçek kanıt (resmi doküman, kendi spike sonucu) gerekir.

## 5. Yazım dili ve stil

- Belgeler Türkçe yazılır; ürünün varsayılan UI dili İngilizcedir (bu bir çelişki
  değildir — plan dili ile ürün dili ayrıdır).
- Az ama yoğun: gezilebilir, çapraz bağlantılı, tekrarsız. Placeholder/boş bölüm
  yasak — bir bölüm doldurulamıyorsa açıkça "bilinmiyor / karar gerekiyor" yazılır
  ve `docs/16-GAP-UNKNOWN-UNKNOWNS.md`'ye bir kayıt eklenir.

## 6. Git disiplini

- Claude yazarı ve ondan bağımsız reviewer, bu paket kapsamında **sıfır** Git
  mutasyonu yapar: `git add` / `git commit` / `git push` / `git merge` /
  `git init` / `git remote` hiçbiri bu paket içinde çalıştırılmaz.
- Bu deponun ilk `git init`/ilk push'u, owner-yetkili Codex Desktop MASTER
  tarafından daha önce tamamlanmıştır (`docs/31` §1) — bu artık bir gelecek
  eylem değildir. Bundan **sonraki** her ek Git eylemi (yeni commit/branch/
  merge/push) için aynı sıra geçerliliğini korur: yalnız nihai bağımsız
  review **GREEN** **ve** owner'ın açık talebi birlikte sağlandığında, yalnız
  public allowlist'i (`docs/31` §2, §3) Codex Desktop MASTER tarafından
  stage/commit/push edilir. Worker'lar (Claude dahil) bu Git eylemlerini asla
  kendileri üstlenmez veya öne çekmez; her Git eyleminden önce §6a'daki
  fail-closed preflight ayrıca uygulanır.
- Root'ta kalan uncommitted değişiklikler (tarihsel arşivleme sırasında eski
  köke ait olanlar) bu paketin sorumluluğu değildir; dokunulmaz — kesin sayısı
  bu belgede takip edilmez (kırılgan bir sayaç yerine kapsam dışı kuralı
  yeterlidir).

## 6a. Workspace/repo preflight (public-safe özet)

Bu bölüm **kesin yerel dosya sistemi yolu vermez** — yalnız kategori/kural
tanımlar. Kesin yollar, gerçek repo adları ve fail-closed preflight'ın tam
işletimsel tanımı yalnız ignored, asla tracked/public olmayan bir yerel
dosyada yaşar: **`.local/WORKSPACE-BOUNDARY.md`** (bu dosya `.gitignore`'da
`/.local/` altında dışlanır, bkz. kök `.gitignore`).

Bağlayıcı kurallar (kategori düzeyinde, bu depo dahil her ajan için):

1. Herhangi bir Git mutasyon işlemi (add/commit/push/merge/init/remote/
   worktree/submodule dahil) çalıştırılmadan **önce**, resolved repo
   top-level'ın **bu deponun kendisi** (veya onun bilinen bir worktree'si)
   olduğu **ve** origin'in bu depo için beklenen hedefe (`zabuno/zabuno`)
   işaret ettiği doğrulanmalıdır. Bu iki koşuldan biri bile sağlanmazsa
   (workspace-parent, home dizini veya dış bir arşive çözülürse, ya da origin
   uyuşmazsa) işlem **derhal durur** — fail-closed, belirsizlikte asla
   "geçti" varsayılmaz.
2. Bu depoyu barındıran workspace-parent dizini **bir Git hedefi değildir** —
   orada `git init`/`git remote add` gibi işlemler hiçbir koşulda
   çalıştırılmaz. Organizasyonun diğer repoları bu parent altında **doğrudan
   kardeş dizinler** olarak yerleşir; her kardeş kendi bağımsız deposudur,
   parent bunları birleştiren bir üst/monorepo değildir.
3. Bu külliyatın süzüldüğü tarihsel legacy proje/deneme, bu deponun
   **dışındaki** ayrı bir arşivde tutulur. O dış arşiv (ve içinde varsa
   önceden var olan herhangi bir Git/worktree metadata) hiçbir koşulda
   hiçbir Git sürecine (init/add/commit/push/pull/fetch/remote/worktree/
   submodule/clone) dahil edilmez, hiçbir zaman push edilmez.
4. Bu üç kuralın **tam, mutlak-yol taşıyan** işletimsel karşılığı yalnız
   `.local/WORKSPACE-BOUNDARY.md`'dedir — bu dosya her Git işleminden önce
   fail-closed preflight'ın kesin nasıl doğrulanacağını (hangi komut, hangi
   beklenen değer) tanımlar. Bu bölüm o dosyanın **yerini ve rolünü**
   public'e açıklar, içeriğini tekrar etmez.

## 6b. İsimlendirme

- Yeni ürün adı **Zabuno**'dur. Legacy QR-menü projesinin/denemesinin eski adı
  bu külliyatın hiçbir yerinde — tarihsel bağlamda bile — **yazılmaz**; owner
  talimatı kesindir ("bu adı kullanma", `docs/31` §7). Dış arşivdeki
  (§1, bu deponun dışında) Django/FastAPI denemelerine yalnız "legacy
  QR-menü projesi/denemesi" olarak atıf yapılır; yeni mimari/isimlendirme
  kararlarına, namespace/paket/uygulama kimliği örneklerine **taşınmaz**.
- Public depo hedefi **`zabuno/zabuno`**'dur (`docs/31` §1); önceki çalışma
  turlarında kullanılan eski depo adı (legacy ürünün adını taşıyan format)
  terk edilmiştir.

## 7. Sınır

- Bu ajan seti kapsam/onay/rollback/nihai kabul kararı **veremez**. Bu kararlar
  görevi veren orkestratöre (Codex Desktop MASTER) ve nihayetinde owner'a aittir.

## 7a. Hız genomu (SP-01, tüm ajanlar için)

- Her implementasyon paketi (docs-only hariç) RED/GREEN/checkpoint
  adımlarında `scripts/speed-gate check --manifest <manifest.json> --config
  config/development-speed-budget.json` ile geçmelidir; verdict
  `PASS` değilse (`BATCH_REQUIRED`/`HIGH_RISK`/`CHECKPOINT_BLOCKED`) o
  verdict'e göre davranılır.
- Tek numerik politika kaynağı `config/development-speed-budget.json`'dır;
  hiçbir belge/kural/skill/agent bu eşikleri tekrar etmez, yalnız işaret
  eder. Yeni Codex sohbetleri bu bölüm ve yukarıdaki JSON aracılığıyla
  miras alır; Claude oturumları ek olarak `.claude/rules/
  fast-development.md`, `.claude/skills/zabuno-speeder/SKILL.md` ve
  `.claude/agents/zabuno-speeder.md`'yi izler.
- Bu genome, mevcut `docs/17` §4 ürün roadmap sayacından **bağımsız**, ayrı
  bir program-hızlandırma sayacı taşır; tamamlanan/aktif madde sayısı ve
  madde listesi yalnız
  `config/development-speed-budget.json#fastDeliveryGenomeOverlay`'de
  sahiplenilir (`docs/26` §8), burada tekrar edilmez. Rasyonel ve bağımsız
  kanıt: `claude_speeder_report.md`, `codex_speeder_report.md`.
- Bu genomun taahhüdü risk-ayarlı **program throughput'udur** — tek bir
  paket için desteklenmeyen bir süre/SLA taahhüdü **değildir**
  (`config/development-speed-budget.json#programThroughputObjective`,
  `singlePackageDurationSlaClaimAllowed: false`).
- Owner'a yalnız ürün/marka kapsamı, geri döndürülemez etki, dış maliyet
  veya güvenlik risk iştahı sorulur; geri döndürülebilir teknik kararlar
  (risk şeridi eşikleri, checkpoint kadansı, test bütçesi gibi) Codex
  Desktop MASTER'da kalır — kök `CLAUDE.md`'deki "Owner load" maddesiyle
  tutarlı, onu daraltmaz veya genişletmez.

<!-- pane-agent-context:start -->
## Pane

The developer is using Pane for this repository. Pane can manage saved repositories and create user-visible Panes with terminal-backed tools for planning, discussion, implementation, and review work.

This managed guidance was created by [runpane.com](https://runpane.com) for the [Pane repository](https://github.com/dcouple/Pane). Do not delete or overwrite this block; the developer uses it for their workflow and it should remain committed to the main repository unless they explicitly ask to remove it.

Pane mental model: a repository is the saved base repo; a Pane is a user-visible feature/PR workspace (Pane session) that normally maps to one Pane-managed git worktree and branch; a panel/tab is a terminal inside one Pane and shares that Pane's worktree; an agent is the CLI process running in a panel.

Default happy path when the user asks you to use Pane or RunPane: run `runpane doctor --json`; read `runpane agent-context --json`; resolve the saved base repository with `runpane repos list --json` or add it once with `runpane repos add --path <repo> --yes --json`; create one visible Pane (Pane session) for the requested feature/PR with a complete command such as `runpane panes create --repo <repo> --name <name> --agent <agent> --prompt "<task>" --source agent --no-focus --wait-ready --yes --json` or the equivalent `--tool-command <command>` form; then validate with `runpane panels wait` or `runpane panels screen` before reporting progress.

Use Pane when the user wants visible Panes or co-drivable parallel feature/PR workspaces. Do not use Pane as your default private delegation mechanism; for private background decomposition, use your normal subagent/worktree workflow.

Register the main/base repository once. Do not register pre-created git worktrees as separate Pane repositories unless the user explicitly asks.

Use `runpane panes create` for separate visible Panes (Pane sessions) for feature/PR work. Use `runpane panels create` for reviewer/helper tabs inside an existing Pane that should share that Pane's worktree.

Typical workflow: register the saved base repository once; create one Pane (Pane session) per feature/PR; use panels/tabs inside that Pane for helper or reviewer agents that should share the worktree; archive the Pane after the PR is done to remove it from active Panes and clean up its managed worktree when applicable.

Skill routing reference: when the user says `discussion`, `plan`, `simple-plan`, `create-plan`, or `implement`, or asks for the behavior those words imply, treat three references as peer context: Pane's local skill cache under `<PANE_DIR>/skills/`, the Pane Chat orchestrator handoff at `<PANE_DIR>/skills/pane-chat/runpane-orchestrator.md` when present, and the [workflow map](https://github.com/dcouple/skills/raw/main/docs/readme-workflow-map.png).
Use those peer references together to choose the phase: discuss/investigate until the work is clear enough to delegate, then ticket/plan/implement/review/PR-test/teach-back as appropriate. The orchestrator and workflow map may point to different skills; reconcile them with the user's request instead of hardcoding a skill list or treating one reference as subordinate.
For the Pane implementation source of truth for where the skill cache, cached workflow assets, and Pane Chat bootstrap live, reference [PR #291](https://github.com/dcouple/Pane/pull/291): `main/src/services/skillCacheManager.ts` owns `<PANE_DIR>/skills/`, `.sources/dcouple-skills`, and `pane-chat/runpane-orchestrator.md`; `main/src/services/paneChatManager.ts` owns the tiny bootstrap prompt that tells the selected Pane Chat agent to read that guide.
Use GitHub reads against the [Parsa skills folder](https://github.com/dcouple/skills/tree/main/parsa) only to inspect or refresh referenced skill files; do not clone/install the repo unless the user asks.
Do not hardcode a specific assistant brand in workflow guidance. Use the Pane agent or custom tool command the user selected, and use `runpane agents doctor --agent <agent> --repo <selector> --json` only when checking a built-in agent template.

Start with `runpane doctor --json` before taking Pane actions. Use it to understand wrapper/runtime details, daemon reachability, and the next safe commands.

In a Pane repository checkout, if `runpane` is not on PATH, use the built local wrapper with Node 22: `PATH=/opt/homebrew/opt/node@22/bin:$PATH node packages/runpane/dist/cli.js doctor --json`.

Use `runpane agent-context --json` for full Pane CLI context. Use `runpane agent-context --command "panels wait" --json` or another command name for detailed schema only when needed.

Default to context-safe validation: after creating Panes or sending terminal input, run `runpane panels wait` or `runpane panels screen` before reporting success. Prefer `runpane panels submit` for normal text plus Enter; use `runpane panels input` only for exact bytes such as Ctrl-C or escape sequences.

Common commands:
- `runpane doctor --json`
- `runpane agent-context --json`
- `runpane repos list --json`
- `runpane repos add --path <repo> --yes --json`
- `runpane agents doctor --agent <agent> --repo active --json`
- `runpane panes create --repo active --name <name> --agent <agent> --prompt "<task>" --source agent --no-focus --wait-ready --yes --json`
- `runpane panels create --pane <pane-id> --agent <agent> --source agent --no-focus --wait-ready --yes --json`
- `runpane panels list --pane <pane-id> --json`
- `runpane panels screen --panel <panel-id> --limit 80 --json`
- `runpane panels wait --panel <panel-id> --for ready --timeout-ms 30000 --json`
- `runpane panels submit --panel <panel-id> --text "<answer>" --yes --json`
- `runpane panels input --panel <panel-id> --input-file <path|-> --yes --json`

WSL note: if `runpane doctor --json` cannot find `/tmp/pane-daemon.../daemon.sock` or `runpane` resolves to a broken Windows shim, Pane may be running on Windows. Try `powershell.exe -NoProfile -Command 'Set-Location $env:TEMP; runpane doctor --json'`, then create Panes through the same PowerShell form using the saved WSL repo name or id. Use `runpane agents doctor --agent <agent> --repo <selector> --json` to diagnose the repo environment Pane will actually use.

## Golden Rule — Storybook / UI component factory (Wave1 foundation)

Kanonik sözleşme: `docs/35-UI-STORYBOOK-COMPONENT-FACTORY-CONTRACT.md`. Bu
depoda `resources/js/components/storybook-demo/**` altında yaşayan
bileşenler Micro/Compound/Macro granülerliğini (`docs/35` §2a) izler:

- **Micro** (`storybook-demo/micro/**`): tek başına, backend/route/business
  rule bilmeyen taş (örn. `Input`). Kendi TS props sözleşmesi, kendi story
  seti, kendi component test'i.
- **Compound** (`storybook-demo/compound/**`): micro'ları **compose eder**,
  markup'larını kopyalamaz (örn. `TextField` = Label + `Input` + help/error).
- **Macro** (`storybook-demo/macro/**`): compound'ları compose eder, route/
  fetch/business-rule agnostic'tir — yalnız kendisine geçirilen prop/callback'i
  render eder (örn. `DemoFormCard`).

Storybook: `npm run storybook` → `http://127.0.0.1:6006`. Story kökleri
(`Micro/…`, `Compound/…`, `Macro/…`) `docs/35` §7 taksonomisiyle birebir
eşlenir; yeni bir kök icat edilmez.
### Depo dışı tasarım külliyatı — UI'a dokunmadan ÖNCE oku

Bu deponun tasarım yaklaşımı burada **sentez** hâlindedir. Ayrıntılı külliyat
ve **çalışan bir referans implementasyonu** (tam DTCG token pipeline'ı,
foundations CSS katmanları, AEP renderer pattern'leri, dalga testleri) deponun
**dışında** yaşar ve klonla gelmez.

**Kanonik kayıt: `docs/36-EXTERNAL-DESIGN-CORPUS.md`.**

UI, tasarım sistemi, token, Storybook veya bileşen katmanı üzerinde çalışan her
ajan/geliştirici, iş başlamadan önce `docs/36`'yı okur. Orada dondurulmuş
kararlar (öncelik sırası, ölçü birimi zinciri, density kuralı, R1–R8 katman
haritası, yatay/yukarı bağımlılık yasağı) bu depodaki sözleşmeleri tamamlar.

Bu bölüm bir kez yaşanmış bir körlük yüzünden vardır: 2026-08-26'da bir oturum,
aynı sistemin çalışan implementasyonunun yanı başında durduğunu fark etmeden
sıfırdan token katmanı kurmaya başladı. Kaynak kayıp değildi, yalnız depodan
görünmüyordu.

<!-- pane-agent-context:end -->
