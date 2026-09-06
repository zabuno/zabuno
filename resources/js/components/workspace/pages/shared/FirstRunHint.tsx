import { useState } from 'react';
import { ArrowRight, ArrowSquareOut, X } from '@phosphor-icons/react';
import { cn } from '../../../../lib/utils';
import { t } from '../../../../i18n/dashboard';
import { trackEvent } from '../../../../lib/analytics';
import { IconButton } from '../../../catalog/navigation/micro/IconButton';
import {
    HELP_ARTICLE_ANCHORS,
    firstRunHintStorageKey,
    type FirstRunNextStep,
    type FirstRunStep,
} from './firstRunHints';

/**
 * İLK KEZ İPUCU — kurulum adımının HEDEF ekranındaki tek cümle (FF-202,
 * `docs/107` 1.7, `docs/101` §3).
 *
 * Ölçüm (2026-09-06, ÖNCE): Home'daki büyük düğme ("Name your restaurant")
 * kullanıcıyı adımın ekranına bırakıyor ve o ekran susuyordu — "burada ne
 * yapılacak" diyen bir cümle yoktu. Yardım makalesi vardı (`/help`), ama
 * panelden ona giden bağlantı sayısı SIFIRDI. Marka kaydedilince kullanıcı
 * aynı verilerin düzenleme formunda kalıyor, şube kaydedilince listede
 * kalıyordu: bir sonraki adımı bulmak için Home'a dönmek gerekiyordu.
 *
 * Bu kutu iki kipte çalışır ve ikisi de aynı dört kurala uyar:
 *
 *   EKRAN kipi — adımın kendi ekranında, adım bitmemişken: tek cümle ne
 *   yapılacağını söyler. İKİNCİ bir büyük düğme çizmez (`docs/101` A1):
 *   birincil eylem ekranın kendi formudur; iki büyük düğme "hangisi?"
 *   sorusu, o soru da donma demektir.
 *
 *   DEVAM kipi (`onContinue`) — bir ÖNCEKİ adımın ekranında, kayıt
 *   bittikten sonra: "kaydedildi, sırada şu var" der ve tek düğmeyle oraya
 *   götürür. Düğmenin fiili Home'daki "şimdi" düğmesiyle AYNIDIR
 *   (`dashboard.now.*`): kullanıcı aynı kelimeyi iki ekranda görür ve aynı
 *   iş olduğunu anlar. Zemin de Home'daki kartla aynı (marka rengi), çünkü
 *   o an sayfadaki tek eylem budur.
 *
 * Dört kural:
 *   1. Adım bitmişse kutu YOK — biten bir işi öğretmek gürültüdür (A8).
 *   2. Kapatılabilir ve kapatma CİHAZDA hatırlanır (`localStorage`, çalışma
 *      alanına özel anahtar). Sunucuda "bunu bir daha gösterme" kaydı yok;
 *      olmayan bir kalıcılığı varmış gibi göstermemek için anahtar cihazda
 *      kalır ve bu sınır burada yazılıdır.
 *   3. Yardım bağlantısı yalnız makalede O BÖLÜM VARSA çizilir
 *      (`HELP_ARTICLE_ANCHORS`); uydurma çapa yok. Test makale dosyasını
 *      okuyup çapaların gerçek olduğunu doğrular.
 *   4. Hiçbir cümle ölçülmemiş bir süre vaat etmez ("5 dakikada" yok).
 *
 * `noviceHome` bayrağına BAĞLI DEĞİL ve bu bilinçli: bayrak Home'daki
 * "şimdi" kartını yönetir; bu kutu adımın kendi ekranında durur ve iki
 * onboarding formu kabuk tarafından çizildiği için bayrak onlara ulaşamaz.
 * Yarısı bayrağa bağlı bir rehberlik, tutarsız bir rehberliktir.
 */
export type { FirstRunStep, FirstRunNextStep } from './firstRunHints';

type OnScreenProps = {
    step: FirstRunStep;
    onContinue?: undefined;
};

type ContinueProps = {
    step: FirstRunNextStep;
    /** Sıradaki adıma GERÇEK gezinti; kutu ikinci bir yol icat etmez. */
    onContinue: () => void;
};

export type FirstRunHintProps = (OnScreenProps | ContinueProps) & {
    /** Kapatmanın hatırlanacağı çalışma alanı. */
    workspaceId: number;
    /** Adım bittiyse kutu HİÇ çizilmez. */
    done: boolean;
};

const SENTENCE: Record<FirstRunStep, Parameters<typeof t>[0]> = {
    brand: 'dashboard.firstRun.brand',
    location: 'dashboard.firstRun.location',
    menu: 'dashboard.firstRun.menu',
    publication: 'dashboard.firstRun.publication',
    qr: 'dashboard.firstRun.qr',
};

const NEXT_SENTENCE: Record<FirstRunNextStep, Parameters<typeof t>[0]> = {
    location: 'dashboard.firstRun.next.location',
    menu: 'dashboard.firstRun.next.menu',
};

/** Home'daki "şimdi" düğmesinin fiili — aynı anahtar, aynı kelime. */
const NOW_VERB: Record<FirstRunNextStep, Parameters<typeof t>[0]> = {
    location: 'dashboard.now.location',
    menu: 'dashboard.now.menu',
};

const HELP_LABEL: Partial<Record<FirstRunStep, Parameters<typeof t>[0]>> = {
    menu: 'dashboard.firstRun.help.menu',
    qr: 'dashboard.firstRun.help.qr',
};

