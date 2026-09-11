<?php

declare(strict_types=1);

namespace App\Application\Mail\Port;

/**
 * Bu gönderim hangi posta sürücüsüyle çıkacak?
 *
 * Üretimde `.env` konteynerin içinde yaşamaz ve config önyüklemede
 * dondurulur; kasadan girilen bir anahtar ise çalışma zamanında değişir.
 * Bu port, göndermeden HEMEN ÖNCE kimlik-bilgisini kasadan (yoksa env'den)
 * çözer, sürücü yapılandırmasını tazeler ve kullanılacak sürücünün adını
 * döner. Böylece superadmin UI'dan anahtar girdiği an posta çalışır —
 * sunucuya dokunmadan, yeniden deploy etmeden.
 *
 * SEÇİM BAŞARISIZ OLABİLİR — VE SESSİZCE DEĞİL.
 *
 * Kimlik hiç girilmemişse bu kural olarak bir arıza değildir: varsayılan
 * sürücünün adı döner. Ama kimlik GİRİLMİŞ ve yapılandırma taşınamıyorsa
 * (örneğin uç nokta taşıyıcının host alanına sığmıyorsa) gerçekleştirme bir
 * istisna ATAR; sessizce `mail.default`'a dönmek, üretimde `log` demek —
 * yani "gönderildi" deyip hiçbir yere ulaşmayan bir e-posta demek — olurdu.
 *
 * ÜRETİMDE VARSAYILANIN DA GÖNDERİYOR OLMASI GEREKİR. Kimlik hiç
 * girilmemişken bile, üretimdeki varsayılan gönderici e-postayı bir dosyaya
 * (`log`), belleğe (`array`) ya da hiçliğe (`null`) yazıyorsa — adı ne
 * olursa olsun, takma ad da çözülür — gerçekleştirme yine istisna ATAR:
 * ortada giden bir yol yoktur ve "gönderildi" demek yanlış olurdu. Gerçek
 * bir yedek (örneğin SMTP) korunur, yerel/test ortamı kapsam dışıdır.
 *
 * ÇAĞIRANIN YÜKÜMLÜLÜĞÜ. Bu yüzden `select()` çağrısı, çağıranın KENDİ
 * gönderim hata yolunun İÇİNDE yapılır — gönderme çağrısıyla aynı `try`
 * bloğunda. Dışarıda bırakılırsa istisna isteğin tepesine çıkar: kaydı
 * çoktan yazılmış bir destek talebi ya da veri hakkı talebi için kullanıcı
 * 500 görür ve satır "bildirilemedi" sonucunu bile alamaz. İstisna
 * mesajının arındırılmış olması gerekir (sır ve hedef adres taşımaz), ama
 * çağıran yine de onu ekrana değil kendi arıza yüzeyine yazar.
 *
 * Kimliğin doğrulanması gereken ve kaydı henüz OLUŞMAMIŞ bir istekte
 * (örneğin "şifremi unuttum") doğru yer, hesap aranmadan ÖNCE yapılan bir
 * ön kontroldür: aksi hâlde var olan hesap 500, olmayan hesap 200 alır ve
 * bu fark tek başına hesap sayımına yarar.
 *
 * @throws \RuntimeException Kimlik girilmiş ama yapılandırma taşınamıyorsa;
 *                           ya da üretimde kimlik yokken varsayılan
 *                           gönderici hiçbir yere göndermiyorsa.
 */
interface MailTransportSelectorPort
{
    public function select(): string;
}
