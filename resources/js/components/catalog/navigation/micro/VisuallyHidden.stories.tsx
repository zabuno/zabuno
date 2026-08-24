import type { Meta, StoryObj } from '@storybook/react-vite';
import { VisuallyHidden } from './VisuallyHidden';

const meta: Meta<typeof VisuallyHidden> = {
    title: 'Micro/Navigation/VisuallyHidden',
    component: VisuallyHidden,
};

export default meta;
type Story = StoryObj<typeof VisuallyHidden>;

export const Default: Story = {
    render: (args) => (
        <p>
            Visible text <VisuallyHidden {...args} />
        </p>
    ),
    args: { children: 'screen-reader-only text' },
};

export const RightToLeft: Story = {
    ...Default,
    args: { children: 'نص مخفي بصريا' },
    parameters: { direction: 'rtl' },
};
