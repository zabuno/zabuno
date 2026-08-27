# 51 — AI-First: boşluk analizi, unk-unks ve fazlanmış çekirdek

**Durum:** Analiz + plan. Faz 1 çekirdeği tanımlı, kod yazılmadı.
**Requirement ID:** `AI-CORE-v1`
**İlgili:** `docs/14` (AI-First doktrini), `docs/16` (unknown-unknowns kaydı),
`docs/18`–`docs/25` (Stage 1–8), `docs/26` (WP matrisi), `docs/32` (yetenek
matrisi), `modules/ai-platform.md`, `modules/ai-provider-account-vault.md`

---

## 0. Bağımsız denetim ve bu sürümün düzelttikleri (2026-08-27)

Bu belgenin ilk sürümü bağımsız bir denetimden geçti (ChatGPT Codex, salt
okunur). Denetim **haklı çıktı** ve altı somut kusur buldu. Hepsi burada
düzeltildi; kaydedilmeleri gerekir, çünkü ikisi mimari hataydı:

| # | Bulgu | Düzeltme |
| --- | --- | --- |
| 1 | **"Faz 2–10" ürün fazı değildi** — medya planının (`docs/49`) kendi adımlarını ürünün Stage 1–8'i gibi sunuyordu | §8 yeniden yazıldı: **Stage 1–8** (`docs/18`–`docs/25`) |
| 2 | **Stage 1 üç ayrı şey söylüyordu** — `docs/18` "AI üretim özellikleri non-goal", `docs/26` "pre-wired kapalı", bu belge "çekirdek Stage 1'de kodlanır" | §9'da uzlaştırıldı; `docs/18` ve `docs/26` düzeltildi |
| 3 | **`TextGenerationPort` bütün AI çekirdeği olamaz** — OCR, gömme, görme, sınıflandırma, yeniden sıralama, tool intent metin üretimi DEĞİLDİR | §3.1 **yetenek portu matrisi** ile değiştirildi |
| 4 | **`ANTHROPIC_KEY_1/2/3` yanlış soyutlama** — ve `modules/ai-provider-account-vault.md` zaten daha doğrusunu söylüyordu | §3.2 **Provider → Connection → ModelDeployment → CapabilityRoute**; vault kanonik |
| 5 | **Ölçülmemiş gecikme sayıları** ("10–40 ms") | Kaldırıldı. Sayı, gerçek sunucuda ölçüldükten sonra yazılır (§4.5) |
| 6 | **"Altın küme boş başlar" kabul edilemez** | Faz 1 teslimi: **asgari fixture kümesi**; boş eval ile model yönlendirme ölçüsüz kalır |

Ayrıca denetim, mevcut UNK listesine eklenmesi gereken bir sınıf daha
gösterdi: tedarik zinciri, nicemleme kayması, gömme yeniden indeksleme ve
görünmez enjeksiyon (§2b).

**Denetimin bir noktasında ondan ayrılıyorum:** somut model adları
(sürüm numaralarıyla) burada **kesin gerçek olarak yazılmaz.** Sağlayıcılar
katalogu bizim sürüm döngümüzden bağımsız değiştirir; bir model adını plana
gömmek, o ad emekliye ayrıldığı gün planı yanlış yapar. Bağlayıcı olan
**seçim yordamı ve eval kapısıdır** (§4.3), model kimliği yapılandırmadadır.

---

## 1. Boşluk analizi — doktrin var, uygulama yok

Ölçüldü, tahmin edilmedi.

