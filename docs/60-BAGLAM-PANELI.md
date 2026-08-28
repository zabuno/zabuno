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

Bölüm kaydı isteğe bağlı bir `renderInspector` taşır:

```ts
renderInspector?: (ctx: WorkspaceSectionContext) => ReactNode;
```

- Bildirmeyen bölüm panelsizdir; kabuk sütunu çizmez, orta alan genişler.
- `WorkspaceApp` sayfa ile paneli **tek bir** `sectionContext`'ten besler; iki
  ayrı bağlam olsaydı panel ile içerik birbirinden kayabilirdi.
- `supportsInspector` yalnız masaüstü girişinden gelir. Mobil pakette panel kodu
  **paketlenmez** (`docs/54`) — bir bayrakla gizlenmiş ölü kod değil, hiç
  indirilmeyen kod.

## 6. Kalan hedef

Panel şu an tek bölümde: menü editörü. Sıradaki adım, `docs/50` §21 şablon
kataloğu ile birlikte diğer nesne-merkezli ekranlara (lokasyon, medya varlığı,
yayın) aynı sözleşmeyle genişletmektir — her biri yalnız o ekranda gerçekten
var olan veriyle.
