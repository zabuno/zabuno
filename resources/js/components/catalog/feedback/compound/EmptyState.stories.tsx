import type { Meta, StoryObj } from '@storybook/react-vite';
import { EmptyState } from './EmptyState';
import { Button } from '../../forms/micro/Button';

const meta: Meta<typeof EmptyState> = {
    title: 'Compound/Feedback/EmptyState',
    component: EmptyState,
};

export default meta;
type Story = StoryObj<typeof EmptyState>;

export const NoResults: Story = {
    args: { title: 'No menu items yet', description: 'Add your first item to get started.' },
};

export const WithAction: Story = {
    args: {
        title: 'No menu items yet',
        description: 'Add your first item to get started.',
        /*
            EYLEM, KATALOĞUN KENDİ DÜĞMESİDİR (`docs/117` M3).

            Önceki hâl story içinde elle kurulmuş bir düğmeydi: ham palet
            (`bg-blue-600`) ve ham dolgu. Ölçüldü (320×568): 89×36 — asgari
            dokunma hedefinin altında. Hikâye ürünün kullandığı düğmeyi
            kullanmazsa ölçülen şey üründe olan şey değildir; kapı da
            olmayan bir kusuru raporlar ya da olanı kaçırır.
        */
        action: <Button>Add item</Button>,
    },
};

export const Loading: Story = {
    args: { title: 'Checking for menu items', loading: true },
};

export const RightToLeft: Story = {
    args: { title: 'لا توجد عناصر قائمة بعد', description: 'أضف عنصرك الأول للبدء.' },
    parameters: { direction: 'rtl' },
};
