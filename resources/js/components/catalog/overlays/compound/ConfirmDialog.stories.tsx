import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { Button } from 'flowbite-react';
import { ConfirmDialog } from './ConfirmDialog';

const meta: Meta<typeof ConfirmDialog> = {
    title: 'Compound/Overlays/ConfirmDialog',
    component: ConfirmDialog,
    parameters: {
        docs: {
            description: {
                component:
                    'Composes Micro/Overlays/CloseButton with Flowbite Modal/ModalBody/ModalFooter.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof ConfirmDialog>;

function ConfirmDialogDemo(args: Parameters<typeof ConfirmDialog>[0]) {
    const [open, setOpen] = useState(true);
    return (
        <>
            <Button onClick={() => setOpen(true)}>Open dialog</Button>
            <ConfirmDialog
                {...args}
                open={open}
                onClose={() => setOpen(false)}
                onConfirm={() => setOpen(false)}
            />
        </>
    );
}

export const Default: Story = {
    render: (args) => <ConfirmDialogDemo {...args} />,
    args: {
        title: 'Publish menu changes?',
        children: 'Your changes will become visible to customers immediately.',
    },
};

export const Destructive: Story = {
    render: (args) => <ConfirmDialogDemo {...args} />,
    args: {
        title: 'Delete this menu item?',
        children: 'This action cannot be undone.',
        destructive: true,
        confirmLabel: 'Delete',
    },
};

export const ConfirmLoading: Story = {
    render: (args) => <ConfirmDialogDemo {...args} />,
    args: {
        title: 'Deleting menu item…',
        destructive: true,
        confirmLabel: 'Delete',
        confirmLoading: true,
    },
};

export const RightToLeft: Story = {
    render: (args) => <ConfirmDialogDemo {...args} />,
    args: {
        title: 'هل تريد حذف هذا العنصر؟',
        children: 'لا يمكن التراجع عن هذا الإجراء.',
        destructive: true,
        confirmLabel: 'حذف',
        cancelLabel: 'إلغاء',
    },
    parameters: { direction: 'rtl' },
};
