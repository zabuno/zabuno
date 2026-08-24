import type { Meta, StoryObj } from '@storybook/react-vite';
import { Button } from 'flowbite-react';
import { TooltipHint } from './TooltipHint';

const meta: Meta<typeof TooltipHint> = {
    title: 'Compound/Overlays/TooltipHint',
    component: TooltipHint,
    parameters: {
        docs: {
            description: {
                component: 'Typed wrapper around Flowbite Tooltip for catalog consumers.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof TooltipHint>;

export const Default: Story = {
    args: {
        content: 'Duplicate this menu item',
        children: <Button>Duplicate</Button>,
    },
};

export const BottomPlacement: Story = {
    args: {
        content: 'Publish makes this visible to customers',
        placement: 'bottom',
        children: <Button>Publish</Button>,
    },
};

export const RightToLeft: Story = {
    args: {
        content: 'نسخ عنصر القائمة هذا',
        children: <Button>نسخ</Button>,
    },
    parameters: { direction: 'rtl' },
};
