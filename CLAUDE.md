# CLAUDE.md — bu repo (zabuno/zabuno) içinde Claude'a özel kurallar

`AGENTS.md` bu dizindeki tüm ajanlar için geçerlidir; bu dosya yalnız Claude
oturumları için ek netleştirme taşır. Workspace/Git sınırı (aktif repo, dış
arşiv, workspace-parent, fail-closed preflight) `AGENTS.md` §6a'da
kategorik olarak, yerel `.local/WORKSPACE-BOUNDARY.md`'de ise mutlak-yol
düzeyinde tanımlıdır; bu dosya o sınırı **tekrar tanımlamaz**, ona uyar.

## Kapsam sınırı

- Bu paketteki Claude oturumu **tek writer**'dır (bkz. görev talimatındaki ROL VE
  YETKİ bölümü). Aynı pakette başka bir writer'ın (Codex dahil) production/runtime
  kodu, Kernel testi veya Actionplan kanonik içeriği üretmesi kabul edilmez; bu
  paket zaten yalnız Claude tarafından yazılmıştır.
- Bu dizin artık S1-WP01A foundation kapsamında gerçek ürün/runtime kodu da
  içerir (`app/`, `config/`, `routes/`, `resources/`, `composer.json`/
  `package.json` ve kilit dosyaları — bkz. `AGENTS.md` §1); dolayısıyla "tek
  writer" kısıtı hem *belge tutarlılığı* (aynı kavramın iki farklı dosyada
  çelişen şekilde tanımlanmaması) hem de bu runtime kodun kendisi için
  geçerlidir — aynı değişiklik paketinde başka bir writer'ın (Codex dahil)
  bu production kodunu değiştirmesi kabul edilmez.

## Yazım sırası önerisi (bağlayıcı değil, rehber)

1. Provenance ve şart (00, 01)
2. Journey/roller, mimari kararlar (02, 03)
3. Modül kataloğu ve alt sistemler (04–15)
4. Gap/unknown-unknowns (16)
5. Waterfall master + 8 stage (17–25)
6. Matris, QA, kaynak kaydı, izlenebilirlik (26–29)
7. `modules/`, `skills/`, `templates/`, `research/`

Bu sıra modülü/dokümanı birbirine referans verirken ileri-referansların (henüz
yazılmamış dosyaya link) geçici olarak var olmasına izin verir; her yeni dosya
tamamlandığında geriye dönük linkler doğrulanır (bkz. self-check madde 5, broken
link hedefi sıfır).

## Pane yaşam döngüsü — garbage collector çağrısı zorunludur

- `.claude/skills/pane-garbage-collector/SKILL.md` (agent: `.claude/agents/
  pane-garbage-collector.md`) bu repoda **zorunlu** bir yaşam döngüsü
  adımıdır: yeni worker kabulünden önce, worker handoff/exit sonrası, task
  kapanışında, Guardian/PTX bellek baskısında ve owner'ın açık isteğinde
  çağrılır. Asla zamanlayıcı/daemon/cron/sleep-loop olarak çalıştırılmaz —
  yalnız gerçek bir olaya yanıt olarak, tek seferlik.
- Script varsayılan olarak dry-run'dır; `--apply` yalnız bu dosyadaki
  duran owner yetkilendirmesi altında ve dry-run çıktısı incelendikten
  sonra kullanılır. Script tek seferde en fazla bir, tam kanıtlanmış
  güvenli Pane'i arşivler; asla `kill`/sinyal/`--force`/`reset --hard`/
  `stash`/silme kullanmaz ve şüpheli her durumda fail-closed davranır
  (arşivleme sıfır).
- **Owner yetkilendirmesi (standing authorization):** GC01 paketi
  kapsamında owner, script'in bu SKILL.md prosedürüne uyan `--apply`
  çağrılarını —dry-run çıktısı önce incelenmek kaydıyla— önceden
  onaylamıştır. Bu yetki yalnız script'in kendi güvenlik kısıtlarına
  (tek pane, non-force, fail-closed) uyan çağrılar için geçerlidir; script
  dışında elle kurulan hiçbir Pane/git komutunu kapsamaz.
- **PANE_RESTART_REQUIRED sonrası kim ne yapar:** script bu token'ı
  bastığında Pane'i kendisi asla yeniden başlatmaz/relaunch etmez. Pane'in
  zarifçe kapatılıp yeniden açılması yalnız dış Codex Desktop MASTER
  oturumunun kararıdır ve yalnız o an aktif olan tüm writer'lar güvenle
  handoff edilmişken yapılır. GC agent'ı bu kararı asla kendisi almaz.

## Ton

- Owner teknik değildir; teknik kararlar somut restoran/SaaS kullanıcı yolculuğu
  örnekleriyle açıklanır (bkz. kök yönetişim talimatındaki "Owner persona" maddesi).
  Bu, `docs/` içindeki her stage dokümanının `kullaniciYolculugu` alanında yapılır.
- Metafor karar/kontrat/test kanıtının yerine geçmez; yalnız anlaşılırlığı artırır.
