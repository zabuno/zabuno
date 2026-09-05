import { useEffect, useState } from 'react';
import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';

export type MediaSettingsPattern = {
    key: string;
    value: string;
    changeable: boolean;
};

export type MediaSecurityMeasure = {
    key: string;
    /** `on` · `partial` · `unavailable` · `missing` — sunucunun sözlüğü. */
    state: string;
    switchable: boolean;
};

export type MediaSettingsBody = {
    patterns: MediaSettingsPattern[];
    security: MediaSecurityMeasure[];
};

type MediaSettingsRegionProps = {
    workspaceId: number;
};

type TranslationKey = Parameters<typeof t>[0];

/**
 * Desen satırının üç metni: ADI, bugünkü DEĞERİ ve NEDEN seçilemediği.
 *
 * "Yapamazsın" tek başına bir cevap değildir. Sahip dizin desenini
 * seçemediğini görüyorsa, sebebini de okumalı — yoksa bunu bir eksiklik
 * sanır ve her sürümde yeniden sorar.
 */
const PATTERN_TEXT: Record<string, { label: TranslationKey; why: TranslationKey }> = {
    directory: {
        label: 'workspace.media.settings.pattern.directory',
        why: 'workspace.media.settings.pattern.directory.why',
    },
    fileName: {
        label: 'workspace.media.settings.pattern.fileName',
        why: 'workspace.media.settings.pattern.fileName.why',
    },
    date: {
        label: 'workspace.media.settings.pattern.date',
        why: 'workspace.media.settings.pattern.date.why',
    },
};

/** Sunucunun gönderdiği DEĞER anahtarının sahibin dilindeki karşılığı. */
const PATTERN_VALUE: Record<string, TranslationKey> = {
    workspaceFolder: 'workspace.media.settings.pattern.directory.workspaceFolder',
    opaqueKey: 'workspace.media.settings.pattern.fileName.opaqueKey',
    deviceLocale: 'workspace.media.settings.pattern.date.deviceLocale',
};

const SECURITY_LABEL: Record<string, TranslationKey> = {
    virusScan: 'workspace.media.settings.security.virusScan',
    contentSignature: 'workspace.media.settings.security.contentSignature',
    metadataStrip: 'workspace.media.settings.security.metadataStrip',
    signedLink: 'workspace.media.settings.security.signedLink',
    watermark: 'workspace.media.settings.security.watermark',
};

/**
 * Açıklama DURUMA göre değişir, önleme göre değil.
 *
 * `metadataStrip` "açık" değil YARIM: türevler temiz, asıl dosya olduğu
 * gibi duruyor. Aynı satıra iki farklı gerçeği tek cümleyle yazmak, ikisini
 * de yanlış anlatırdı.
 */
const SECURITY_DESCRIPTION: Record<string, TranslationKey> = {
    'virusScan:on': 'workspace.media.settings.security.virusScan.on',
    'virusScan:unavailable': 'workspace.media.settings.security.virusScan.unavailable',
    'contentSignature:on': 'workspace.media.settings.security.contentSignature.on',
    'metadataStrip:partial': 'workspace.media.settings.security.metadataStrip.partial',
    'signedLink:on': 'workspace.media.settings.security.signedLink.on',
    'watermark:missing': 'workspace.media.settings.security.watermark.missing',
};

function isBody(value: unknown): value is MediaSettingsBody {
    if (typeof value !== 'object' || value === null) return false;
    const body = value as Record<string, unknown>;

    return Array.isArray(body.patterns) && Array.isArray(body.security);
}

/**
 * Salt okunur anahtar.
 *
 * ANAHTAR ÇİZİLİR ama ÇEVRİLEMEZ. Sahibin kararı (2026-09-05):
 * kapatılabilir bir güvenlik anahtarı, kapatıldığı gün bir güvenlik
 * açığıdır. Yine de anahtar biçiminde çizilir, çünkü sorulan soru
 * "açık mı?" — ve bir anahtar bu soruyu tek bakışta cevaplar.
 *
 * `role="switch"` + `aria-checked` yazılır ve düğme `disabled`'dır:
 * ekran okuyucu hem durumu hem de çevrilemez olduğunu duyurur. Yanındaki
 * "kapatılamaz" cümlesi bunu gören kullanıcıya da söyler.
 */
function LockedSwitch({ label, checked }: { label: string; checked: boolean }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled
            className={`inline-flex min-h-[var(--control-height)] w-[3rem] shrink-0 items-center rounded-pill border border-border p-[var(--space-1)] ${
                checked ? 'bg-action' : 'bg-surface-active'
            }`}
        >
            <span
                aria-hidden="true"
                className={`block size-[1.25rem] rounded-pill bg-surface ${
                    checked ? 'ms-auto' : ''
                }`}
            />
        </button>
    );
}

