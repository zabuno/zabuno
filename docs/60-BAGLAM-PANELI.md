# 60 — Bağlam paneli (sağ sidebar)

**Paket:** FF-03a · **Öncesi:** `docs/50` §3.4, §4, §13, §21, §25 · `docs/54`

## 1. Neden bu belge var

Sağ panel planlarda dört ayrı yerde geçiyordu. Hiçbir ekranda yoktu.

`docs/50` §4'teki masaüstü şemasında üç sütun tarif edilmişti — sol gezinti,
orta çalışma alanı, sağ bağlam. FF-02a bu şemayı `AdminShell`'e taşırken
`inspector` **yuvasını açtı**, ama yuvayı hiç kimse doldurmadı. Kabuk üç sütunu
çizebiliyordu; hiçbir sayfa üçüncüsünü vermiyordu, dolayısıyla ürün iki sütunlu
kaldı ve eksik fark edilmedi: kod "destekliyor" göründüğü için tamamlanmış
sayıldı.

Bu, yapılmamış işi yapılmış gösteren bir hata sınıfıdır. Bir yuva açmak bir
yetenek değildir. Yetenek, yuvanın dolduğu ekranda başlar.

## 2. Sağ panel ne DEĞİLDİR

- **İkinci bir menü değildir.** Gezinti solda yaşar. Sağa gezinti koymak,
  kullanıcının bir şeyi iki yerde aramasına yol açar.
- **Bir alet çantası değildir.** Ana alanda yeri olmayan düğmeleri buraya
  sürüklemek, paneli bir çöp çekmecesine çevirir.
- **Zorunlu bir adım değildir.** Panel kapalıyken de menü düzenlenir. Panel
  mobil pakette hiç YOKTUR (`docs/54`) ve orada ürün eksiksiz çalışır. Eğer bir
  iş yalnız panelden yapılabiliyorsa, o iş yanlış yere konmuştur.

## 3. Sağ panel nedir

Üzerinde çalışılan nesnenin **ikincil bilgisi**: menüyü düzenlerken sürekli
sorulan ama orta sütunda yeri olmayan sorular.

Menü editöründe bunlar:

| Alan | Kaynak |
| --- | --- |
| Durum ve sürüm | yayın kaydı (`useCurrentPublication`) |
| Lokasyon | menünün bağlı olduğu lokasyonun adı |
| Kategori sayısı | menü ağacından sayılır |
| Ürün sayısı | menü ağacından sayılır |
| "Preview & publish" kısayolu | ana alanda zaten var olan yola götürür |

Son satır kuralın kendisidir: panelin tek eylemi **yeni bir yol açmaz**, bilinen
bir yola kısa yol verir. Aksi hâlde panel bir kolaylık değil, gizli bir ön koşul
olurdu.

## 4. Uydurulmayanlar

`docs/50` §21 şablonunda tema, desteklenen diller ve yayın zamanlaması da
sayılıyor. Üçü de **eklenmedi**: ürün bugün bu üç veriyi tutmuyor.

Boş ya da devre dışı bir "Tema" alanı çizmek ucuz olurdu ve paneli dolu
gösterirdi. Ama kullanıcıya olmayan bir yetenek vaat ederdi — kapatılan bir
sorun değil, açılan bir sorun. Panel yalnız gerçek veri gösterir; alanlar veriyi
tutan göç geldiğinde eklenir.

Aynı sebeple `MenuInspector` menü yokken `null` döner ve kabuk `<aside>`
öğesini hiç çizmez. Boş bir sütun, olmayan bir bağlamı varmış gibi gösterir.

## 5. Nasıl bağlanır

`WorkspaceApp` bir **panel haritası** alır:

```ts
inspectors?: WorkspaceInspectorMap;   // bölüm anahtarı → panel
```

- Haritada olmayan bölüm panelsizdir; kabuk sütunu çizmez, orta alan genişler.
- Sayfa ile panel **tek bir** `sectionContext`'ten beslenir; iki ayrı bağlam
  olsaydı panel ile içerik birbirinden kayabilirdi.
