import type { Meta, StoryObj } from '@storybook/react-vite';

import { PlainButton } from './PlainButton';

const meta = {
    title: 'Micro/Forms/PlainButton',
    component: PlainButton,
    parameters: {
        docs: {
            description: {
                component:
                    'Yalnız token’lardan giyinen buton. Flowbite’ın varsayılan teması ham palet ve sabit yükseklik getirdiği için, yoğunluk token’ına saygı duyması gereken yüzeyler bunu kullanır.',
            },
        },
    },
} satisfies Meta<typeof PlainButton>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Secondary: Story = { args: { children: 'Retry' } };
export const Primary: Story = { args: { children: 'Publish', variant: 'primary' } };
export const Disabled: Story = { args: { children: 'Publish', disabled: true } };
