import type { Meta, StoryObj } from '@storybook/react-vite';
import { AlertMessage } from './AlertMessage';

const meta: Meta<typeof AlertMessage> = {
    title: 'Compound/Feedback/AlertMessage',
    component: AlertMessage,
};

export default meta;
type Story = StoryObj<typeof AlertMessage>;

export const Info: Story = {
    args: {
        status: 'info',
        title: 'New feature available',
        children: 'Try the new dashboard layout.',
    },
};

export const Success: Story = {
    args: {
        status: 'success',
        title: 'Changes saved',
        children: 'Your menu was published successfully.',
    },
};

export const Warning: Story = {
    args: {
        status: 'warning',
        title: 'Approaching quota',
        children: 'You have used 90% of your monthly quota.',
    },
};

export const ErrorStatus: Story = {
    args: {
        status: 'error',
        title: 'Save failed',
        children: 'Check your connection and try again.',
    },
};

export const TitleOnly: Story = { args: { status: 'info', title: 'No description needed here.' } };

export const RightToLeft: Story = {
    args: {
        status: 'warning',
        title: 'اقتربت من الحد',
        children: 'لقد استخدمت 90% من حصتك الشهرية.',
    },
    parameters: { direction: 'rtl' },
};
