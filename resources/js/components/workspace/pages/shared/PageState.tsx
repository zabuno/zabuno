import type { ReactNode } from 'react';
import clsx from 'clsx';
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
    'loading' | 'empty' | 'error' | 'permission' | 'planRestricted' | 'prerequisite';

/**
 * Hangi durum GERÇEKTEN bir arızadır?
 *
 * Yalnız `error`. Boş bir liste, eksik bir ön koşul, satın alınmamış bir plan
 * ya da olmayan bir yetki — hiçbirinde bozulmuş bir şey yoktur. `role="alert"`
 * ekran okuyucuyu böler ve aciliyet bildirir; bunu normal bir durum için
 * kullanmak, gerçek uyarının değerini düşürür.
 */
const ASSERTIVE: ReadonlySet<PageStateKind> = new Set<PageStateKind>(['error']);

type PageStateBase = {
    kind: PageStateKind;
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
    const { kind, title, description, className } = props;
    const assertive = ASSERTIVE.has(kind);
    return (
        <div
            role={assertive ? 'alert' : 'status'}
            aria-live={assertive ? undefined : 'polite'}
            className={clsx(
                'flex flex-col items-start gap-3 rounded-lg border p-6',
                assertive
                    ? 'border-border-danger bg-surface-danger'
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
                    'text-body font-semibold',
                    assertive ? 'text-fg-danger' : 'text-fg',
                )}
            >
                {title}
            </p>

            {description ? <p className="text-body text-fg-secondary">{description}</p> : null}

            {'action' in props && props.action ? <div>{props.action}</div> : null}

            {'whyNoAction' in props && props.whyNoAction ? (
                <p className="text-meta text-fg-muted">{props.whyNoAction}</p>
            ) : null}
        </div>
    );
}

export default PageState;
