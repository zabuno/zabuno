# 142 — Dağıtım ne dağıttığını kanıtlar

> Bir dağıtımın yeşil olması, canlı sitenin güncel olduğu anlamına gelmiyordu.
> Bu belge o boşluğun ölçümünü, kök nedenini ve kapatılışını taşıyor.

İlgili: `docs/52` (Preview Truth), `docs/87` (üretimde görünen iki kusur),
`docs/43` (sunucu kurulumu).

---

## 1. Sahibin yaşadığı şey

8 Eylül 2026 sabahı sahibi kurumsal kabuğun yeniden yazımını (`docs/136`,
FF-232) `main`'e birleştirdi. Dağıtım kendiliğinden koştu ve **her adımı
yeşil tamamladı** — sağlık kontrolü dâhil.

Sonra siteyi açtı. Hiçbir şey değişmemişti.

Sayfayı yeniledi. Önbelleği temizledi. Gizli sekmede açtı. Yine aynı.
Dağıtım günlüğüne baktı: yeşil. Commit `main`'de: evet. Saatlerce "ben mi
yanlış yaptım, bir şey mi unuttum" diye aradı.

Yanlış yapmamıştı. Site gerçekten eski koddu. **Ve bunu ona söyleyebilecek
hiçbir şey yoktu.**

---

## 2. Ölçülen belirti

O anda dışarıdan alınan ölçümler:

| Ne | Ölçüm |
| --- | --- |
| Canlı `/build/manifest.json` giriş CSS'i | `assets/app-BCn_NUuu.css` |
| O dosyanın boyutu | 126.594 bayt |
| İçinde daisyUI değişkeni (`--color-base-100`) | YOK |
| İçinde bileşen sınıfı (`.dz-*`) | YOK |
| Aynı commit yerelde derlendiğinde | `app-BX-f7rRV.css`, 161.175 bayt, daisyUI İÇİNDE |
| Canlı `/login` sayfasının bildirdiği commit | `c59d499b…` — yani daisyUI commit'i |
| `/about`, `/delivery` (daha önceki bir birleşmeden) | 200 |

Yani: **uygulama daisyUI commit'ini çalıştırdığını söylüyordu, ama sunduğu
CSS daisyUI'den öncekiydi.**

---

## 3. Kök neden — ölçüldü

İlk akla gelen açıklama derleme önbelleğiydi: "GitHub Actions önbelleği eski
bir katmanı verdi." **Bu ölçüldü ve YANLIŞ çıktı.** Önbellek doğru
davranmıştı; `package.json` daisyUI ile değiştiği için bağımlılık katmanı
gerçekten geçersizleşmiş, `npm ci` yeniden koşmuş ve derleme 156,36 kB'lık
daisyUI'li CSS'i üretmişti.

Gerçek neden bir **sıralama** ve bir **yalan etiket**ti.

### 3.1 Sıralama

| Saat (UTC) | Olay |
| --- | --- |
| 01:56:30 | `c1414278` (menü düzeltmesi) `main`'e girdi, CI başladı |
| 02:08:57 | `c59d499b` (daisyUI) `main`'e girdi, CI başladı |
| 02:14:48 | **`c1414278`'in CI'ı bitti** |
| 02:14:50 | Dağıtım koşumu 34179388196 başladı — **`c1414278` için** |
| 02:16:34 | O koşum bitti: canlı site artık `c1414278` çalıştırıyor |
| 02:26:30 | `c59d499b`'nin CI'ı bitti |
| 02:26:32 | Dağıtım koşumu 34180060411 başladı — `c59d499b` için |
| 02:28:57 | Bitti: canlı site nihayet daisyUI'yi çalıştırıyor |

Dağıtım `workflow_run` ile tetiklenir ve **her CI koşumu için ayrı** çalışır.
Daha yeni bir commit `main`'e girdikten sonra daha eski bir commit'in CI'ı
bitebilir — burada tam olarak bu oldu. 02:16 ile 02:28 arasında canlı site,
sahibi az önce birleştirdiği şeyi değil, bir öncekini çalıştırıyordu.

