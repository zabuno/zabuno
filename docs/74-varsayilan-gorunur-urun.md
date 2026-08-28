# 74 — Yeni ürün görünür doğar (P0-02)

## Önce ne oluyordu

Sahip menüsünü giriyor. Kırk satır: çorbalar, kebaplar, tatlılar, içecekler.
Her satırı yazarken bir onay hissi var — liste büyüyor, iş ilerliyor. Sonunda
**Yayınla**'ya basıyor.

> Yayınlanacak görünür ürün yok.

Kırk ürün girmiş bir insanın ekranında "ürün yok" yazıyor. Sebebini hiçbir
yerde okumuyor: her satır `is_visible = false` ile doğuyordu ve kırkının
görünürlüğünü **tek tek** açması gerekiyordu. Bu bir hata mesajı değil,
sessiz bir aktivasyon duvarıydı — ürün çalışıyor, kullanıcı çalışmıyor
sanıyor.

## Şimdi ne oluyor

Yeni ürün **görünür** doğuyor. Sahip kırk satırı giriyor, Yayınla'ya basıyor,
menü yayına giriyor. Saklamak istediği bir ürün varsa görünürlüğünü
kapatıyor — yani iş, istisna olduğunda yapılıyor, her seferinde değil.

## Neden bu doğru gate

Eski gerekçe şuydu: *"menüye eklemek YAYINLAMAK değildir."* Cümle doğru, ama
koruma **yanlış kapıya** bağlanmıştı.

Misafiri koruyan şey `is_visible` değil, **yayındır**. Taslakta görünür olan
bir ürün hiçbir misafire ulaşmaz; `POST .../publications` çağrılana kadar
karekodu okutan kişi hâlâ bir önceki yayını görür. Yani gizlilik zaten
yayın kapısında duruyordu; `is_visible = false` ikinci bir kapı değil,
sadece sahibin önüne konmuş fazladan bir iş yüküydü.

`is_visible` gerçek işini koruyor: **bugün menüde olmayan** bir ürünü
listeden silmeden saklamak.

## Kanıt

`tests/Feature/MenuCatalog/MenuOperationsTest.php`

| Test | Neyi donduruyor |
| --- | --- |
| `test_a_new_item_is_visible_so_the_first_publish_is_not_a_wall` | Yeni satır `isVisible: true` döner **ve** hiçbir görünürlük tıklaması olmadan ilk yayın başarılı olur |
| `test_an_item_can_still_be_hidden` | Saklama yolu iki yönde de çalışır |

`RestaurantCriticalJourneyTest` ve `MenuEntrySingleSubmitTest` eski
varsayılanı dondurmuştu; ikisi de yeni gerekçeyle güncellendi.

## Şema

`database/migrations/2026_08_28_000300_menu_items_default_to_visible.php`
sütun varsayılanını da `true` yapar. Uygulama kodu değeri her zaman açıkça
yazdığı için varsayılan bugün okunmuyor; ama doğrudan `INSERT` yapan bir
tohum ya da ileride yazılacak bir kod yolu duvarı sessizce geri kurardı.

**Mevcut satırlar değişmez.** Bugün gizli olan bir ürünü görünür yapmak,
sahibin bilerek sakladığı bir şeyi misafire açmak olurdu.

## Ürün iddiası

Çalışır: sahip menüsünü girer ve ilk denemede yayınlar.
