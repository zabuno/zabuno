# AGENTS.md — laravelv01 çalışma kuralları

Bu dosya `laravelv01/` içinde çalışan tüm ajanlar (insan veya AI) için geçerlidir.
Kök dizindeki genel Codex/Claude yönlendirme talimatları bu dosyanın **üzerinde**
kalır; çelişki halinde kök talimatlar kazanır.

## 1. Bu bir planlama paketidir

- Hiçbir dosya çalışan kod, kurulmuş bağımlılık veya tamamlanmış entegrasyon iddia
  edemez. Her modül/stage dokümanı PLANNING ONLY etiketini taşımalıdır.
- `old/` içeriğine yazma yasaktır — yalnız okuma/referans amaçlı. `old/` bir arşivdir,
  kaynak değil; oradan yalnız ürün felsefesi/journey/iş kuralı/kapsam dersleri
  süzülüp yeni dille yazılır. Eski teknoloji seçimi (Django, FastAPI, MVVM, Filament,
  Astro vb.) yeni karar gibi sunulamaz.

## 2. Tek kanonik sahip, projeksiyon yaklaşımı

- Bir bilgi yalnız bir dosyada "sahiplenilir"; başka dosyalar ona bağlantı verir
  (göreli bir Markdown linkiyle — örn. `metin -> relative-path.md`, gerçek bir
  link hedefi değil, yalnız biçim anlatımı), tekrar etmez. Örn: modül matrisi
  `docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md`'de sahiplenilir; başka dosyalar oraya
  link verir.
- Yeni kanonik belge gerekiyorsa oluşturulabilir, ancak önce mevcut 33 dosyadan
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
- Yalnız şu sıra tamamlandıktan **sonra** — nihai bağımsız review **GREEN**
  **ve** owner'ın açık public yayın talebi birlikte sağlandığında — Codex
  Desktop MASTER standalone deposunu (`docs/31` §1) başlatabilir
  (`git init`), yalnız public allowlist'i (`docs/31` §2, §3) stage edebilir,
  commit edebilir, public `zabuno/zabuno` deposunu oluşturabilir ve push
  edebilir. Worker'lar (Claude dahil) bu Git eylemlerini asla kendileri
  üstlenmez veya öne çekmez.
- Root'ta kalan uncommitted değişiklikler (`old/` altına taşınmış haliyle) bu
  paketin sorumluluğu değildir; dokunulmaz — kesin sayısı bu belgede takip
  edilmez (kırılgan bir sayaç yerine kapsam dışı kuralı yeterlidir).

## 6b. İsimlendirme

- Yeni ürün adı **Zabuno**'dur. Legacy QR-menü projesinin/denemesinin eski adı
  bu külliyatın hiçbir yerinde — tarihsel bağlamda bile — **yazılmaz**; owner
  talimatı kesindir ("bu adı kullanma", `docs/31` §7). `old/` altındaki
  arşivlenmiş Django/FastAPI denemelerine yalnız "legacy QR-menü projesi/
  denemesi" olarak atıf yapılır; yeni mimari/isimlendirme kararlarına,
  namespace/paket/uygulama kimliği örneklerine **taşınmaz**.
- Public depo hedefi **`zabuno/zabuno`**'dur (`docs/31` §1); önceki çalışma
  turlarında kullanılan eski depo adı (legacy ürünün adını taşıyan format)
  terk edilmiştir.

## 7. Sınır

- Bu ajan seti kapsam/onay/rollback/nihai kabul kararı **veremez**. Bu kararlar
  görevi veren orkestratöre (Codex Desktop MASTER) ve nihayetinde owner'a aittir.
