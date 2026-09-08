<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;
use App\Domain\Legal\ServiceLevelCommitment;

final class ServiceLevelTerms
{
    public static function document(ServiceLevelCommitment $commitment): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'sla',
            version: '0.1',
            effectiveDate: '2026-09-08',
            title: 'Hizmet Seviyesi Koşulları',
            summary: $commitment->isComplete()
                ? '{company.legal_name} tarafından ücretli Zabuno planları için taahhüt edilen kullanılabilirlik, nasıl ölçüldüğü, kapsam dışı durumlar, kesintinin nasıl bildirildiği ve hedef karşılanmadığında sağlanan telafi.'
                : 'Kullanılabilirliğin nasıl ölçüleceği, nelerin kapsam dışı olacağı, kesintinin nasıl bildirilip telafi edileceği; bugün kullanılabilirlik oranı taahhüt edilmediğine ve taahhütten önce nelerin bulunması gerektiğine ilişkin açık açıklama.',
            sections: array_merge(
                [
                    new LegalSection('Kapsam', [
                        'Bu Hizmet Seviyesi Koşulları {company.legal_name} tarafından yayımlanır ve ücretli Zabuno planlarına uygulanır. Çalışma alanı uygulamasını ve hizmetin herkese açık adreslerden sunduğu yayımlanmış menü sayfalarını kapsar.',
                        'Hizmetin ücretsiz bölümlerini, yazılımın kendi sunucunuzdaki kurulumunu, kendi internet bağlantınızı veya cihazınızı ya da hizmetin bağımlı olduğu fakat işletmediği üçüncü taraf sistemi kapsamaz.',
                        'Bu koşullar Hizmet Koşullarının parçasıdır ve onların yerine geçmez. İptal ve iadeler İptal ve İade Politikasında kalır.',
                    ]),
                ],
                self::commitmentSections($commitment),
                [
                    new LegalSection('Kullanılabilirlik nasıl ölçülür?', [
                        'Kullanılabilirlik, bir takvim ayı boyunca hizmetin herkese açık adreslere gelen istekleri doğru yanıtladığı sürenin aya oranı olarak ölçülür.',
                        'İşletmecinin kontrolündeki nedenlerle hizmetin yanıt vermediği veya sunucu hatası döndürdüğü dönem kullanılamaz sayılır. Tek başarısız istek kesinti değildir; hizmetin yanıt vermediği dönem kesintidir.',
                        'Ölçüm yalnızca işletmeci tarafından değil sizin tarafınızdan da okunabilmelidir. Müşterinin kontrol edemediği oran taahhüt değil beyandır; bu nedenle koşullar oranın okunduğu herkese açık kaynağı belirtir ve bu kaynak oluşana kadar oran taahhüt edilmez.',
                    ]),
                    new LegalSection('Kapsam dışı durumlar', [
                        'Önceden duyurulmuş planlı bakım.',
                        'Kontrolünüzdeki unsurlardan kaynaklanan arızalar: internet bağlantınız, cihazınız, tarayıcınız veya çalışma alanınızda yaptığınız değişiklik.',
                        'Hizmetin bağımlı olduğu üçüncü tarafta — barındırma, ödeme hizmeti, e-posta iletim sağlayıcısı, yapılandırılmış yapay zekâ sağlayıcısı veya ağ işletmecisi — nedenin işletmeciye değil o tarafa ait olduğu arızalar.',
                        'Hizmet Koşulları veya Kabul Edilebilir Kullanım Politikası ihlali nedeniyle hesabın askıya alınması ve plan ödemesinin alınmadığı herhangi bir dönem.',
                        'Doğal afet, savaş, kamu makamlarının işlemleri, grev veya genel internet arızası dahil makul kontrol dışındaki olaylar.',
                    ]),
                    new LegalSection('Kesintiler ve size nasıl bildirildiği', [
                        'İşletmeci hizmetin kullanılamadığını öğrendiğinde yeniden çalışır hale getirmek için çalışır ve etkilenen müşterilere bilinenleri, etkilenenleri ve yapılanları bildirir.',
                        'Bildirim hesaptaki iletişim e-posta adresine gönderilir. Bugün herkese açık durum sayfası olmadığından kesinti başka bir yerden sorgulanamaz; bu eksikliği kesinti sırasında keşfetmenize bırakmak yerine burada belirtiyoruz.',
                    ]),
                    new LegalSection('Hedef karşılanmadığında sağlanan telafi', [
                        'Bu koşullar kapsamındaki telafi, etkilenen ayın ücreti üzerinden sonraki döneme uygulanan hizmet kredisidir. Nakit iade değildir ve İptal ve İade Politikasının iadelerle ilgili hükümlerini değiştirmez.',
                        'Hizmet kredisi, ilgili ayın bitiminden itibaren otuz gün içinde hesaptaki adresten {company.email} adresine yazarak ve talebin hangi aya ait olduğunu belirterek istenir. İşletmeci o ayın ölçümüyle yanıt verir.',
                        'Hizmet kredisi, kullanılabilirlik hedefinin karşılanmaması için bu koşulların sağladığı tek telafidir. Kanundan doğan haklarınız ve Hizmet Koşullarındaki sorumluluk hükümleri etkilenmez.',
                    ]),
                    new LegalSection('Destek', [
                        'Desteğe bu sitedeki iletişim formundan ve e-postayla ulaşılır. Yanıt süresi taahhüt edilmişse iletişim sayfasında ve aldığınız alındı e-postasında gösterilir; süre gösterilmiyorsa taahhüt yoktur ve bu koşullar böyle bir taahhüt oluşturmaz.',
                    ]),
                    new LegalSection('Bu koşullardaki değişiklikler', [
                        'Sayfanın üstündeki sürüm numarası ve yürürlük tarihi güncel metni tanımlar. Bir değer belirlenir, eklenir veya değiştirilirse sürüm numarası da değişir; böylece belirli bir ayda neyin geçerli olduğu kanıtlanabilir kalır.',
                    ]),
                ],
            ),
            requiresSellerIdentity: true,
        );
    }

    private static function commitmentSections(ServiceLevelCommitment $commitment): array
    {
        if (! $commitment->isComplete()) {
            return [new LegalSection('Bugün kullanılabilirlik oranı taahhüt edilmiyor', [
                'Önce bu bölümü okuyun: bu koşullarla bugün kullanılabilirlik yüzdesi, kesinti bildirim süresi veya hizmet kredisi taahhüt edilmez. Sonraki bölümler bunların nasıl işleyeceğini açıklar; taahhüt oluşturmaz.',
                'Neden ihtiyat değil, ölçümdür. Hizmet kendi kullanılabilirliğini ölçmez: herkese açık durum sayfası veya kullanılabilirlik kaydı olmadığından tarafların kontrol edebileceği oran yoktur. Ölçüme dayanmadan yazılmış yüzde, tutulup tutulmadığı anlaşılamayan bir söz olurdu ve ilk kesinti bunu sözleşme ihlaline dönüştürürdü.',
                'Taahhüt yayımlanmadan önce dört değer kararlaştırılmalıdır; her biri bu hizmetin işletmecisinin hukuki danışmanlıkla vereceği ticari karardır, bir mühendisin dolduracağı alan değildir. Hâlâ karar bekleyenler: '.self::missingSentence($commitment),
                'Dördü de belirlenene kadar bu sayfa, kararlaştırılmış olanlar dahil hiçbir oran göstermez. Bu bilinçlidir: bir cümlede yüzde, diğerinde sorumluluk reddi bulunan sayfa, göz gezdiren herkesçe taahhüt olarak okunur.',
                'Kuruluşunuz satın almadan önce kullanılabilirlik taahhüdü istiyorsa abone olmadan belirtin. Bu, işletmecinin neyi vaat edip ölçmeye hazır olduğuyla ilgilidir; yanıtı bu sayfa değil bir kişi verir.',
            ])];
        }

        return [new LegalSection('Kullanılabilirlik taahhüdü', [
            'İşletmeci, bir sonraki bölümde açıklandığı şekilde ölçülmek üzere her takvim ayı için en az %'.$commitment->availabilityTargetPercent().' kullanılabilirlik sağlamayı taahhüt eder.',
            'Ölçüm şu adreste yayımlanır: '.$commitment->measurementSource().'. Kendiniz okuyabilirsiniz; talep etmeniz gerekmez.',
            'Hizmet kullanılamadığında işletmeci, haberdar olmasından itibaren '.$commitment->incidentNotificationMinutes().' dakika içinde etkilenen müşterilere bildirim yapar.',
            self::creditSentence($commitment),
        ])];
    }

    public static function creditSentence(ServiceLevelCommitment $commitment): string
    {
        $percent = $commitment->serviceCreditPercent();

        if ($percent === null) {

            return 'Bu kurulum için hizmet kredisi oranı (service_credit_percent) girilmediğinden bu koşullar hizmet kredisi taahhüt etmez. Girilene kadar, hedefin kaçırıldığı ay için telafiyi iptal koşulları kapsamında iptal olarak değerlendirin.';
        }

        if ($percent === 0) {
            return 'Bir takvim ayında hedef karşılanmazsa hizmet kredisi ödenmez. Hizmet, kullanılabilirlik hedefinin karşılanmaması için mali telafi sunmaz; başvurabileceğiniz yol iptal koşulları kapsamında iptaldir.';
        }

        return 'Bir takvim ayında hedef karşılanmazsa etkilenen planın o ayki ücreti üzerinden %'.$percent.' oranında hizmet kredisi, aşağıdaki telafi bölümünün koşullarıyla sonraki döneme uygulanır.';
    }

    public static function missingSentence(ServiceLevelCommitment $commitment): string
    {
        $labels = [
            'availability_target_percent' => 'bir takvim ayı için taahhüt edilecek kullanılabilirlik yüzdesi',
            'measurement_source' => 'bu yüzdenin okunup kontrol edilebileceği herkese açık adres',
            'incident_notification_minutes' => 'etkilenen müşterilerin kesintiden ne kadar sürede haberdar edileceği',
            'service_credit_percent' => 'hedef karşılanmadığında aylık ücretin kredi olarak tanımlanacak payı',
        ];

        $missing = [];

        foreach ($commitment->missing() as $field) {
            $missing[] = $labels[$field].' ('.$field.')';
        }

        return $missing === [] ? '' : implode('; ', $missing).'.';
    }
}
