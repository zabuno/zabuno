import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { Button } from 'flowbite-react';
import { DrawerPanel } from './DrawerPanel';

const meta: Meta<typeof DrawerPanel> = {
    title: 'Compound/Overlays/DrawerPanel',
    component: DrawerPanel,
    parameters: {
        docs: {
            description: {
                component: 'Composes Micro/Overlays/CloseButton with Flowbite Drawer/DrawerItems.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof DrawerPanel>;

function DrawerPanelDemo(args: Parameters<typeof DrawerPanel>[0]) {
    const [open, setOpen] = useState(true);
    return (
        <>
            <Button onClick={() => setOpen(true)}>Open drawer</Button>
            <DrawerPanel {...args} open={open} onClose={() => setOpen(false)}>
                {args.children}
            </DrawerPanel>
        </>
    );
}

export const Default: Story = {
    render: (args) => <DrawerPanelDemo {...args} />,
    args: {
        title: 'Filter menu items',
        children: 'Filter controls go here.',
    },
};

export const LeftPosition: Story = {
    render: (args) => <DrawerPanelDemo {...args} />,
    args: {
        title: 'Navigation',
        position: 'left',
        children: 'Nav links go here.',
    },
};

export const RightToLeft: Story = {
    render: (args) => <DrawerPanelDemo {...args} />,
    args: {
        title: 'تصفية عناصر القائمة',
        children: 'عناصر التحكم في التصفية هنا.',
    },
    parameters: { direction: 'rtl' },
};
