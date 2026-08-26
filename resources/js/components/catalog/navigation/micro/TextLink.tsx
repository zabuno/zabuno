import type { AnchorHTMLAttributes } from 'react';
import clsx from 'clsx';

export type TextLinkProps = AnchorHTMLAttributes<HTMLAnchorElement>;

/**
 * Micro: metin içi bağlantı.
 *
 * Bağlantı rengi tek bir yerde yaşar. Elle yazılan açık/karanlık ham palet
 * çiftleri, karanlık temayı unutulmaya açık bırakıyordu; `--fg-link` her
 * temada kendi değerini bilir.
 *
 * Altı çizili kalır: bağlantıyı yalnız RENKLE ayırmak, renk körü bir
 * kullanıcı için onu görünmez yapar (WCAG 2.2 §1.4.1).
 */
export function TextLink({ className, ...rest }: TextLinkProps) {
    return (
        <a
            className={clsx(
                'text-fg-link underline underline-offset-2',
                'hover:no-underline',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                className,
            )}
            {...rest}
        />
    );
}
