# 00 — Provenance & Archive

**PLANNING ONLY.** Bu doküman, bu plan külliyatının nereden geldiğini ve eski proje
kökünün nereye taşındığını kayıt altına alır.

## 1. Girdi kaynakları

| Kaynak | Konum | Rol |
|---|---|---|
| Codex ana kapsam metni | Orkestratör oturum eki (attachment) — dosya adı/UUID public dokümanda taşınmaz (`docs/31` §7) | Kullanıcının orijinal QR Menü SaaS kapsam/gereksinim dokümanı — bu külliyatın birincil girdisi |
| Kök `AGENTS.md` (eski) | `old/AGENTS.md` (tarihsel arşivleme konumu — bkz. §6, bu depoda karşılığı yoktur) | Aynı kapsam metninin proje köküne önceden yerleştirilmiş kopyası |
| Kök `CLAUDE.md` (eski) | `old/CLAUDE.md` (tarihsel arşivleme konumu — bkz. §6, bu depoda karşılığı yoktur) | **[legacy]** Django tabanlı, önceki legacy QR-menü projesinin/denemesinin referans dokümanı (eski ürün adı owner talimatı gereği bu külliyatta hiçbir bağlamda yazılmaz) — yeni ürün adı **Zabuno**'dur (`docs/31` §7); teknoloji seçimleri taşınmaz, yalnız modül/rol/domain dersleri süzülür (`docs/30` postmortem ile ilişkili) |
| Görev talimatı (bu paketi tetikleyen) | Konuşma geçmişi | Sekiz aşamalı waterfall sırası, dosya listesi, mimari kararlar, donmuş kapsam kanıtları buradan alınmıştır |

## 2. Donmuş kapsam kanıtları (görev talimatından, doğrudan aktarım)

Bu paketi tetikleyen görev talimatı, ana kapsam metni ve lifecycle delta için
Codex tarafı ve bağımsız Claude tarafı SHA-256 değerlerini bildirmiştir; kök
işlem başlangıç HEAD taahhüdü de aynı şekilde doğrulanmıştır (`git rev-parse
HEAD` çıktısı eşleşti). Bu değerlerin kendisi (dört SHA-256 hash'i ve HEAD
commit id'si) yalnız yerel ham kanıt kaydında (`evidence/`, yalnız yerel —
public depoya taşınmaz) tutulur; bu doküman değerleri tekrar basmaz.

Bu değerler bu Claude oturumu tarafından yeniden hesaplanmamıştır; görevi veren
orkestratörün beyanı olarak kayda geçirilmiştir. Bağımsız doğrulama gerekiyorsa
Codex tarafı / Claude tarafı hash'lerinin aynı girdi metninden mi yoksa iki
farklı süzülmüş kapsam belgesinden mi üretildiği açıkça sorulmalıdır — bu açık
madde `docs/16-GAP-UNKNOWN-UNKNOWNS.md` ARCH-01'de kayıtlıdır; bu doküman şu an
bu ayrımı çözecek bağımsız erişime sahip değildir.

## 3. Arşivleme (Part A) özeti

Tam ham kanıt seti yalnız yerel `evidence/` dizinindedir (public depoya
taşınmaz, `docs/31` §5); sanitize edilmiş public özeti
`evidence/PUBLIC-ARCHIVE-ATTESTATION.md`'dir. Özet:

- 99 top-level girdi, tek tek (wildcard yok, içerik düzenleme yok) bir arşiv
  dizinine taşındı.
- İstisnalar (kökte kaldı): `.git`, `old`, `laravelv01`, `worktrees`.
- Bütünlük doğrulaması: 99/99 girdi için dosya sistemi meta verisi
  (tür/aygıt/inode/izin/boyut/sembolik-link-hedefi) taşımadan önce ve sonra
  birebir eşleşti (bkz. `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §2).
- Taşınan bir sembolik link, hedef metni byte-eş korunarak taşındı; göreli
  hedefin artık farklı bir mutlak yola çözülebileceği not edildi (aynı
  kaynakta, §3).
- Taşıma kapsamındaki bağlı yardımcı geliştirme çalışma alanları taşındı ve
  standart bir onarım komutuyla düzeltildi; hepsi sağlıklı (bkz.
  `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §4).
