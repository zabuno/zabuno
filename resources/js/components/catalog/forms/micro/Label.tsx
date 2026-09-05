import { Label as FlowbiteLabel } from 'flowbite-react';
import type { ComponentProps } from 'react';

import { labelTokenTheme } from '../../../../design-system/flowbite-theme';

export type LabelProps = ComponentProps<typeof FlowbiteLabel> & {
    required?: boolean;
};

/**
 * SEÇİM KONTROLÜNÜN (onay kutusu, radyo) ETİKETİ DOKUNMA HEDEFİDİR.
 *
 * Onay kutusunun kendisi 16 pikseldir (`--control-indicator-size`) ve öyle
 * kalır: onu parmak boyuna büyütmek 320 piksellik bir satırın önemli bir
 * kısmını yer ve `docs/117` M4'ün tam tersini yapardı. Zaten kullanıcının
 * dokunduğu şey kutu değildir — `htmlFor` ile bağlı bir etikete dokunmak
 * kutuyu değiştirir, yani hedef ikisinin BİRLEŞİMİDİR.
 *
 * Ölçüldü (320×568, `docs/117` K1): o birleşim 24 piksel yüksekliğindeydi —
 * asgarinin yarısından az. Yükseklik etikete verilir çünkü satırdaki geniş
 * olan odur; kutu yerinde kalır, hedef büyür.
 *
 * Yalnız SEÇİM etiketlerinde kullanılır. Her etikete 44 piksel vermek, metin
 * alanlarının üstündeki tek satırlık etiketleri de şişirir ve formu dar
 * ekranda gereksiz uzatır — kazanılan yer geri kaybedilirdi.
 */
export const CHOICE_LABEL_TOUCH_CLASS =
    'inline-flex min-h-[var(--density-hit-area-min)] items-center';

/**
 * Micro building block: a form field label. Knows nothing about the field
 * it labels beyond `htmlFor` — no fetch, no route, no business rule.
 */
export function Label({ required = false, children, ...rest }: LabelProps) {
    return (
        <FlowbiteLabel theme={labelTokenTheme} {...rest}>
            {children}
            {required ? (
                <span aria-hidden="true" className="ms-0.5 text-fg-danger ">
                    *
                </span>
            ) : null}
        </FlowbiteLabel>
    );
}
