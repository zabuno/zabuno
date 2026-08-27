import { useId } from 'react';
import { Button, Label, TextInput } from 'flowbite-react';
import { t } from '../../../i18n/workspace';

type AiAssistPanelProps = {
    context: string;
};

/**
 * Shared contextual AI affordance rendered on every workspace page. No real
 * AI is wired up yet: the command field and Review and approve control are
 * inert, and the panel issues no fetch/mutation of its own — it only shows
 * the honest unavailable state until a real assistant exists.
 */
export function AiAssistPanel({ context }: AiAssistPanelProps) {
    const commandInputId = useId();

    return (
        <section
            aria-label={t('workspace.ai.panel.label', { context })}
            className="mt-8 flex flex-col gap-3 rounded-lg border border-border p-4 "
        >
            <h3 className="text-body font-semibold text-fg">{t('workspace.ai.panel.heading')}</h3>

            <p role="status" className="text-body text-fg-muted">
                {t('workspace.ai.unavailable')}
            </p>

            <div>
                <div className="mb-2 block">
                    <Label htmlFor={commandInputId}>{t('workspace.ai.command.label')}</Label>
                </div>
                <TextInput
                    id={commandInputId}
                    name="ai-command"
                    type="text"
                    className="w-full"
                    disabled
                />
            </div>

            <div className="flex flex-col gap-1">
                <p className="text-meta font-semibold uppercase tracking-wide text-fg-muted">
                    {t('workspace.ai.whyThisSuggestion')}
                </p>
                <p className="text-body text-fg-secondary">
                    {t('workspace.ai.whyThisSuggestion.empty')}
                </p>
            </div>

            <div className="flex flex-col gap-1">
                <p className="text-meta font-semibold uppercase tracking-wide text-fg-muted">
                    {t('workspace.ai.affectedRecords')}
                </p>
                <p className="text-body text-fg-secondary">
                    {t('workspace.ai.affectedRecords.empty')}
                </p>
            </div>

            <Button disabled className="self-start">
                {t('workspace.ai.reviewAndApprove')}
            </Button>
        </section>
    );
}

export default AiAssistPanel;
