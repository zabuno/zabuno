# 14 — AI-First, MCP & Skills

**PLANNING ONLY.**

## 1. Temel ilke: AI kapalıyken tam determinizm

Platformun **hiçbir kritik iş akışı** AI'ya bağımlı değildir. AI kapatıldığında
(kill switch, §6) menü yönetimi, QR üretimi, yayınlama, ödeme, yetkilendirme —
hepsi tam olarak çalışmaya devam eder. AI yalnız **öneri/üretim yardımcısı**dır
(`docs/01` §3, `docs/06` §7).

## 2. Provider/model registry ve feature×LLM matrisi

Desteklenen provider adayları: OpenAI, Claude, Gemini, Kimi, private/self-host/
OpenAI-compatible endpoint'ler. Her **özellik** (örn. "ürün açıklaması üret") ×
**model** kombinasyonu için ayrı bir kayıt tutulur:

```
feature × model → capability, cost, latency, privacy/residency, budget, fallback, human-approval-required
```

Bir özellik için birden fazla model aday olabilir; hangi model kullanılacağı bu
matristen çözülür, hard-code edilmez.

## 3. Zorunlu güvenlik/operasyon katmanı

Secrets yönetimi, prompt versiyonlama + JSON schema ile structured output, tool
**allowlist** (AI'nın çağırabileceği araçlar sabit bir listeyle sınırlı), MCP
server registry, skills registry (`skills/`), eval seti (regresyon testleri),
redaction (PII'nin prompt'a sızmaması), tenant isolation (bir tenant'ın AI
context'i başka tenant'a sızmaz), audit, **kill switch** (AI'yı anlık olarak
tamamen kapatma), cost cap (bütçe tavanı), prompt-injection / content-poisoning
koruması.

## 4. Human approval — UX kuralı + backend zorlaması

`docs/06` §7'deki UX kuralı ("AI önerir, destructive/publish/payment/permission
action'ları asla onaysız çalışmaz") burada **backend seviyesinde de** zorlanır:
AI tool-allowlist'inde bu dört kategori action, "requires_human_approval: true"
bayrağı olmadan register edilemez.

## 5. Laravel AI SDK ve Boost ayrımı

Laravel'in resmi AI SDK'sı güncel olsa bile **pre-1.0/conditional adapter
arkasında** kullanılır (deneysel sınıf — API stabilitesi henüz garanti değil).
Laravel Boost'taki **always-on guidelines** (her zaman aktif kurallar) ile
**on-demand skills** (talep üzerine yüklenen yetenekler) ayrımı bu plana
yansıtılır: `skills/` dizinindeki her plan on-demand kategorisindedir, hiçbiri
"always-on" değildir.

## 6. MCP/skills sınırsız yetki vermez

MCP server registry veya skills registry'ye eklenen hiçbir kaynak, herhangi bir
modele **sınırsız yetki** vermez — her MCP server'ın kendi tool-allowlist'i,
her skill'in kendi izin verilen/yasak eylem listesi vardır (`skills/*` şablonu,
bkz. `templates/SKILL-SPEC.md`).

## 7. Resmi kaynaklar

OpenAI resmi developer/Codex use-case kaynakları `docs/28-SOURCE-REGISTER.md`'de
kayıtlıdır; bu doküman onları tekrar basmaz.

## 8. Kanonik sahiplik

AI platform mimarisi (feature×model routing, güvenlik katmanı) burada
kanoniktir. Provider **hesap/bağlantı** yönetimi (N adet, platform-owned vs.
tenant BYOK) `modules/ai-provider-account-vault.md`'de ayrı kanoniktir. Her
skill'in *kendi* trigger/input/output sözleşmesi `skills/*.md` dosyalarında,
`modules/ai-platform.md`'de ise bu modülün contract/entitlement/state detayı
yaşar. 61 modülün AI ile ilişkisinin (mode/posture/kullanım örneği) tek
kanonik kaynağı `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md`'dir — bu doküman
onu tekrar etmez.

## 9a. Global AI Command Center sözleşmesi

Header/command palette üzerinden erişilen tek yatay AI yüzeyi (`docs/06`
§4 global panel shell, §7 destructive-onay kuralıyla tutarlı). Görünürlük
yüzeyleri: aktif workspace/restaurant/location bağlamı, prompt history, son
aksiyonlar, sonuç açıklaması (explanation + provenance + diff), "kabul et /
düzenle / reddet" kontrolü, gerçek değişiklik öncesi preview. Bu yüzey
**yalnız görüntüleme/orkestrasyon**dur — hiçbir mutasyonu doğrudan
yürütmez, her komut §3'teki tool-allowlist/PDP zincirinden geçer.

