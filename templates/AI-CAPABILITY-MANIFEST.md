# AI-CAPABILITY-MANIFEST şablonu

Bu şablon her `modules/<key>.md` dosyasının sonuna eklenen **AI Capability
Manifest** bölümü için kullanılır (bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md`
kanonik matris, kök yönetişim talimatı madde 3). AI ürün genlerinin bir
parçasıdır: 61 modülün tamamı ports/events/context/permission/eval kancalarıyla
**AI-hazır**dır. "AI-off'ta tam determinizm" ile "AI kabiliyeti yok" **aynı
şey değildir** — bunlar iki ayrı eksendir (bkz. §İki eksen).

## İki eksen — bağlayıcı ayrım

```
deterministic_baseline: required   (her modülde SABİT — asla "optional" olamaz)
ai_posture:              advisory | assistive | automated_guarded | agentic_guarded | none
```

- **`deterministic_baseline: required`**: Her modülün AI kapalıyken/sıfır
  krediyle/quota aşımıyla/outage'da/policy-denied'de/429'da **tam ve eksiksiz**
  çalışan, veri kaybetmeyen bir deterministik yolu vardır. Bu satır 62/62
  dosyada **sabittir**, hiçbir modülde gevşetilemez.
- **`ai_posture`**: O deterministik omurganın üzerine binen **opsiyonel**
  AI zenginleştirmesinin türü. Varsayılan olarak kapalı/config'e bağlı
  olabilir ama mimari olarak Stage 0'dan itibaren **pre-wired**'dır (port/
  event/permission/eval kancası spec'te tanımlıdır, implementasyon henüz
  yoktur).

## `ai_posture` tanımları

- **advisory**: AI açıklar/özetler/analiz eder; hiçbir alana taslak
  **yazmaz**. Çıktı yalnız insana sunulan bir içgörü/açıklama metnidir (örn.
  "bu yetki reddi neden oluştu", "bu ay churn eğilimi neden yükseldi").
- **assistive**: AI bir alana **düzenlenebilir taslak** üretir; taslak insan
  onayından geçmeden kalıcı veri olmaz (`docs/01` §3). Var olan çoğu OPT-21/22/23
  deseni budur.
- **automated_guarded**: AI, dar kapsamlı, **geri alınabilir**, loglanan,
  feature-flag'li bir eylemi insan onayı **beklemeden** tetikleyebilir (örn.
  otomatik alt-text üretimi, otomatik içerik moderasyon bayrağı) — ama bu
  eylem asla final-authority alanlarından (§Forbidden authority) biri olamaz
  ve her zaman tek tık geri alınabilir/görünür şekilde "AI-generated"
  etiketlenir.
- **agentic_guarded**: AI, sınırlı bir tool-allowlist içinde **çok adımlı**
  bir işlemi (örn. sandbox'ta entegrasyon eşleme testi, ECA kural simülasyonu)
  yürütebilir; yalnız **simülasyon/sandbox/test** kapsamındadır, üretim
  verisine veya para/yetki/yayın kararına asla dokunmaz.
- **none**: Modülün **tamamının** hiçbir AI kabiliyeti sunmaması — bu
  **istisnai** bir durumdur ve gerekçelendirilmelidir (örn. saf paketleme/shell
  modülü, kullanıcıya sunulan içerik/karar yüzeyi yok). "none", bir modülün
  final-authority eylemini korumak için **kullanılmaz** — final-authority
  koruması `Forbidden authority` alanının işidir, `ai_posture: none`'un değil.

## Zorunlu alanlar

```markdown
## AI Capability Manifest

Bkz. `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md` (kanonik satır) ve bu şablon
(alan sözleşmesi). Kompakt format korunabilir; aşağıdaki alanlar zorunludur.

- **deterministic_baseline**: required
- **ai_posture**: advisory | assistive | automated_guarded | agentic_guarded | none
- **Optional AI use case(ler)**: (en az bir somut, provider-nötr kullanım örneği — `ai_posture: none` hariç her modülde zorunlu)
- **AI-off / no-credit deterministic path**: (AI kapalıyken/sıfır krediyle bu modül nasıl çalışır — veri kaybı yok)
- **Data classification**: (AI'ya giden veri var mı, varsa sınıfı — PII/ödeme/secret/yok)
- **Allowed tools/side effects**: (AI'nın bu modül bağlamında çağırabileceği tool/eylem allowlist'i — `docs/14` §3/§6)
- **Forbidden authority (final-authority)**: (bu modülde AI'nın asla karar veremeyeceği otorite alanları — authz/tenant isolation/money finality/permission/publish-delete-purge/legal consent; bu alan `ai_posture`'dan **bağımsız**, her modülde tekrarlanır)
- **Human approval**: (hangi eylem insan onayı gerektirir — `docs/14` §4, `docs/06` §7)
- **Feature policy**: (feature × provider/model × account × policy × tenant/residency matrisine bağlantı — `docs/14` §2, `modules/ai-provider-account-vault.md`)
- **Budget/credit behavior**: (reserve→invoke→debit/reconcile/release/refund davranışı — `docs/09`, `modules/ai-provider-account-vault.md` §credit ledger)
- **Eval/audit**: (bu modülün AI kullanımına dair eval seti/audit kaydı — `ai_posture: none` ise "N/A")
- **Phase**: (hangi stage'de aktif — `docs/26`; AI-hazır mimari Stage 0'dan itibaren spec'te var, **etkinleştirme** fazı ayrı olabilir)
```

## Değişmez kurallar

- **`ai_posture` ne olursa olsun**, authz (CORE-03), tenant isolation (CORE-02),
  para/ledger/ödeme finalitesi (CORE-12, Iyzico Payment), permission (CORE-03),
  publish/delete/purge (Publication, CORE-15) ve legal/consent (CORE-16) kararı
  **hiçbir zaman** AI otoritesine devredilmez. Bu, "final-authority action"
  kısıtıdır — bir modülün *geri kalanının* AI'dan tamamen mahrum kalması için
  bir gerekçe **değildir** (örn. Money/Ledger modülü hesaplama/finalite için
  AI'ya kapalıdır ama anomaly-explanation için `advisory` olabilir — nitekim
  öyledir, bkz. `modules/core-money-ledger.md`).
- AI hard-off, sıfır-kredi, quota/429, outage, residency/policy-denial,
  safety-block veya invalid-schema durumlarında modülün normal/kritik akışı
  veri kaybı olmadan devam etmelidir (`deterministic_baseline: required`).
- Bu marker, `templates/MODULE-SPEC.md`'nin "AI-off / AI-on davranışı"
  alanının **yerine geçmez** — o alan modülün genel AI duruşunu, bu marker ise
  AI Capability Plane ile teknik/operasyonel sözleşmeyi (posture/provider/
  credit/eval) tanımlar.
- Üç ayrı AI kullanım katmanı birbirine karıştırılmaz: **ürün-runtime AI**
  (bu şablon, `docs/32`), **AI-destekli geliştirme** (Boost/MCP/skills,
  `docs/14` §5), **AI operasyon yönetişimi** (provider evaluator, kill switch
  operasyonu, `skills/ai-account-routing.md`). Bu marker yalnız birincisini
  kapsar.
- AI kullanılabilirliği hiçbir zaman bir **entitlement ön-koşulu** olmaz —
  bir tenant'ın temel plan özelliğine erişimi AI'nın çalışır olmasına
  bağlanmaz; AI yalnız *ek* bir zenginleştirmedir.

## Kullanım kuralı

Her `modules/*.md` dosyası (62/62, `ai-platform.md` ve
`ai-provider-account-vault.md` dahil) bu markerı taşır. Alan doldurulamıyorsa
"bilinmiyor / karar gerekiyor" yazılır ve `docs/16-GAP-UNKNOWN-UNKNOWNS.md`'ye
kayıt açılır (`AGENTS.md` §5 ile aynı disiplin). `ai_posture: none` seçimi
gerekçesiz bırakılamaz.
