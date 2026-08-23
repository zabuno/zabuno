import type { Meta, StoryObj } from '@storybook/react-vite';
import { EmptyState } from './EmptyState';

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
        action: (
            <button type="button" className="rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white">
                Add item
            </button>
        ),
    },
};

export const Loading: Story = {
    args: { title: 'Checking for menu items', loading: true },
};

export const RightToLeft: Story = {
    args: { title: 'لا توجد عناصر قائمة بعد', description: 'أضف عنصرك الأول للبدء.' },
    parameters: { direction: 'rtl' },
};