/*
    `localStorage` her ortamda yoktur (özel pencere, kapalı site verisi) ve
    o zaman ERİŞİM fırlatır. Kutu ölçümün yan ürünü değil, rehberliğin
    kendisidir: depolama yoksa kapatma bu bağlanmayla sınırlı kalır ve kutu
    çalışmaya devam eder.
*/
function readDismissed(key: string): boolean {
    try {
        return window.localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

function writeDismissed(key: string): void {
    try {
        window.localStorage.setItem(key, '1');
    } catch {
        /* Depolama kapalı: kapatma yalnız bu sayfa ömrünce geçerli. */
    }
}

export function FirstRunHint(props: FirstRunHintProps) {
    const { step, workspaceId, done } = props;
    const isContinue = props.onContinue !== undefined;
    const storageKey = firstRunHintStorageKey(workspaceId, step, isContinue ? 'next' : 'screen');

    /*
        Kapatılan anahtarlar LİSTE olarak tutulur, tek bir boolean değil:
        aynı bileşen çalışma alanı değişince başka bir anahtara geçer ve tek
        bir "kapatıldı" bayrağı, komşu restoranın ipucunu da götürürdü.
    */
    const [dismissedKeys, setDismissedKeys] = useState<string[]>([]);

    if (done || dismissedKeys.includes(storageKey) || readDismissed(storageKey)) {
        return null;
    }

    function dismiss() {
        writeDismissed(storageKey);
        setDismissedKeys((keys) => [...keys, storageKey]);
        // Hangi adımda ipucu gürültü sayılıyor (`docs/112` §4.3).
        trackEvent('setup_hint_dismissed', { step });
    }

    const helpHref = HELP_ARTICLE_ANCHORS[step];
    const helpLabel = HELP_LABEL[step];

    const sentence = isContinue ? t(NEXT_SENTENCE[step as FirstRunNextStep]) : t(SENTENCE[step]);

    return (
        <aside
            aria-label={
                isContinue ? t('dashboard.firstRun.next.region') : t('dashboard.firstRun.region')
            }
            className={cn(
                /*
                    DOLGU SIKI (`docs/118` E3): dokunma hedefi 44 kalır, ölü
                    alan büyümez. 320 pikselde ölçüldü — bkz. mobil denetim.
                */
                'flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border p-[var(--space-3)]',
                isContinue
                    ? 'border-transparent bg-action text-action-fg'
                    : 'border-border-info bg-surface-info text-fg',
            )}
        >
            <div className="flex items-start gap-[var(--space-2)]">
                {/*
                    Cümle 44 piksellik kapatma düğmesiyle AYNI HİZADA başlar:
                    metnin dikey dolgusu düğmenin ikonunu ortalayan boşluğa
                    denk gelir; iki satır arasında rastgele bir kayma olmaz.
                */}
                <p className="min-w-0 flex-1 py-[var(--space-2)] text-body">{sentence}</p>
                <IconButton
                    icon={<X size={18} weight="bold" />}
                    label={t('dashboard.firstRun.dismiss')}
                    onClick={dismiss}
                    className={cn(
                        // Düğmenin kutusu kartın dolgusuna GİRER: 44'lük hedef
                        // korunur, kart o hedefi taşımak için büyümez.
                        '-me-[var(--space-2)] -mt-[var(--space-2)] shrink-0',
                        isContinue &&
                            'text-action-fg hover:bg-[var(--color-fg)]/10 hover:text-action-fg',
                    )}
                />
            </div>

            {isContinue ? (
                <button
                    type="button"
                    onClick={props.onContinue}
                    /*
                        Home'daki "şimdi" düğmesinin aynı grameri: marka zemin
                        üstünde mürekkep dolgu. Sarı üstünde sarı bir düğme
                        görünmez olurdu.
                    */
                    className="inline-flex min-h-[var(--control-height)] items-center justify-center gap-[var(--space-2)] self-start rounded-[var(--radius-lg)] bg-[var(--color-fg)] px-[var(--space-5)] py-[var(--space-3)] text-body font-bold text-[var(--color-surface)] transition-opacity duration-[var(--duration-fast)] ease-[var(--easing-inout)] hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    {t(NOW_VERB[step as FirstRunNextStep])}
                    <ArrowRight aria-hidden="true" size={20} weight="bold" />
                </button>
            ) : null}

            {helpHref !== undefined && helpLabel !== undefined ? (
                /*
                    YENİ SEKMEDE açılır: yardım bir kamu sayfasıdır ve
                    panelin dışındadır. Aynı sekmede açmak, yarısı doldurulmuş
                    bir formu geride bırakırdı. Bağlantı 44 piksel yüksekliğinde
                    bir hedef olarak çizilir; satır içi bir metin bağlantısı
                    dar ekranda ölçümde küçük hedef çıkıyor.
                */
                <a
                    href={helpHref}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={() => trackEvent('setup_help_opened', { step })}
                    className="inline-flex min-h-[var(--density-hit-area-min)] items-center gap-[var(--space-1)] self-start text-body font-medium text-fg-link underline underline-offset-2 hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    {t(helpLabel)}
                    <ArrowSquareOut aria-hidden="true" size={16} weight="bold" />
                    <span className="sr-only">({t('dashboard.firstRun.help.newTab')})</span>
                </a>
            ) : null}
        </aside>
    );
}

export default FirstRunHint;
