# 59 — Durum tasarımdır

**Paket:** `FF-02c`.

## 1. Sorun

Sayfalar durumlarını **her yerde ayrı ayrı** yazıyordu: 15 farklı yerde elle
kurulmuş `role="status"` / `role="alert"`. Aynı kavram her sayfada başka
göründü, ve daha kötüsü — bazıları **yanlış duyuruldu.**

En pahalı karışma hata ile kısıt arasındaydı. Bu depoda bir kez yaşandı:
Analytics'in 402 yanıtı hata gibi sunuluyor ve "tekrar deneyin" diyordu. Plan
satın alınmadıkça tekrar denemek **hiçbir zaman** işe yaramaz.

## 2. Yalnız biri gerçekten arızadır

| Durum | Duyuru | Neden |
| --- | --- | --- |
| `error` | `role="alert"` | Gerçekten bozulmuş bir şey var |
| `empty` | `role="status"` | Bozuk değil — henüz veri yok |
| `prerequisite` | `role="status"` | Bozuk değil — sıradaki adım yapılmamış |
| `planRestricted` | `role="status"` | Bozuk değil — satın alınmamış |
| `permission` | `role="status"` | Bozuk değil — yetki yok |
| `loading` | `role="status"` | Bekleme, sonuç değil |

`role="alert"` ekran okuyucuyu **böler** ve aciliyet bildirir. Normal bir durum
için kullanmak, gerçek uyarının değerini düşürür.

## 3. Çıkış yolu TİP seviyesinde zorunlu

```ts
type WithAction    = Base & { action: ReactNode; whyNoAction?: never };
type WithoutAction = Base & { action?: never; whyNoAction: string };
```

Eylemsiz ve gerekçesiz bir durum **derlenmiyor**. Testle değil tiple
zorlanıyor, çünkü test yazılmayabilir ama tip atlanamaz.

`whyNoAction` bir kaçış deliği değil: gerçekten eylem olmayan durumlar vardır —
yetki kendine verilemez — ve o zaman söylenmesi gereken şey **kimden
isteneceğidir.**

## 4. Yolda çıkan iki gerçek kusur

**Menü hatasının çıkış yolu yoktu.** Kullanıcı "menü yüklenemedi" görüyor ve
yapabileceği hiçbir şey bulunmuyordu. Yeniden yükleme `WorkspaceApp`'te vardı
ama sayfaya hiç geçirilmemişti.

**İç içe iki canlı bölge.** `Spinner` kendi `role="status"`'unu taşıyor; onu
duyurulan bir kabın içine koymak aynı metni iki kez okutuyordu. `Spinner`
artık dekoratif kip alıyor: zaten duyurulan bir bölgenin içindeki gösterge
ikinci kez duyurmaz.

## 5. Kanıt

1030 ön yüz testi. Tip kısıtı, eylemsiz bir hata durumu yazılarak doğrulandı —
derleyici reddetti.

## 6. Kalan

`partial`, `success` ve `degraded` durumları ile kalan 12 ad-hoc durumun
dönüştürülmesi. Şablon kataloğu (Overview / Collection / List-detail / Editor /
Settings / Analytics / Task-flow / Review) ayrı bir paket.