- Haritayı yalnız `workspace.desktop.tsx` verir. Mobil giriş
  `desktopInspectors` dosyasına hiç dokunmaz, dolayısıyla panel kodu o pakete
  hiç girmez.

### 5.1 İlk denemede yapılan hata

Panel önce bölüm kaydında (`MenuPage.section.tsx`) `renderInspector` olarak
bildirildi ve kabuğa bir `supportsInspector` bayrağı eklendi. Ekranda doğru
çalışıyordu: masaüstünde panel vardı, telefonda yoktu.

Paket ölçüldüğünde yanlış olduğu görüldü. Bölüm kayıtları
`import.meta.glob('../pages/*.section.tsx')` ile toplanır ve iki girişte de
bulunur; panel bileşeni oradan mobil pakete de giriyordu. Bayrak `false`
olduğu için ÇİZİLMİYOR, ama İNDİRİLİYORDU.

Bu, `docs/54`'ün reddettiği şeyin ta kendisidir. Adaptive yükleme "kodu
göstermemek" değil, "kodu indirmemek"tir. Bir bayrakla gizlenen kod adaptive
bir ayrım değil, ölü ağırlıktır.

Doğrusu bir koşul değil, bir **modül sınırı**: panel haritası yalnız masaüstü
girişinin `import` ettiği bir dosyada durur. Sözleşme tipi (`inspectors/types.ts`)
ayrı ve cihazdan bağımsızdır — kabuk masaüstü dosyasını adıyla anmaz, yoksa
ayrımın doğruluğu tek bir `type` kelimesine bağlı kalırdı.

### 5.2 `adaptive-bundle-gate`

Yanlış cümle yazıldığı anda yanlıştı ve kimse fark etmedi. `docs/60` "mobil
pakette bulunmaz" diyordu; ölçüm bunu yalanladı. Bir daha sessizce olmasın diye
`scripts/adaptive-bundle-gate` eklendi.

Kapı iki girişin kaynak import kapanışını (statik, dinamik **ve**
`import.meta.glob`) gezer ve cihaza özgü bildirilen modüllerin diğer girişten
ulaşılabilir olup olmadığına bakar.

Üç şey ölçümü mümkün kılıyor:

1. **Manifest değil kaynak.** Vite manifest'i yığın düzeyindedir, modül
   düzeyinde değil; "şu dosya mobil pakete girdi mi" sorusunu cevaplayamaz.
2. **Glob genişletilir.** Sızıntı düz bir `import` satırından değil, glob'dan
   geldi. Glob'u atlayan bir gezgin, yazıldığı sızıntıyı kaçırırdı.
3. **`import type` silinir.** Tip importu derlemede hiçbir bayt bırakmaz;
   saymak sürekli yanlış alarm olurdu. Ama `type` kelimesi düştüğü gün import
   gerçek olur ve kapı onu yakalar.

Kapının kendi testi (`scripts/adaptive-bundle-gate.test.sh`) geçici bir kaynak
ağacında sızıntıyı yeniden üretir. Buna ihtiyaç vardı: kapının ilk hâli,
sızıntıyı geri koyduğumda **PASS diyordu** — bildirim panel HARİTASINI
adlandırıyordu, oysa sızan şey panel BİLEŞENİYDİ. Bildirim bu yüzden artık tek
tek dosya değil örüntüdür (`pages/**/*Inspector.tsx`), ve eşleşmeyen bir
örüntü sessiz geçiş değil hatadır.

## 6. Kalan hedef

Panel şu an tek bölümde: menü editörü. Sıradaki adım, `docs/50` §21 şablon
kataloğu ile birlikte diğer nesne-merkezli ekranlara (lokasyon, medya varlığı,
yayın) aynı sözleşmeyle genişletmektir — her biri yalnız o ekranda gerçekten
var olan veriyle.
