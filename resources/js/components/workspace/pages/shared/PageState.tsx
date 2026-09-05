import { useEffect, type ReactNode } from 'react';
import clsx from 'clsx';
import { trackEvent } from '../../../../lib/analytics';
import { Spinner } from '../../../catalog/feedback/micro/Spinner';

/**
 * Bir sayfanın veri DIŞINDAKİ hâlleri — `docs/59`.
 *
 * Her ekran en az şu durumlar için ayrı tasarlanır: yükleniyor, boş, hata,
 * yetki yok, plan kısıtı, ön koşul eksik. Bunlar "içerik yok" başlığı altında
 * tek bir kutuya toplanamaz, çünkü **çıkış yolları farklıdır** ve kullanıcıya
 * yanlış çıkış yolu göstermek onu hiç çıkamayacağı bir döngüye sokar.
 *
 * En pahalı karıştırma hata ile kısıt arasındadır. Ortada bozulmuş bir şey
 * yokken `role="alert"` kullanmak ve "tekrar deneyin" demek, kullanıcıyı hiç
 * işe yaramayacak bir eylemi tekrarlamaya iter — bu depoda Analytics'in 402
 * yanıtında bir kez yaşandı. Bu bileşen o dersi tek yerde tutar.
 */
export type PageStateKind =
    | 'loading'
    | 'empty'
    | 'error'
    | 'permission'
    | 'planRestricted'
    | 'prerequisite'
    /**
     * Veri geldi ama EKSİK: bir parçası yüklenemedi.
     *
     * `error` değildir, çünkü ekranda kullanılabilir bir şey var; `empty`
     * de değildir, çünkü boş değil. İkisinden birine yuvarlamak ya var olan
     * veriyi gizler ya da eksiği görünmez kılar.
     */
    | 'partial'
    /**
     * Sistem ÇALIŞIYOR ama tam kapasitede değil — gecikmiş ölçüm, kuyruk
     * birikmesi, düşürülmüş bir alt servis.
     *
     * Kullanıcı için anlamı "bekle ve yeniden dene" değil, "gördüğün şey
     * güncel olmayabilir"dir. Hata olarak sunmak, düzeltilecek bir şey
     * varmış izlenimi verir.
     */
    | 'degraded'
    /**
     * Bir iş TAMAMLANDI.
     *
     * Boş durumların yanında yaşaması gerekiyor, çünkü aynı yeri kaplar ve
     * aynı sözleşmeye uyar: ne oldu, ne anlama geliyor, şimdi nereye.
     */
    | 'success';

/**
 * Hangi durum GERÇEKTEN bir arızadır?
 *
 * Yalnız `error`. Boş bir liste, eksik bir ön koşul, satın alınmamış bir plan
 * ya da olmayan bir yetki — hiçbirinde bozulmuş bir şey yoktur. `role="alert"`
 * ekran okuyucuyu böler ve aciliyet bildirir; bunu normal bir durum için
 * kullanmak, gerçek uyarının değerini düşürür.
 */
const ASSERTIVE: ReadonlySet<PageStateKind> = new Set<PageStateKind>(['error']);

/**
 * Başarı, boşluk gibi görünmemelidir.
 *
 * Kesikli çerçeve "burada bir şey eksik" der; tamamlanmış bir iş için yanlış
 * sinyaldir. `degraded` ve `partial` ise kesikli kalır: ikisinde de gerçekten
 * eksik bir şey vardır.
 */
const SOLID_SURFACE: ReadonlySet<PageStateKind> = new Set<PageStateKind>(['success']);

/**
 * Durum türü → `action_blocked` gerekçesi (`docs/112` §4.3).
 *
 * `action_blocked` sözleşmede özellikle değerlidir: bu depo "yapılamayan iş
 * çizilmez" kuralını uygular, dolayısıyla kullanıcının bir kısıtla
 * karşılaştığı HER yer bir tasarım eksiğinin izidir. Boş bir liste ürünün
 * normal bir hâli, kapalı bir kapı ise bir sorudur: "kaç sahip yapamadığı
 * bir şeye bakıyor?".
 *
 * `error` ve `degraded` burada YOKTUR: onlar bir kısıt değil arızadır ve
 * ölçümleri sunucu tarafında zaten yaşar; ikisini aynı olayda toplamak
 * "izin eksikliği" ile "sunucu düştü"yü tek çubuğa yığardı.
 */
const BLOCKED_REASON: Partial<Record<PageStateKind, 'permission' | 'plan' | 'state'>> = {
    permission: 'permission',
    planRestricted: 'plan',
    prerequisite: 'state',
};

