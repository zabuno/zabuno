# Codex Bağımsız Kök-Neden Raporu — Hız/Orkestrasyon (SP-01 kaynak girdisi)

Provenance: Source-Type: codex-desktop-owner-attachment. Original-Source-SHA-256: 31c98e9860a8fffa8e9a7491aeb0202197ff3ddc54e1020e4937c43c14f077ab. Bu gövde, orijinal kaynağın yalnız kamuya-açık-güvenli yol redaksiyonlarıyla kopyalanmış halidir; SP-01 görev talimatının "MANDATORY SOURCES" maddesi uyarınca içerik başka hiçbir şekilde değiştirilmemiştir (kaynağın kendi son paragrafındaki, bu iki dosyanın ana görevde taze bir Claude worker tarafından üretilmesi gerektiğine dair not dahil).

---

Kök neden “test-first” değil; sınırlandırılmamış test kapsamı, sabit orkestrasyon maliyeti ve kirli entegrasyon akışının birleşmesi.

## Codex bağımsız kök neden raporu

Somut repo verileri:

- 85 PHP + 107 frontend test dosyası var.
- Yaklaşık 1.514 test çağrısı bulunuyor.
- Test kodu yaklaşık 46.538 satır; ürün kodu yaklaşık 28.831 satır. Test/ürün oranı `1,61:1`.
- Password reset paketi: 33 test, 689 test satırı.
- Ownership transfer paketi: 14 test, 763 test satırı.
- İki küçük dialog düzeltmesi: yaklaşık 24 ürün satırına karşı 175 test satırı.
- Aynı Stage 1 Pane’inde 530 panel kaydı bulunuyor.
- Guardian şu anda yalnız 1 yeni worker öneriyor.
- Beş CI koşusu da testlere ulaşmadan `composer validate --strict` adımında bitmiş.
- CI yalnız license yüzünden değil; bilerek kullanılan dört exact dependency pini de strict warning üretiyor. Yani mevcut CI tasarımı yapısal olarak sürekli RED.
- Beş frontend iş alanı için 10 ayrı worktree oluşturulmuş; bazıları yarım veya kirli kalmış.
- Aktif entegrasyon worktree’sinde billing ve Pane GC paketleri aynı anda bulunuyor. Bu, bağımsız snapshot/review maliyetini büyütüyor.

Git geçmişine göre tek paket gerçekten beş gün sürmemiş:

- Password reset → ownership transfer: yaklaşık 82 dakika.
- Ownership → ilk dialog düzeltmesi: yaklaşık 22 dakika.
- Sonraki dialog düzeltmesi: yaklaşık 27 dakika.
- Beş günlük aralık, 753 dosyalık ve 98 bin satırlık toplu foundation checkpoint’i içeriyor.

Yine de 22–82 dakika, hedeflediğiniz web-benzeri tempo için gereğinden uzun.

## Asıl kök nedenler

1. Hedefli test bütçesi sınırsız

“Tam QA en fazla iki kere” sınırı var; fakat “targeted test” sayısı ve targeted tekrar sayısı sınırsız. Böylece tam-suite bütçesi korunurken 17–33 testlik mikro paketler üretilebiliyor.

2. Testler risk yerine yüzey sayıyor

ARIA özelliği, buton durumu, label, focus, backdrop, Escape ve disabled davranışlarının her biri ayrı teste dönüşüyor. Bunların bir kısmı tek güçlü journey testinde doğrulanabilir.

3. Sabit ajan maliyeti mikro paketten büyük

Kapsam analisti, test writer, implementation writer, reviewer, Guardian, auth, capability ve Pane başlangıçları küçük bir buton düzeltmesinden daha uzun sürüyor.

4. Aşırı küçük paketleme

Bir prop veya dialog davranışı tek paket oluyor. Ayrı test writer ve reviewer korununca 10 satırlık düzeltme de tam orkestrasyon vergisi ödüyor.

5. CI sürekli ve bilinen şekilde RED

`composer validate --strict`, exact pin politikasına ters düşüyor. CI testleri hiç çalıştırmadığı için CI kanıtı üretilemiyor; eksik güven yerel test ve review tekrarlarıyla telafi ediliyor.

6. Worktree ve snapshot entanglement

Aynı worktree’de birden fazla paket bulunduğunda reviewer’ın immutable snapshot’ı her küçük değişiklikte bozuluyor. Eski/yarım worktree’ler de Pane ve zihinsel kapasite tüketiyor.

7. Browser QA yanlış seviyede uygulanıyor

Her mikro UI düzeltmesini bütün journey ile tarayıcıda tekrar doğrulamak düşük getirili. Browser QA dikey dilim kapanışında yapılmalı.

8. Hız için makine-okunur kapı yok

[docs/27](docs/27-QA-ACCEPTANCE-VIBECODING.md) test-first ve full-QA bütçesini tanımlıyor; ancak test adedi, süre, browser koşusu, reviewer tekrarı ve orkestrasyon vergisi sınırlandırılmamış.

## Yeni “ultra hızlı normal paket” sözleşmesi

Zayıf test önermiyorum. Daha az ve hata yakalama gücü yüksek test öneriyorum.

Normal paket:

