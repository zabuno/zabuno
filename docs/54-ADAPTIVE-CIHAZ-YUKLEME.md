# 54 — Adaptive cihaz yükleme: responsive değil

**Paket:** `FF-02a`.
**Durum:** uygulandı ve ölçülerek doğrulandı.

## 1. Sahibin kuralı

> "her ekran için adaptive, sonradan ekran sorgusu media query ile değişen
> değil, responsive kod yazarak tüm kodları her cihazda fazlaca yükleyen
> değil, sadece cihazı sorgulayıp, cihaza uygun frontend kodunu, cihaza
> yükleyen."

Ve iki ayrı yönde:

> "telefonla açtığımda, desktop için özel olan kod yüklenmesin."
> "desktop üzerinden açtığımda telefona özgü kod yüklenmesin."

Bu bir görsel tercih değil, bir **yükleme mimarisi** kararıdır.

## 2. Fark neden önemsiz değil

Medya sorgusuyla yapılan uyarlamada telefon, masaüstü düzeninin kodunu da
**indirir, ayrıştırır, DOM'a koyar** — sonra CSS ile gizler. 320 pikselde
indirilen her fazladan kilobayt, kullanıcının beklediği süredir.

Depoda tam olarak bu vardı: kalıcı kenar çubuğu her cihazda çiziliyor,
`hidden` sınıfı ve tek bir kapsayıcı sorgusuyla dar ekranda gizleniyordu.

## 3. Karar sunucuda verilir

`App\Support\Device\DeviceClass` — sinyal sırası:

1. **`Sec-CH-UA-Mobile`** — İstemci İpucu. Yapılandırılmış, tek amaçlı,
   tarayıcının kendi beyanı.
2. **User-Agent metni** — ipucu göndermeyen tarayıcılar için.
3. **Varsayılan: mobil.**

Varsayılanın mobil olması bilinçli ve **simetrik olmayan bir bedele**
dayanıyor: telefona masaüstü paketi göndermek, dar ekranda kullanılamayan bir
arayüz ve boşa harcanmış indirme demektir; masaüstüne mobil paket göndermek
ise yalnız daha sade bir düzen demektir — çalışır. Belirsizlikte çalışan
tarafa düşülür.

## 4. `Vary` bir süs değil, doğruluk şartı

Aynı adres (`/app`) cihaza göre farklı HTML döndürüyor. `Vary` olmadan araya
giren herhangi bir önbellek — tarayıcı, vekil, CDN — ilk gelen yanıtı herkese
servis eder.

Ortaya çıkan arıza teşhis edilmesi en zor türdendir: masaüstü kullanıcısı
mobil düzeni görür, sayfayı yenileyince düzelir, **ve kayıtlarda hiçbir iz
kalmaz.**

`Accept-CH` ise ipucu İSTER: tarayıcı onu ancak sunucu talep ettikten sonra
gönderir, yani ilk karar User-Agent'a dayanır ve sonraki isteklerde
yapılandırılmış ipuca yükselir.

## 5. Ayrım MODÜL SINIRINDA olmalı

Kabuğun içinde şunu yazmak **yetmez**:

```tsx
{deviceClass === 'desktop' ? <PersistentSidebar /> : null}
```

O dal çalışmasa bile **kod pakette bulunur, indirilir ve ayrıştırılır.** Bunu
önce böyle yazdım, sonra ölçüp değiştirdim.

Doğrusu:

- `chrome/DesktopChrome.tsx` — yalnız `workspace.desktop.tsx` içeri alır
- `chrome/MobileChrome.tsx` — yalnız `workspace.mobile.tsx` içeri alır
- `AdminShell` bu parçaları **yuva** olarak alır; kendisi üretmez
- `WorkspaceApp` da onları `import` ETMEZ — giriş noktasından işlev olarak
  alır, çünkü kendisi içeri alsaydı Vite ikisini de ortak parçaya koyardı

## 6. Ölçüm

Derlenmiş paketlerde masaüstü rayının işareti (`admin-shell-sidebar`,
`flex-[1_1_17rem]`):

| Paket | İçeriyor mu |
| --- | --- |
| `workspace.desktop-*.js` | **Evet** |
| `workspace.mobile-*.js` | **Hayır — sıfır iz** |
| `platform-*.js` | Evet (ayrı yüzey) |

Canlı: iPhone User-Agent'ı `workspace.mobile` girişini, Mac User-Agent'ı
`workspace.desktop` girişini alıyor; ikisi de diğerini **hiç** içermiyor.

### Paylaşılan olan neydi

`DrawerPanel`'i mobil-özel sanıp ayrı parçaya zorladım. **Yanlıştı**: AI komut
merkezi de onu kullanıyor, yani her iki cihazda gerekli. Gerekçesi yanlış olan
yapılandırmayı geri aldım. Cihaza özgü olan, çekmecenin kendisi değil,
*gezinti çekmecesi kompozisyonudur* — ve o ayrı.

## 7. Yolda çıkan gerçek kusur

Mobil kabuğa geçince ortaya çıktı: telefonda **hiç gezinti landmark'ı
kalmıyordu**. Çekmecedeki `SidebarNav` `asLandmark={false}` ile çiziliyordu,
çünkü eskiden kalıcı rayla aynı adı taşıyan ikinci bir `<nav>` oluşuyordu.
Kalıcı ray mobil pakette artık hiç yok; landmark'ı da kapatmak, ekran okuyucu
kullanan birinin telefonda gezintiye landmark listesinden ulaşamaması demekti.

## 8. Kaldırılan muhafız

`viewport.guard.test.ts` sahibin talimatıyla kaldırıldı. Haklı bir karardı:
o muhafız kuralın **yarısını** (akışkanlık) diğer yarısını (uyarlama)
yasaklayarak dayatıyordu ve masaüstü düzenini imkânsız kılan şey oydu.

320 px başlangıç ilkesi geçerliliğini koruyor; dayatma biçimi değişti.

## 9. Kanıt

1001 PHP testi, 1013 ön yüz testi, pint / eslint / prettier temiz.
Cihaz sınıflandırması için 4 birim testi, adaptive sunum için 4 özellik testi
(ikisi `Vary` ve `Accept-CH` üzerine).

## 10. Bu paketin KAPSAMADIĞI

`docs/50` §5'teki bilgi mimarisi henüz uygulanmadı: `Primary / Management /
Utility` grupları, Brand → Settings, Billing → Settings, Publication →
Menus içine, workspace switcher sidebar üstüne, bağlam paneli içeriği.
Ayrıca `docs/47` §4'teki form standardı ve marka formundan
timezone/currency/locale alanlarının çıkarılması.