| Katman | Doktrin | Uygulama |
| --- | --- | --- |
| Sağlayıcı/model kayıt defteri | `docs/14` §2 tanımlı | **YOK** |
| feature × model matrisi | `docs/14` §2 tanımlı | **YOK** |
| Tool allowlist / PDP zinciri | `docs/14` §3, §6 tanımlı | **YOK** |
| İnsan onayı (backend zorlaması) | `docs/14` §4 tanımlı | **YOK** |
| Kill switch, cost cap, redaction | `docs/14` §3 tanımlı | **YOK** |
| Eval seti (regresyon) | `docs/14` §3 tanımlı | **YOK** |
| Komut merkezi | `docs/14` §9a — 14 adımlı akış | Kabuk var, **her kontrol devre dışı** |
| Sayfa içi AI eylemleri | `docs/32` 61 modül | **YOK** (boş kart 2026-08-27'de kaldırıldı) |

**AI ile ilgili üretim PHP dosyası sayısı: 0.**

Bu bir kusur değil, bir SIRA meselesiydi: doktrin önce yazıldı. Ama artık
sıra geldi ve şu risk gerçek: doktrin uygulanmadan büyüdükçe, uygulama
geldiğinde doktrini karşılamayan bir kestirme cazip hâle gelir.

### 1.1 Sahibinin eklediği ve doktrinde OLMAYAN üç şey

| Talep | Doktrinde | Sonuç |
| --- | --- | --- |
| **Çoklu HESAP** (Claude 1/2/3, Gemini 1/2/3, OpenAI 1/2/3) | Yok — yalnız sağlayıcı vardı | Yeni: hesap havuzu, kota/limit dağıtımı, sağlıksız hesabın devre dışı kalması |
| **Kullanıcının model seçmesi** | Yok — matris otomatik çözüyordu | Yeni: seçim yüzeyi + matrisin sınırları içinde kalması |
| **Küçük açık kaynak modeller, basit VPS'te yerel** | Yok | Yeni: yerel çıkarım katmanı ve hangi işin oraya gideceği |

---

## 2. Unknown unknowns — sormadığımız için görmediğimiz riskler

> **Kanonik kayıt `docs/16`'dır.** Denetim haklı olarak uyardı: iki ayrı
> unknown-unknown kaydı tutmak, ikisinin bir gün ayrışması demektir. Aşağıdaki
> UNK-01..18 maddeleri `docs/16` §AI'daki `AI-01..16` satırlarına **taşındı**
> ve orada sahibi, tetikleyicisi ve containment'ı ile yaşıyorlar. Burada
> kalanlar, o satırların **gerekçesidir** — kayıt değil, açıklama.


Bunlar "bilmediğimizi bilmediğimiz" sınıfından; her biri ürünü sessizce
bozabilir ve hiçbiri bugünkü planda yoktu.

### UNK-01 — Hesap havuzu bir güvenlik SINIRIDIR, yalnız kota değil
Üç Claude hesabını sırayla kullanmak "daha çok kota" gibi görünür. Ama
hesaplar arasında **prompt cache, konuşma geçmişi ve organizasyon ayarları**
paylaşılmaz. Bir tenant'ın isteği bir hesaba, devamı başkasına giderse
önbellek ıskalanır (maliyet artar) ve bazı sağlayıcılarda oturum bağlamı
kaybolur. **Karar: tenant → hesap eşlemesi YAPIŞKAN olmalı**, rastgele değil.

### UNK-02 — Aynı prompt farklı modelde farklı ŞEMA döndürür
"Ürün açıklaması üret" üç modelde üç farklı JSON üretir. Şema doğrulaması
olmadan bu, arayüzde sessiz bozulmadır. **Karar: her yetenek bir JSON
şemasına bağlanır; şemaya uymayan cevap BAŞARISIZ sayılır**, kullanıcıya
gösterilmez.

### UNK-03 — Sağlayıcı çöktüğünde ürün de çökmemeli, ama SESSİZCE de bozulmamalı
Fallback zinciri (Claude → Gemini → yerel) iyi görünür. Tehlikesi: kullanıcı
hangi modelin cevapladığını bilmez ve kalite dalgalanır. **Karar: fallback
görünür olur** — "bu öneri yedek modelden geldi".

### UNK-04 — Menü içeriği KİŞİSEL VERİ içerebilir
"Şef Ayşe'nin özel tarifi", müşteri yorumları, personel adları. Prompt'a
giden her şey sağlayıcıya gider. **Karar: redaction bir Faz 1 işidir**, sonra
eklenecek bir güvenlik katmanı değil.

### UNK-05 — Yerel model, sağlayıcıyla AYNI ARAYÜZ arkasında olmalı
Yerel model "ucuz alternatif" diye ayrı bir yola konursa, iki kod yolu
oluşur ve biri her zaman geride kalır. **Karar: yerel model de bir
sağlayıcıdır**, aynı port arkasında.

### UNK-06 — Maliyet tavanı TENANT başına olmalı, global değil
Global tavan, bir tenant'ın tüketimiyle diğerlerinin AI'sını kapatır.
**Karar: bütçe tenant başına, ve dolduğunda ürün çalışmaya devam eder** —
yalnız AI önerileri durur (`docs/49` §10 ile aynı ilke: kota dolunca canlı
menü kesilmez).

### UNK-07 — Prompt enjeksiyonu MENÜ İÇERİĞİNDEN gelir
Restoran sahibi ürün açıklamasına "önceki talimatları yoksay" yazabilir —
ya da bir saldırgan, halka açık bir alandan. AI o metni özetlerken talimat
sanabilir. **Karar: kullanıcı içeriği prompt'ta VERİ olarak işaretlenir**,
talimat olarak değil.

### UNK-08 — Değerlendirme (eval) olmadan model değişimi kör bir bahistir
"Gemini 3 çıktı, geçelim" demek, ölçüm yoksa kalite kumarıdır. **Karar: her
yetenek için küçük bir altın küme**; model değişimi o kümeden geçmeden
yayınlanmaz.

### UNK-09 — AI çıktısı YAYINLANMIŞ menüye giderse geri alma zorlaşır
Bir AI çevirisi yayınlanır ve QR ile dağıtılırsa, geri alma yalnız veritabanı
işi değildir. **Karar: AI çıktısı taslakta kalır; yayın ayrı ve insan
onaylıdır** (`docs/49` Faz 5 publication snapshot ile aynı omurga).

### UNK-10 — Basit VPS'te yerel model, ASIL işi aç bırakabilir
Aynı sunucuda PHP-FPM, PostgreSQL ve bir LLM. Model bellek ve CPU'yu alırsa
menü sayfası yavaşlar. **Karar: yerel çıkarım ayrı bir süreç ve KAYNAK
SINIRLI**; sınır aşılırsa AI reddedilir, menü etkilenmez.

---

## 2b. Denetimin eklettiği unknown-unknown sınıfları

UNK-01..10 yetersizdi. Bağımsız denetim altı sınıf daha gösterdi; hepsi
gerçek ve hiçbiri ilk sürümde yoktu.

### UNK-11 — Model tedarik zinciri
İndirilen bir model dosyası kod kadar güvenilmez bir girdidir. **Karar:**
lisans, sağlama (checksum) ve tam revizyon kaydedilir; doğrulanmayan dosya
yüklenmez. Model manifesti sürüm kontrolündedir, dosyanın kendisi değil.

### UNK-12 — Nicemleme (quantization) kayması
Aynı model, farklı nicemleme düzeyinde farklı kalite verir. Sessizce
güncellenirse çıktı bozulur ve kimse fark etmez. **Karar:** nicemleme düzeyi
model kimliğinin PARÇASIDIR; değişirse eval tekrarlanır.

### UNK-13 — Tenant'lar arası gömme ve önbellek sızıntısı
Vektör alanı ve sonuç önbelleği tenant kimliği taşımazsa, bir restoranın
ürünleri başkasının aramasında çıkar. **Karar:** tenant kimliği vektör
alanının, önbellek anahtarının ve getirme filtresinin zorunlu parçasıdır —
sonradan eklenen bir `WHERE` değil.

### UNK-14 — Görünmez enjeksiyon PDF/görselin İÇİNDEDİR
Beyaz üstüne beyaz metin, görünmez katman, meta veri. OCR onu okur ve model
talimat sanabilir. **Karar:** OCR çıktısı da kullanıcı içeriğidir; veri
olarak işaretlenir (UNK-07 ile aynı kural, ama kaynağı farklı).

### UNK-15 — ECA fırtınası ve yinelenen iş
Bir kural başka bir kuralı tetikleyebilir; yeniden deneme aynı taslağı ikinci
kez uygulayabilir. **Karar:** her AI işinin **idempotency anahtarı** var;
ECA özyinelemesi derinlik sınırlı ve tenant başına oran sınırlı.

### UNK-16 — Gömme yeniden indeksleme
Gömme modeli değişince ESKİ vektörler yeni sorgularla karşılaştırılamaz.
**Karar:** vektör kaydı model kimliğini taşır; model değişimi bir yeniden
indeksleme işidir ve maliyeti planlanır.

### UNK-17 — İnceleme yığılması ve otomasyon yanlılığı
"İnsan onaylar" bir güvence değildir: yüz öneri gelirse insan hepsini
okumadan onaylar. **Karar:** öneri hacmi sınırlı; güven düşükse öneri
GÖSTERİLMEZ. Az ve doğru öneri, çok ve gürültülü öneriden iyidir.

### UNK-18 — Alerjen iddiasının hukuki ağırlığı
**En kritik sınır.** AI yalnız **"aday alerjen"** gösterebilir.

| İzin verilen | YASAK |
| --- | --- |
| "Bu üründe süt olabilir — kontrol edin" | "Bu ürün alerjensizdir" |
| "Tarif tereyağı içeriyor" | "Vegan" etiketini otomatik koymak |
| Adayı işaretlemek | Çapraz bulaşma hakkında herhangi bir iddia |

Çapraz bulaşma menü metninden **çıkarılamaz** — mutfak pratiği bilgisi
gerektirir ve bir model onu bilemez. Yanlış "alerjensiz" iddiası bir sağlık
olayıdır ve hukuki sorumluluk doğurur.

Aynı sınır fiyat için de geçerlidir: **model fiyat uydurmaz.** Okunamayan
fiyat `uncertain` ile gelir; sıfır ya da tahmin olarak değil.

---

## 3. Faz 1 çekirdeği — AI Capability Plane

### 3.1 Tek port DEĞİL — yetenek portu matrisi

İlk sürüm `TextGenerationPort` öneriyordu. **Yanlıştı.** OCR, gömme, görme
çıkarımı, sınıflandırma, yeniden sıralama ve tool intent metin üretimi
değildir; tek portun arkasına konduklarında sağlayıcı bağımsızlığı değil,
**yeteneklerin birbirine karışması** üretilir — ve şema, maliyet, gecikme,
gizlilik profilleri birbirinden çok farklı olduğu için o karışım sonradan
ayrılamaz.

```
Application/Ai/Port
  StructuredGenerationPort   şemaya bağlı metin üretimi
  OcrPort                    görüntü/PDF → metin + kutu koordinatları
  EmbeddingPort              metin/görsel → vektör
  VisionExtractionPort       görüntü → yapılandırılmış kayıt
  ClassificationPort         etiket + güven
  RerankPort                 aday sıralama
  ToolIntentPort             doğal dil → typed komut ADAYI (yürütmez)
  EvaluationPort             altın kümeye karşı ölçüm
```

Destek sözleşmeleri:

```
ProviderConnectionVault    kimlik bilgileri ve bağlantılar
CapabilityRouter           yetenek + kısıt → aday model
AiArtifactRepository       üretilen her şey, kaynağıyla
AiBudgetPort               tenant başına bütçe
```

`modules/ai-platform.md` bugün `AIPort::invoke(feature, model, input, schema)`
diyor. Bu imza korunur ama **plane'in dış yüzü** olur; içeride yukarıdaki
portlara dağıtılır. Modül belgesi buna göre güncellenir.

### 3.2 Sağlayıcı hiyerarşisi — `ANTHROPIC_KEY_1/2/3` değil

İlk sürümdeki numaralı anahtar dizisi yanlış soyutlamaydı. Depoda zaten
daha doğrusu vardı: `modules/ai-provider-account-vault.md`. **O kanoniktir.**

```
Provider                 anthropic | google | openai | local
  └─ Connection          resmi API projesi/workspace/service account (N adet)
       └─ ModelDeployment   tam model revizyonu + uç nokta
            └─ CapabilityRoute  yetenek → aday dağıtım
```

**Bağlayıcı kural — tüketici aboneliği kimlik bilgisi DEĞİLDİR.** ChatGPT
Plus/Pro ya da Claude.ai aboneliği API kullanımını kapsamaz; API ayrı ürün ve
ayrı faturalamadır. Vault modülü bunu zaten yasaklıyor (§149) ve bu belge o
yasağı tekrar etmez, ona **uyar**.

Hesap sayısı koda gömülmez: `N` bağlantı, çalışma zamanında yapılandırılır.
"Üç Claude hesabı" bir yapılandırma verisidir, bir mimari sabit değil.

### 3.3 Yapışkanlık — dar ve gerekçeli

İlk sürüm "tenant → hesap DAİMA yapışkan" diyordu ve bunu bir **güvenlik
sınırı** gibi sunuyordu. İkisi de fazla kesindi.

**Düzeltme:** tenant izolasyonu hesap seçiminde değil, **veri, önbellek,
getirme ve politika** katmanlarında sağlanır. Hesap seçimi bir izolasyon
mekanizması değildir ve öyle sunulursa yanlış güven verir.

Yapışkanlık yalnız şu üç sebepten biri varsa uygulanır:

| Sebep | Neden |
| --- | --- |
| Sağlayıcı prompt önbelleği | Önbellek bağlantıya bağlıdır; sıçrama maliyeti artırır |
| Veri ikametgâhı (residency) | Bağlantı bölgeye bağlıysa seçim serbest değildir |
| Sözleşme/kota taahhüdü | Belirli bağlantıya bağlı taahhüt varsa |

Bunların hiçbiri yoksa yönlendirme serbesttir ve sağlıklı bağlantıyı seçer.

### 3.4 Kaynak modeli — model bir BAŞVURU KAYNAĞI DEĞİLDİR

Bu, denetimin en değerli maddesi ve ürün için en riskli olanı.

**Kaynak şunlardır:** yüklenen belge/fotoğraf, kanonik menü kaydı, restoranın
doğruladığı içerik, mevzuat/ontoloji kaydı. **Model yalnız bir çıkarım
motorudur.**

Bu yüzden AI'nın ürettiği her kayıt şunları taşır:

```
source_refs[]      hangi dosya, hangi sayfa, hangi koordinat (bbox)
file_hash          kaynağın sağlaması
model_id           tam revizyon
prompt_version     prompt sürümü
schema_version     çıktı şeması sürümü
confidence         alan bazında
uncertain          alan bazında bayrak
```

Bunlar olmadan "bu fiyat nereden geldi" sorusu cevapsız kalır — ve o soru
menü yayınlandıktan sonra sorulur.

### 3.5 İşletim katmanı — bugün YOK

Denetim haklı: `docker/supervisord.conf` yalnız `php-fpm` ve `nginx`
çalıştırıyor. AI için gereken ve bugün bulunmayanlar:

| Bileşen | Neden |
| --- | --- |
| `queue-worker` servisi | AI işleri istek döngüsünde çalışamaz |
| Dead-letter kuyruğu | Başarısız iş sessizce kaybolmaz |
| Zaman aşımı + iptal | Kullanıcı vazgeçtiğinde iş de durur |
| **Idempotency anahtarı** | Yeniden deneme aynı taslağı İKİ KEZ uygulamaz |
| `ai-local` sidecar | Kaynak sınırlı ayrı süreç |
| Devre kesici (circuit breaker) | Sağlayıcı çöktüğünde kuyruk şişmez |

### 3.6 Faz 1 kabul ölçütü

1. **Hiçbir sağlayıcı bağlı değilken ürün TAM çalışır.**
2. Sahte sağlayıcıyla bütün zincir uçtan uca koşar (CI'da).
3. Şemaya + anlamsal doğrulamaya uymayan cevap kullanıcıya ULAŞMAZ.
4. Her AI kaydı kaynağını (§3.4) taşır.
5. Kill switch: global ve tenant başına; ürün etkilenmez.
6. Bütçe tenant başına; dolunca AI durur, ürün durmaz.
7. **Asgari eval fixture kümesi mevcut** — boş değil.
8. Kuyruk işçisi, dead-letter ve idempotency çalışıyor.
9. Denetim: bağlantı, model revizyonu, token, maliyet, gecikme.

## 4. Yerel küçük modeller — 32 GB sunucuda ne gerçekten çalışır

Sahibinin kararı: **önce yerel modeller, MVP'de gerçekten fayda sağlayanlar
indirilip çalıştırılacak.** Sunucuda 32 GB RAM var.

### 4.1 32 GB bir HIZ kanıtı değildir

İlk sürüm "~10–40 ms" gibi sayılar yazıyordu. **Bunlar ölçülmedi ve
kaldırıldı.** Bellek modelin sığıp sığmadığını söyler; hızı söylemez. Hızı
belirleyen şunlardır ve hiçbiri bilinmiyor:

- CPU çekirdek sayısı ve AVX/AVX-512 desteği
- Disk türü (model yükleme ve mmap davranışı)
- **Aynı anda koşan PHP-FPM ve PostgreSQL yükü**
- Eşzamanlı istek sayısı

**Kural:** gecikme hedefleri gerçek netcup sunucusunda ölçüldükten sonra
yazılır. Ölçülmemiş sayı bu belgede yer almaz.

### 4.2 Bellek bütçesi — 32 GB nasıl bölünür

| Dilim | Ayrılan | Gerekçe |
| --- | --- | --- |
| İşletim sistemi + PostgreSQL + PHP-FPM + proxy + dosya önbelleği | ~12 GB | Ürünün kendisi; AI için kısılamaz |
| `ai-local` sürekli kullanım tavanı | ~8 GB | Normal çalışma |
| `ai-local` sert sınır | ~10 GB | Aşılırsa süreç reddeder, öldürülmez |
| Boş güvenlik payı | ≥ 8 GB | Ani yük ve sayfa önbelleği |

**Aynı anda tek üretken model.** Bağlam 4K–8K. Üretken işler arka plan
kuyruğunda, eşzamanlılık 1.

Sebebi UNK-10: model belleği alırsa menü sayfası yavaşlar. Sert sınır, AI
isteğinin reddedilmesini sağlar — sunucunun takılmasını değil.

### 4.3 Model seçimi — ad değil, YORDAM bağlayıcıdır

Bu belge **somut model adı ve sürümü sabitlemez.** Sağlayıcılar katalogu
bizim sürüm döngümüzden bağımsız değiştirir; bir adı plana gömmek, o ad
emekliye ayrıldığı gün planı yanlış yapar.

Bağlayıcı olan seçim yordamıdır:

| Adım | Kural |
| --- | --- |
| 1 | Aday, **açık lisanslı** olmalı (kapılı/gated lisans varsayılan olmaz) |
| 2 | Aday, model kartıyla birlikte kaydedilir: lisans, boyut, sağlama, revizyon |
| 3 | Aday, **Türkçe restoran fixture'larıyla** eval'den geçmeli |
| 4 | Nicemleme (quantization) düzeyi kaydedilir — değişirse eval TEKRARLANIR |
| 5 | Kabul edilen model `config/ai.php`'ye yazılır; kod model adı bilmez |

### 4.4 MVP'de hangi YETENEKLER yerel çalışır

Ayrım "küçük/büyük" değil, **kodlayıcı/üretken**:

| Yetenek | Sınıf | MVP | Not |
| --- | --- | --- | --- |
| Görsel düzeltme, kalite, near-duplicate | **Model değil** (`libvips` + pHash) | ✅ Zorunlu | Daima ilk adım; LLM'den önce |
| **OCR** (metin + kutu koordinatları) | Kodlayıcı | ✅ Zorunlu | Menü çıkarımının deterministik yarısı; kutu koordinatları kaynak izi için şart (§3.4) |
| **Çok dilli metin gömme** | Kodlayıcı | ✅ | Yinelenen ürün, anlamsal arama, kategori adayı |
| Dil algılama | Kodlayıcı | ✅ | Çeviri kuyruğunu besler |
| Sınıflandırma | Kodlayıcı | ✅ | Vejetaryen/vegan adayı, "yemek mi logo mu" |
| Yeniden sıralama | Kodlayıcı | ⬜ Stage 2 | Arama kalitesi |
| **Görsel gömme** | Kodlayıcı | ⬜ Stage 2 | Görsel–ürün uyumu; pHash'in YERİNE değil ÜSTÜNE |
| Kısa taslak üretimi | Üretken | ⚠️ Sınırlı | Arka planda, eşzamanlılık 1. **Fiyat ya da alerjen otoritesi olamaz** |

### 4.5 Çalışma zamanı ve dağıtım profilleri

Yerel model bir **sağlayıcıdır** (UNK-05), ayrı kod yolu değil. OpenAI-uyumlu
bir HTTP uç noktası arkasında durur — böylece aynı adaptör hem bulut hem
yerel için çalışır.

**Ama tam uyumluluk varsayılmaz:** yerel sunucular OpenAI API'sinin tamamını
uygulamaz ve çok kipli destek çoğu zaman deneyseldir. Bu yüzden bir
**uyumluluk (conformance) katmanı** gerekir: hangi alanların desteklendiği
sınanır, desteklenmeyen yetenek o sağlayıcı için aday olmaz.

Deponun paylaşımlı barındırmayı da desteklediği unutulmamalı. **Üç profil:**

| Profil | Yerel AI | Kullanım |
| --- | --- | --- |
| `shared-host` | **Kapalı** | Paylaşımlı barındırma; sidecar varsayılamaz. Ürün deterministik çalışır, AI yalnız uzak uç noktayla |
| `vps-ai-32gb` | Açık | `ai-local` + `queue-worker` ayrı servisler |
| `private-gpu` | Açık | Enterprise; Stage 6 |

**Model dosyaları sürüm kontrolünde DEĞİL**: `install.sh` indirir, sağlaması
doğrulanır, revizyon kaydedilir. Depoya yarım GB koymak klonlamayı ve CI'yı
kalıcı bozar.

## 4b. Büyük API modelleri — yerel modelin yapamadıkları

### 4b.1 Görselden menü çıkarma (sahibinin eklediği)

Sahibi netleştirdi: **yalnız PDF değil — resim, fotoğraf, grafik.**
Restoran sahibinin elinde çoğu zaman PDF yoktur; telefonla çekilmiş bir
menü fotoğrafı vardır.

Bu, iki yarımlı bir iştir ve ikisi de gereklidir:

```
DETERMİNİST YARIM                   AI YARIMI
─────────────────                   ─────────
görüntü düzeltme (perspektif)   →   görme modeli okur
kontrast/eğrilik                    → yapılandırılmış JSON üretir
OCR ham metin                       → kategori/ürün/fiyat ilişkisini kurar
                                    → belirsiz alanları İŞARETLER
```

**Kritik kural: model belirsizliği gizlemez.** Okunamayan bir fiyat "0" ya da
uydurma bir sayı olarak gelmez; `uncertain: true` ile gelir ve arayüz onu
kullanıcıya sorar. Uydurulmuş bir fiyat menüye girerse, kullanıcı bunu ancak
müşteri şikâyet edince öğrenir.

| Girdi | Ele alınışı |
| --- | --- |
| PDF (metin katmanlı) | OCR gereksiz; doğrudan metin + yapı |
| PDF (taranmış) | OCR + görme modeli |
| Fotoğraf (telefon) | Perspektif düzeltme + görme modeli |
| Ekran görüntüsü | Doğrudan görme modeli |
| Grafik/tasarım (PNG) | Görme modeli |
| Birden çok sayfa/fotoğraf | Sayfa sırası kullanıcıya doğrulatılır |

**Sağlayıcı:** görme yeteneği Gemini'de başlar (ucuz, güçlü), yetmezse
OpenAI, en son Claude. Yerel model bu işi yapamaz — 4.1'deki dürüst sınır.

### 4b.2 Büyük modellerin yaptığı diğer işler

| İş | Neden yerel model yapamaz |
| --- | --- |
| **Menü tonunda çeviri** | Kısa metin değil, marka sesi; küçük model düzleştirir |
| **Uzun bağlamlı tutarlılık denetimi** | "84 üründe fiyat mantığı tutarlı mı" — tüm menü tek bağlamda |
| **Analitik anomali açıklaması** | "Taramalar %40 düştü" → sebep hipotezi, veriyle bağ kurma |
| **Doğal dil komut merkezi** | `docs/14` §9a 14 adımlı akış; akıl yürütme gerekir |
| **Yayın öncesi risk taraması** | Eksik çeviri + eksik görsel + fiyat anomalisi, birlikte |
| **Alerjen çıkarımı (metinden)** | "İçinde tereyağı geçen tarif" → süt alerjeni; bilgi gerektirir |

---

## 5. Token optimizasyonu — ayrı bir servis

Sahibinin talebi. Bu bir "ipucu" değil, **ölçülen ve zorlanan bir katman**;
maliyetin tek başına en büyük kaldıracı.

`Application/Ai/TokenOptimizer` yedi işi yapar:

| # | Teknik | Kazanç |
| --- | --- | --- |
| 1 | **Model doğru boyutlandırma** | En küçük model, eval'den geçtiği sürece. Çoğu iş için Haiku sınıfı yeterli |
| 2 | **Prompt önbelleği** | Sabit ön ek (sistem yönergesi, şema) önbelleğe alınır — tenant→hesap yapışkanlığı bunun ön koşulu (UNK-01) |
| 3 | **Getirme, doldurma değil** | Tüm menüyü prompt'a koymak yerine gömme ile ilgili 5 ürünü getir |
| 4 | **Bağlam budama** | Yetenek için gereksiz alanlar (id, timestamp) prompt'a girmez |
| 5 | **Şema sıkılığı** | Çıktı şeması dar; model gereksiz açıklama üretmez |
| 6 | **Toplu işleme** | 20 ürünün alt metni tek istekte |
| 7 | **Yinelenen iş önleme** | Aynı girdi için sonuç önbelleği (içerik sağlaması anahtarlı) |

**Ölçülür:** her çağrı için giriş/çıkış token'ı ve kuruş cinsinden maliyet
denetim kaydına yazılır. Optimizasyon iddiası ölçülmeden yapılmaz.

**Skill olarak da açılır:** `skills/token-optimization.md` — geliştirme
hattındaki AI'nın da aynı kurallara uyması için.

---

## 6. Yönerge katmanı — MCP, skills, .md ve ECA kuralları

Sahibinin talebi: *"mcp'ler, skill'ler, .md dosyaları ile AI yönergeleri,
sınırlar, kurallar, eca rules."*

### 6.1 Dört ayrı şey — karıştırılmaz

| Katman | Nedir | Nerede yaşar |
| --- | --- | --- |
| **Yönerge (.md)** | AI'nın uyacağı yazılı kural; insan da okur | `ai/guidelines/*.md` |
| **Skill (.md + şema)** | Talep üzerine yüklenen yetenek tanımı: ne yapar, neyi yapamaz, hangi araçları çağırabilir | `ai/skills/*.md` |
| **MCP server** | Dış araç erişimi — kendi allowlist'iyle | `config/ai-mcp.php` |
| **ECA kuralı** | Olay → koşul → eylem. AI'yı NE ZAMAN çağıracağımız | `config/ai-eca.php` |

**Hiçbiri "always-on" değildir** (`docs/14` §5). Yönerge bile bir yeteneğe
bağlanır; her prompt'a her kuralı eklemek hem token yakar hem modeli
körleştirir.

### 6.2 Skill dosyasının zorunlu bölümleri

```markdown
---
name: menu-extraction
capability: menu.extract
requires_human_approval: true
allowed_tools: [media.read, menu.draft.write]
forbidden: [menu.publish, media.delete, billing.*]
schema: menu-extraction.v1
escalation: [local, gemini, openai]
---
### Ne yapar
### Ne YAPAMAZ
### Belirsizlik nasıl işaretlenir
### Örnekler (eval kümesinin çekirdeği)
```

`forbidden` listesi bir yorum değil, **çalışma zamanında zorlanır**: listede
olmayan araç çağrısı reddedilir ve denetime yazılır (`docs/14` §6).

### 6.3 ECA kuralları — AI'yı ne zaman çağırırız

Proaktif öneri **yalnız gerçek sinyal varsa** görünür (`docs/50` Faz 10).
ECA bunu bir kurala çevirir:

| Olay | Koşul | Eylem |
| --- | --- | --- |
| `menu.item.created` | alt metin yok VE görsel var | Alt metin önerisi kuyruğa |
| `menu.publish.requested` | eksik çeviri > 0 | Yayın öncesi tarama, insan onayı |
| `media.uploaded` | benzer gömme mesafesi < eşik | "Bu görsel zaten var" önerisi |
| `analytics.weekly` | değişim > %30 | Anomali açıklaması |
| `ai.budget.threshold` | %80 | Sahibine uyarı; %100'de AI durur, ürün durmaz |

ECA motoru **AI'yı çağırmaz, kuyruğa koyar.** Kullanıcı beklerken model
çalışmaz; öneri hazır olduğunda görünür.

## 7. Rol bazında AI — kim neyden yararlanır

Depodaki gerçek roller ölçüldü: restoran tarafında **Owner / Member /
Editor** (`MembershipRole`), platform tarafında **SuperAdmin**
(`PlatformRole`), 13 izinle (`Permission`).

**Temel kural:** AI hiçbir zaman rolün YAPAMADIĞI bir şeyi yapamaz. Öneri
üretmek serbesttir; uygulamak izne bağlıdır ve izin **sunucuda yeniden**
doğrulanır (`docs/14` §9a adım 13). Editor'a "menüyü yayınla" önerisi
gösterilebilir — ama düğme onda çalışmaz; öneri sahibine iletilir.

### 7.1 Restoran tarafı

| Rol | İzinleri | AI'nın onun için yaptığı iş | AI'nın YAPAMADIĞI |
| --- | --- | --- | --- |
| **Owner** | Hepsi | Yayın öncesi risk taraması; plan/kullanım açıklaması ("bu ay neden arttı"); analitik anomali açıklaması; ekip yetkisi önerisi (`docs/14` §9a akışı, insan onaylı + step-up) | Yayınlamak, ödeme, rol değiştirmek — hiçbiri onaysız |
| **Editor** | `menu.manage`, `qr.*`, medya | **En çok yararlanan rol.** Fotoğraf/PDF'ten menü çıkarma; ürün açıklaması taslağı; alt metin; alerjen önerisi; çeviri taslağı; yinelenen ürün/görsel tespiti; kırpma odak noktası | `menu.publish` yok → yayın önerisi üretir, Owner'a gider |
| **Member** | `*.view` | Okuma yüzeyi: menüde anlamsal arama ("içinde süt olan ürünler"); analitik özetini sade dille okuma | Hiçbir mutasyon önerisi gösterilmez — gösterilse boş bir umut olurdu |

**Neden Editor en çok yararlanır:** günlük iş onun elinde. Menü doldurmak,
görsel yüklemek, çeviri girmek — AI'nın gerçekten saat kazandırdığı işler
bunlar. Owner ise ayda birkaç kez karar verir; ona lazım olan üretim değil,
**özet ve risk**.

### 7.2 Platform tarafı (SuperAdmin)

Bu roldeki kişi restoran içeriğiyle ilgilenmez; **filoyu** yönetir.

| İş | AI'nın katkısı |
| --- | --- |
| Destek | Bir tenant'ın son 20 hatasını okuyup olası sebep; "bu menü neden yayınlanamıyor" |
| Sağlık | İşleme kuyruğundaki başarısızlıkların gruplanması: 40 ayrı hata mı, tek bir kök neden mi |
| Kötüye kullanım | Yüklenen medyada moderasyon işareti; anormal kota tüketimi |
| Maliyet | Hangi tenant hangi yeteneği ne kadar kullanıyor; hangi yetenek daha ucuz modele inebilir (eval geçerse) |
| İçerik incelemesi | Halka açık menüde riskli içerik önerisi — **karar insanın** |

**Sert sınır — tenant izolasyonu AI'da da geçerlidir.** SuperAdmin'in AI'sı
bir tenant'ın içeriğini başka bir tenant'ın bağlamına KOYAMAZ. "Bütün
tenant'ları karşılaştır" gibi bir istek, ancak toplulaştırılmış (agrega)
veriyle cevaplanır; ham içerik çapraz okunmaz (`docs/14` §3 tenant
isolation).

### 7.3 Geliştirme hattı — ayrı bütçe, ayrı matris

Sahibinin kararı gereği Claude bu hatta birincildir: **sistemin kendi
kodlama ve teknik gelişimi.** Bu hat ürün çalışma zamanı değildir:

- Tenant bütçesinden beslenmez
- Yetenek matrisinde görünmez
- Restoran verisine erişmez — geliştirme ortamında çalışır
- Kendi yönergeleri `ai/guidelines/` ve `skills/` altında

İki hattın karışması, en pahalı modelin en sık işi yapmasına yol açardı.

## 8. Stage 1–8 AI yol haritası

**İlk sürümün en büyük kusuru buradaydı.** "Faz 2–10" diye sunulan tablo,
ürünün yaşam döngüsü değil `docs/49`'daki MEDYA planının kendi adımlarıydı.
Ürün genelindeki AI yol haritası fiilen yoktu.

Ürünün kanonik aşamaları `docs/18`–`docs/25`'tedir. Doğrusu:

| Stage | AI teslimi |
| --- | --- |
| **1 — MVP** | **Capability Plane** (§3): yetenek portları, N-bağlantılı vault, sahte sağlayıcı, yerel çalışma zamanı, Gemini/OpenAI/Claude adaptör sözleşmeleri, bütçe + kill switch + denetim + eval fixture'ları, kuyruk işçisi. **Tek görünür dikey:** fotoğraf/PDF → kaynaklı menü taslağı → insan incelemesi |
| **2 — Post-MVP** | Çeviri taslağı, ürün açıklaması, alt metin, yinelenen ürün/görsel, anlamsal arama, görsel gömme, yeniden sıralama, güvenli ECA tetikleyicileri |
| **3 — GTM** | Onboarding koçu, SEO/schema taslağı, kampanya metni, destek triyajı, çoklu bağlantı, kontrollü BYOK |
| **4 — PMF** | Geri bildirim kümeleme, onboarding sürtünme analizi, churn açıklaması, deney sonuçlarının kanıtlı özeti |
| **5 — Growth** | Şubeler arası katalog normalizasyonu, POS alan eşleme, kampanya segmentleri, zincir anomali analizi |
| **6 — Enterprise** | Veri ikametgâhı, müşteri model politikası, BYOK/KMS, özel uç nokta/on-prem, denetim dışa aktarımı, legal hold, model allowlist |
| **7 — Maturity** | ModelOps: champion/challenger, drift, AI SLO, kapasite ve maliyet tahmini, red-team, olay ve felaket tatbikatı |
| **8 — Exit-ready** | Sağlayıcı çıkış provası, prompt/model/veri kümesi soyağacı, lisans/IP dosyası, yeniden üretilebilir model manifestosu, devralma runbook'u |

**Ufuklar (kanonik sekiz aşamayı bozmadan):**
- **Ufuk 9:** Güvenli doğal dil komut merkezi (`docs/14` §9a) ve dış tool ekosistemi
- **Ufuk 10:** Onaylı çapraz-şube öğrenimi, gizlilik koruyan toplulaştırma

### 8.1 Medya planındaki AI satırları nereye düşer

`docs/49`'un fazları ürün aşaması değildir; medya modülünün kendi
adımlarıdır. AI karşılıkları şöyle eşlenir:

| `docs/49` fazı | AI işi | Ürün aşaması |
| --- | --- | --- |
| Faz 2 (alım) | Alt metin taslağı, dil algılama | Stage 2 |
| Faz 3 (sürüm/rendition) | Gömme ile yinelenen tespiti | Stage 2 |
| Faz 4 (kütüphane) | Anlamsal arama, akıllı koleksiyon | Stage 2 |
| Faz 5 (yayın) | Yayın öncesi risk taraması | Stage 2 |
| Faz 6 (teslim) | **Görsel/PDF'ten menü çıkarma** | **Stage 1** — tek dikey |
| Faz 8 (crop) | Akıllı kırpma | Stage 2 |

Menü çıkarımının Stage 1'e alınmasının sebebi sahibinin talimatıdır: *"MVP'de
AI çekirdeği çok güçlü olmalı."* Çekirdeğin gerçekten çalıştığını gösteren
tek şey, uçtan uca bir dikeydir.

### 8.2 Stage 1 dikeyi — kabul ölçütü

```
Fotoğraf/PDF yüklenir
  → libvips düzeltme + kalite kontrolü
  → OCR (metin + kutu koordinatları)
  → görme modeli → yapılandırılmış taslak
  → HER ALAN kaynağını taşır: dosya, sayfa, koordinat, güven
  → belirsiz alanlar İŞARETLİ
  → insan inceleme ekranı
  → onay → typed komut → yetki YENİDEN doğrulanır → taslak menü
```

**Belirsiz fiyat yayınlanamaz.** Bu bir uyarı değil, bir kapıdır.

## 9. Kanonik uzlaştırma ve ilk iş paketleri

### 9.1 Çelişki çözüldü

| Belge | Önce | Şimdi |
| --- | --- | --- |
| `docs/18` | "AI-destekli üretim özellikleri" tamamen non-goal | Daraltıldı: **çekirdek + tek dikey var**, geniş üretim özellikleri yok |
| `docs/26` | AI Platform Stage 1'de "pre-wired (kapalı)" | "**çekirdek + tek dikey**" |
| `docs/51` | Faz 1'de çekirdek | Aynı — ama artık Stage 1 ile aynı şeyi söylüyor |

Çözümün dayanağı sahibinin talimatıdır, benim tercihim değil.

### 9.2 İlk beş iş paketi

| # | Paket | Kapsam |
| --- | --- | --- |
| `AI-S1-01` | Yetenek kayıt defteri | Ayrı portlar (§3.1), sahte sağlayıcı, şema + denetim + **artifact modeli** (§3.4) |
| `AI-S1-02` | Yerel çalışma zamanı | `ai-local` sidecar, model manifestosu, OCR + gömme, kaynak sınırı, uyumluluk katmanı |
| `AI-S1-03` | Bağlantı vault'u | N bağlantı, Gemini gerçek adaptörü, OpenAI/Claude sözleşme adaptörleri, tüketici abonelik reddi |
| `AI-S1-04` | Menü çıkarımı dikeyi | Fotoğraf/PDF → OCR → yapılandırılmış taslak → kutu/güven → inceleme ekranı |
| `AI-S1-05` | İşletim | Bütçe, kill switch, kuyruk + dead-letter + idempotency, kaynak sınırı, eval, gözlemlenebilirlik |

### 9.3 Üç bağlayıcı entegrasyon testi

Paket başına 3–8 hedefli test, artı bu üçü:

1. **AI tamamen kapalıyken ürün çalışır.**
2. **Menü fotoğrafı taslağa dönüşür; belirsiz fiyat yayınlanamaz.**
3. **Sağlayıcı çöktüğünde / bütçe dolduğunda / tenant ihlalinde** güvenli
   deterministik yola düşülür.

Model kalitesi için 50 ayrı test değil, **12–20 anonimleştirilmiş menü
fixture'ı** aynı veri sürümlü eval içinde koşar.

### 9.4 Geri alma (rollback)

Global ve tenant kill switch; yerel servisi kapatma; bulut bağlantısını devre
dışı bırakma; AI kuyruğunu durdurma; bütün AI artifact'lerini taslakta tutma.

**Hiçbir geri alma yayınlanmış menüyü ya da deterministik akışı etkilemez.**

### 9.5 Sahibinin kararı gereken — ve gerekmeyen

Denetimin haklı bir tespiti daha: **Faz 1'i başlatmak için bulut hesap
sayısının bilinmesi ŞART DEĞİL.** Sahte + yerel sağlayıcıyla çekirdek hemen
geliştirilebilir; resmi bağlantılar geldikçe etkinleştirilir.

| # | Karar | Ne zaman |
| --- | --- | --- |
| 1 | Yerel modeller aynı VPS'te mi? (`vps-ai-32gb` mi `shared-host` mu) | **Faz 1 başlarken** — dağıtım profilini belirler |
| 2 | Hangi sağlayıcılarda gerçek API bağlantısı var | `AI-S1-03` sırasında; öncesinde gerekmez |
| 3 | Tenant başına aylık AI bütçesi | `AI-S1-05` |
| 4 | Kullanıcı model seçebilecek mi | Öneri: **hayır** — kullanıcı `Yerel/Gizli`, `Ekonomik`, `Hızlı`, `En yüksek kalite` profili seçer; tam model seçimi SuperAdmin ve eval promosyonundadır |
| 5 | AI çıktısı hangi dilde | Stage 2 |

**Yalnız 1. karar Faz 1'i bekletiyor.**

## 10. Ne ölçülecek — iddia edilmeyecek

AI planlarının en yaygın kusuru, kazancın hiç ölçülmemesidir. Bu plan üç şeyi
ölçer ve raporlar:

| Ölçüm | Nasıl |
| --- | --- |
| **Kalite** | Yetenek başına altın küme; model değişimi o kümeden geçmeden yayınlanmaz |
| **Maliyet** | Her çağrıda giriş/çıkış token'ı ve kuruş; tenant ve yetenek kırılımında |
| **Kazanılan zaman** | Öneri kabul oranı ve elle düzeltme miktarı. Kimse kabul etmiyorsa yetenek işe yaramıyordur |

Üçüncüsü en çok atlanan ve en önemlisidir: **kabul edilmeyen bir AI önerisi,
kullanıcıya zaman kaybettirmiştir.**
