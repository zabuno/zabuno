# 149 — Dağıtım ucu alır, geriye gitmez

> Bir birleşmenin CI'ı yeşil oluyordu, dağıtım koşuyordu, her adım yeşildi —
> ve o birleşme yayına çıkmıyordu. Ancak **bir sonraki** birleşme geldiğinde
> çıkıyordu.

İlgili: `docs/142` (dağıtım ne dağıttığını kanıtlar), `docs/87`, `docs/43`.

---

## 1. Ölçüm

8 Eylül 2026, dağıtım koşumu **34210847469**. GitHub arayüzünde koşum
`8085ab18` commit'ine ait görünüyordu. Yayına aldığı `ad02fc21`'di — yani
`main`'in ucu değil, bir öncekisi.

Koşumun kendi çıktısı bunu dürüstçe söylüyordu (`docs/142` §4.4 ile eklenen
uyarı):

```
EXPECTED_SHA:    ad02fc21...
BRANCH_TIP_SHA:  8085ab18...
Kanıtlandı: canlı uygulama ve varlıklar ad02fc21 commit'inden.
::warning:: Bu dağıtım ad02fc21 commit'ini yayına aldı, ama main'in ucu 8085ab18.
```

Canlı `https://zabuno.com/up/build` da aynısını dedi: `revision: ad02fc21`,
`assets_match: true`. Yani dağıtım **kendi işini doğru yaptı** — yanlış olan,
hangi commit'i dağıtacağıydı.

Aynı gün bu **beş kez** oldu.

---

## 2. Kök neden

Dağıtım `workflow_run` ile CI'ın peşine takılır ve `workflow_run` **her CI
koşumu için ayrı** tetiklenir. Tetiklenen dağıtımın derlediği commit,
tetikleyen koşumun commit'idir (`github.event.workflow_run.head_sha`).

Arka arkaya birleşmelerde koşumlar birbirini geçer:

| Sıra | Olay | Canlıda |
| --- | --- | --- |
| 1 | `ad02fc21` birleşti, CI başladı | eski |
| 2 | `8085ab18` birleşti, CI başladı | eski |
| 3 | `8085ab18`'in CI'ı bitti → dağıtımı koştu | **`8085ab18`** |
| 4 | `ad02fc21`'in CI'ı bitti → dağıtımı koştu | **`ad02fc21`** ← geriye gitti |

