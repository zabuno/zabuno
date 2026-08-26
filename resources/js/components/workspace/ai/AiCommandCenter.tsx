import { useId } from 'react';
import { Button, Label } from 'flowbite-react';
import { DrawerPanel } from '../../catalog/overlays/compound/DrawerPanel';
import { Textarea } from '../../catalog/forms/micro/Textarea';
import { t } from '../../../i18n/workspace';
import type { AiAssistQuickAction } from '../../../lib/aiAssistState';

export type AiCommandCenterProps = {
    open: boolean;
    onClose: () => void;
    workspaceName: string;
    locationDisplayName: string | null;
    quickActions: AiAssistQuickAction[];
    onSelectQuickAction: (key: AiAssistQuickAction['key']) => void;
};

/**
 * Bounded complex component: the shell-level AI command center dialog.
 * There is no AI gateway wired up yet, so the command input and approval
 * control are permanently disabled and no suggestion/history/preview data
 * is fabricated (docs/35 §2a — bounded complex components own their own
 * layout but stay honest about what is and isn't real).
 */
export function AiCommandCenter({
    open,
    onClose,
    workspaceName,
    locationDisplayName,
    quickActions,
    onSelectQuickAction,
}: AiCommandCenterProps) {
    const commandInputId = useId();
    const recentCommandsHeadingId = useId();

    return (
        <DrawerPanel open={open} onClose={onClose} title={t('workspace.aiCommand.title')}>
            <div className="flex flex-col gap-[var(--space-fluid-md)]">
                <div className="flex flex-col gap-1">
                    <p className="text-sm font-medium text-fg">{workspaceName}</p>
                    {locationDisplayName && (
                        <p className="text-sm text-fg-secondary">{locationDisplayName}</p>
                    )}
                </div>

                <p role="status" className="text-sm text-fg-secondary">
                    {t('workspace.aiCommand.status')}
                </p>

                <div>
                    <div className="mb-2 block">
                        <Label htmlFor={commandInputId}>
                            {t('workspace.aiCommand.commandLabel')}
                        </Label>
                    </div>
                    <Textarea id={commandInputId} disabled className="w-full" />
                </div>

                <div className="flex flex-col gap-1">
                    <p className="text-sm font-medium text-fg">
                        {t('workspace.aiCommand.affectedRecords.heading')}
                    </p>
                    <p className="text-sm text-fg-secondary">
                        {t('workspace.aiCommand.affectedRecords.empty')}
                    </p>
                </div>

                <div
                    role="region"
                    aria-labelledby={recentCommandsHeadingId}
                    className="flex flex-col gap-1"
                >
                    <p id={recentCommandsHeadingId} className="text-sm font-medium text-fg">
                        {t('workspace.aiCommand.recentCommands.heading')}
                    </p>
                    <p className="text-sm text-fg-secondary">
                        {t('workspace.aiCommand.recentCommands.empty')}
                    </p>
                </div>

                <Button disabled className="w-fit">
                    {t('workspace.aiCommand.approve')}
                </Button>

                <div className="flex flex-wrap gap-2">
                    {quickActions.map((action) => (
                        <Button
                            key={action.key}
                            color="light"
                            onClick={() => onSelectQuickAction(action.key)}
                        >
                            {action.label}
                        </Button>
                    ))}
                </div>
            </div>
        </DrawerPanel>
    );
}
