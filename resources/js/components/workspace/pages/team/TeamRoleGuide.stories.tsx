import type { Meta, StoryObj } from '@storybook/react-vite';

import { TeamRoleGuide } from './TeamRoleGuide';
import { ThemeRoot } from '../../../theme/ThemeRoot';

/**
 * Roller kartı (`docs/109` §6.4). Hikâyeler kartın DÖRT kanonik rolünü
 * gösterir; kaynağın dördüncüsü olan "Mutfak" bir süre hiçbir hikâyede yoktu
 * çünkü deponun izin matrisinde karşılığı yoktu — artık var. Salt okunur eski
 * rol ise yalnız onu fiilen taşıyan biri varken belirir.
 */
const meta: Meta<typeof TeamRoleGuide> = {
    title: 'Surface/Workspace/TeamRoleGuide',
    component: TeamRoleGuide,
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div style={{ maxWidth: '24rem' }}>
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
    args: { roles: ['owner', 'manager', 'editor', 'kitchen'] },
};

export default meta;
type Story = StoryObj<typeof TeamRoleGuide>;

/** Bugün davet edilebilen ve devredilen roller. */
export const CanonicalRoles: Story = {};

/** Eski bir kayıt salt okunur rolü taşıyor: satırdaki kelime açıklanır. */
export const WithLegacyReadOnlyRole: Story = {
    args: { roles: ['owner', 'manager', 'editor', 'kitchen', 'member'] },
};

/** Tek kişilik çalışma alanı: yalnız sahiplik anlatılır. */
export const OwnerOnly: Story = { args: { roles: ['owner'] } };