Dördüncü satır kusurun kendisidir: eski bir commit'in dağıtımı, yeni bir
commit'in dağıtımından **sonra** koşup canlıyı geriye aldı. Ve `8085ab18`'i
yayına alacak başka hiçbir olay kalmadı — onu ancak bir **sonraki** birleşmenin
dağıtımı (ki o da kendi commit'ini alır) örtebilirdi.

Sonucu iki cümlede:

- **En son birleşme, ancak bir sonraki birleşme geldiğinde yayına çıkıyordu.**
- **Günün son birleşmesi gece boyunca yayında olmuyordu.** Sahibi sabah bakınca
  yeşil bir CI görüyor, canlıda dünkü kodu buluyordu.

Kusur **kendini gizliyordu**: her seferinde bir sonraki birleşme onu örtüyor ve
geriye "bir gün yanlış görmüşüm" hissinden başka bir şey kalmıyordu.

---

## 3. Ne değişti

### 3.1 Hedef, tetikleyen koşumun commit'i değil

Yeni bir kapı adımı — `Hangi commit yayına alınmalı?` — dağıtımdan **önce**,
`guard` işinde çalışır ve şunu sorar:

1. `main`'in ucu şu an hangi commit?
2. O commit'in **CI'ı geçmiş mi?**

Geçmişse yayına alınan **uçtur**. Geçmemişse, elimizdeki dağıtılabilir en yeni
yeşil commit tetikleyen commit'tir ve o alınır.

Uç körü körüne alınmaz: CI'ı geçmemiş bir ucu yayına almak, `workflow_run` ile
beklemenin bütün anlamını yok ederdi (`docs/142`, DEPLOY-GATED-04).

Yukarıdaki tabloda 4. satır artık şöyle biter: tetikleyen `ad02fc21`, uç
`8085ab18`, ucun CI'ı geçmiş → **hedef `8085ab18`** → canlı zaten o → yapacak
iş yok, dağıtım atlanır.

### 3.2 Geriye gitme yasak

Yarışın tamamı bir API cevabıyla kapanmaz: cevap bir an öncesine aittir. Bu
yüzden ikinci bir kapı — `Geriye gitme yasak` — canlının **kendi söylediği**
commit'i okur (`/up/build`, `docs/142` §4.3) ve hedefin onun **atası** olup
olmadığını ölçer.

Ölçüm GitHub'ın karşılaştırma ucuyla yapılır:
`compare/<canlı>...<hedef>` → `behind`. Bu, `git merge-base --is-ancestor
<hedef> <canlı>` ile aynı ölçümdür, ama checkout gerektirmez ve karar
dağıtımdan önce verilebilir.

- Hedef canlıdan **eski** → dağıtım **durur**, canlı geriye alınmaz.
- Hedef canlı ile **aynı** → yapacak iş yok, atlanır.
- Canlı sürüm **okunamıyor** → karşılaştırma yapılamaz; dağıtım **sürer**.
  Bilinmeyeni "geriye gidiyor" saymak, ilk kurulumda ve uç geçici olarak
  cevapsızken siteyi hiç güncellenemez hâle getirirdi — kapatılmak istenen
  kusurdan beteri. Sonucu zaten `Dağıtım ne dağıttı?` ölçer.

**Kırmızı değil, durdurma.** Ortada arıza yok: canlı zaten daha yeni kodu
çalıştırıyor. Her yarışta kırmızı bir X basmak, bu deponun başka yerlerde
bilerek kaçındığı "kırmızıyı görmezden gel" alışkanlığını yaratırdı
(`deploy.yml`, "Sunucu tanımlı mı?"). Ama sessiz de kalmaz: uyarı basılır ve
koşum özetine bir tablo yazılır.

### 3.3 Elle dağıtım muaf — ikisinden de

`workflow_dispatch` bu akışta **geri alma** yoludur ve geri alma tanımı gereği
geriye gitmektir. İki kapı da onu kapsasaydı tek geri alma yolu sessizce
kapanırdı: hedef ucun peşine takılır, geriye gitme de reddedilirdi. Kırık bir
sürüm yayındayken geri dönülemez olmak, bu paketin kapattığı kusurdan ağırdır.

### 3.4 Hedef bir kez çözülür

Checkout, imaj etiketi, sürüm etiketi ve varlık damgası artık **tek bir
değerden** türer: `needs.guard.outputs.target_sha`. İki yerde iki kez
hesaplanan bir değer er geç iki farklı cevap verir — bu depoda tam olarak bu
olmuştu (`docs/142` §3.2: imaj bir commit'ten, etiket başkasından).

### 3.5 #322'nin uyarısı korundu, anlamı daraldı

Kusuru görünür kılan tek şey o uyarıydı; kaldırılmadı.

Anlamı değişti. Eskiden "yayına alınan, tetikleyen koşumun commit'iydi"
demekti ve bu, ucun **hiç** yayına çıkmaması anlamına gelebiliyordu. Artık
yalnız **ucun CI'ının henüz bitmemiş** olduğunu söyler: geçici bir gerilik.
Ucu kendi dağıtımı alacak ve **yeni bir birleşme gerekmeyecek**. Uyarı metni
bunu açıkça yazar.

Uyarının bildirdiği uç da değişti: `github.sha` yerine **kararın verildiği anda
ölçülen** uç. `github.sha`, koşum kuyrukta beklerken eskimiş olabilir.

### 3.6 Eşzamanlılık — neden `cancel-in-progress: false`

`true` bu akış için yanlış seçim. Koşan dağıtım tam da imajı SSH ile
aktarıyor ya da `docker compose up` çalıştırıyor olabilir; yarıda kesilmiş bir
`up`, üretimde **yarım bir yığın** bırakır — bazı konteynerler yeni imajda,
bazıları eskisinde. Hızlanmak için üretimi kırma hakkımız yok.

`false` ile GitHub koşanı bitirir ve bekleyenler arasından yalnız **en
yenisini** tutar. Sıra beklemek eski bir commit'i yayına çıkarmaz, çünkü hedef
koşum **başladığında** çözülür — kuyruğa girdiğinde değil.

---

## 4. Kapılar

`tests/Feature/Deployment/DeploymentContractTest.php`, `DEPLOY-FORWARD-ONLY-15`:

| Test | Ne ölçüyor |
| --- | --- |
| `test_the_deploy_targets_the_branch_tip_not_only_the_run_that_triggered_it` | Ucun sorulduğunu, CI'ının geçtiğinin doğrulandığını, checkout'un çözülen hedefi aldığını |
| `test_the_released_commit_is_resolved_once_and_used_everywhere` | Dağıtım işinin hedefi yeniden hesaplamadığını |
| `test_a_deploy_that_would_move_the_site_backwards_is_stopped` | Canlının okunduğunu, ata ölçümünün yapıldığını, `behind` durumunda dağıtımın durduğunu, uyarıldığını — ve kırmızı olmadığını |
| `test_an_unreadable_live_revision_does_not_wedge_the_deploy` | Bilinmeyenin dağıtımı kilitlemediğini |
| `test_a_manual_deploy_can_still_roll_back_to_an_older_commit` | İki kapının da elle tetiklemeyi ayırdığını |
| `test_two_deploys_never_run_at_once_and_a_running_one_is_never_killed` | Grubun var olduğunu ve koşanın öldürülmediğini |
| `test_the_behind_the_tip_warning_reports_the_tip_that_was_measured` | #322 uyarısının ölçülmüş ucu bildirdiğini |
| `test_the_forward_gate_needs_no_new_secret_and_prints_no_address` | Yeni kapının yeni secret istemediğini ve adresi basmadığını |

Bu kapılar **tanımı** ölçer, koşan bir dağıtımı değil. Gerçek doğrulama ilk
birleşmede görülür: koşum özetinde yayına alınan commit ile `main`'in ucu
aynı olmalı.

---

## 5. Geri alma

Bu paket yalnız `.github/workflows/deploy.yml` ve testleri değiştirir; üretim
kodu, imaj ve `docker/entrypoint.sh` değişmedi. Geri alma tek adımdır: bu
paketin commit'ini geri al. Dağıtım eski davranışına döner (kusuruyla
birlikte).

Yayındaki bir sürümü geri almak ayrı bir iştir ve yolu değişmedi: Actions →
Deploy → **Run workflow**, hedef ref eski commit, onay kutusuna `DEPLOY`.

---

## 6. Sahibi bir daha bu duruma düşerse

Adım sırası `docs/142` §6 ile aynı, tek fark üçüncü maddede:

**İkisi de ESKİ bir commit** → Actions → Deploy → son koşumun özetine bak.

- *"Bu koşum main'in ucunu değil…"* uyarısı varsa: ucun CI'ı henüz bitmemiş.
  **Beklemek yeterli**, yeni bir birleşme gerekmez.
- *"Dağıtım durduruldu — geriye gidecekti"* yazıyorsa: canlı zaten daha yeni
  kodu çalıştırıyor. Bakılacak bir arıza yok.
- *"Yapılacak iş yok"* yazıyorsa: canlı zaten hedeflenen commit'te.