Bu, tek başına, bir arıza değil. Yolda olan bir dağıtım vardı. Ama **hiçbir
yerde yazmıyordu.**

### 3.2 Yalan etiket

Asıl kusur bu. Yayına alma adımı şunu yazıyordu:

```yaml
ZABUNO_BUILD_REVISION: ${{ github.sha }}
```

`workflow_run` bağlamında `github.sha` **dalın o anki ucudur**, dağıtılan
commit değil. İmaj etiketi doğru kaynaktan (`workflow_run.head_sha`)
türüyordu; yalnız *sürüm etiketi* ötekinden geliyordu.

Sonuç: 02:16'da yayına alınan imaj `c1414278`'den derlenmişti, ama konteynere
`ZABUNO_BUILD_REVISION=c59d499b` verildi. Canlı site, çalıştırmadığı bir
commit'i çalıştırdığını **söyledi**.

Bunun ne kadar sinsi olduğuna dikkat: `docs/52`'nin bütün amacı "baktığım şey
gerçekten yazdığım kod mu" sorusunu cevaplamaktı. O gün mekanizma çalıştı,
cevabı verdi, ve **cevap yanlıştı** — sahibi doğrulamak için baksaydı bile
"evet, güncelsin" görecekti.

### 3.3 Ve hiçbir kapı bakmıyordu

Sağlık kontrolü şunu soruyor: *bir şey 200 dönüyor mu?* Eski sürüm de 200
döner. Yeşil bir dağıtım, canlının güncel olduğunu göstermiyordu — ve
göstermediğini kimse söylemiyordu.

### 3.4 Ölçemediğim şey

`docs/52`'nin **istemci yarısı üretimde ölü**: `vite.config.ts` derleme
sürümünü pakete gömerken `git rev-parse HEAD` çağırıyor, ama `.dockerignore`
`.git`'i dışarıda bırakıyor, dolayısıyla üretim paketlerinde sürüm boş
kalıyor. Ölçüldü: canlı `BuildTruthBanner-B2QZYLiT.js` içinde tek bir 40
haneli commit kimliği yok; yerelde derlenen aynı dosyada var.

**Bu pakette DÜZELTİLMEDİ**, bilinçli olarak. Sürümü pakete gömmek her
dağıtımda JavaScript parça özetlerini değiştirir ve ziyaretçiye hiç
değişmemiş paketleri yeniden indirtir; kazanç ise sıfıra yakın, çünkü uyarı
şeridi üretimde zaten kapalıdır (`config/build.php` → `banner`). Kimlik
pakete gömülmek yerine **paketin yanına** kondu (§4.2). Bu bir karar, bir
unutma değil.

---

## 4. Ne değişti

### 4.1 Etiket, derlenen commit'ten türüyor

```yaml
ZABUNO_BUILD_REVISION: ${{ steps.meta.outputs.sha }}
```

`steps.meta.outputs.sha`, imajın derlendiği commit'tir. Artık canlı sitenin
söylediği commit ile çalıştırdığı commit ayrışamaz.

Bu kusuru bir kapı **donduruyordu**: `DeploymentContractTest` tam olarak
`ZABUNO_BUILD_REVISION: ${{ github.sha }}` dizgesini arıyordu. Kapı yeniden
yazıldı ve şimdi tersini zorluyor.

### 4.2 Varlıklar kendi commit'lerini taşıyor

Üretim imajı, varlıkları üreten komutun **kendisinde** bir damga yazıyor:

```dockerfile
ARG ZABUNO_RELEASE_SHA=""
RUN npm run i18n:build && npm run build \
    && printf '%s' "$ZABUNO_RELEASE_SHA" > public/build/revision.txt
```

Aynı komut olması şart: varlıklar ile kimlikleri tek katmanda doğar, önbellek
birini tazeleyip ötekini bırakamaz.