**Örnek uçtan uca akış — doğal dil izin verme** ("Ahmet'e bu restoranı
yönetme yetkisi ver"):

1. Kullanıcı/aday çözümleme ("Ahmet" adlı kayıt(lar) bulunur).
2. Belirsizlik varsa adaylar kullanıcıya gösterilir, seçim istenir.
3. Aktif tenant/restoran/location bağlamı doğrulanır (`docs/05` §1 tenant
   resolver ile aynı kesişim mantığı).
4. İstenen görev permission/relationship intent'ine çevrilir.
5. `docs/05` §2'deki tek PDP üzerinden ReBAC + ABAC policy simulation
   çalıştırılır (deny-by-default).
6. En az yetkili rol/relationship önerilir (least privilege).
7. Süre, kapsam ve kısıtlar gösterilir.
8. Mevcut ve önerilen yetki arasındaki **diff** gösterilir.
9. Conflict/separation-of-duty riski gösterilir (`docs/05` §2 segregation
   of duties ile tutarlı).
10. **İnsan onayı** istenir — bu adım atlanamaz (`docs/06` §7).
11. Gerekirse **step-up authentication** istenir.
12. Onay sonrası typed server command üretilir; prompt **asla** doğrudan
    SQL/DB mutation'a çevrilmez.
13. Server her istekte yetkiyi **yeniden** doğrular (PDP tekrar çağrılır,
    client'ın önceki onayına güvenilmez).
14. Transactional mutation uygulanır.
15. Audit/outbox event'i yazılır (`modules/core-audit-events.md`).
16. Sonuç kullanıcıya açıklanır (ne değişti, neden).
17. Revoke/undo yolu gösterilir.

Bu akış yalnız permission-grant örneğidir; aynı 17 adımlı iskelet (çözümle
→ doğrula → simüle et → onay al → typed command → server-side re-check →
audit → açıkla → geri al) her AI-tetiklemeli mutasyon için (ECA rule
aktivasyonu, fiyat/görünürlük değişikliği vb.) yeniden kullanılır — her
modül kendi intent-çözümleme mantığını tanımlar, PDP/audit/approval
iskeletini **tekrar icat etmez**.

**Bilinen PII-redaksiyon / tool-safety açıkları (bu doküman kapatmaz,
taşır)**: (a) redaction kural seti henüz **hangi alan sınıflarının** (ad,
e-posta, telefon, ödeme son 4 hane, adres) prompt'a girmeden maskeleneceğini
alan-bazlı numaralandırmaz — yalnız §3'teki "redaction" ilkesi genel olarak
zikredilir; somut alan taksonomisi `docs/16-GAP-UNKNOWN-UNKNOWNS.md`'ye gap
olarak işlenmelidir. (b) Tool-allowlist'in **hangi somut tool'ları**
(örn. "invite-user", "update-price") ve bunların JSON-schema input
sözleşmesini içerdiği henüz bir registry'de listelenmedi — §2 feature×model
matrisiyle **karıştırılmamalıdır** (biri model seçimi, diğeri tool
yürütme izni). (c) Adım 13'teki "server yeniden doğrular" kuralının somut
idempotency-key/replay-protection mekanizması henüz `docs/09` ile çapraz
doğrulanmadı. Bu üç boşluk mevcut kanonik guardrail'leri (§3 tool-allowlist,
PDP tek karar noktası, kill switch, audit) **değiştirmez** — onları
kullanan somut envanterin eksik olduğunu işaretler.

## 9. AI ürünün genlerindedir — iki eksen

"AI kapalıyken tam determinizm" (§1) ile "modülün AI kabiliyeti yoktur" **aynı
şey değildir**. Her modülde iki ayrı eksen vardır:
`deterministic_baseline: required` (sabit — hiçbir modülde gevşetilmez) ve
`ai_posture` (advisory/assistive/automated_guarded/agentic_guarded/none —
`docs/32`, `templates/AI-CAPABILITY-MANIFEST.md`). 61 modülün 60'ı en az bir
opsiyonel, provider-nötr AI kullanım örneğine sahiptir; yalnız bir modül
(`opt-29-native-app-shell`, saf paketleme/shell katmanı) gerekçeli `none`
istisnasıdır. Bu, "AI-first" ifadesinin doğru okunuşudur: AI her yerde zorla
çağrılmaz, ama mimari olarak Stage 0'dan itibaren her yerde **hazırdır**.
