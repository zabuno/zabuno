import type { Meta, StoryObj } from '@storybook/react-vite';
import { CloseButton } from './CloseButton';

const meta: Meta<typeof CloseButton> = {
    title: 'Micro/Overlays/CloseButton',
    component: CloseButton,
};

export default meta;
type Story = StoryObj<typeof CloseButton>;

export const Default: Story = {
    args: { onClick: () => {} },
};

export const CustomLabel: Story = {
    args: { onClick: () => {}, label: 'Dismiss notification' },
};

export const RightToLeft: Story = {
    args: { onClick: () => {} },
    parameters: { direction: 'rtl' },
};
