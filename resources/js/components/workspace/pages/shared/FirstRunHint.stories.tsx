import type { Meta, StoryObj } from '@storybook/react-vite';
import { FirstRunHint } from './FirstRunHint';

/**
 * İlk-kez ipucu — kurulum adımının hedef ekranındaki tek cümle (FF-202).
 *
 * Dört hâl: üç ekran-kipi (menü ve karekod yardım bağlantısı taşır, yayın
 * taşımaz — makalede bölümü yok) ve bir "devam" kipi (marka kaydedildikten
 * sonra şubeye götüren, Home'daki kartla aynı zemin ve fiil).
 *
 * Sarmalayıcıda YATAY dolgu YOK: 320 pikselde ölçülen şey bileşenin kendi
 * dolgusudur, hikâyenin değil. Kapatma düğmesi `localStorage`'a yazar;
 * hikâyede kapattıysanız geri görmek için tarayıcı site verisini temizleyin.
 */
const meta: Meta<typeof FirstRunHint> = {
    title: 'Surface/Workspace/FirstRunHint',
    component: FirstRunHint,
    parameters: { layout: 'fullscreen' },
    args: { workspaceId: 7, done: false },
    decorators: [
        (Story) => (
            <div className="bg-canvas py-[var(--space-3)]">
                <Story />
            </div>
        ),
    ],
};

export default meta;
type Story = StoryObj<typeof FirstRunHint>;

/** Menü ekranı: cümle + makalenin "içe aktarma" bölümüne bağlantı. */
export const MenuStep: Story = { args: { step: 'menu' } };

/** Yayın ekranı: cümle var, bağlantı yok — makalede yayın bölümü yok. */
export const PublicationStep: Story = { args: { step: 'publication' } };

/** Karekod ekranı: cümle + "karekod bas" bölümüne bağlantı. */
export const QrStep: Story = { args: { step: 'qr' } };

/** Marka kaydedildi: sıradaki adım, Home'daki fiille ("Add your location"). */
export const NextStepAfterBrand: Story = {
    args: { step: 'location', onContinue: () => undefined },
};
