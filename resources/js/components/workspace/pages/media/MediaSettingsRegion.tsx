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
 * HÂL KELİMESİ — anahtar DEĞİL (sahibin kararı, 2026-09-08: "switch butonlar
 * saçma, UI hatası").
 *
 * Burada bir anahtar vardı ve çevrilemiyordu; altında da "kapatılamaz"
 * yazıyordu. Ekran aynı anda iki şey söylüyordu: anahtarın biçimi
 * "değiştirebilirsin", cümlesi "değiştiremezsin". Kullanıcı dokunuyor,
 * hiçbir şey olmuyor — ve dokunmanın işe yaramadığını ancak DENEYEREK
 * öğreniyordu. Devre dışı bırakmak bunu düzeltmez: devre dışı bir anahtar
 * hâlâ bir anahtardır.
 *
 * Sorulan soru ("açık mı?") yine tek bakışta cevaplanmalı, o yüzden hâl
 * KAYBOLMAZ — biçim değişir: kontrol değil, KELİME. Deponun bu iş için hazır
 * sözcüğü `MediaAssetStatusBadge`tir ve buradaki de aynı dili konuşur:
 * anlamı METİN taşır, renk yalnız pekiştirir. Renk tek başına anlatsaydı,
 * onu ayırt edemeyen için ekran boş kalırdı.
 *
 * İKON YOK. Bir kalkan simgesi kelimenin söylemediği hiçbir şeyi söylemez,
 * ama 320 pikselde satırdan bir sütun genişliği götürür — bu bölümün en kıt
 * kaynağı ekran alanıdır.
 *
 * `role="status"` de YOK: bu satır bir olay bildirmiyor, sayfanın sabit
 * içeriği. Canlı bölge yapmak, ekran okuyucuya olmayan bir değişikliği
 * duyurmak olurdu.
 */
const SECURITY_STATE: Record<string, { label: TranslationKey; tone: string }> = {
    on: { label: 'workspace.media.settings.security.state.on', tone: 'text-fg-success' },
    partial: { label: 'workspace.media.settings.security.state.partial', tone: 'text-fg-warning' },
    unavailable: {
        label: 'workspace.media.settings.security.state.unavailable',
        tone: 'text-fg-warning',
    },
    /*
        `missing` için hâl kelimesi YOK: olmayan bir şeyin durumu olmaz.
        Açıklaması zaten "Not built yet." diyor; ikinci bir kelime aynı
        yokluğu iki kez söylerdi.
    */
};

/**
 * "Kapatılamaz" cümlesi yalnız GERÇEKTEN YÜRÜYEN önlemin altında durur.
 *
 * Çalışmayan bir tarayıcının altına "kapatılamaz" yazmak, hemen üstündeki
 * açıklamayla çelişirdi: o satır zaten "kapalı ve buradan açamazsın" diyor.
 */
const LOCKED_STATES = ['on', 'partial'];

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
 *   - Filigran diye bir kod YOKTUR; "henüz yok" yazılır.
 *
 * BU YÜZDEN EKRANDA HİÇBİR KONTROL YOKTUR — ne kutu, ne anahtar, ne devre
 * dışı bir anahtar (sahibin kararı, 2026-09-08). Kapatılamayan dört önlem
 * bir ayar değil bir OLGUDUR ve olgu okunur: etiket + hâl + sebebi.
 * Kapı `controls.guard.test.ts`tedir.
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

                        const state = SECURITY_STATE[measure.state];

                        return (
                            /*
                                Satırın biçimi YUKARIDAN alındı: desen listesi
                                bu sayfada zaten doğru olan yarıdır ve aynı
                                dikey ritmi taşır. İkinci bir satır düzeni
                                icat etmek, aynı ekranda iki tasarım dili
                                konuşmak olurdu.

                                Anahtarın gittiği yer geri kazanılan yerdir:
                                3rem'lik kontrol ve yanındaki `space-3`
                                boşluğu düştü — satır başına 60 piksel, 320
                                pikselin beşte biri. Dolgu BÜYÜTÜLMEDİ; dar
                                ekranda kıt olan alan metne geri verildi
                                (ölçüm: 270/320 kullanılabilir genişlik).
                            */
                            <li
                                key={measure.key}
                                className="flex flex-col gap-[var(--space-1)] border-t border-border py-[var(--space-3)] first:border-t-0"
                            >
                                <div className="flex flex-wrap items-baseline gap-x-[var(--space-2)]">
                                    <span className="text-body font-medium text-fg">{label}</span>
                                    {state === undefined ? null : (
                                        <span className={`text-meta font-medium ${state.tone}`}>
                                            {t(state.label)}
                                        </span>
                                    )}
                                </div>
                                {descriptionKey === undefined ? null : (
                                    <p className="text-body text-fg-muted">{t(descriptionKey)}</p>
                                )}
                                {LOCKED_STATES.includes(measure.state) ? (
                                    <span className="text-meta text-fg-secondary">
                                        {t('workspace.media.settings.security.locked')}
                                    </span>
                                ) : null}
                            </li>
                        );
                    })}
                </ul>
            </div>
        </section>
    );
}
