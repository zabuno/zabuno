import type { ReactNode } from 'react';
import { Camera, CurrencyCircleDollar, Prohibit, QrCode } from '@phosphor-icons/react';
import { t } from '../../../../i18n/dashboard';

/**
 * Dört hızlı eylem — kaynak `quickActions`, `docs/109` §6.2.
 *
 * Bu karolar Home'a yeni bir VERİ getirmez; bir günün gerçek ritmini getirir.
 * Bir restoran sahibinin panelde yaptığı işlerin neredeyse tamamı dört
 * tanedir: bir fiyatı değiştirmek, biten bir ürünü kapatmak, bir masanın
 * karekodunu yeniden indirmek, bir fotoğraf eklemek. Bunların her biri
 * bugüne kadar önce sol gezintiden bölüm seçmeyi, sonra o bölümde doğru yeri
 * bulmayı gerektiriyordu.
 *
 * Kurulum kartı "ilk gün"ün listesidir ve bir kez biter. Bu karolar
 * "her gün"ün listesidir ve hiç bitmez — bu yüzden ikisi ayrı bölümdür.
 */

type QuickAction = {
    key: string;
    label: string;
    icon: ReactNode;
    section: string;
};

const ICON_SIZE = 22;

export function DashboardQuickActions({
    onNavigateToSection,
}: {
    onNavigateToSection?: (section: string) => void;
}) {
    /*
        GEZİNTİ YOKSA KARO YOK. Hiçbir yere götürmeyen bir düğme tıklanana
        kadar çalışıyor görünür; tıklandığı an ürünün bozuk olduğunu söyler.
        Aynı karar Home'un "şimdi" düğmesinde de verilmişti.
    */
    if (!onNavigateToSection) {
        return null;
    }

    const actions: QuickAction[] = [
        {
            key: 'price',
            label: t('dashboard.quick.price'),
            icon: <CurrencyCircleDollar aria-hidden="true" size={ICON_SIZE} weight="regular" />,
            section: 'menu',
        },
        {
            key: 'hide',
            label: t('dashboard.quick.hide'),
            icon: <Prohibit aria-hidden="true" size={ICON_SIZE} weight="regular" />,
            section: 'menu',
        },
        {
            key: 'qr',
            label: t('dashboard.quick.qr'),
            icon: <QrCode aria-hidden="true" size={ICON_SIZE} weight="regular" />,
            section: 'qr-codes',
        },
        {
            key: 'photo',
            label: t('dashboard.quick.photo'),
            icon: <Camera aria-hidden="true" size={ICON_SIZE} weight="regular" />,
            section: 'media',
        },
    ];

    return (
        <section
            aria-label={t('dashboard.quick.region')}
            /*
                Kırılma noktası sınıfı YOK: ızgara kendi kendine sarar, yani
                ölçüyü tarayıcı değil içerik belirler. 320 pikselde tek sütun,
                yer açıldıkça iki, sonra dört.
            */
            className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,9rem),1fr))] gap-[var(--space-3)]"
        >
            {actions.map((action) => (
                <button
                    key={action.key}
                    type="button"
                    onClick={() => onNavigateToSection(action.section)}
                    className="flex min-h-[var(--control-height)] flex-col items-start gap-[var(--space-3)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)] text-start text-fg transition-colors duration-[var(--duration-fast)] ease-[var(--easing-inout)] hover:bg-surface-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    <span className="grid size-[2.5rem] place-items-center rounded-[var(--radius-md)] bg-surface-subtle text-fg-secondary">
                        {action.icon}
                    </span>
                    {/*
                        Etiket FİİLLE başlar ("Change a price"), isimle değil.
                        Bir karo neyin sayfası olduğunu değil, sahibin orada ne
                        YAPACAĞINI söylemeli — "Menu" bir yerdir, "fiyat
                        değiştir" bir iştir.
                    */}
                    <span className="text-body font-bold text-pretty">{action.label}</span>
                </button>
            ))}
        </section>
    );
}

export default DashboardQuickActions;
