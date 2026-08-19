# 31 — Public Repository Governance

**PLANNING ONLY (doküman içeriği için) — ama depo hedefi artık current-state.**
Bu doküman, bu külliyatın tek bir public GitHub deposu — **`zabuno/zabuno`**
— olarak yayınlanma kuralının **tek kanonik kaynağıdır**. Yeni ürün adı
**Zabuno**'dur. Legacy QR-menü projesinin/denemesinin eski adı, owner'ın
kesin talimatı gereği ("bu adı kullanma") bu külliyatın **hiçbir yerinde —
tarihsel bağlamda bile — yazılmaz**; yalnız "legacy QR-menü projesi/denemesi"
ifadesiyle anılır (bkz. §7, §9).

## 1. Hedef depo

- Tek monorepo: **`zabuno/zabuno`**, **PUBLIC**. Bu külliyatın bulunduğu depo
  **zaten bu deponun kendisidir** — güncel kökte artık ayrı, gelecekte
  taşınacak bir "standalone root" beklentisi yoktur; bu dosyanın kendisi bu
  deponun içindedir (bkz. `README.md` §Dizin yapısı, `docs/00` §6 güncel
  topoloji notu).
- Önceki çalışma turlarında kullanılan, legacy ürün adını taşıyan eski depo
  adı **terk edilmiştir** — her public-plan referansı `zabuno/zabuno`'yu
  kullanır.

### 1a. Git yetkisi ve yayın sırası

Bu paket kapsamında (ve genel olarak bu külliyatın doküman-yazım paketlerinde)
Claude yazarı ve ondan bağımsız reviewer **sıfır** Git mutasyonu yapar
(`AGENTS.md` §6). Bu deponun ilk oluşturulması/ilk push'u önceden,
owner-yetkili bir MASTER adımıyla tamamlanmıştır — bu artık bir "gelecek
eylem" değil, gerçekleşmiş bir olgudur. Bundan **sonraki** her ek public
Git eylemi (yeni commit, branch, merge, ek push) için aynı disiplin
**geçerliliğini sürdürür**: fiili Git eylemi yalnız aşağıdaki iki koşul
**birlikte** sağlandığında Codex Desktop MASTER tarafından yapılır:

1. Nihai bağımsız review **GREEN** sonucu, ve
2. Owner'ın açık public yayın talebi.

Bu, her yeni doküman paketi için tekrar sıfırdan başlayan bir kural
**değildir** — depo bir kere kurulmuş olsa bile, ondan sonraki her yeni
değişiklik seti için worker'lar Git eylemini asla kendi başlarına öne çekmez
veya üstlenmez; fail-closed workspace/repo preflight (`AGENTS.md` §6a,
`.local/WORKSPACE-BOUNDARY.md`) her Git işleminden önce ayrıca uygulanır.

## 2. Yayınlanmayacaklar (kesin liste)

- Arşivlenmiş eski proje kökü (Django/FastAPI legacy denemeleri) — bu depoda
  güncel kökte böyle bir dizin (`old/`) zaten yoktur (`docs/00` §6); bu madde,
  gelecekte herhangi bir arşiv içeriğinin yanlışlıkla bu depoya staged
  edilmesine karşı **standing** bir yasaktır.
- Root Git history, raw local evidence, mutlak dosya yolları, attachment ID'leri.
- İç geliştirme-orkestrasyon aracının (hangi araç kullanılırsa kullanılsın)
  worktree/session/capability/panel iç detayları.
- Secret/token/credential — hiçbir biçimde.
- Lisanssız `imageoptimization` snapshot byte'ları (bkz. §6).

## 3. Yayınlanabilir (public-safe) küme

Kök `README.md`, kök `AGENTS.md`, kök `CLAUDE.md`, kök `.gitignore` (§5),
`docs/`, `modules/`, `skills/`, `templates/`, sanitize edilmiş provenance/
archive attestation (`evidence/PUBLIC-ARCHIVE-ATTESTATION.md`) ve upstream
provenance (`research/upstream/imageoptimization/UPSTREAM.md`) — yalnız
provenance metni, snapshot byte'ları değil.

## 4. Görünürlük ≠ lisans

Public görünürlük, open-source lisans izni **değildir**. Owner kararı
olmadan `LICENSE`/`COPYING` dosyası **eklenmez**. Bu külliyatın hiçbir yerinde
zımni bir lisans iddiası yoktur; depo public olsa bile "kullanılabilir/
kopyalanabilir" anlamına gelmez (bkz. GitHub resmi doküman:
`docs/28-SOURCE-REGISTER.md`'deki "GitHub public visibility" / "adding a
license" satırları).

## 5. `.gitignore` sözleşmesi

Bu deponun kök `.gitignore`'ında şu kurallar uygulanır:

```
/.local/                                  # ignore — yerel workspace/Git sınır sözleşmesi
                                           # (mutlak yol taşır, bkz. AGENTS.md §6a)
/evidence/*                              # ignore
!/evidence/PUBLIC-ARCHIVE-ATTESTATION.md # allow — tek istisna
/research/upstream/imageoptimization/snapshot/   # ignore (lisanssız)
# standart: secret/env/vendor/node_modules/cache/IDE çıktıları
```

`evidence/` altındaki ham kanıt dosyaları (archive-before.tsv, git-before.txt
vb.) **silinmez veya düzenlenmez** — yalnız `.gitignore` ile public depodan
**dışlanır**. Yerelde aynen kalırlar (`AGENTS.md` §1'deki dış arşive
dokunmama ilkesiyle aynı disiplin).

## 6. Upstream provenance — imageoptimization

