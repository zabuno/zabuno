import type { Meta, StoryObj } from '@storybook/react-vite';

import { ActionLink } from './ActionLink';

const meta = {
    title: 'Micro/Navigation/ActionLink',
    component: ActionLink,
    parameters: {
        docs: {
            description: {
                component:
                    'Anlamı bağlantı, görünüşü eylem. Frontpage ve admin aynı `--color-action` token’ını okur; token değişince ikisi birden değişir.',
            },
        },
    },
} satisfies Meta<typeof ActionLink>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Primary: Story = {
    args: { href: '#', children: 'Open workspace app', variant: 'primary' },
};

export const Secondary: Story = {
    args: { href: '#', children: 'Log in', variant: 'secondary' },
};

export const SideBySide: Story = {
    args: { href: '#', children: 'Primary' },
    render: () => (
        <div className="flex flex-wrap gap-3">
            <ActionLink href="#">Open workspace app</ActionLink>
            <ActionLink href="#" variant="secondary">
                Log in
            </ActionLink>
            <ActionLink href="#" variant="secondary">
                Create account
            </ActionLink>
        </div>
    ),
};