Argüman adı bilerek `ZABUNO_BUILD_REVISION` **değil**. Derleme argümanları
`RUN` içinde ortam değişkeni olarak görünür ve `vite.config.ts` o adı okuyup
değeri paketin içine gömer (§3.4). Ölçüldü: bu adla derlendiğinde üretimdeki
paket özetleri değişmiyor — `BuildTruthBanner-B2QZYLiT.js` bugün canlıda olan
dosyayla aynı ada sahip.

### 4.3 Kimlik dışarıdan okunabiliyor: `GET /up/build`

```json
{
  "revision": "c59d499b7cb29b4246da53a59a7096b39377faeb",
  "short_revision": "c59d499",
  "assets_revision": "c1414278b3f2f577963b435f44292f86d9ad8014",
  "assets_match": false
}
```

**İki yarı ayrı bildirilir.** 8 Eylül'deki arıza tam olarak ayrışmalarıydı:
PHP ileri, varlıklar geri. Tek bir "sürüm" alanı bunu yapısal olarak
gösteremez — hangisini söylerse söylesin öbür yarıyı gizler.

Neden bir uç, neden başka bir şey değil:

- **`<meta>` etiketi zaten vardı** ama yalnız uygulama kabuğunu basan
  sayfalarda (`/login`, `/app`, …). Kurumsal ana sayfa onu hiç basmıyor ve
  bir dağıtım kapısının hangi sayfanın hangi kabuğu kullandığına bağlı olması
  kırılgan bir sözleşmedir.
- **HTTP başlığı** her yanıtta taşınırdı ama tarayıcıdan bakan bir insana
  görünmez ve vekil katmanında sessizce düşürülebilir. Sahibi teknik değil;
  adresi açıp cevabı okuyabilmeli.
- **`/up` değiştirilmedi.** O, konteynerin sağlık probu ve dağıtımın bekleme
  döngüsüdür; gövdesini değiştirmek çalışan bir kapıyı riske atardı. Kanıt
  onun *yerine* değil *yanına* geldi.
- Adres `/build` **olamazdı**: orası nginx'in diskten sunduğu statik varlık
  dizinidir.

Sır sızdırmaz: commit kimliği zaten `/login` HTML'inde duruyor ve depo açık
kaynak. Sunucudaki yol, PHP/çatı sürümü, ortam adı ya da yapılandırma
**yok** — ve bir kapı alan kümesinin dar kaldığını zorluyor. Damganın biçimi
de zorlanıyor: `public/` altındaki bir dosyanın ham içeriğini yansıtan bir
uç, o dosyaya yazabilen herkese küçük bir yayın kanalı verirdi.

Bilinmeyen **uydurulmaz**: yarılardan biri okunamıyorsa `null` döner ve
`assets_match` bir iddiada bulunmaz. Uydurulmuş bir sürüm, sürüm
olmamasından kötüdür — karşılaştırmayı her zaman "eşit" yapıp dedektörü
sessizce işe yaramaz hâle getirir.

### 4.4 Dağıtım artık kanıtlıyor

Sağlık kontrolünden **sonra** yeni bir adım: *Dağıtım ne dağıttı?*

Canlı `/up/build` okunur ve iki karşılaştırma yapılır:

1. `revision` == bu koşumun yayına aldığı commit → değilse **KIRMIZI**
2. `assets_revision` == aynı commit → değilse **KIRMIZI**

Konteyner değişimi bir an sürdüğü için uyuşana kadar beklenir (15 deneme,
6'şar saniye); beklemeden kırmızı vermek, gerçek bir arıza ile bir saniyelik
geçişi aynı şey sayardı ve kapı güvenilmez olurdu.

Ayrıca **geride kalmış bir yayın sessiz kalmaz**: yayına alınan commit
`main`'in ucu değilse (§3.1'deki durum) koşum bir uyarı basar ve koşum
özetine yazar. Bu **kırmızı değildir** — yapacak bir iş yoktu ve daha yeni
bir dağıtım zaten yolda — ama artık söylenmiş olur.

Her koşum, günlüğe değil **koşum özetine** şu tabloyu yazar:

| | commit |
| --- | --- |
| Yayına alınan | `c59d499b` |
| Canlı uygulama | `c59d499b` |
| Canlı varlıklar (CSS/JS) | `c1414278` |

Sahibi günlük okumaz; tabloya bakar.

### 4.5 Sessiz atlama korundu

Sunucu secret'ları tanımlı değilse dağıtım **hâlâ atlanır**, kırmızı vermez.
Gerekçe akışta yazılı ve değişmedi: her birleşmede kırmızı bir X görmek
"kırmızıyı görmezden gel" alışkanlığı yaratır, ve o alışkanlık gerçek
arızaları da gizler.

Yeni kapı `deploy` işinin **içinde** durur, yani yalnız gerçekten dağıtım
yapıldığında çalışır. Ve **yeni bir secret istemez**: var olan sağlık
adresinden kök adresi türetir. Yeni bir secret isteseydi, kurulumu bugün
çalışan makinede kapı sessizce kapalı kalırdı — kapının hiç var olmadığı
hâlin aynısı, ama var sanıldığı için daha kötüsü.

Sağlık kontrolü de **zayıflatılmadı**: yeni kapı onun yerine değil üstüne
geldi, ve bir kapı sağlık kontrolünün hâlâ cevap vermeyen bir siteyi kırmızı
yaptığını zorluyor.

---

## 5. Kapıların ölçülmüş davranışı

Üç yol da yerel bir sunucuya karşı, 8 Eylül'ün durumu birebir yeniden
kurularak koşuldu:

| Kurulan durum | Sonuç |
| --- | --- |
| Uygulama ve varlıklar beklenen commit'ten | çıkış 0, "Kanıtlandı" |
| Uygulama `c59d499b`, varlıklar `c1414278` (8 Eylül'ün hâli) | çıkış 1, `::error::Derlenmiş varlıklar c1414278 commit'inden` |
| Her ikisi `c1414278`, `main`'in ucu `c59d499b` (kök nedenin hâli) | çıkış 0 + `::warning::` + özete not |

Damganın imaja gerçekten girdiği de ölçüldü: `assets` aşaması bir sahte
commit ile derlendi ve `public/build/revision.txt` imajın içinde 40 bayt
olarak bulundu.

---

## 6. Sahibi bir daha bu duruma düşerse nereye bakacak

Sayfayı yeniledin, birleştirdiğin şey görünmüyor. Sırayla:

**1. Şu adresi aç:** `https://zabuno.com/up/build`

Küçük bir metin çıkar. İçinde iki kimlik var:

- `revision` — **sitenin çalıştırdığı** kod
- `assets_revision` — **görünüşü çizen** dosyaların derlendiği kod

**2. Bunları GitHub'da birleştirdiğin commit ile karşılaştır.** Birleşme
sayfasında commit kimliğinin ilk 7-8 hanesi yazar; buradaki değerlerin ilk
hanelerine bakman yeterli.

- **İkisi de senin commit'in** → site günceldir. Gördüğün fark bir dağıtım
  sorunu değil; kodun kendisi ya da tarayıcı önbelleğin.
- **`assets_match` `false` diyor** → görünüş bayat. Bu artık dağıtımı
  kırmızı yapar; Actions sekmesinde kırmızı koşumu aç.
- **İkisi de ESKİ bir commit** → henüz senin dağıtımın koşmadı. Actions
  sekmesinde "Deploy" koşumları listesine bak; sonuncusunun özetinde
  *"Bu koşum main'in ucunu değil…"* uyarısı varsa **beklemen yeterli**:
  senin commit'inin CI'ı bitince kendi dağıtımı koşacak. Genelde 15-20
  dakika.

**3. Actions → Deploy → son koşum → özet.** Orada her zaman şu tablo durur:
yayına alınan commit, canlı uygulama, canlı varlıklar. Üçü aynıysa site
güncel; değilse hangi yarının geride kaldığı yazıyor.

Artık cevaplanmamış bir "ben mi yanlış yaptım" sorusu yok: **soru bir
adreste, cevabı bir tabloda.**
