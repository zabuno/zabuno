# public-repository-gate

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
`zabuno/zabuno` public deposuna herhangi bir dosya eklenmeden/güncellenmeden
önce (`docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md` §9 doğrulama kapısı).

## Inputs
Aday dosya/diff + `docs/31` §2 (yayınlanmayacaklar **kategorileri**) + §9
(marka/token kontrolünün **kural/kategori** tanımı — bkz. §Zorunlu marka
kontrolü, `docs/31` literal değer taşımaz) + **zorunlu, ayrı bir owner-yetkili
yayınlanmamış girdi**: `forbidden_token_input` — bkz. §Girdi sözleşmesi
(forbidden-token seti).

## Girdi sözleşmesi (forbidden-token seti)

Bu gate, çalışması için aşağıdaki **owner-yetkili, yayınlanmamış** girdiyi
**zorunlu** olarak alır — bu girdi bu skill dosyasının veya başka herhangi
bir public/repository dosyasının **içinde yaşamaz**; gate çağrısına ayrı bir
parametre olarak (örn. korunan bir invocation parametresi, veya repo dışı bir
private local gate manifest dosyası — hangisi olursa olsun bu külliyatta
**oluşturulmaz/commit edilmez**) verilir:

```
forbidden_token_input = {
  tokens: [ ...non-empty string set... ],   // iptal edilmiş adlar + legacy
                                             // ürün adı + eski depo adı biçimi
  owner_scope_attestation: "<owner onay/versiyon etiketi>",
  version: "<girdi seti versiyonu>",
}
```

**Fail-closed girdi kuralı**: `forbidden_token_input` **yok**, `tokens` **boş**
veya girdi **okunamıyorsa** (parse hatası, erişim reddi vb.), gate **hiçbir
dosyayı taramadan** `passed: false` döner ve yayın engellenir — belirsizlikte
asla varsayılan boş listeyle "geçti" sonucu üretilmez (§Failure/rollback ile
aynı disiplin). `docs/31` §7/§9 bu girdinin **hangi kategorilerden oluşması
gerektiğini** (iptal edilmiş adlar, legacy ürün adı, eski depo adı biçimi)
kanonik olarak tanımlar — ama **literal değerleri taşımaz**; bu ayrım
kasıtlıdır (bkz. §Zorunlu marka kontrolü).

## Authority
Salt-okunur kapı — bir dosyayı **yayınlamaz**, yalnız "geçti/geçmedi"
sonucu üretir. Fiili `git add`/`git commit`/push eylemi Claude yazarına veya
reviewer'a **asla** ait değildir (`AGENTS.md` §6); bu deponun ilk `git init`/
ilk push'u önceden owner-yetkili MASTER tarafından tamamlanmıştır — bundan
sonraki her ek public commit/push için de aynı disiplin geçerlidir: yalnız
nihai bağımsız review GREEN olduktan **ve** owner'ın açık public yayın
talebi geldikten **sonra**, Codex Desktop MASTER bu iki koşulu birlikte
sağlayarak fiili Git eylemini gerçekleştirir (`docs/31` §1a). Bu skill
kendisi hiçbir koşulda Git mutasyonu tetiklemez.

## Permitted tools/actions
Mutlak yol deseni taraması — bu skill'in kendi metni gerçek bir örnek yol
**yazmaz**; kural soyut bir `HOME_PATH` placeholder'ıyla anlatılır (işletim
sistemi kullanıcı ana dizini kökünü içeren herhangi bir mutlak yol, veya
bir geliştirme aracının kendi konfigürasyon dizinine işaret eden gizli
(dot-prefixed) bir ana-dizin-göreli yol — spesifik bir araç adı veya örnek
segment bu kuralın gövdesine yazılmaz). Attachment UUID deseni taraması,
secret/credential deseni taraması, `old/`/`worktrees` içerik taraması,
**internal orchestration/session/capability metadata** taraması (bir belirli
orkestrasyon aracının ürün/marka adının literal string'ini aramak değil —
herhangi bir üçüncü taraf geliştirme-orkestrasyon aracının oturum/panel/
worktree/capability iç detaylarının semantik olarak sızıp sızmadığını
değerlendirmek; bu kural herhangi bir spesifik araç adına bağlı değildir,
araç değişse de geçerli kalır), **externally-supplied forbidden-token seti**
taraması (§Zorunlu marka kontrolü, §Girdi sözleşmesi — iptal edilmiş proje/
persona adları ve legacy ürün adı bu skill dosyasının **içine yazılmaz**; bu
skill yalnız "çağıran taraf tarafından, gate invocation'ına ayrı ve
yayınlanmamış bir girdi olarak sağlanan bir forbidden-token setine karşı
case/ayraç-duyarsız arama yap" mekanizmasını tanımlar, listenin kendisini
**hiçbir public/repository dosyasında taşımaz** — `docs/31` §7/§9 yalnız bu
girdinin **kategori/kural tanımını** kanonik olarak taşır, literal değerleri
**değil**), `evidence/*` hariç
`PUBLIC-ARCHIVE-ATTESTATION.md` kontrolü, `imageoptimization/snapshot/`
byte taraması.

## Forbidden actions
Bir ihlali "küçük, göz ardı edilebilir" diye otomatik geçirmek; `LICENSE`/
`COPYING` dosyası önermek/eklemek (bu owner kararıdır, `docs/31` §4); ham
kanıt/snapshot dosyalarını silmek (yalnız `.gitignore` ile dışlanır,
silinmez).

