import type { Meta, StoryObj } from '@storybook/react-vite';
import { MediaSlotHint } from './MediaSlotHint';

/**
 * Kutu 320 pikselde ÖLÇÜLEBİLİR olsun diye hikâyeleşti
 * (`scripts/mobile-ux-audit`). Panelin kendisi ancak kullanıcı bir ürünün
 * sunum panelini açtıktan sonra çizilir; statik bir denetim onu göremez.
 * Ölçülmeyen bir sonuç "geçti" değil "bilinmiyor"dur.
 */
const meta: Meta<typeof MediaSlotHint> = {
    title: 'Micro/Menu/MediaSlotHint',
    component: MediaSlotHint,
    args: {
        message:
            'No processed photo is available yet. Upload one on the Media page (slot: List/card/detail item) first.',
        requirement: 'That slot needs at least 1000×1000 px, 1:1.',
        href: '/app/zeytin-restoranlari/media',
        linkLabel: 'Open the Media page',
    },
};

export default meta;
type Story = StoryObj<typeof MediaSlotHint>;

export const Default: Story = {};

/** Sunucu politikayı vermediyse ölçü satırı HİÇ çizilmez — uydurulmaz. */
export const WithoutRequirement: Story = {
    args: { requirement: null },
};

/**
 * Medya ekranını açamayan bir rol (Mutfak) bağlantı GÖRMEZ: açamayacağı bir
 * yere bağlantı, yeni bir çıkmaz sokaktır. Cümle yine kimin yükleyeceğini
 * söyler.
 */
export const WithoutLink: Story = {
    args: {
        message:
            'No processed photo is available yet. Someone with media access has to upload one on the Media page (slot: List/card/detail item).',
        href: null,
    },
};
