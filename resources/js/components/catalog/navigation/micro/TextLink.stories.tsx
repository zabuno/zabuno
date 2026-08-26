import type { Meta, StoryObj } from '@storybook/react-vite';

import { TextLink } from './TextLink';

const meta = {
    title: 'Micro/Navigation/TextLink',
    component: TextLink,
    parameters: {
        docs: {
            description: {
                component:
                    'Metin içi bağlantı. Altı çizili kalır: bağlantıyı yalnız renkle ayırmak, renk körü bir kullanıcı için onu görünmez yapar.',
            },
        },
    },
} satisfies Meta<typeof TextLink>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = { args: { href: '#', children: 'Download PNG' } };

export const InParagraph: Story = {
    args: { href: '#', children: 'yayınlanan menü' },
    render: (args) => (
        <p className="max-w-prose text-sm text-fg-secondary">
            QR kodu bastırdıktan sonra <TextLink {...args} /> sayfasını telefonunuzla bir kez
            tarayın.
        </p>
    ),
};