type PageStateBase = {
    kind: PageStateKind;
    /**
     * Bu durumun görüldüğü ekran — ZORUNLU, çünkü ölçüm buradan çıkar.
     *
     * İsteğe bağlı olsaydı yeni bir ekran ölçüm dışında kalırdı ve bunu
     * hiçbir test söylemezdi: eksik ölçüm, yanlış ölçüm kadar sessizdir
     * (`docs/112` §7). Zorunlu olduğu için derleyici her yeni çağrı yerine
     * adını sorar.
     *
     * Kısa ve `snake_case`/tire içermeyen bir ad verin (`menu`, `analytics`);
     * GTM'de kırılım burada yazan dizeyle yapılır.
     */
    screen: string;
    /** NE yok / ne oldu. */
    title: string;
    /** NEDEN öyle ve kullanıcı için anlamı ne. */
    description?: string;
    className?: string;
};

/**
 * Durum ya bir ÇIKIŞ YOLU sunar, ya da neden sunamadığını söyler.
 *
 * Tip seviyesinde zorlanıyor çünkü unutmak kolay ve sonucu ağır: çıkışsız bir
 * boş durum, kullanıcıyı "burada yapılacak bir şey yok" diye bırakır ve o
 * ekranda ne yapacağını hiç öğrenemez. `whyNoAction` bir kaçış deliği değil —
 * gerçekten eylem OLMAYAN durumlar vardır (yetki kendine verilemez) ve o zaman
 * söylenmesi gereken şey kimden isteneceğidir.
 */
type PageStateWithAction = PageStateBase & { action: ReactNode; whyNoAction?: never };
type PageStateWithoutAction = PageStateBase & { action?: never; whyNoAction: string };
type PageStateLoading = PageStateBase & {
    kind: 'loading';
    action?: never;
    whyNoAction?: never;
};

export type PageStateProps = PageStateWithAction | PageStateWithoutAction | PageStateLoading;

export function PageState(props: PageStateProps) {
    const { kind, screen, title, description, className } = props;
    const assertive = ASSERTIVE.has(kind);

    /*
        SÜRTÜNME ÖLÇÜMÜ BURADA, tek yerde (`docs/112` §4.3).

        Onbir ekranın her birine tek tek olay basmak, on birinci ekranda
        unutulmaya mahkûmdu. Boş durum ve kapalı kapı zaten TEK bileşenden
        çiziliyor; ölçüm de oradan çıkar. Böylece yarın eklenen bir ekran,
        hiç kimse hatırlamasa bile ölçülür.

        Metin GÖNDERİLMEZ, yalnız ekranın adı: başlık ve açıklama ürün
        metnidir, çevrilir ve değişir; ölçüm ise sabit bir kırılım ister.
    */
    useEffect(() => {
        if (kind === 'empty') {
            trackEvent('empty_state_seen', { screen });

            return;
        }

        const reason = BLOCKED_REASON[kind];

        if (reason !== undefined) {
            trackEvent('action_blocked', { action: screen, reason });
        }
    }, [kind, screen]);
    return (
        <div
            role={assertive ? 'alert' : 'status'}
            aria-live={assertive ? undefined : 'polite'}
            className={clsx(
                /*
                    2026 boş-durum dili: ferah dolgu, ORTALANMIŞ hizalama ve
                    ölçülü metin genişliği. Sola yaslı ve dar dolgulu hâli
                    sayfanın geri kalanından ayrışmıyor, "burada bir şey
                    eksik" demek yerine yarım kalmış bir liste gibi
                    görünüyordu (2026-09-04 ekran incelemesi).
                */
                'flex flex-col items-center gap-[var(--space-3)] rounded-[var(--radius-lg)] border',
                'px-[var(--space-5)] py-[var(--space-7)] text-center',
                assertive
                    ? 'border-border-danger bg-surface-danger'
                    : SOLID_SURFACE.has(kind)
                      ? 'border-border bg-surface'
                      : 'border-dashed border-border',
                className,
            )}
        >
            {/*
                Gösterge DEKORATİF: duyuruyu kap yapar. İkisi birden
                duyurursa aynı metin iki kez okunur.
            */}
            {kind === 'loading' ? <Spinner decorative /> : null}

            <p
                className={clsx(
                    'text-section font-bold tracking-tight',
                    assertive ? 'text-fg-danger' : 'text-fg',
                )}
            >
                {title}
            </p>

            {description ? (
                <p className="max-w-[48ch] text-body text-fg-secondary">{description}</p>
            ) : null}

            {'action' in props && props.action ? <div>{props.action}</div> : null}

            {'whyNoAction' in props && props.whyNoAction ? (
                <p className="text-meta text-fg-muted">{props.whyNoAction}</p>
            ) : null}
        </div>
    );
}

export default PageState;