- Kökte taşıma sırasında çok sayıda commit edilmemiş değişiklik vardı;
  bunlara dokunulmadı, yalnız mevcut haliyle taşındı (bkz.
  `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §5).
- Git add/commit/push/merge çalıştırılmadı.
- Geri alma prosedürü tanımlı ama çalıştırılmadı (bkz.
  `evidence/PUBLIC-ARCHIVE-ATTESTATION.md` §8; tam prosedür detayı yalnız
  yerel).

## 4. `old/` ile bu külliyat arasındaki ilişki

`old/` salt referanstır. Bu külliyatın hiçbir dosyası `old/` içindeki bir dosyaya
"kaynak kod" olarak bağımlı değildir. `old/` içinden yalnızca şu sınıflar süzülüp
yeniden yazılır:

- Ürün felsefesi ("Beauty for everyone", self-service, tenant izolasyonu vb.) →
  [`docs/01-PRODUCT-CHARTER-SCOPE.md`](01-PRODUCT-CHARTER-SCOPE.md)
- Kullanıcı yolculukları ve roller → [`docs/02-JOURNEYS-PERSONAS-ROLES.md`](02-JOURNEYS-PERSONAS-ROLES.md)
- İş kuralları (para modeli, alerjen, publish/draft ayrımı vb.) → ilgili domain
  dokümanlarına dağıtılmıştır (bkz. `docs/29-TRACEABILITY-MATRIX.md`)
- Kapsam ve MVP sınırları → [`docs/18-STAGE-01-MVP.md`](18-STAGE-01-MVP.md)
- Planlama disiplini dersleri (waterfall, gap analizi biçimi) → bu külliyatın
  kendi yapısına (bkz. `docs/17-WATERFALL-LIFECYCLE-MASTER.md`, `docs/16-GAP-UNKNOWN-UNKNOWNS.md`)

Açıkça **taşınmayanlar**: Django/Python/DRF seçimi, FastAPI referansları, MVVM,
Filament (restoran paneli için), Astro, Alpine.js, mevcut veritabanı şeması,
mevcut URL yapısı. Bunların yerine `docs/03-ARCHITECTURE-DECISIONS.md`'de bağımsız
olarak Laravel + React + MVC kararı gerekçelendirilmiştir.

## 5. Bu dokümanın kanonik konumu

Bu dosya, provenance ve arşivleme konusunda **tek kanonik sahiptir**. Başka hiçbir
doküman arşivleme kanıtlarını veya donmuş hash değerlerini tekrar basmaz; yalnız
buraya link verir.

## 6. Güncel topoloji notu — tarihsel kayıt ile bugünü karıştırmayın

§1–§4'teki `old/`, `laravelv01` ve arşivleme-anı kök yapısı referansları
**tarihsel bir kayıttır**: Part A arşivleme işlemi yapıldığı andaki (bu
külliyatın henüz public bir depo olmadığı, pre-publication) kök düzenini
anlatır ve bu kayıt **değiştirilmez/silinmez** (`AGENTS.md` §4 kanıt disiplini
ile tutarlı).

Bu, **bugünün** topolojisi değildir. Bu külliyat artık public
[`zabuno/zabuno`](https://github.com/zabuno/zabuno) deposunun **kökünün
kendisidir**; güncel kökte `old/` veya `laravelv01` adlı bir dizin **yoktur**.
Tarihsel arşivin kendisi bu depodan tamamen ayrı, dış bir konumda tutulur ve
bu depoyla hiçbir Git ilişkisi yoktur (mutlak konum yalnız yerel
`.local/WORKSPACE-BOUNDARY.md`'de kayıtlıdır — bkz. `AGENTS.md` §6a; bu genel
kural `docs/31` §1a/§2'de de uygulanır). Yeni okuyucu §1–§4'ü **o anki**
düzenin kanıtı olarak okumalı, güncel dizin yapısı için `README.md` §Dizin
yapısı'na bakmalıdır.