- Toplam en fazla `3–8` yeni/değiştirilen hedefli test.
- En fazla 2 test dosyası.
- Tercihen üç ana test:

  1. Başarılı kullanıcı yolculuğu.
  2. Yetki/validasyon veya güvenli hata.
  3. Retry, idempotency veya otoritatif yeniden yükleme.

- Ayrı test writer, implementation writer ve reviewer korunur.
- RED hedefli test bir kere çalışır.
- Implementation aynı hedefli paketi GREEN için bir kere çalıştırır.
- Reviewer aynı suite’i tekrar etmez; immutable hash’i ve çıktıyı doğrular, gerekirse en fazla 1–2 adversarial test çalıştırır.
- Tam local QA writer tarafından yalnız bir kere.
- CI full QA yalnız bir kere.
- Değişmeyen snapshot’ta test tekrarı yasak.
- Mikro UI düzeltmesinde browser QA yok.
- Kullanıcıya görünen dikey dilim kapanışında en fazla bir browser smoke.
- Auth, tenancy, ödeme, webhook ve migration gibi yüksek riskli paketler normal sınırın dışında tutulur; ek test gerekçeli olmalıdır.

20 dakikalık checkpoint bütçesi:

| Süre | İş |
|---|---|
| 0–3 dk | Kapsam ve risk sınıfı |
| 3–6 dk | 3–8 testlik RED |
| 6–14 dk | Implementasyon ve hedefli GREEN |
| 14–18 dk | Bağımsız review |
| 18–20 dk | Güvenli checkpoint/handoff |

20 dakikada bitmezse paket başarısız sayılmaz; güvenli checkpoint alınır. Worker aynı belirsizlik üzerinde sınırsız düşünmeye devam etmez.

Mikro düzeltme kuralı:

- Beklenen ürün değişikliği yaklaşık 30 satırdan küçükse tek başına paket açılmaz.
- Aynı component/journey içindeki en fazla üç bitişik mikro düzeltme tek pakette gruplanır.
- Sekizden fazla test gerekiyorsa normal paket yanlış bölünmüştür; journey bazında ayrılır veya yüksek-risk olarak sınıflandırılır.

## Gerekli kalıcı guardrail’ler

Ana görevde şu artefaktlar oluşturulmalı:

- `.claude/rules/fast-development.md`
- `.claude/skills/zabuno-speeder/SKILL.md`
- `.claude/agents/zabuno-speeder.md`
- `config/development-speed-budget.json`
- `scripts/speed-gate`
- `claude_speeder_report.md`
- `codex_speeder_report.md`

`speed-gate` şu kontrolleri deterministik yapmalı:

- `targetedTestCount <= 8`
- `testFilesChanged <= 2`
- `fullLocalQaRuns <= 1`
- `ciFullQaRuns <= 1`
- `browserRuns <= risk sınıfı bütçesi`
- `reviewerFullSuiteRuns == 0`
- `elapsedCheckpointMinutes <= 20`
- aynı snapshot hash’inde kanıt tekrar kullanımı
- mikro paketlerde orkestrasyon maliyeti ürün değişikliğinden büyükse `BATCH_REQUIRED`

MCP bir LLM orkestratörü olmamalı. Yerel ve hızlı dört fonksiyon sunması yeterli:

- `speed_budget_check`
- `qa_run_admit`
- `evidence_reuse_check`
- `checkpoint_record`

Önce yerel checker yapılmalı; MCP bunun ince adaptörü olmalı. Aksi halde hızlandırma için yeni bir orkestrasyon katmanı ekleyip sistemi daha da yavaşlatırız.

## Dikkate alınmayan unknown unknown’lar

- Claude Web’deki “5 dakika” sonucu aynı test, review, auth, tenant ve CI şartlarına tabi mi? Apples-to-apples benchmark yok.
- Test azaltınca kaçan hata oranı ölçülmüyor.
- Testlerin mutation/bug-killing değeri ölçülmüyor; yalnız test sayısı artıyor.
- Ajan başlangıç, capability, Pane bekleme ve test koşma süreleri ayrı ayrı kaydedilmiyor.
- Exact Composer pinleri ile strict CI politikasının çelişkisi daha önce tam teşhis edilmemiş.
- Yarım worktree’lerin ürün değeri ve integrasyon durumu kayıtlı değil.
- Büyük ortak frontend dosyalarının küçük değişiklikleri geniş test alanına yayma maliyeti ölçülmüyor.
- Flaky test bütçesi ve karantina protokolü yok.
- “Browser gerekli mi?” karar matrisi yok.
- “Normal / yüksek-risk / prototip” olmak üzere farklı teslimat şeritleri yok.

Nihai karar: önerdiğiniz `3–8 güçlü test` sınırı doğru. Fakat tek başına yeterli değil. CI’nin yapısal RED’i, sınırsız targeted tekrarlar, mikro paketleme, worktree birikimi ve reviewer test tekrarı birlikte kapatılmalı.

Bu yan sohbetin izolasyon kuralı nedeniyle yeni Claude worker açmadım ve iki `.md` dosyasını yazmadım; aksi halde “iki gerçek bağımsız rapor” iddiası sahte olurdu. Yukarıdaki içerik gerçek Codex bağımsız raporudur. `claude_speeder_report.md` ve iki dosyanın repo içinde üretilmesi ana görevde, taze Claude worker ile yapılmalıdır.