`research/upstream/imageoptimization/UPSTREAM.md` **yayınlanabilir** —
snapshot'ın içeride araştırma amacıyla alındığını, ancak lisans dosyası
bulunmadığı için public depoya **dahil edilmediğini** açıkça belirtir
(`docs/16` LIC-01, `docs/28` §1). Snapshot'ın kendisi (byte içeriği)
`.gitignore` ile dışlanır ve bu paket kapsamında **silinmez/değiştirilmez**.

## 7. Sanitizasyon kuralı — legacy isim ve mutlak yol

`README.md`, `docs/00`, `AGENTS.md`, `CLAUDE.md` ve `UPSTREAM.md` şu içerikten
arındırılır:

- Mutlak dosya yolları (işletim sistemi kullanıcı ana dizinini içeren herhangi
  bir mutlak yol deseni — örn. bir kullanıcı ana dizini kökü altındaki
  herhangi bir alt yol; soyut desen, gerçek bir örnek yol bu kuralın kendisine
  bile yazılmaz).
- Attachment UUID'leri.
- Root HEAD hash değeri ve iç geliştirme-orkestrasyon aracının (hangi araç
  olursa olsun) oturum/worktree/panel ayrıntıları.
- **Legacy ürün adının kendisi** — owner'ın açık talimatıyla ("bu adı
  kullanma") bu token **hiçbir biçimde, hiçbir case/ayraç varyantında ve
  hiçbir bağlamda** (tarihsel anımsatma dahil) yazılmaz; onun yerine yalnız
  "legacy QR-menü projesi/denemesi" ifadesi kullanılır (`docs/30` §2). Bu
  kural, kuralı **tanımlayan** cümlelerin kendisi için de geçerlidir — token
  bir örnek olarak bile alıntılanmaz.

Ham kanıt (raw evidence, `evidence/*.tsv`, `evidence/git-before.txt` vb.)
**yerelde aynen kalır** — bu madde onları silmez/düzenlemez, yalnız public
kümeye dahil edilmelerini `.gitignore` ile engeller.

**Kanonik sahiplik ayrımı (bilinçli — iki public kaynak değildir)**: bu bölüm
ve §9, yukarıdaki kategorileri (iptal edilmiş adlar, legacy ürün adı, eski
depo adı biçimi) ve sıfır-eşleşme gereksinimini **kanonik olarak** tanımlar,
ama **literal token değerlerini asla taşımaz ve taşıyamaz** — bu kasıtlı bir
tasarımdır (aksi hâlde bu public doküman kendisi bir sızıntı kaynağı olurdu).
Literal forbidden-token setinin operasyonel tedarik kanalı bu külliyatın
**dışındadır**: `skills/public-repository-gate.md` §Girdi sözleşmesi, bu
seti owner-yetkili, **yayınlanmamış** bir gate-invocation girdisi olarak
tanımlar (örn. repo dışı bir private local gate manifest veya korunan bir
invocation parametresi) — bu girdi dosyası bu külliyatta **oluşturulmaz**.
Bu iki sahiplik (public **kategori/kural** burada; private **literal veri**
tamamen dışarıda) birbirini tekrar etmez, birbiriyle çelişmez ve ikinci bir
public "gerçek kaynak" **yaratmaz**.

## 8. Public archive attestation

`evidence/PUBLIC-ARCHIVE-ATTESTATION.md`, 99 girdinin `old/` altına
taşındığını ve 99/99 metadata eşleşmesini (`evidence/archive-verification.md`
temel alınarak) **path/symlink detayı sızdırmadan** özetler — mutlak yol,
inode numarası veya sembolik link hedefi **yayınlanmaz**, yalnız "99/99
doğrulandı" sonucu ve doğrulama yöntemi (device/inode/mode/size/symlink-target
karşılaştırması) anlatılır.

## 9. Doğrulama kapısı (validation gate)

Public-safe küme üzerinde (§3, hariç tutulan geçmiş/ham/snapshot kanıtları
**dışında**), büyük/küçük harf ve ayraç (`-`/`_`/boşluk) duyarsız bir arama,
legacy ürün adının **kendisini** ve onu içeren eski depo adı biçimini **sıfır**
kez döndürmelidir — bu doküman dahil, hiçbir public dosyada bu iki dize bir
örnek/açıklama amacıyla bile yazılmaz (§7 son paragraf). Kaçınılmaz bir
tarihsel dosya-sistemi-yolu anımsatması gerekiyorsa, token'ın kendisi
yazılmadan "legacy ürünün eski adı" gibi dolaylı bir ifadeyle ve açıkça
**legacy** etiketiyle işaretlenir.

Bu aramayı fiilen çalıştıran mekanizma `skills/public-repository-gate.md`'dir
— o skill, bu bölümdeki **kategorileri** literal bir forbidden-token setine
kendisi çevirmez; literal seti owner-yetkili, yayınlanmamış bir girdi olarak
**dışarıdan** alır ve o girdi eksik/boş/okunamazsa taramadan önce
`passed: false` döner (fail-closed — bkz. o skill'in §Girdi sözleşmesi). Bu
doküman (`docs/31`) o girdinin **kaynağı değildir**, yalnız hangi
kategorilerin girdide bulunması gerektiğini tanımlar.

## 10. Kanonik sahiplik

Public repository governance kararının (hedef depo, yayınlanmayacaklar,
`.gitignore` sözleşmesi, sanitizasyon kuralı, doğrulama kapısı) tek kanonik
kaynağı burasıdır. `docs/00` (provenance) ve `evidence/archive-verification.md`
buraya link verir, kendi public-yayın kuralını tekrar tanımlamaz.
