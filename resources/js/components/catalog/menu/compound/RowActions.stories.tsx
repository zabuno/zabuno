import type { Meta, StoryObj } from '@storybook/react-vite';
import { RowActions } from './RowActions';

const meta = {
    title: 'Compound/Menu/RowActions',
    component: RowActions,
    parameters: {
        docs: {
            description: {
                component:
                    'Move and remove actions for one menu row. Up/down instead of drag-and-drop, because dragging is unreliable on touch and with a keyboard. Renaming lives on the name itself (InlineRename), and removing sits in the overflow menu so a mis-click cannot destroy a row that was only meant to move.',
            },
        },
    },
    args: {
        onDelete: () => {},
        onMoveUp: () => {},
        onMoveDown: () => {},
        deleteLabel: 'Remove Lentil soup',
        upLabel: 'Move Lentil soup up',
        downLabel: 'Move Lentil soup down',
        moreLabel: 'More actions for Lentil soup',
        deleteText: 'Remove',
    },
} satisfies Meta<typeof RowActions>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** Bir satırın yanında gerçek bağlamda. */
export const InARow: Story = {
    render: (args) => (
        <div className="flex items-center gap-3 border-b border-border py-2">
            <span className="flex-1 text-body text-fg">Mercimek Çorbası</span>
            <span className="text-body tabular-nums text-fg-secondary">45.00 TRY</span>
            <RowActions {...args} />
        </div>
    ),
};
