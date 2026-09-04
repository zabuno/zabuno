import { t } from '../../../../i18n/dashboard';
import type { BrandProfile } from '../../BrandEditForm';

type DashboardGreetingProps = {
    brand: BrandProfile | null;
};

/**
 * Home'un açılış bloğu — AEP teslim paketi (`Restoran Paneli v2.dc.html`,
 * `DESIGN_SPEC.md` §2).
 *
 * Referans ekran iki satırla açılır: küçük ve sakin bir üst satır, hemen
 * altında büyük bir KARŞILAMA. Depodaki hâl tek bir "Ana sayfa" başlığı ve
 * altında panelin ne yaptığını anlatan bir paragraftı — yani her sabah
 * açılan ilk ekran, kullanıcıya kendisini değil KENDİNİ tanıtıyordu. O
 * paragraf ilk gün yardımcıdır, ikinci günden itibaren gürültüdür.
 *
 * BAŞLIK NEDEN HÂLÂ "Ana sayfa"? Kabuk sözleşmesi gezinti etiketi ile sayfa
 * başlığının aynı olmasını şart koşar: kullanıcı "Ana sayfa"ya tıklar ve
 * gittiği yerin adının o olduğunu görür. Karşılama bu yüzden başlığın
 * YERİNE geçmez — başlık sayfayı adlandırmayı sürdürür, karşılama ise
 * ekranın ilk baktığın yerindeki insan sesidir. Referansta üst satır tarih
 * taşıyor; burada sayfanın adını taşıyor, çünkü bir tarih hiçbir soruyu
 * cevaplamıyor ve ekranın adı cevaplıyor.
 *
 * Hiyerarşi BOYUT ve RENKLE kurulur, büyük harfle değil: üst satır küçük ve
 * soluk, karşılama büyük ve koyu. `h1`'in altındaki satırdan görsel olarak
 * daha küçük olması bilinçli — anlamsal başlık ile görsel vurgu ayrı
 * sorulardır.
 */
export function DashboardGreeting({ brand }: DashboardGreetingProps) {
    /*
        Ad UYDURULMAZ. İlk gün marka henüz yazılmamıştır ve o boşluğa
        "İşletmeniz" gibi bir yer tutucu koymak, kullanıcının adını
        bildiğimizi ima etmek olurdu. Ad yoksa cümle adsız kurulur.
    */
    const name = brand?.name ?? '';
    const greeting = name ? t('dashboard.greeting.named', { name }) : t('dashboard.greeting');

    return (
        <div className="flex flex-col gap-[var(--space-1)]">
            <h1 className="text-meta font-bold text-fg-secondary">{t('dashboard.heading')}</h1>
            <p className="text-title font-bold tracking-tight text-pretty text-fg">{greeting}</p>
        </div>
    );
}

export default DashboardGreeting;
