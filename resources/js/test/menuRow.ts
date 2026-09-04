import { fireEvent, screen } from '@testing-library/react';

/**
 * Menü satırının SEYREK işleri taşma menüsündedir (FF-102, `docs/103` Döngü 2).
 *
 * Fotoğraf, alerjen ve görünürlük satırda kalıcı düğme olarak dururken bir
 * ürün satırı dokuz kontrol taşıyordu; hepsi aynı ağırlıkta olduğu için gözün
 * gireceği bir nokta yoktu. Artık menüde ADLARIYLA duruyorlar.
 *
 * Bu yardımcı testlerin niyetini tek yerde tutar: "şu üründe şu işi yap".
 * Sözleşme değişmedi — yalnız yol iki adım oldu.
 *
 * `fireEvent` kullanılır çünkü çağıran testlerin bir kısmı `userEvent`
 * kurmuyor; menü açılışı ikisinde de aynı olayla tetiklenir.
 */
export async function clickRowMenuItem(
    itemName: string,
    actionName: string | RegExp,
): Promise<void> {
    fireEvent.click(screen.getByRole('button', { name: `More actions for ${itemName}` }));
    fireEvent.click(await screen.findByRole('menuitem', { name: actionName }));
}