/**
 * MEDYA AYARLARI (kanonik kaynak: `docs/reference/media-manager/
 * Medya Yonetimi v2.dc.html`, ekran etiketi "Ayarlar"; somut listeler
 * `docs/108` §6.5 ve §6.6).
 *
 * BU BÖLÜMDE KAYDETME KUTUSU YOKTUR ve bu bir eksiklik değil, ekranın
 * SÖZÜDÜR. Bir ayar ekranındaki her kontrol bir söz verir: kullanıcı onu
 * çevirdiğinde bir şeyin değişeceğini söyler. Bu depoda:
 *
 *   - Dizin ve dosya adı deseni DEPOLAMA ANAHTARIDIR ve anahtar asla
 *     değişmez; değişse yayınlanmış her menü görselini kaybederdi.
 *   - Güvenlik önlemleri bir ayara bağlı DEĞİLDİR; hepsi koşulsuz uygulanır.
 *   - Filigran diye bir kod YOKTUR; anahtar çizilmez, "henüz yok" yazılır.
 *
 * Virüs taraması sahibin AÇIK kararıdır: gösterilir, kapatılamaz. Tarayıcı
 * bu ortamda bağlı değilse durum "kapalı" değil "çalışmıyor" diye okunur —
 * biri bir kullanıcı kararı, diğeri bir ortam gerçeğidir.
 */
export function MediaSettingsRegion({ workspaceId }: MediaSettingsRegionProps) {
    const [data, setData] = useState<MediaSettingsBody | null>(null);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/media/settings`,
                    buildAuthRequestInit(),
                );

                if (!response.ok) return;

                const body = (await response.json()) as unknown;

                if (!cancelled && isBody(body)) {
                    setData(body);
                }
            } catch {
                // Sessiz: ayar okunamadı diye kütüphane çalışmaz olmaz.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    if (data === null) {
        return null;
    }

    return (
        <section
            aria-label={t('workspace.media.settings.region')}
            className="flex flex-col gap-[var(--space-5)]"
        >
            <div className="flex flex-col gap-[var(--space-3)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.media.settings.patterns.heading')}
                </h3>
                <p className="text-body text-fg-muted">
                    {t('workspace.media.settings.patterns.lead')}
                </p>
                <ul className="flex flex-col">
                    {data.patterns.map((pattern) => {
                        const text = PATTERN_TEXT[pattern.key];

                        if (text === undefined) {
                            // Tanınmayan bir desen anahtarı UYDURULMAZ.
                            return null;
                        }

                        const valueKey = PATTERN_VALUE[pattern.value];

                        return (
                            <li
                                key={pattern.key}
                                /*
                                    Ayraç ÜSTTEDİR ve ilk satırda susar: alttan
                                    ayraçta son satırın çizgisi kartın kendi
                                    kenarlığıyla çakışır.
                                */
                                className="flex flex-col gap-[var(--space-1)] border-t border-border py-[var(--space-3)] first:border-t-0"
                            >
                                <span className="text-body font-medium text-fg">
                                    {t(text.label)}
                                </span>
                                {valueKey === undefined ? null : (
                                    <span className="text-body text-fg-secondary">
                                        {t(valueKey)}
                                    </span>
                                )}
                                <p className="text-body text-fg-muted">{t(text.why)}</p>
                            </li>
                        );
                    })}
                </ul>
            </div>

            {/*
                ASIL HER ZAMAN SAKLANIR — bir anahtar DEĞİL, bir cümle
                (sahibin kararı, 2026-09-05).

                Kaynak burada "Aslını sakla" anahtarı gösteriyor. Anahtar
                yapmak, kapatılabilir yapmak demektir; oysa bu depoda "asıl
                korunur" koşulsuz bir kuraldır ve yanlış bir dönüştürmeden
                sonra aslı geri getirmenin başka yolu yoktur. Kullanıcı
                neyin garanti olduğunu okur, kapatacak bir şey aramaz.
            */}
            <div className="flex flex-col gap-[var(--space-1)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.media.settings.originals.heading')}
                </h3>
                <p className="text-body text-fg-muted">
                    {t('workspace.media.settings.originals.body')}
                </p>
            </div>

            <div className="flex flex-col gap-[var(--space-3)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.media.settings.security.heading')}
                </h3>
                <ul className="flex flex-col">
                    {data.security.map((measure) => {
                        const labelKey = SECURITY_LABEL[measure.key];

                        if (labelKey === undefined) {
                            return null;
                        }

                        const label = t(labelKey);
                        const descriptionKey =
                            SECURITY_DESCRIPTION[`${measure.key}:${measure.state}`];

                        return (
                            <li
                                key={measure.key}
                                className="flex items-start gap-[var(--space-3)] border-t border-border py-[var(--space-3)] first:border-t-0"
                            >
                                {/*
                                    `missing` durumunda ANAHTAR ÇİZİLMEZ.
                                    Kapalı görünen bir anahtar "açabilirsin"
                                    der; oysa açılacak bir şey yok.
                                */}
                                {measure.state === 'missing' ? null : (
                                    <LockedSwitch
                                        label={label}
                                        checked={measure.state !== 'unavailable'}
                                    />
                                )}
                                <div className="flex min-w-0 flex-col gap-[var(--space-1)]">
                                    <span className="text-body font-medium text-fg">{label}</span>
                                    {descriptionKey === undefined ? null : (
                                        <p className="text-body text-fg-muted">
                                            {t(descriptionKey)}
                                        </p>
                                    )}
                                    {measure.state === 'missing' ? null : (
                                        <span className="text-meta text-fg-secondary">
                                            {t('workspace.media.settings.security.locked')}
                                        </span>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </section>
    );
}
