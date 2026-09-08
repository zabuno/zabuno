<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class AcceptableUsePolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'acceptable-use',
            version: '0.1',
            effectiveDate: '2026-09-08',
            title: 'Kabul Edilebilir Kullanım Politikası',
            summary: 'Zabuno ile nelerin yapılamayacağı, yine de yapılırsa ne olacağı ve hizmetin bugün fiilen hangi kuralları uygulayıp hangilerini uygulayamadığı.',
            sections: [
                new LegalSection('Kimlere uygulanır?', [
                    'Bu politika {company.legal_name} tarafından yayımlanır ve Zabuno\'yu kullanan herkese uygulanır: hesabı elinde bulunduran işletme, çalışma alanına davet ettiği her üye ve yayımlanmış menü sayfalarını kullanan herkes.',
                    'Hizmet Koşullarının parçasıdır ve kabul edilebilir kullanım hükmünü ayrıntılandırır. Hizmet Koşulları ile bu politika aynı şeyi farklı şekilde ifade ediyorsa birlikte okunur; biri diğerini ortadan kaldırmaz.',
                ]),
                new LegalSection('Yayımlamamanız gereken içerikler', [
                    'Nefret veya şiddeti teşvik eden ya da müstehcen içerikler dahil, Türk hukukuna veya yayımlandığı yerin hukukuna aykırı içerikler.',
                    'Haklarına sahip olmadığınız içerikler. Yüklediğiniz görsel ve metinler size ait olmalı veya kullanım lisansınız bulunmalıdır. Rakibin fotoğraflarını ya da internette bulunan bir fotoğrafı almak bu kuralın en sık ihlal edildiği durumdur.',
                    'Başka bir işletme veya kişinin kimliğine bürünen ya da işletmenizi bağlantılı olmadığı bir işletmeyle bağlantılı gösteren içerikler.',
                    'Bir misafire zarar verebilecek şekilde yanlış olduğunu bildiğiniz menü bilgileri. Alerjen bilgileri en açık örnektir: misafir ne yiyeceğine buna göre karar verebilir.',
                    'Yayımlanmasına onay vermemiş bir kişiye ait kişisel veriler. Menü herkese açık bir sayfadır; oradaki çalışan fotoğrafı herkese yayımlanır.',
                ]),
                new LegalSection('Hizmete yönelik yapmamanız gerekenler', [
                    'Size ait olmayan verilere erişmeye çalışmak: başka çalışma alanı, başka hesap veya başka bir işletmenin henüz yayımlamadığı durumdaki menüsü.',
                    'Hizmeti yoklamak, taramak veya hizmete saldırmak; hız sınırlarını, kimlik doğrulamayı, dosya taramasını veya izin modelini aşmaya çalışmak.',
                    'Hizmetin normal kullanımı olmayan otomatik trafik: yayımlanmış menüleri topluca kazımak veya QR adreslerini keşfetmek için yoğun isteklerle taramak.',
                    'Zarar vermek amacıyla dosya yüklemek: zararlı yazılım veya görsel işleyen yazılımdaki açığı kullanmak üzere hazırlanmış dosya.',
                    'Hizmeti yeniden satmak veya her biri için ödeme yapmamak amacıyla tek hesabı ayrı işletmeler arasında paylaşmak. Ayrı işletmeler çalışma alanlarıyla birbirinden ayrılır ve her biri ayrı fiyatlandırılır.',
                ]),
                new LegalSection('İletiler', [
                    'Hizmetin sizin adınıza gönderdiği ekip davetleri ve bildirim e-postaları, bunları bekleyen kişiler içindir. Davetleri istenmeyen ileti göndermek için kullanmak bu politikayı ve 6563 sayılı Elektronik Ticaretin Düzenlenmesi Hakkında Kanunu ihlal eder.',
                    'Bu hizmetin ticari elektronik iletileri, Ticari Elektronik İleti Onayı metninde açıklandığı gibi yalnızca onay veren kişilere gönderilir. Hizmet kendi misafir listenize pazarlama iletisi gönderme olanağı sunmaz; buradaki hiçbir hüküm buna izin vermez.',
                ]),
                new LegalSection('Yapay zekâ destekli özellikler', [
                    'Yapay zekâ destekli menü aktarımını kullanırken yalnızca haklarına sahip olduğunuz menü materyallerini gönderin. Gönderdikleriniz, Veri İşleme Sözleşmesinde açıklandığı gibi hizmet için yapılandırılmış yapay zekâ sağlayıcısına iletilir.',
                    'Yapay zekâ destekli özelliğin çıktısı bir taslaktır. Yayımlamak sizin kararınız ve sorumluluğunuzdur; özellikle fiyatlar ve alerjenler misafir okumadan önce bir kişi tarafından kontrol edilmelidir.',
                ]),
                new LegalSection('Bu politika ihlal edildiğinde gerçekte ne olur ve hizmet bugün neleri uygulayabilir?', [
                    'Bazı önlemler otomatiktir ve birinin fark etmesini gerektirmez. Herkese açık adreslerde hız sınırı uygulanır; bir forma veya QR adresine yoğun istek gönderen trafik, bir kişinin karar vermesi gerekmeden yavaşlatılır veya reddedilir.',
                    'Her yüklenen dosya yayımlanabilmeden önce karantinaya alınır ve taranır. Taramadan geçemeyen dosya reddedilir. Tarayıcı o ortamda bulunmadığı için taranamayan dosya bekletilir ve hiçbir zaman yayımlanmaz; sessizce temiz sayılmaz.',
                    'Geri kalanı bir kişi tarafından yapılır. Hizmette otomatik içerik denetimi yoktur: menü metninizi okuyup değerlendiren bir sistem bulunmaz. İhlal bildirildiğinde veya fark edildiğinde işlem yapılır; işletmeci, Hizmet Koşullarının öngördüğü şekilde ihlalli içeriği kaldırabilir, menüyü yayından çekebilir veya hesabı kapatabilir.',
                    'Bir dosya uyuşmazlık konusuysa çalışma alanında hukuki koruma altına alınabilir; böylece toplu işlem dahil silinemez ve koruma gerekçesi kaydedilir.',
                    'Çalışma alanı yönetim ekranlarında bugün bir hesabı tek tıkla askıya alma olanağı yoktur: askıya alma veya kapatma, işletmecinin sunucuda yaptığı bir işlemdir. Bu durum olduğundan farklı gösterilmeden yazılmıştır; çünkü ürünün yapamadığı bir yaptırımı anlatan politikaya kimse güvenemez.',
                    'Kanunun gerektirdiği durumlarda işletmeci hukuka aykırı içeriği yetkili makamlara bildirir ve kendisine iletilen karara uyar.',
                ]),
                new LegalSection('Ölçülülük ve hatalı işlem', [
                    'İşlem, yaşanan olayla orantılı yapılır. Hakları olmadan yüklenen menü fotoğrafı için herhangi bir şey kapatılmadan önce açıklama istenir; hizmete saldırı için böyle yapılmaz.',
                    'İşletmeci işlem yaptıysa ve bunun yanlış olduğunu düşünüyorsanız hesabınızdaki adresten, bu sitedeki iletişim formuyla veya {company.email} adresine yazın. Otomatik itiraz süreci yoktur ve burada var olduğu iddia edilmez.',
                    'Bu politikanın ihlali nedeniyle hesabın kapatılması tek başına iade hakkı doğurmaz. Nelerin iade edileceği İptal ve İade Politikasına tabidir.',
                ]),
                new LegalSection('Sorun bildirme', [
                    'Bu hizmet üzerinden yayımlanan ve politikayı ihlal eden içeriği ya da bulduğunuz güvenlik açığını bildirmek için iletişim formundan veya {company.email} adresine yazın. Neyi nerede gördüğünüzü belirtin.',
                    'Ödüllü açık bildirim programı veya yayımlanmış açıklama takvimi yoktur. Bildirimi bir kişi okur; ileride takvim taahhüt edilirse kendi sürüm numarasıyla buraya yazılır.',
                ]),
                new LegalSection('Bu politikadaki değişiklikler', [
                    'Sayfanın üstündeki sürüm numarası ve yürürlük tarihi güncel metni tanımlar. Bir değişiklik hizmeti mevcut kullanımınızı önemli ölçüde etkileyen kısıtlama getiriyorsa yürürlüğe girmeden önce duyurulur.',
                ]),
            ],
        );
    }
}
