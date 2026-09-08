# 145 — Yayın kararı dağıtımda uygulanır

Sahibin isteği tek cümleydi: **dağıtım kendi başına tamamlansın.**

## 1. Ölçülen boşluk

Bugünkü akış şuydu:

1. Sahip bir sayfayı yayına almaya karar verir.
2. Karar `config/content-publication-decisions.php` içine yazılır — sayfa
   adıyla sayılır, kararı veren, gün ve sebep aynı satırda durur.
3. Dosya kod incelemesinden geçer ve `main`'e girer.
4. CI yeşil yanar, dağıtım koşar, sağlık kontrolü geçer, **her şey yeşildir.**
5. Ve sayfa canlıda **yoktur.**

Beşinci adımın sebebi: `ShowCorporatePageController` kütükteki
(`content_pages`) yayın durumunu okur. Durum `planned` kaldığı sürece adres
404 döner. Durumu değiştiren komut (`site:apply-publication-decisions`)
depoda vardı, testleri de vardı — ama üretimde onu çalıştıran tek yol,
birinin sunucuya SSH ile girip elle koşmasıydı.

Bu, deponun iki kez yaşadığı kusurla aynı sınıftan:

| Kusur | Eksik olan | Dağıtım ne diyordu |
|---|---|---|
| DEPLOY-SEED (`docs/90`) | Plan kataloğu | Yeşil |
| DEPLOY-PAGE-LEDGER-12 (`docs/128`) | Sayfa kütüğü | Yeşil |
| **DEPLOY-PUBLICATION-APPLIED-14** (bu belge) | Yayın kararı | Yeşil |

Üçünde de şema değil **veri** eksik, ve veri eksikliği hiçbir yerde kırmızı
göstermiyordu.

## 2. Kapı silinmedi, DARALTILDI

Yolu kapatan şey bir kapıydı ve o kapı doğru bir sebeple oradaydı:

> Giriş betiği kütüğü DOLDURUR, durumu İLERLETMEZ.
> *Bir betiğin her seferinde geçtiği kalite kapısı, kapı değildir.*

`docs/144` bu yasağı ikinci bir komuta, `site:apply-publication-decisions`e
genişletmişti. Genişletme iyi niyetliydi — yasak eskimesin diye — ama
kapının **sebebini** değil, **adını** taşıdı.

Sebebi geri koyunca ayrım kendiliğinden çıkıyor, ve komutun kendi belgesi
bunu zaten söylüyordu:

| Komut | Karar nereden gelir | Dağıtımda koşar mı |
|---|---|---|
| `site:sync-content-status` | Bir ÖLÇÜMDEN türetilir ("bu sayfanın metni yazılmış mı?"). Hiçbir insan devrede değil; bu yüzden tavanı `content_draft`. | **Hayır.** Her dağıtımda kendiliğinden yürüyen bir ilerletici, o tavanı bir gün yükseltmenin en kolay yolunu açardı. |
| `site:apply-publication-decisions` | Verilmiş bir karardan. Dosya sayfaları ADIYLA sayar, kararı vereni ve sebebini taşır, kod incelemesinden geçer. | **Evet.** Kapıdan geçen şey bir betik değil, bir insandır — ve o kapı burada değil, incelemededir. |

Kapı dağıtıma taşınmadı. Dağıtım yalnız **verilmiş** kararı taşır.

Bu, `DEPLOY-GATED-04`'ün izlediği yol: sahip kararını değiştirdiğinde kapı
sessizce silinmez, **yeniden yazılır** ve sebebi kaydedilir.

## 3. Ne değişti

`docker/entrypoint.sh`, göçlerden ve kütük içe aktarımından sonra:

```
php artisan migrate --force
php artisan site:import-map
php artisan site:apply-publication-decisions   ← yeni
```

Sıra keyfî değil:

- **Göçlerden sonra**, çünkü tablo henüz yoktur.
- **`site:import-map`ten sonra**, çünkü komut adı geçen her satırın kütükte
  VAR olmasını ölçer ve bulamazsa yüksek sesle durur.
- **`exec supervisord`den önce**, çünkü site o satırda cevap vermeye başlar.
  Sağlık kontrolünün geçebildiği ilk an odur; adım ondan sonra olsaydı,
  sağlık kontrolü kararı uygulanmamış bir siteyi yeşil sayardı.

## 4. Tekrar koşması zararsız

Adım her konteyner açılışında koşar, dolayısıyla **idempotent olmak
zorunda** — ve zaten öyle:

- Bir kez yayınlanmış satıra (`was_ever_published`) bir daha dokunulmaz.
- Bu, sahibin SONRADAN verdiği bir kararı korur: bir sayfayı bakıma aldıysa,
  bir sonraki dağıtım onu sessizce yayına döndürmez. Bir insanın kararını bir
  betikle geri almak, kütüğü güvenilmez yapardı.
- Kararlar tek bir işlemde uygulanır; yarım açılmış bir yayın yoktur.

Ölçen testler bu pakette YAZILMADI, zaten vardılar:
`ApplyPublicationDecisionsCommandTest::test_running_it_twice_changes_nothing`
ve `::test_it_never_overrules_a_later_human_decision`.

## 5. Kırmızı, kırmızı kalır

Sessizce atlanan bir adım, hiç olmayan bir adımdan kötüdür: yeşil bir
dağıtım sahibe "kararın uygulandı" der.

- Giriş betiği `set -euo pipefail` ile koşar. Komut başarısız olursa
  konteyner **hiç açılmaz**, sağlık kontrolü 120 saniyede geçemez ve dağıtım
  kırmızı verir.
- Çağrı `|| true` benzeri bir kaçışla yumuşatılamaz; kapı özellikle onu arar.
- Komut zaten ölçmeden yayınlamaz: metni olmayan ya da kütükte bulunmayan bir
  satır için **hiçbir şey yapmadan** durur.

### Sunucu secret'ı yoksa

Akışın bilinen ve bilinçli davranışı: sunucu secret'ları tanımlı değilse
deploy işi kırmızı vermeden **atlanır**. Bu doğru bir karar — her birleşmede
kırmızı bir X görmek "kırmızıyı görmezden gel" alışkanlığı yaratır — ama
sessiz kaldığı sürece tehlikeli.

`guard` işi artık atlamayı **adıyla** duyuruyor: bir `::warning::` ve koşum
özetinde, koşmayan adımların listesi (göçler, sayfa kütüğü, yayın kararları)
ile birlikte *"bu koşumun yeşil olması, sitenin güncellendiği anlamına
gelmez"*.

## 6. Kapılar

`tests/Feature/Deployment/DeploymentContractTest.php`:

| Kapı | Ne ölçer |
|---|---|
| `DEPLOY-PAGE-LEDGER-12` (yeniden yazıldı) | Giriş betiği ölçümden karar TÜRETEN bir komut çalıştırmaz. |
| `DEPLOY-PAGE-LEDGER-13` (yeniden yazıldı) | Kütüğün yayın durumuna yazan her `site:` komutu iki listeden **tam olarak birinde**: yasaklı ya da "verilmiş insan kararını uygular". Sınıflandırılmamış üçüncü bir komut kapıyı kırar. |
| `DEPLOY-PUBLICATION-APPLIED-14` | Adım VAR; göçlerden ve kütükten SONRA; site trafiğe açılmadan ÖNCE. |
| `DEPLOY-PUBLICATION-APPLIED-14` | Adım yutulamaz: `set -euo pipefail` duruyor, `set +e` yok, çağrı çıplak. Ve sağlık kontrolü konteyner yayına alındıktan sonra geliyor. |
| `DEPLOY-PUBLICATION-APPLIED-14` | Sunucu tanımsızken atlama, uygulanmayan kararı adıyla söylüyor. |

## 7. Geri alma

Üç kademe, en ucuzdan en pahalıya:

1. **Kararı geri al.** `config/content-publication-decisions.php` içinden
   satırı çıkar ve birleştir. Bir sonraki dağıtım onu artık yayınlamaz —
   ama ZATEN yayınlanmış bir satırı geri de almaz (madde 4). Yayındaki
   sayfayı kapatmak için `--rollback` gerekir (`docs/144` §6).
2. **Adımı geri al.** `docker/entrypoint.sh`'taki iki satırı sil; akış
   `docs/144`'teki hâline, kararın elle uygulandığı düzene döner.
3. **Paketi geri al.** Bu paketin taahhüdünü `git revert` et. Kapılar da
   eski hâline döner; `DEPLOY-PAGE-LEDGER-12` yasağı yeniden iki komutu
   kapsar.

Konteyner düzeyinde geri alma yolu değişmedi: sunucudaki `.image.env`
içindeki etiketi bir öncekiyle değiştirip `docker compose up -d` (`docs/42`).