## Zorunlu marka kontrolü
Bu skill, iptal edilmiş proje/persona adlarını veya legacy ürün adını **kendi
metninde bir liste olarak taşımaz** — bu, sabit kodlanmış bir kural olsaydı
skill dosyasının kendisi taramayı tetikleyen tokenleri içerir ve kendi
kapısını kırılgan hale getirirdi. **Kanonik ownership ayrımı** (iki ayrı,
çelişmeyen sahiplik — iki public kaynak değil, biri public/kural biri
private/veri):

- `docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md` §7/§9, **public governance
  policy'nin** tek kanonik sahibidir: hangi **kategorilerin** yasak olduğu
  (iptal edilmiş adlar, legacy ürün adı, bunu içeren eski depo adı biçimi),
  sıfır-eşleşme gereksinimi, istisna kuralları ve onay/yayın sırası. `docs/31`
  **hiçbir zaman** literal bir token taşımaz ve taşıyamaz — bu **kasıtlı** bir
  tasarımdır (aksi hâlde public dokümanın kendisi bir sızıntı kaynağı olurdu).
- **Literal forbidden-token setinin kendisi** hiçbir public/repository
  dosyasında **yaşamaz**. Operasyonel tedarik kanalı: bu skill'in her
  çalıştırılmasında §Girdi sözleşmesi'ndeki `forbidden_token_input`
  owner-yetkili, yayınlanmamış bir girdi olarak (örn. repo dışı bir private
  local gate manifest dosyası veya korunan bir invocation parametresi —
  hangisi seçilirse seçilsin bu külliyatta **oluşturulmaz**) sağlanır.

Bu skill, sağlanan girdideki her token için büyük/küçük harf ve ayraç
(`-`/`_`/boşluk) duyarsız bir arama çalıştırır ve hariç tutulan geçmiş/ham/
snapshot kanıtları dışında **sıfır** eşleşme bekler. Girdi eksik/boş/
okunamazsa §Girdi sözleşmesi'ndeki fail-closed kuralı devreye girer
(`passed: false`, tarama yapılmadan). Kaçınılmaz tarihsel bir dosya-yolu
anımsatması gerekiyorsa, token'ın kendisi yazılmadan dolaylı bir ifadeyle ve
açıkça `legacy` etiketiyle işaretlenmelidir.

## Deterministic outputs / schema
```
{ file, passed: boolean,
  input_status: "ok" | "missing" | "empty" | "unreadable",
  input_digest: "<forbidden_token_input.tokens setinin non-reversible hash'i>",
  input_version: "<forbidden_token_input.version>",
  violations: [{ rule: "absolute-path"|"attachment-uuid"|"secret-pattern"|
    "excluded-dir"|"legacy-brand-leak"|"license-file"|"snapshot-bytes",
    match_count: <int>, detail }] }
```
`input_status` "missing"/"empty"/"unreadable" olduğunda `passed` her zaman
`false`'tur ve `violations` boş kalabilir (tarama hiç çalışmamıştır) — bu
durum kendi başına bir engelleme nedenidir.

## Evidence
Geçen/geçmeyen dosya listesi + `input_digest`/`input_version` +
kural/kategori bazlı `match_count`, çalıştırıldığında `evidence/` altına
(yalnız yerel, public'e dahil değil) bir tarama özeti olarak kaydedilir.
**Non-echo kuralı**: bu evidence kaydı ve gate raporu **hiçbir koşulda**
eşleşen ham/gerçek token değerini, onu çevreleyen ham metni veya forbidden
token setinin kendisini içermez/loglamaz — yalnız kural adı, dosya, dosya
içindeki güvenli konum (satır/offset), eşleşme sayısı ve girdi seti için
non-reversible bir digest/versiyon raporlanır. `violations[].detail` alanı
da eşleşen literal metni **taşımaz** — yalnız kural/konum/sayım bilgisi
taşır.

## Human approval
Bir dosya bu kapıdan geçmeden **hiçbir koşulda** public depoya eklenmez;
"violations" boş olsa bile ilk public yayın insan (owner) onayı gerektirir.
Owner'ın açık public yayın talebi tek başına yeterli değildir — nihai
bağımsız review GREEN sonucuyla **birlikte** aranır (`docs/31` §1a); ikisi
birlikte sağlandığında fiili Git eylemini yalnız Codex Desktop MASTER yapar.

## Failure / rollback
Bir kural belirsiz sonuç üretirse (örn. bir yol parçasının mutlak mı göreli
mi olduğu net değilse) varsayılan sonuç **"passed: false"**tir — belirsizlikte
asla otomatik geçiş yapılmaz.

## Eval cases
- Mutlak yol içeren bir cümlenin yakalandığının testi.
- `old/` içeriğine bir linkin yakalandığının testi.
- Meşru bir "legacy" etiketli tarihsel anımsatmanın **yanlışlıkla**
  engellenmediğinin testi (false-positive kontrolü).
- Snapshot byte'ının (`imageoptimization/snapshot/`) her koşulda
  engellendiğinin testi.
- `forbidden_token_input` eksik/boş/okunamaz olduğunda gate'in **hiçbir**
  dosyayı taramadan `passed: false` döndüğünün testi (fail-closed girdi
  kuralı, §Girdi sözleşmesi).
- Bir eşleşme bulunduğunda evidence/rapor çıktısının eşleşen ham token
  değerini **hiçbir alanda** taşımadığının testi (non-echo kuralı).

## Phase
Public repository governance karar alındığı andan itibaren (`docs/31`,
mimari olarak Stage 0'dan pre-wired; fiili public ilk push owner kararına
bağlı, waterfall stage'inden bağımsız bir yönetişim eylemidir).
