# 45 — Kirli worktree diff manifestosu (2026-08-27)

İlk talimatın bir maddesi karşılıksız kalmıştı: commit edilmemiş değişiklik
taşıyan worktree'ler için **dosya düzeyinde bir manifesto** çıkarılacak, her
değişiklik *eskimiş* ya da *değerli* olarak işaretlenecek, değerli olan yeni
bir `origin/main` paketine elle taşınacaktı. Bu, o manifestodur.

## Yöntem

Her dosya `origin/main` karşılığıyla karşılaştırıldı. Üç sonuç mümkündü:
main'de yok (potansiyel değerli), main ile birebir aynı (eskimiş), ya da
farklı — bu son durumda **farkın ne olduğuna bakıldı**, çünkü satır sayısı
tek başına "değerli" demez.

## Bulgu

| Worktree | Dosya | Sonuç |
| --- | --- | --- |
| `zabuno-fe-brand-locations` | `AGENTS.md` | **Eskimiş** — main 72 satır önde |
| `zabuno-fe-dashboard` | `AGENTS.md` | **Eskimiş** — aynı |
| `zabuno-fe-media` | `AGENTS.md` | **Eskimiş** — aynı |
| `zabuno-fe-menu` | `AGENTS.md` | **Eskimiş** — aynı |
| `zabuno-fe-publication-qr` | `AGENTS.md` | **Eskimiş** — aynı |
| `zabuno-fe-dashboard-task` | `DashboardPage.tsx`, `DashboardSetupJourney.tsx` | **Eskimiş** |
| `zabuno-fe-menu-task` | `MenuCatalogWorkspace.tsx` | **Eskimiş** |
| `zabuno-s1-wp01a-foundation` | `WorkspaceApp.wp05.test.tsx`, `BillingPage.tsx` | **Eskimiş** |

**Değerli çıkan hiçbir değişiklik yok.**

## Neden eskimiş — satır sayısına değil içeriğe bakıldı

Beş dosyada main daha uzun; ama worktree'lerde main'de bulunmayan satırlar da
vardı (58, 11, 42, 3 ve 120 satır). O satırlar okundu ve hepsi aynı şeye
çıktı: **main'in çoktan değiştirdiği eski hâller.**

`MenuCatalogWorkspace.tsx` fazlalıkları ham palet sınıfları
(`bg-white`, `border-neutral-300`, `dark:bg-neutral-900`) ve bileşene özgü
para biçimleme içeriyor. İkisi de main'de kaldırıldı: ham palet mutlak yasak
(`DS-RAW-PALETTE-BANNED-01`, borç 895 → 0), para biçimleme tek yerde
birleştirildi. Yani o satırlar kaybedilecek iş değil, **geri alınmış karar.**

`DashboardPage.tsx` fazlalıkları eski bir bileşen imzası ve eski bir
`id="dashboard"` — main `id="section-dashboard"` kullanıyor; o değişiklik iki
öğeyi eşleştiren belirsiz bir test sorgusunu düzeltmek için yapılmıştı.

## Sonuç

Sekiz worktree'nin tamamındaki commit edilmemiş değişiklikler **güvenle
atılabilir.** Elle taşınacak bir şey yok, dolayısıyla talimatın "değerli olanı
yeni pakete taşı" kısmının içeriği boştur.

Silme işlemi bu belgenin kapsamında DEĞİLDİR ve yapılmadı: bir worktree'yi
temizlemek geri alınamaz ve karar sahibinindir. Manifesto, o kararın
verilebilmesi için gereken kanıttır.


---

# Ek — dal ve worktree temizliği (2026-08-27)

Sahibi kalan bütün worktree'lerin birleştirilmesini, `main`'in GitHub'a
gönderilmesini ve **hiçbir dal/worktree kalmamasını** istedi.

## Yöntem — ölçüm, varsayım değil

İlk denenen ölçü YANLIŞTI ve kaydedilmesi gerekir: `git diff main..dal`
kullanmak, main'in *daha yeni* işini de fark sayar. 109 dalın 109'u "farklı"
çıktı ve bu hiçbir şey söylemiyordu.

Doğru soru: **dalın GETİRDİĞİ içerik main'de var mı?** Her dal için birleşme
tabanından beri değişen dosyalar bulundu, sonra o dosyaların dal ve main
sürümleri nesne kimliğiyle karşılaştırıldı.

| Sonuç | Dal sayısı |
| --- | --- |
| `main`'in atası — tamamen girmiş | 56 |
| İçeriği main ile birebir aynı | 3 |
| Gerçek farkı olan | 51 |

## 51 farklı dalın incelenmesi

Fark, "birleşmemiş iş" demek değildir; çoğu **eski kopyadır**. Ayırt etmek
için tek soru soruldu: **main'de HİÇ karşılığı olmayan dosya var mı?**

19 dalda böyle dosyalar bulundu ve üç kümeye ayrıldı:

| Küme | Örnek | Karar |
| --- | --- | --- |
| **Bu turun işi** | `app/Domain/Media/*`, `config/media-slots.php`, `docs/50`, `docs/51`, `MediaDropzone.tsx`, medya migration'ı | PR #108 ile main'e girdi — silinmeden ÖNCE doğrulandı |
| **Main'in taşıdığı dosyalar** | `catalog/forms/Button.tsx` → `catalog/forms/micro/Button.tsx`; `launch-readiness/*` → `admin/pages/release-readiness/*` | Aynı içerik, yeni yol. Kayıp yok |
| **Aşılmış mimari** | `resources/js/app.tsx`, `components/AppShell.tsx`, `views/app.blade.php`, `components/public/PublicHomePage.tsx`, `PlainButton.tsx`, `raw-palette-debt.json` | Halefi var ve doğrulandı: tek giriş yerine `auth/platform/workspace.tsx`; public sayfalar Blade'de sunucuda üretiliyor; palet borcu 895 → 0 olduğu için borç dosyası kalktı |

**Değerli çıkan, main'de karşılığı olmayan hiçbir iş yok.**

## Yerel çalışma ortamı

`local-preview` dalı localhost'u (`:8787`) besliyordu. Dal silindi; worktree
`origin/main`'e **ayrık HEAD** ile bağlandı. Böylece hem "dal kalmasın"
şartı sağlanır hem localhost çalışmaya devam eder. Güncellemek için:

```bash
cd worktrees/zabuno-local-runtime-8787 && git fetch origin && git checkout --detach origin/main
```
