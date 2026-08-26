import type { ComponentProps } from 'react';
import { Button } from './Button';

export type PlainButtonVariant = 'primary' | 'secondary';

export type PlainButtonProps = Omit<ComponentProps<typeof Button>, 'color'> & {
    variant?: PlainButtonVariant;
};

/**
 * @deprecated Artık `Button` ile aynı şeydir; yeni kod doğrudan `Button`
 * kullanmalıdır.
 *
 * Bu bileşen, Flowbite'ın varsayılan teması ham palet ve sabit yükseklik
 * getirdiği için ayrı bir uygulama olarak vardı. Flowbite artık token köküne
 * bağlı (`resources/js/design-system/flowbite-theme.ts`), yani o gerekçe
 * ortadan kalktı.
 *
 * İki uygulamayı bir arada bırakmak, ikisinin zamanla ayrışması demektir; bu
 * yüzden gövde silinip `Button`'a yönlendirildi. Ad, on dokuz çağrı yerini tek
 * bir pakette değiştirmemek için korunuyor — o taşıma ayrı ve mekanik bir
 * iştir, ve bittiğinde bu dosya kaldırılır.
 */
export function PlainButton({ variant = 'secondary', ...rest }: PlainButtonProps) {
    return <Button color={variant === 'primary' ? 'default' : 'light'} {...rest} />;
}
