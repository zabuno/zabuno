# 86 — "1 categories, 1 dishes" (FF-28)

## Ne görüldü

`docs/85` ile misafir sayfası İngilizce konuşmaya başladı. Localhost'ta
375 px'lik bir telefonda bakıldığında başlığın altında şu vardı:

> **1 categories, 1 dishes**

Türkçede sorun yoktu — "1 kategori, 1 ürün" doğru. İngilizcede sayıya göre
çoğul gerekiyor ve katalogda çoğul motoru yok.

## Neden çoğul motoru eklemedim

Tek bir cümle için çoğul motoru eklemek, **bütün dillere çoğul kuralı borcu**
getirirdi: Arapçada altı biçim, Rusçada üç. Sahibin dolduracağı PO dosyaları
o gün iki katına çıkardı ve yarısı boş kalırdı.

Etiket-değer biçimi her sayıda doğru okunur ve her dile aynı kolaylıkla
çevrilir:

```
Categories: 1 · Dishes: 1
Kategori: 12 · Ürün: 84
```

## Testler cümleye değil sayıya bakar

İki test özet metnini Türkçe cümlesiyle sabitliyordu (`/2\s*kategori/`).
Metin artık katalogdan geliyor; cümleyi teste sabitlemek, metni her
düzelttiğimizde testi kırardı.

İddia artık **özet elemanının içindeki sayılara** bakıyor — ölçülmek istenen
şey zaten buydu: sayılar snapshot'tan **hesaplanıyor**, sabit ya da uydurma
değil.

## Ürün iddiası

Çalışır: özet satırı her sayıda ve her dilde doğru okunur.
