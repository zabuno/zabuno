# 83 — Kullanıcı kendi hesabını kendi onarır (P1-07)

## Önce ne oluyordu

Self-service bir üründe kullanıcı kendi hesabını kendi onarır. Bugün üç şey
yapamıyordu:

- **Adını değiştiremiyordu.** `config/fortify.php` içinde
  `updateProfileInformation()` yoktu.
- **Oturumu açıkken şifresini değiştiremiyordu.** `updatePasswords()` de
  yoktu; tek yol "şifremi unuttum" akışıydı — yani şifresini **bilen** bir
  kullanıcının onu değiştirmek için önce unutmuş gibi davranması gerekiyordu.
- **Davet ettiği kişinin rolünü düzeltemiyordu.** Yanlış rol verdiyse tek
  çare üyeyi **silip yeniden davet etmekti**: kişi erişimini kaybediyor, yeni
  bir davet bekliyor ve bu sırada iş duruyordu.

## Şimdi ne oluyor

Ayarlar → **Hesap**: ad ve şifre. Ekip listesinde her satırda bir rol
seçici.

## Şifre değişince ne olur — açık karar

Gereksinim, diğer oturumlar için davranışın **açıkça kararlaştırılmasını ve
testli olmasını** istiyordu (kriter 1).

**Karar: diğer oturumlar sonlandırılır.**

İnsanların şifre değiştirmesinin en yaygın nedeni, birinin onu ele
geçirdiğinden şüphelenmektir. Diğer oturumları açık bırakmak işlemin amacını
boşa çıkarırdı: şifre değişir, izinsiz giren kişi içeride kalır.

Sonlandırma **oturum tablosundan** yapılır (`SESSION_DRIVER=database`), çünkü
ölçülebilir olan tek yol budur. "Remember token"ı döndürmek yalnız hatırlanan
girişleri keser, açık oturumları değil — ikisi de yapılıyor.

Bir test hem kullanıcının iki cihazdaki oturumunun kapandığını **hem de
başkasının oturumuna dokunulmadığını** donduruyor.

Ve bu **önceden söyleniyor**: sürpriz bir çıkış, kullanıcıya ürünün
bozulduğunu düşündürür.

> Changing your password signs you out everywhere else. This device stays
> signed in.

## Mevcut şifre neden soruluyor

Sorulmasaydı, açık bırakılmış bir bilgisayarın başına oturan kişi hesabın
tamamını ele geçirirdi: oturum zaten açık, şifreyi değiştirmek ise diğer her
cihazı dışarı atardı.

Yol **hız sınırlı** (6/dk): mevcut şifre burada doğrulanıyor ve sınırsız
deneme, açık bırakılmış bir makinede şifre tahmin etmenin yolu olurdu.

## Rol düzeltme

### Sahip yapar, Manager yapmaz

İzin listesiyle ayrılamıyordu: `WorkspaceManage` Manager'da da var ve
**olmalı** — Manager şube ve karekod yönetir. Ama kimin ne yapabileceğine
karar vermek ayrı bir şeydir; sahiplik devri de aynı sebeple sahibe bağlı.

Cevap **403, 404 değil**: Manager çalışma alanını görüyor, varlığını
gizlemenin anlamı yok. Deponun iki aşamalı kapı dili tam olarak bunu söylüyor
— göremiyorsa 404, görüyor ama yetkisi yoksa 403; ve kullanıcının çıkış yolu
farklıdır (sahipten istemek).

### Sahibin rolü buradan değişmez

Sahibi düşürmek çalışma alanını **sahipsiz** bırakabilirdi — ve sahipsiz bir
çalışma alanını kimse onaramaz. Sahiplik ayrı bir akışla **devredilir**.

Aynı sebeple `owner` dağıtılabilir roller listesinde yok; `member` de yok
çünkü o rol yalnız eski kayıtlar için var.

### Değişiklik anında etkilidir

Bir test iznin **yokluğunu** ölçüyor: Manager'ken ekip listesini görebilen
kişi, Editor'a düşürüldükten sonraki ilk istekte 404 alıyor.

## Yol boyunca çıkan gerçek kusur

Rol seçicisi ilk hâlinde yalnız Editor ve Manager seçeneklerini basıyordu.
Veritabanında **eski `member` rolünü** taşıyan satırlar var; o satırlarda
seçici, kişiyi listedeki ilk seçenek gibi — yani **"Editor" gibi** —
gösteriyordu. Ekran yalan söylüyordu.

Mevcut rol dağıtılabilir listede değilse artık **devre dışı bir seçenek**
olarak basılıyor: gerçek okunur, ama geri seçilemez. Sahip isterse o kişiyi
Editor ya da Manager'a taşıyabilir — eski rolü geri veremez, ki zaten
verilmemeli.

Bu kusuru mevcut bir test yakaladı (`TeamPage.members.test.tsx`, satırda
`member` metnini arıyordu) — testin bulduğu şey bir test sorunu değil, bir
ürün yalanıydı.

## Kanıt

`tests/Feature/Account/AccountMaintenanceTest.php` (10),
`AccountSettingsRegion.test.tsx` (4)

| Requirement | Ne donduruluyor |
| --- | --- |
| `ACCOUNT-NAME-01` | Ad düzeltilir; boş ad reddedilir, sessizce yutulmaz |
| `ACCOUNT-PASSWORD-01` | Mevcut şifre kanıtlanarak yenisi belirlenir |
| `ACCOUNT-PASSWORD-WRONG-CURRENT-01` | Yanlış mevcut şifre 422 |
| `ACCOUNT-PASSWORD-OTHER-SESSIONS-01` | Kullanıcının diğer oturumları kapanır, başkasınınki kapanmaz |
| `TEAM-ROLE-CHANGE-01` | Rol düzeltilir, kişi silinmez |
| `TEAM-ROLE-IMMEDIATE-01` | Düşürülen kullanıcının sonraki isteği 404 |
| `TEAM-ROLE-LAST-OWNER-01` | Sahip kendini düşüremez |

## Ürün iddiası

Çalışır: kullanıcı adını ve şifresini panelden değiştirir (ve şifre değişince
diğer cihazlarından çıkış yapılır); sahip yanlış verilmiş bir rolü kişiyi
silmeden düzeltir.
