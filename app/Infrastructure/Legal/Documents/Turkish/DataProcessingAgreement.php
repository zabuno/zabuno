<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;
use App\Domain\Legal\SubprocessorInventory;

final class DataProcessingAgreement
{
    public static function document(SubprocessorInventory $subprocessors): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'data-processing',
            version: '0.1',
            effectiveDate: '2026-09-08',
            title: 'Veri İşleme Sözleşmesi',
            summary: '{company.legal_name} tarafından Zabuno\'ya eklediğiniz kişisel verilerin sizin adınıza nasıl işlendiği: nelerin neden işlendiği, başka kimlerin eriştiği, nerede tutulduğu, nasıl korunduğu ve geri istediğinizde veya silinmesini talep ettiğinizde ne olduğu.',
            sections: array_merge(
                [
                    new LegalSection('Taraflar ve bu sözleşmenin niteliği', [
                        'Bu Veri İşleme Sözleşmesi ("VİS"), Zabuno\'yu kullanan işletme olarak sizin ("veri sorumlusu") ile kayıtlı adresi {company.address}, MERSİS numarası {company.mersis}, vergi dairesi {company.tax_office}, vergi numarası {company.tax_number} olan {company.legal_name} ("veri işleyen") arasında kurulur. Veri işleyene {company.email} veya {company.phone} üzerinden ulaşabilirsiniz.',
                        'Veri işleyenin Hizmet Koşullarında açıklanan hizmeti sunarken sizin adınıza kişisel veri işlediği her durumda uygulanır. O sözleşmenin parçasıdır ve onun yerine geçmez. Hesap sahibi olarak size ait kişisel veriler bakımından veri işleyen veri sorumlusudur; bunlara Gizlilik Politikası ve Kişisel Verilerin Korunması Aydınlatma Metni uygulanır.',
                        'Bu metin, 6698 sayılı Kişisel Verilerin Korunması Kanununun 12. maddesini ve Genel Veri Koruma Tüzüğünün size uygulandığı durumlarda Tüzüğün 28. maddesini karşılamak üzere yazılmıştır. İkisinin farklı düzenlemeler gerektirdiği durumlarda daha sıkı gereklilik uygulanır.',
                    ]),
                    new LegalSection('Konu, nitelik, amaç ve süre', [
                        'Konu ve nitelik: çalışma alanınızı barındırmak ve işletmek, hazırladığınız menüyü saklamak ve yayımlamak, yayımlanmış menüyü sabit QR adresiyle misafirlerinize sunmak, hizmetin adınıza gönderdiği e-postaları iletmek ve etkinleştirdiğinizde sipariş, misafir puanlaması, ekip üyeliği ve yapay zekâ destekli menü aktarımı.',
                        'Amaç: yalnızca abone olduğunuz hizmeti sağlamak. Veri işleyen, hizmete eklediğiniz verileri kendi amaçları için kullanmaz, satmaz ve kendi modellerini eğitmek için kullanmaz.',
                        'Süre: hesabınız var olduğu sürece, sonrasında ise yalnızca 11. bölümde açıklanan süre boyunca.',
                    ]),
                    new LegalSection('İlgili kişi ve kişisel veri kategorileri', [
                        'İlgili kişiler: ekibinizde çalışma alanına davet ettiğiniz kişiler, yayımladığınız menüyü açan misafirler ve iletişim formundan size veya veri işleyene yazan kişiler.',
                        'Ekip üyeleri: ad, e-posta adresi, verdiğiniz rol ve oturumları açıkken kaydedilen ağ adresi ile tarayıcı tanımlayıcısı.',
                        'Kendinizin girdiği işletme iletişim bilgileri: bilerek yayımladığınız işletme adı, adresi, iletişim e-postası, telefon numarası ve çalışma saatleri.',
                        'Kendinizin girdiği menü içeriği: kategoriler, ürünler, fiyatlar, alerjenler, açıklamalar ve yüklediğiniz görseller. Bu içeriğe bir kişiyi eklemeyi seçerseniz — örneğin çalışan fotoğrafı — o kişi de ilgili kişi olur ve onu oraya ekleyen sizin kararınızdır.',
                        'Misafirler: hangi menünün açıldığı, hangi ürünlerin görüntülendiği veya arandığı ve ilgili özellikleri açtığınızda sipariş veya puanlama. Misafirlerden ad veya hesap istenmez. Ziyaretler ağ adresi, tarayıcı tanımlayıcısı ve tarihten türetilen takma kimlikli anahtarla sayılır; anahtar tekrar adrese dönüştürülemez ve her gün değişir.',
                        'Hizmet hiçbir özel nitelikli kişisel veri istemez; bu tür veriler hizmete girilmemelidir.',
                    ]),
                    new LegalSection('Talimatlarınız', [
                        'Veri işleyen kişisel verileri yalnızca belgelenmiş talimatlarınızla işler. Hizmeti kullanmanız başlı başına talimattır: çalışma alanınızda oluşturduğunuz, yayımladığınız, dışa aktardığınız, sildiğiniz veya etkinleştirdiğiniz şeyler, veri işleyenin verilerle yaptığı işlemlerdir. Bunun ötesindeki işlemler yazılı talimat gerektirir.',
                        'Veri işleyen, bir talimatın veri koruma hukukunu ihlal ettiğini düşünürse size bildirir ve yerine getirmeyi reddedebilir. Kanun veriyi başka şekilde işlemesini zorunlu kılıyorsa — örneğin mahkeme kararı — kanun yasaklamadıkça işlemden önce size bildirir.',
                    ]),
                    new LegalSection('Gizlilik ve verilerinizi kimlerin görebildiği', [
                        'Çalışma alanınızda kimin neyi görebileceğini ve değiştirebileceğini ekibinize verdiğiniz roller belirler; veri işleyen bunları sizin yerinize genişletmez.',
                        'Veri işleyen tarafında üretim verilerine erişebilenler hizmeti işleten kişilerdir. Gizlilik yükümlülüğü altındadırlar. Erişim destek, hata giderme ve hizmeti çalışır tutma amacıyla gerçekleşir ve görevin gerektirdiğiyle sınırlıdır.',
                        'Bu hizmette henüz kendiniz okuyabileceğiniz hesap düzeyinde denetim izi tutulmaz. Çalışma alanındaki medya işlemleri kaydedilir ve orada incelenebilir; daha kapsamlı iz planlanmıştır ancak bugün mevcut değildir ve bu metin aksini iddia etmez.',
                    ]),
                ],
                self::subprocessorSections($subprocessors),
                [
                    new LegalSection('Veriler nerede işlenir?', [
                        'Hizmete eklediğiniz veriler yukarıdaki alt işleyen listesinde açıklanan sunucuda saklanır. Sunucu Türkiye dışındaysa yurt dışına aktarım, 6698 sayılı Kanunun 9. maddesindeki ve Genel Veri Koruma Tüzüğü size uygulanıyorsa Tüzüğün V. Bölümündeki şartlara göre yapılır.',
                        'Yukarıda adı geçen bazı alt işleyenler kendi sistemlerini işletir; bu sistemlerin konumu bu hizmet tarafından ölçülmez, kendi koşullarında belirtilir. Listede tahmin yürütülmeden bu durum açıkça söylenir.',
                        'Bugün verilerin yalnızca Türkiye içinde tutulduğu bir düzenleme yoktur. Kuruluşunuz buna ihtiyaç duyuyorsa abone olmadan önce belirtin: bu, hizmetin içindeki bir ayar değil, nerede çalıştığıyla ilgili bir konudur.',
                    ]),
                    new LegalSection('Bugün uygulanan güvenlik önlemleri', [
                        'Şifreler yalnızca kriptografik özet olarak saklanır ve geri okunamaz. Hizmete bağlantılar aktarım sırasında şifrelenir.',
                        'Sağlayıcı kimlik bilgileri (e-posta, ödeme ve yapay zekâ anahtarları) şifreli tutulur; uygulamanın web sayfalarını sunan kısmı bunları hiçbir şekilde geri okuyamaz, yalnızca çağrı yapan bileşenler okuyabilir.',
                        'Her çalışma alanı diğerlerinden yalıtılmıştır; bir üyenin alan içinde neler yapabileceğini rolü belirler.',
                        'Yüklenen dosyalar yayımlanmadan önce karantinaya alınır ve taranır. Taramadan geçemeyen dosya reddedilir. Tarayıcı ilgili ortamda kullanılamadığı için taranamayan dosya bekletilir ve yayımlanmaz; hiçbir zaman taranmış olarak işaretlenmez.',
                        'Herkese açık uç noktalarda hız sınırı vardır; bir forma veya QR adresine yoğun istek gönderildiğinde trafik yavaşlatılır.',
                        'Uyuşmazlık konusu dosyalar çalışma alanında hukuki koruma altına alınabilir; bu, toplu işlemler dahil silmeyi engeller ve koruma gerekçesiyle birlikte kaydedilir.',
                        'Onay kayıtları yalnızca eklemeye açıktır: onay verildiğinde yazılır ve sonradan yeniden yazılmaz; böylece hangi metnin ne zaman kabul edildiği kanıtlanabilir kalır.',
                        'Ziyaretçi kabul etmeden hiçbir üçüncü taraf ölçüm aracı yüklenmez; içerik güvenliği politikası etkinleştirilmemiş araçları engeller.',
                    ]),
                    new LegalSection('Mevcut OLMAYAN güvenlik önlemleri — yukarıdaki bölüme güvenmeden önce okuyun', [
                        'ISO 27001 sertifikası, SOC 2 raporu veya dış güvenlik denetimi yoktur. Tedarikçi anketi bunlardan birini soruyorsa bugünkü yanıt bulunmadığıdır.',
                        'Yedekler hizmetin kendisiyle aynı sunucuda tutulur. Bugün sunucunun dışında kopya yoktur. Bu, işletmecinin bilinçli ve geçici kararıdır; sonucu açıkça söylenmelidir: sunucu tamamen kaybedilirse yedekler de onunla birlikte kaybedilir. Olağan arızadan sonra yedekten dönme otomatik tatbikatla sınanır; tüm makinenin kaybından sağ çıkmak bu hizmetin bugün ileri sürebileceği bir iddia değildir.',
                        'Belirli bir zamana dönüş olanağı yoktur. Geri yüklemeyle ulaşılabilecek en güncel nokta, son veritabanı dökümünün alındığı andır.',
                        'Kullanılabilirlik ölçümü ve herkese açık durum sayfası yoktur; bu nedenle hiçbir yerde kullanılabilirlik oranı taahhüt edilmez. Hizmet Seviyesi Koşulları, bunun için önceden nelerin bulunması gerektiğini açıklar.',
                        'Bu eksikler atlanmak yerine burada listelenir; çünkü yalnızca yukarıdaki bölüme dayanarak doldurulan tedarikçi anketi yanlış yanıtlanmış olurdu.',
                    ]),
                    new LegalSection('Verisi işlenen kişilere yanıt vermenize yardım', [
                        'Bir ekip üyesi veya misafir 6698 sayılı Kanunun 11. maddesi ya da Genel Veri Koruma Tüzüğünün III. Bölümü kapsamındaki hakkını kullanıp size değil veri işleyene yazarsa veri işleyen sizin yerinize yanıt vermez. Talebi size iletir ve mevcut erişiminizi kullanarak yanıtlamanıza yardım eder.',
                        'Bu yardımın büyük kısmı talep gerektirmez: çalışma alanı içeriğini kendiniz okuyabilir, düzeltebilir ve silebilir; menünüzü istediğiniz zaman çalışma alanından CSV olarak dışa aktarabilirsiniz.',
                    ]),
                    new LegalSection('Kişisel veri ihlalleri', [
                        'Veri işleyen, sizin için işlediği kişisel verileri etkileyen ihlalden haberdar olursa gereksiz gecikme olmadan size bildirir, bilinenleri, etkilenenleri ve yapılanları açıklar; yeni bilgiler geldikçe sizi bilgilendirmeye devam eder.',
                        'Bugün ölçülmüş veya vaat edilmiş süre olmadığı için burada saat cinsinden bildirim süresi belirtilmez. 6698 sayılı Kanunun ve uygulanıyorsa Genel Veri Koruma Tüzüğünün veri sorumlusu olarak size yüklediği yükümlülükler bundan etkilenmez ve bunları yerine getirmek sizin sorumluluğunuzdadır.',
                    ]),
                    new LegalSection('Verilerin iadesi ve silinmesi', [
                        'Menünüzü istediğiniz zaman kimseye başvurmadan çalışma alanınızdan CSV dosyası olarak dışa aktarabilirsiniz.',
                        'İletişim formundan hesabınızın silinmesini isteyebilirsiniz. Veri işleyenin yasal olarak saklamak zorunda olmadığı veriler ardından kaldırılır. Kanunen saklaması gerekenler — ticari ve vergisel kayıtlar ile neyin kabul edildiğini kanıtlayan onay kayıtları — kanunun belirlediği süre boyunca ve başka hiçbir amaçla kullanılmadan saklanır.',
                        'Bugün ölçülen bir süre olmadığı için burada gün cinsinden silme süresi belirtilmez. Silme takvimi taahhüt edildiğinde kendi sürüm numarasıyla bu metne yazılacak ve bu cümle bunu belirtecektir.',
                        'Hesap kapatılmadan abonelik sona erdiğinde hiçbir şey silinmez: İptal ve İade Politikasında açıklandığı gibi çalışma alanı, menüler ve veriler olduğu gibi kalır; yayımlanmış menüler sunulmaya devam eder.',
                    ]),
                    new LegalSection('Bilgi ve denetim', [
                        'Veri işleyen, bu VİS\'e uyulduğunu göstermek için makul olarak ihtiyaç duyduğunuz bilgiyi sağlar; 8. bölümdeki eksikler dahil, tedarikçi anketini fiilen ölçülenlere dayanarak yanıtlar.',
                        'Bugün yerinde denetim sürekli bir hak olarak sunulmaz; çünkü bunu karşılayacak denetim programı yoktur. Kuruluşunuz böyle bir denetim istiyorsa varsaymak yerine ayrıca kararlaştırılabilmesi için abone olmadan önce gündeme getirin.',
                    ]),
                    new LegalSection('Değişiklikler, öncelik sırası ve hukuk', [
                        'Bu VİS Hizmet Koşullarının parçasıdır. Hizmet Koşullarının bir hükmü kişisel veri işleme konusunda bu VİS ile çelişirse VİS önceliklidir.',
                        'Sayfanın üstündeki sürüm numarası ve yürürlük tarihi güncel metni tanımlar. Alt işleyen listesindeki değişiklik bu metnin değişmesi değildir: liste her sayfa açılışında hizmetin çalışan yapılandırmasından üretilir; dolayısıyla okuduğunuz liste o anda kullanımda olanları gösterir.',
                        'Bu VİS\'e Hizmet Koşullarıyla birlikte Türkiye Cumhuriyeti hukuku uygulanır.',
                    ]),
                ],
            ),
            requiresSellerIdentity: true,
        );
    }

    private static function subprocessorSections(SubprocessorInventory $inventory): array
    {
        $paragraphs = [
            'Veri işleyen aşağıdaki alt işleyenleri kullanır. Liste elle yazılmaz: bu sayfayı açtığınızda kurulumun fiilen çalışan yapılandırmasından üretilir; kasada kimlik bilgisi bulunmayan sağlayıcı, bugün veri işlemediği için listelenmez.',
        ];

        if ($inventory->vaultUnreadable) {

            $paragraphs[] = 'Bu sayfa oluşturulurken kimlik bilgisi deposu okunamadığından aşağıdaki liste eksiktir: yalnızca depo olmadan belirlenebilenleri içerir. Bu durum gizlenmeden belirtilir. Sayfayı yenileyin; aynı durum sürüyorsa listeye güvenmeden önce veri işleyenden yazılı liste isteyin.';
        }

        foreach ($inventory->active as $subprocessor) {
            $paragraphs[] = $subprocessor->sentence('tr');
        }

        $paragraphs[] = 'Veri işleyen, sizin için işlediği kişisel verileri işleyecek alt işleyen eklemeden önce itiraz edebilmeniz için size bildirir. Makul veri koruma gerekçeleriyle itiraz ederseniz ve alternatif üzerinde anlaşılamazsa ücretli planı sona erdirebilirsiniz; iade edilecekler İptal ve İade Politikasına tabidir ve bu VİS buna hiçbir şey eklemez veya bundan hiçbir şey çıkarmaz.';
        $paragraphs[] = 'Veri işleyen, alt işleyenin sizin adınıza işlediği verilerle yaptıklarından size karşı sorumlu kalır. Belirli alt işleyenle yazılı veri işleme sözleşmesi yapılıp yapılmadığını veri işleyen talep üzerine yazılı yanıtlar; bu metin imzalanmamış sözleşmenin varlığını ileri sürmez.';

        return [new LegalSection('Alt işleyenler', $paragraphs)];
    }
}
