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
