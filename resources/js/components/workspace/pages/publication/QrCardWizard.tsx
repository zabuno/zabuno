import { useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { SegmentedControl } from '../../../catalog/forms/compound/SegmentedControl';
import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { ActionLink } from '../../../catalog/navigation/micro/ActionLink';
import { Button } from '../../../catalog/forms/micro/Button';
import type { QrCodeItem } from './qr-destination/QrCodeListItem';

/**
 * MASADAKİ KART — FF-120, sahibin talebi (2026-09-04).
 *
 * "Menümü masalarda pleksiglas içinde göstermek istiyorum, printout
 * alabilmeliyim. Fakat her restoranın marka kimliği ayrı."
 *
 * Bu bileşen eski "Themes" bloğunun yerini aldı. O blok YANLIŞ ŞEYİ
 * adlandırıyordu: altı düğme karekodun piksel renklerini değiştiriyordu ve
 * "tema" diyordu. Karekodun rengi bir tema değil bir KISITTIR — koyu modül,
 * açık zemin, kontrast ≥ 4:1 — ve kullanıcıya seçtirilecek bir şey değildir.
 * Sahibin sorduğu tema, masaya konacak kartın kendisiydi.
 *
 * ADIMLI, çünkü tek ekranda dokuz kontrol (tasarım, metin, kâğıt, oran, yön,
 * biçim, tekil/toplu, alan) bir form değil bir kokpittir. Her adım tek bir
 * soru sorar ve önizleme her adımda cevabı gösterir.
 */
/**
 * Sunucu tek istekte en fazla bu kadar kart basar
 * (`App\\Domain\\QrDestination\\QrPrintSheet::CARDS_PER_REQUEST`). Her kart ayrı
 * bir render demek; 500 masalık bir istek kullanıcıya hiçbir şey vermeden
 * zaman aşımına uğrardı. Sayı burada da biliniyor ki ekran sessizce kırpılmış
 * bir arşiv vermek yerine sınırı SÖYLESİN.
 */
const CARDS_PER_REQUEST = 48;

const CARD_THEMES = ['classic', 'minimal', 'banner', 'frame'] as const;

type CardThemeKey = (typeof CARD_THEMES)[number];

/** Kâğıt ailesi: standart bir sayfaya basılır. */
const PAPER_SIZES = ['A3', 'A4', 'A5', 'A6', 'B3', 'B4', 'B5', 'B6'] as const;

/** Kart ailesi: pleksiglas standın kendi oranı; kâğıt boyuna karşılık gelmez. */
const RATIO_SIZES = ['1:2', '4:3', '16:9'] as const;

type CardSizeKey = (typeof PAPER_SIZES)[number] | (typeof RATIO_SIZES)[number];

const THEME_LABEL_KEYS: Record<CardThemeKey, Parameters<typeof t>[0]> = {
    classic: 'workspace.publication.qrCard.theme.classic',
    minimal: 'workspace.publication.qrCard.theme.minimal',
    banner: 'workspace.publication.qrCard.theme.banner',
    frame: 'workspace.publication.qrCard.theme.frame',
};

/**
 * Milimetre EKRANDA YAZAR. "A6" bir restoran sahibine hiçbir şey anlatmaz;
 * "105 × 148 mm" standın içine girip girmeyeceğini anlatır.
 *
 * Sayılar `App\Domain\QrDestination\CardSize` ile aynı ve bunun testi var:
 * ayrışırlarsa ekran bir ölçü yazar, yazıcıdan başka bir ölçü çıkar.
 */
export const CARD_SIZE_MM: Record<CardSizeKey, [number, number]> = {
    A3: [297, 420],
    A4: [210, 297],
    A5: [148, 210],
    A6: [105, 148],
    B3: [353, 500],
    B4: [250, 353],
    B5: [176, 250],
    B6: [125, 176],
    '1:2': [75, 150],
    '4:3': [150, 112.5],
    '16:9': [150, 84.4],
};

type QrCardWizardProps = {
    item: QrCodeItem;
    /**
     * Aynı şubedeki BÜTÜN etkin kodlar — toplu baskı ve alan süzgeci için
     * (FF-122). Süzgecin seçenekleri buradan türetilir; ikinci bir istek
     * atmaya gerek yok, liste zaten yüklü.
     */
    activeItems?: QrCodeItem[];
};

/** Kimin basılacağı: tek kart, bir alanın kartları, ya da hepsi. */
type Scope = 'single' | 'area' | 'all';

export function QrCardWizard({ item, activeItems = [] }: QrCardWizardProps) {
    const [step, setStep] = useState(0);
    const [scope, setScope] = useState<Scope>('single');
    const [areaId, setAreaId] = useState<number | null>(null);
    const [theme, setTheme] = useState<CardThemeKey>('classic');
    const [headline, setHeadline] = useState('');
    const [size, setSize] = useState<CardSizeKey>('A6');
    const [landscape, setLandscape] = useState(false);

    const [width, height] = CARD_SIZE_MM[size];
    const [shownWidth, shownHeight] = landscape ? [height, width] : [width, height];

    function cardUrl(format: 'svg' | 'pdf', download: boolean): string {
        const params = new URLSearchParams();
        params.set('cardTheme', theme);
        params.set('size', size);
        params.set('orientation', landscape ? 'landscape' : 'portrait');
        if (headline.trim() !== '') params.set('headline', headline.trim());
        if (download) params.set('download', '1');

        return `/api/workspaces/${String(item.workspaceId)}/qr-codes/${String(item.id)}/card.${format}?${params.toString()}`;
    }

    /*
        ALANLAR LİSTEDEN TÜRETİLİR. Sahibin cümlesi "salon üst kat, salon
        içerisi, salon bahçe" idi; bunlar ürünün bildiği gerçek alanlardır ve
        kimliğiyle taşınır — iki alan aynı adı taşıyabilir.
    */
    const areas = Array.from(
        new Map(
            activeItems
                .filter((candidate) => candidate.areaId != null && candidate.areaLabel)
                .map((candidate) => [candidate.areaId, candidate.areaLabel ?? '']),
        ),
    );

    const bulkCount =
        scope === 'all'
            ? activeItems.length
            : activeItems.filter((candidate) => candidate.areaId === areaId).length;

    function zipUrl(format: 'svg' | 'pdf'): string {
        const params = new URLSearchParams();
        params.set('cardTheme', theme);
        params.set('size', size);
        params.set('orientation', landscape ? 'landscape' : 'portrait');
        params.set('format', format);
        if (headline.trim() !== '') params.set('headline', headline.trim());
        if (scope === 'area' && areaId !== null) params.set('areaId', String(areaId));

        return `/api/workspaces/${String(item.workspaceId)}/brand/locations/${String(item.locationId)}/qr-cards.zip?${params.toString()}`;
    }

    const steps = [
        t('workspace.publication.qrCard.step.scope'),
        t('workspace.publication.qrCard.step.design'),
        t('workspace.publication.qrCard.step.size'),
        t('workspace.publication.qrCard.step.export'),
    ];

    return (
        <section
            aria-label={t('workspace.publication.qrCard.heading')}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <div className="flex flex-col gap-[var(--space-1)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.publication.qrCard.heading')}
                </h3>
                <p className="text-body text-fg-secondary">
                    {t('workspace.publication.qrCard.explanation')}
                </p>
            </div>

            {/*
                ADIM LİSTESİ bir süs değil: kullanıcı kaçıncı adımda olduğunu
                ve kaç adım kaldığını görmeden çok adımlı bir formu bitirmez.
                `aria-current` ekran okuyucuya da aynı şeyi söyler.

                BİÇİM teslim paketinin çok adımlı sihirbazından alındı
                (`Restoran Paneli v2`, `importSteps`): adımlar AĞIRLIKLA değil
                DOLGUYLA ayrışır — geçerli adım koyu dolgudur, diğerleri soluk
                dolgudur, hepsi 700'dür.

                Önceki hâlde geçerli olmayan adımlar hiç dolgusuzdu ve zemini
                yalnız `hover` sırasında kazanıyordu. Dokunmatik bir tablette
                `hover` yoktur: sahip dört adımın üçünü zeminsiz soluk bir yazı
                olarak görüyor ve tıklanabilir olduklarını anlamıyordu. Dolgu
                "burası bir hedeftir" bilgisini fareye sormadan verir.
            */}
            <ol className="flex flex-wrap gap-[var(--space-2)]">
                {steps.map((label, index) => (
                    <li key={label}>
                        <button
                            type="button"
                            aria-current={index === step ? 'step' : undefined}
                            onClick={() => setStep(index)}
                            className={
                                index === step
                                    ? 'min-h-[var(--density-hit-area-min)] rounded-pill bg-fg px-[var(--space-3)] text-body font-bold text-surface'
                                    : 'min-h-[var(--density-hit-area-min)] rounded-pill bg-surface-subtle px-[var(--space-3)] text-body font-bold text-fg-muted hover:text-fg'
                            }
                        >
                            {String(index + 1)}. {label}
                        </button>
                    </li>
                ))}
            </ol>

            <div className="flex flex-wrap items-start gap-[var(--space-6)]">
                <div className="flex min-w-[16rem] flex-1 flex-col gap-[var(--space-4)]">
                    {step === 0 ? (
                        <>
                            {/*
                                İLK SORU "kim basılacak", "hangi dosya" değil.
                                Restoran sahibi buraya bir biçim seçmeye
                                gelmez; masalarına kart koymaya gelir.
                            */}
                            <SegmentedControl
                                label={t('workspace.publication.qrCard.step.scope')}
                                value={scope}
                                options={[
                                    {
                                        value: 'single',
                                        label: t('workspace.publication.qrCard.scope.single'),
                                    },
                                    ...(areas.length > 0
                                        ? [
                                              {
                                                  value: 'area',
                                                  label: t(
                                                      'workspace.publication.qrCard.scope.area',
                                                  ),
                                              },
                                          ]
                                        : []),
                                    ...(activeItems.length > 1
                                        ? [
                                              {
                                                  value: 'all',
                                                  label: t(
                                                      'workspace.publication.qrCard.scope.all',
                                                      { count: String(activeItems.length) },
                                                  ),
                                              },
                                          ]
                                        : []),
                                ]}
                                onChange={(value) => setScope(value as Scope)}
                            />

                            {scope === 'area' ? (
                                <SegmentedControl
                                    label={t('workspace.publication.qrCard.scope.areaLabel')}
                                    value={areaId === null ? '' : String(areaId)}
                                    options={areas.map(([id, label]) => ({
                                        value: String(id),
                                        label,
                                    }))}
                                    onChange={(value) => setAreaId(Number(value))}
                                />
                            ) : null}
                        </>
                    ) : null}

                    {step === 1 ? (
                        <>
                            <SegmentedControl
                                label={t('workspace.publication.qrCard.step.design')}
                                value={theme}
                                options={CARD_THEMES.map((key) => ({
                                    value: key,
                                    label: t(THEME_LABEL_KEYS[key]),
                                }))}
                                onChange={setTheme}
                            />
                            <label className="flex flex-col gap-[var(--space-1)] text-body font-medium text-fg-secondary">
                                {t('workspace.publication.qrCard.headline.label')}
                                {/*
                                    Kartın cümlesi RESTORANIN cümlesidir. Boş
                                    bırakılırsa ürünün hazır cümlesi basılır;
                                    uydurulmuş bir yer tutucu değil.
                                */}
                                <TextInput
                                    type="text"
                                    maxLength={60}
                                    value={headline}
                                    placeholder={t('workspace.publication.qrCard.headline.default')}
                                    onChange={(event) => setHeadline(event.target.value)}
                                />
                            </label>
                        </>
                    ) : null}

                    {step === 2 ? (
                        <>
                            <fieldset className="flex flex-col gap-[var(--space-2)]">
                                <legend className="text-body font-medium text-fg-secondary">
                                    {t('workspace.publication.qrCard.size.paper')}
                                </legend>
                                <SegmentedControl
                                    label={t('workspace.publication.qrCard.size.paper')}
                                    value={PAPER_SIZES.includes(size as never) ? size : ''}
                                    options={PAPER_SIZES.map((key) => ({ value: key, label: key }))}
                                    onChange={(value) => setSize(value as CardSizeKey)}
                                />
                            </fieldset>

                            <fieldset className="flex flex-col gap-[var(--space-2)]">
                                <legend className="text-body font-medium text-fg-secondary">
                                    {t('workspace.publication.qrCard.size.ratio')}
                                </legend>
                                {/*
                                    KÂĞIT ve ORAN iki ayrı aile, tek seçim.
                                    Yan yana iki bağımsız liste olsaydı "A4 +
                                    16:9" gibi ne basılacağı belirsiz bir çift
                                    seçilebilirdi.
                                */}
                                <SegmentedControl
                                    label={t('workspace.publication.qrCard.size.ratio')}
                                    value={RATIO_SIZES.includes(size as never) ? size : ''}
                                    options={RATIO_SIZES.map((key) => ({ value: key, label: key }))}
                                    onChange={(value) => setSize(value as CardSizeKey)}
                                />
                            </fieldset>

                            <SegmentedControl
                                label={t('workspace.publication.qrCard.orientation.label')}
                                value={landscape ? 'landscape' : 'portrait'}
                                options={[
                                    {
                                        value: 'portrait',
                                        label: t(
                                            'workspace.publication.qrCard.orientation.portrait',
                                        ),
                                    },
                                    {
                                        value: 'landscape',
                                        label: t(
                                            'workspace.publication.qrCard.orientation.landscape',
                                        ),
                                    },
                                ]}
                                onChange={(value) => setLandscape(value === 'landscape')}
                            />
                        </>
                    ) : null}

                    {step === 3 ? (
                        <div className="flex flex-col gap-[var(--space-3)]">
                            <p className="text-body text-fg-secondary">
                                {t('workspace.publication.qrCard.export.help')}
                            </p>
                            {scope === 'single' ? (
                                <span className="flex flex-wrap gap-[var(--space-2)]">
                                    <ActionLink href={cardUrl('pdf', true)}>
                                        {t('workspace.publication.qrCard.export.pdf')}
                                    </ActionLink>
                                    <ActionLink variant="secondary" href={cardUrl('svg', true)}>
                                        {t('workspace.publication.qrCard.export.svg')}
                                    </ActionLink>
                                </span>
                            ) : (
                                /*
                                    TOPLU BASKI BİR ARŞİVDİR. Matbaa her kartı
                                    ayrı dosya olarak ister ve dosya adı hangi
                                    masa olduğunu söyler — yoksa kırk dosyayı
                                    açıp tek tek bakmak gerekir.
                                */
                                <span className="flex flex-col items-start gap-[var(--space-2)]">
                                    <span className="flex flex-wrap gap-[var(--space-2)]">
                                        <ActionLink href={zipUrl('pdf')}>
                                            {t('workspace.publication.qrCard.export.zipPdf', {
                                                count: String(bulkCount),
                                            })}
                                        </ActionLink>
                                        <ActionLink variant="secondary" href={zipUrl('svg')}>
                                            {t('workspace.publication.qrCard.export.zipSvg')}
                                        </ActionLink>
                                    </span>
                                    {bulkCount > CARDS_PER_REQUEST ? (
                                        <span role="status" className="text-meta text-fg-secondary">
                                            {t('workspace.publication.qrCard.export.capped', {
                                                cap: String(CARDS_PER_REQUEST),
                                            })}
                                        </span>
                                    ) : null}
                                </span>
                            )}
                            {/*
                                PNG YOK ve bu bir eksiklik değil bir karar.
                                Söylenmezse kullanıcı onu arar ve bulamayınca
                                ürünü eksik sanır.
                            */}
                            <p className="text-meta text-fg-muted">
                                {t('workspace.publication.qrCard.export.noPng')}
                            </p>
                        </div>
                    ) : null}

                    <span className="flex gap-[var(--space-2)]">
                        {step > 0 ? (
                            <Button type="button" color="light" onClick={() => setStep(step - 1)}>
                                {t('workspace.publication.qrCard.back')}
                            </Button>
                        ) : null}
                        {step < steps.length - 1 ? (
                            <Button type="button" color="light" onClick={() => setStep(step + 1)}>
                                {t('workspace.publication.qrCard.next')}
                            </Button>
                        ) : null}
                    </span>
                </div>

                {/*
                    ÖNİZLEME HER ADIMDA GÖRÜNÜR. Ayarların bir önizlemeye bağlı
                    olması yazdırma deneyiminin temel kuralıdır; ayrı bir
                    "önizle" adımına saklamak, kullanıcıyı üç adım boyunca
                    karanlıkta bırakırdı.
                */}
                <figure className="flex flex-col items-start gap-[var(--space-2)]">
                    {/*
                        ZEMİN JETON DEĞİL, KÂĞITTIR.

                        Buradaki `bg-white` bir tema rengi değil bir BASKI
                        gerçeğidir: kart beyaz kâğıda basılır ve karekod ISO/IEC
                        18004 gereği koyu modül / açık zemin olmak zorundadır.
                        Zemini `surface` jetonuna bağlasaydık koyu temada
                        önizleme koyu bir kâğıt gösterirdi — yani sahip, eline
                        hiç geçmeyecek bir kartı görür ve kontrast kararını
                        yanlış veri üstünden verirdi.
                    */}
                    <img
                        key={cardUrl('svg', false)}
                        src={cardUrl('svg', false)}
                        alt={t('workspace.publication.qrCard.preview.alt')}
                        className="max-h-[22rem] w-auto rounded-[var(--radius-md)] border border-border bg-white"
                        style={{ aspectRatio: `${String(shownWidth)} / ${String(shownHeight)}` }}
                    />
                    <figcaption className="text-meta text-fg-secondary">
                        {t('workspace.publication.qrCard.preview.size', {
                            width: String(shownWidth),
                            height: String(shownHeight),
                        })}
                    </figcaption>
                </figure>
            </div>
        </section>
    );
}

export default QrCardWizard;
