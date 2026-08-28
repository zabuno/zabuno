# 63 — Hesap menüsü ve görünüm tercihi

**Tur:** 2/6 · **Kaynak:** SaaS panel kabuk mimarisi §5, §7, §16, §25 ·
`docs/61` A6, A15

## 1. İki ayrı sorun

**Tema seçici sayfanın dibinde yüzüyordu.** Her ekranda, her görevin altında.
Tema hiçbir sayfanın görevi değildir; kişisel bir tercihtir. 320×480'de (iPhone
4) sabit bir alt çubuk ekranın kalıcı olarak yaklaşık %12'sini kaplıyordu ve
küçük ekranda en pahalı şey dikey alandır.

**Hesap menüsü üst çubuktaydı.** Bu benim önceki kararımdı ve gerekçesi doğruydu
(hesap işleri gezinti değildir, kenar çubuğundaki görev maddelerinin arasına
karışmamalı). Ama plan başka bir yer söylüyor.

## 2. Planın söylediği yer

`docs/50` §7 ve §25: masaüstünde **kalıcı kenar çubuğunun dibi**, dar ekranda
üst çubuk.

Sebep üst çubuğun ne taşıdığıdır: çalışma bağlamı — marka, lokasyon, arama,
oluştur. Hesap orada bağlamla yarışır. Kenar çubuğunun dibi ise yardımcı
araçların yeridir; `mt-auto` ile aşağı itilir ve gezinti maddelerinin arasına
karışmaz.

Aynı bölüm bir uyarı da taşıyor: kenar çubuğunun dibine **günlük kritik bir
görev konmaz**, çünkü dar pencerelerde ilk kaybolan yer orasıdır. Hesap
yardımcıdır; oraya uygundur.

Bu bir geri dönüştür ve öyle yazıldı. Önceki karar sessizce değiştirilmedi:
testlerdeki gerekçe yorumları da güncellendi.

## 3. Görünüm tercihi menüye girdi

Üç seçenek menü içinde `role="menuitemradio"` olarak duruyor.

Neden düz `menuitem` değil: ekran okuyucu "üç ayrı eylem" derdi. Oysa bunlar
tek bir ayarın birbirini dışlayan değerleridir ve hangisinin açık olduğu
**duyulmalıdır**.

Neden menünün içine `radiogroup` gömülmedi: `menu` çocukları `menuitem`
ailesinden olmalıdır; `radiogroup` orada geçerli bir çocuk değildir ve axe bunu
`aria-required-parent` ihlali olarak bildirir.

Seçim renkten başka bir kanalda da görünür: işaretli satır görünür bir işaret
taşır. Yüksek kontrast modunda arka plan/metin çiftleri işletim sistemi
paletine düşer ve renge dayanan her ayrım kaybolur.

## 4. `ThemeRoot` artık hiçbir şey çizmez

Tercihi tutan, `localStorage`'a yazan ve `<html>` üzerine uygulayan yer
değişmedi. Değişen tek şey, kontrolü ÇİZEN yerin ayrılması: `ThemeRoot` bir
bağlam sağlar, kontrolü hesap menüsü çizer.

### 4.1 Sağlayıcı yoksa

İlk hâlde `useThemeControl` sağlayıcı yokken **hata fırlatıyordu**. Gerekçe
doğruydu — sessizce çalışmayan bir kontrol göstermek yalandır — ama sonuç
yanlıştı: temayı hiç yönetmeyen bir kabuğu da çökertiyordu ve 110 test kırıldı.

Doğru cevap üçüncüsüydü: sağlayıcı yoksa **görünüm bölümü hiç çizilmez**. Ne
çalışmayan bir düğme kalır, ne de temasız bir kabuk çöker.

## 5. Silinen bir deseni test etmeye devam etmemek

`ThemeKeyboardNavigation.test.tsx` ARIA radiogroup "roving tabindex" desenini
donduruyordu: ok tuşlarıyla dolaşım, Home/End, yalnız seçili öğenin sekme
sırasında olması. O desen artık yok — menü içindeki dolaşım Flowbite
Dropdown'ın sorumluluğudur.

Dosya `ThemeMenuSemantics.test.tsx` oldu ve **bizim sahip olduğumuz** sözleşmeyi
donduruyor: doğru rol, doğru `aria-checked`, renk dışı seçim işareti, menü
içinde ayrı `radiogroup` bulunmaması, ve ayarın eylemlerden ayrı bir başlık
altında toplanması.

### 5.1 Kararsız bir iddia kaldırıldı

Klavyeyle etkinleştirme `{Enter}` ile ölçülüyordu ve tam takım koşusunda dört
denemeden ikisinde kırılıyordu. Native bir `button`'ın Enter ile etkinleşmesi
platformun davranışıdır, bizim kodumuzun değil.

Kararsız bir test olmayan bir testten kötüdür: gerçek bir kusuru gürültünün
içinde saklar. İddia tıklamaya çevrildi; klavye erişilebilirliği ise
seçeneklerin gerçek, devre dışı olmayan `button` öğeleri olmasıyla ölçülüyor.

## 6. Menüye NE GİRMEZ

Plan bu sınırı açıkça çiziyor ve uygulandı: organizasyon yönetimi, plan ve
faturalama, ekip ve roller, marka ayarları, entegrasyonlar, yardım merkezi.
Girdikleri anda menü bir "her şey çekmecesi"ne döner ve kullanıcı bir ayarı
ararken önce menünün kendisini aramaya başlar.

Bugün menüde: görünüm tercihi, çalışma alanı değiştir, çıkış.

## 7. Kalan

- Profil, hesap güvenliği, dil ve bölge, klavye kısayolları — bunların hiçbiri
  henüz ürün olarak yok; olmayan bir ekrana bağlantı koymak, olmayan bir
  yetenek vaat etmek olurdu.
- Dar ekranda menünün açılır liste yerine alttan gelen bir sayfa (bottom sheet)
  olması (`docs/50` §7).
