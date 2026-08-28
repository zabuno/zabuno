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

Üzerinde çalışılan nesnenin **ikincil bilgisi**: o ekranda sürekli sorulan ama
orta sütunda yeri olmayan sorular.

Panel bugün üç editörde var. Her biri farklı bir soruyu cevaplar:

| Ekran | Panelin cevapladığı soru |
| --- | --- |
| Menü | Bu menü hangi şubenin, yayında mı, ne kadar dolu? |
| Marka | Bu adı değiştirirsem NEREYİ değiştirmiş olurum? |
| Şube | Bu şubenin menüsü var mı, hangi markayı taşıyor? |

Menü editöründe alanlar:

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

### 3.1 Marka paneli

Marka formu markanın kendisini gösterir ama şunu söyleyemez: bu adı ve bu
logoyu değiştirirsem nereyi değiştirmiş olurum. Panel markanın **kapsamını**
gösterir — kaç şubede görünüyor ve hangi şehirlerde. Şehirler tekilleştirilir;
üç şube iki şehir edebilir. Hiç şube yoksa şehir satırı **çizilmez**: boş bir
satır doldurulmayı bekleyen bir alan gibi görünür, oysa ortada eksik bir alan
değil henüz açılmamış bir şube vardır.

### 3.2 Şube paneli

Şube formunda adres alanları şunu söylemez: bu şubenin menüsü var mı. Panel
markayı, şehri ve menü özetini gösterir.

Menü satırının bir koşulu var ve bu koşul paneldeki en önemli karardır:
**yüklü menü ağacı çalışma alanında SEÇİLİ şubeye aittir.** Panelde başka bir
şube açıkken o ağacın sayısını göstermek, yanlış şubenin verisini doğru
etiketle sunmak olurdu — hiç bilgi vermemekten kötüdür. Bu yüzden menü satırı
ve menüye kısayol yalnız ağaç gerçekten o şubeye aitken çizilir.

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
type WorkspaceInspector = {
    titleKey: string;                                  // panel başlığı VE bölge adı
    render: (ctx) => ReactNode | null;                 // bağlam yoksa null
};
```

İki alan da bilinçli:

- **`titleKey` tek kaynaktır.** Kabuk bölgenin erişilebilir adını buradan
  üretir, panel aynı metni başlık olarak çizer. Başlığı iki yerde tutmak, panel
  değişince ekran okuyucunun eski adı okuması demekti — ilk hâlde kabuk her
  panel için "This menu" diyordu.
- **Uygunluk kararı haritadadır**, panel bileşeninin içinde değil. Bileşenin
  içinden `null` dönmek yetmez: kabuk elementi görür, element her zaman
  doğrudur, ve adlandırılmış ama **boş** bir sütun çizilir. Boş bir sütun
  olmayan bir bağlamı varmış gibi gösterir.

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

Panel üç editörde: menü, marka, şube. Genişlemeyen ekranlar bilerek dışarıda:

- **Medya** — varlık/sürüm modeli henüz yok, gösterilecek ikincil veri yok.
- **Analitik, Ekip, Faturalama, Ayarlar** — bunlar liste ve ayar ekranları;
  `docs/50` §3.4 paneli editörler için tarif eder.
- **Yayın** — ekranın ANA içeriği zaten yayın durumudur. Aynı bilgiyi sağda
  tekrarlamak ikincil bağlam değil, gürültü olurdu.

Sıradaki adım `docs/50` §21 şablon kataloğudur; medya varlık modeli geldiğinde
panel oraya da aynı sözleşmeyle girer.
