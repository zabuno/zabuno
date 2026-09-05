import { useState } from 'react';
import { CheckCircle, WarningCircle } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import { currentLocale } from '../../../../i18n/locales';
import {
    CODE_READABLE_AT_TABLE_MM,
    CODE_READABLE_STANDING_MM,
} from '../../../../lib/qrCardGeometry';
import type { QrScreenCode } from '../publication/QrTableCardGrid';
import {
    cardUrl,
    codeName,
    dimensionLabel,
    planCodeSideMm,
    planDimensionsMm,
    type QrPrintPlan,
} from './qrPrintPlan';

type QrPrintPreviewPanelProps = {
    /** Kapsamın İLK kodu — maket değil, gerçekten basılacak kartlardan biri. */
    code: QrScreenCode | null;
    plan: QrPrintPlan;
};

/**
 * BÖYLE ÇIKACAK — panel v3.1 kanonik kaynağının önizleme paneli.
 *
 * Kaynağın panelinde kart ELLE ÇİZİLMİŞ bir makettir: kutular, çizgiler,
 * tahmini punto. Burada öyle değil ve olmamalı — depoda kartı gerçekten basan
 * bir uç var (`card.svg`) ve önizleme onu çiziyor. Elle çizilen bir maket,
 * bestecinin yerleşimi bir gün değiştiğinde sessizce yalan söyler ve o yalan
 * ancak kırk kart basıldıktan sonra fark edilir.
 *
 * ZEMİN JETON DEĞİL KÂĞITTIR (`bg-white`): kart beyaz kâğıda basılır. `surface`
 * jetonuna bağlansaydı koyu arayüz temasında önizleme koyu bir kâğıt gösterir
 * ve sahip, eline hiç geçmeyecek bir kartı görürdü.
 *
 * TARANABİLİRLİK NOTU ÖLÇÜLMÜŞTÜR. Kaynağın kendi cümlesi ("Kod 88 mm — masa
 * mesafesinden rahat okunur") bir temenni değil bir sayıdır ve sayı sunucunun
 * bestecisiyle aynı hesaptan gelir (`lib/qrCardGeometry`, iki taraflı test).
 * "Tarayıcı testi geçti" YAZILMAZ: ürün hiçbir telefonda tarama testi
 * çalıştırmıyor ve çalıştırmadığı bir testin geçtiğini yazmak, sahibin kırk
 * kart bastırmasını sağlayan cümledir.
 */
export function QrPrintPreviewPanel({ code, plan }: QrPrintPreviewPanelProps) {
    const [failed, setFailed] = useState(false);

    const [width, height] = planDimensionsMm(plan);
    const codeSide = planCodeSideMm(plan, code?.tableName != null && code.tableName !== '');
    const mm = new Intl.NumberFormat(currentLocale(), { maximumFractionDigits: 0 }).format(
        codeSide,
    );

    const readableStanding = codeSide >= CODE_READABLE_STANDING_MM;
    const readableAtTable = codeSide >= CODE_READABLE_AT_TABLE_MM;

    return (
        <aside
            aria-label={t('workspace.publication.qrScreen.preview')}
            className="flex flex-col gap-[var(--space-3)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]"
        >
            <div className="flex flex-wrap items-baseline gap-[var(--space-2)]">
                <h2 className="flex-1 text-body font-bold text-fg">
                    {t('workspace.publication.qrScreen.preview')}
                </h2>
                <span className="text-body tabular-nums text-fg-secondary">
                    {dimensionLabel(plan)}
                </span>
            </div>

            <div className="flex justify-center rounded-[var(--radius-md)] bg-surface-subtle p-[var(--space-4)]">
                {code === null ? (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.publication.qrScreen.empty')}
                    </p>
                ) : failed ? (
                    /*
                        TESLİMATIN DA BİR DURUMU OLMALI. Görselin ucu
                        `qr.design.manage` yetkisi ister; yetkisi olmayan bir
                        kullanıcıda burada tarayıcının kırık resim simgesi
                        kalırdı. İndirme bağlantıları durur: görsel
                        üretilememesi, dosyanın üretilemeyeceği anlamına gelmez.
                    */
                    <p role="status" className="text-meta text-fg-danger">
                        {t('workspace.publication.qrExport.preview.failed')}
                    </p>
                ) : (
                    <img
                        key={cardUrl(code, plan, 'svg', false)}
                        src={cardUrl(code, plan, 'svg', false)}
                        alt={t('workspace.publication.qrScreen.previewAlt', {
                            name: codeName(code),
                        })}
                        onError={() => setFailed(true)}
                        className="max-h-[16rem] w-auto max-w-full rounded-[2px] border border-border bg-white"
                        style={{ aspectRatio: `${String(width)} / ${String(height)}` }}
                    />
                )}
            </div>

            <p className="flex items-start gap-[var(--space-2)] text-body text-fg-secondary">
                {readableAtTable ? (
                    <CheckCircle
                        size={18}
                        weight="fill"
                        aria-hidden="true"
                        className="mt-[2px] shrink-0 text-fg-success"
                    />
                ) : (
                    <WarningCircle
                        size={18}
                        weight="fill"
                        aria-hidden="true"
                        className="mt-[2px] shrink-0 text-fg-warning"
                    />
                )}
                <span className="tabular-nums">
                    {t(
                        readableStanding
                            ? 'workspace.publication.qrScreen.scan.standing'
                            : readableAtTable
                              ? 'workspace.publication.qrScreen.scan.table'
                              : 'workspace.publication.qrScreen.scan.tooSmall',
                        { mm },
                    )}
                </span>
            </p>
        </aside>
    );
}
