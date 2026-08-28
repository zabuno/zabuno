# 71 — Mavi çerçeve: üç ayrı kaynak

**Talep:** girdinin, kartın ya da düğmenin etrafında mavi bir çizgi olmayacak —
ne sekmeyle gezilirken ne fareyle tıklanınca.

## 1. Mavi nereden geliyordu

Tek bir yerden değil, **üç ayrı yerden**:

1. **`app.css` içinde sabit kodlanmış `#2563eb`.** Jeton sistemi vardı ve
   `:focus-visible` kuralı onu kullanmıyordu. Karanlık temayla, yüksek
   kontrastla ve marka rengiyle birlikte değişmeyen bir mavi.
2. **`--focus` jetonunun kendisi.** `oklch(0.55 0.18 255)` — ton açısı 255,
   yani mavi. Sabit kodlanmış rengi jetonla değiştirmek, sorunu çözmek değil
   taşımak olurdu.
3. **Flowbite'ın kendi `focus:ring-*` sınıfları.** Jetona bağlanmamış aileler
   (Modal, Dropdown, Badge …) `focus:ring-4 focus:ring-primary-300` taşımaya
   devam ediyor ve o halka mavidir.

Ayrıca tarayıcının kendi halkası: Safari `-webkit-focus-ring-color` ile sistem
mavisini, Chrome kendi `auto` halkasını çizer. İkisi farklı görünür ve ikisi
de mavidir.

## 2. Ne yapıldı

**Odak rengi kromasız.** `oklch(0.28 0 0)` açık temada, `oklch(0.96 0 0)`
karanlıkta. Chroma 0 demek **hiç ton yok** demek: jetonun "şu an mavi değil"
olması yetmez, mavi **olamamalı**. Nötr aynı zamanda her iki temada da yüksek
kontrast verir; marka sarısı (`#ffb900`) açık zeminde veremezdi.

**Sıra: önce sustur, sonra çiz.**

```css
:focus          { outline: none; }                    /* tarayıcının kendisi */
:focus-visible  { outline: 2px solid var(--color-focus); }
```

**Halka yapısal olarak imkânsız.**

```css
*:focus, *:focus-visible, *:focus-within {
    --tw-ring-shadow: 0 0 #0000;
    --tw-ring-offset-shadow: 0 0 #0000;
}
```

Bu bir temizlik değil bir garanti: hangi Flowbite bileşeninin jetona
bağlandığını tek tek takip etmek, yeni bir bileşen eklendiği gün mavinin geri
gelmesi demekti. Yalnız halka silinir; normal gölgeler etkilenmez.

## 3. Testlerin geçtiği ama ekranın yanlış olduğu an

Beş sözleşme testi yeşildi ve **çizgi hiç çizilmiyordu**. Tarayıcıda ölçünce
iki ayrı sebep çıktı:

**Kural sırası.** `:focus` ile `:focus-visible` aynı özgüllüktedir (0,1,0);
sonra gelen kazanır. `:focus { outline: none }` sonraya yazılmıştı — kural
eşleşiyor, `outline-offset` uygulanıyor, çizgi yok.

**`outline-none` çatışması.** Bileşenler `focus:outline-none
focus-visible:outline-2 …` taşıyordu. Öğe hem `:focus` hem `:focus-visible`
olduğunda ikisi de uygulanır ve `outline-none`, Tailwind'in
`--tw-outline-style` değişkenini `none` yapar. Genişlik ve renk verilse de
biçim `none` kalır. Artık `outline-solid` **açıkça** yazılıyor ve
`focus:outline-none` kaldırıldı — tarayıcının halkasını kapatmak zaten global
`:focus` kuralının işi.

Bu, "test yeşil ama ürün yanlış" sınıfının ders kitabı örneği: sözleşme
kaynağı doğru ölçüyordu, ölçmediği şey **birleşik sonuçtu**.

## 4. Tarayıcıda ölçüm

Chromium 1440×900, `/login`, karanlık tema:

| Ne | Ölçülen |
| --- | --- |
| Klavye odağı (düğme) | `solid 2px oklch(0.96 0 0)`, offset `2px` |
| Klavye odağı (girdi) | `solid 2px oklab(0.95 0 0)` |
| `box-shadow` (tüm kontroller) | `none` |
| Mavi ana hat sayısı | **0** |
| Halka sayısı | **0** |

Kroma 0 doğrudan ölçüldü — renk adı okunmadı, **sayısı okundu**.

## 5. Fare tıklaması

Düğme ve bağlantıda fare tıklaması **hiçbir gösterge bırakmaz**: tarayıcı
`:focus-visible`'ı yalnız klavye için işaretler.

Metin girdisinde tarayıcı fare tıklamasında da `:focus-visible` işaretler —
şartname böyle, çünkü girdi klavye girişi bekler. O gösterge artık **nötr**;
mavi değil. Tamamen kaldırılması istenirse tek satırlık bir değişiklik, ama
yazdığınız yerin neresi olduğu görünmez olur.

## 6. Kalan

Açılır menünün AÇIK listesi bu paketin dışında: `<select>`'in açılan paneli
işletim sistemi tarafından çizilir ve CSS ile Safari/Chrome arasında
eşitlenemez. Ayrı paket (`docs/72`).
