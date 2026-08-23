import type { Meta, StoryObj } from '@storybook/react-vite';
import { Divider } from './Divider';

const meta: Meta<typeof Divider> = {
    title: 'Micro/Data Display/Divider',
    component: Divider,
};

export default meta;
type Story = StoryObj<typeof Divider>;

export const Horizontal: Story = { args: { orientation: 'horizontal' } };

export const Vertical: Story = {
    args: { orientation: 'vertical' },
    decorators: [
        (Story) => (
            <div style={{ display: 'flex', height: '48px', alignItems: 'stretch' }}>
                <Story />
            </div>
        ),
    ],
};

export const RightToLeft: Story = {
    args: { orientation: 'horizontal' },
    parameters: { direction: 'rtl' },
};
