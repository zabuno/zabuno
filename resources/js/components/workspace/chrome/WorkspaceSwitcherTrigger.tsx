import { t } from '../../../i18n/workspace';

export type WorkspaceSwitcherTriggerProps = {
    workspaceName?: string;
    onSwitchWorkspace?: () => void;
};

/**
 * Kenar çubuğunun ÜSTÜNDEKİ çalışma alanı bağlamı — `docs/50` §6.
 *
 * Bu bir gezinti maddesi değil, gezintinin üstündeki BAĞLAMDIR: "hangi
 * restorandayım" sorusunun cevabı listenin içinde aranmaz, listenin başında
 * durur. Önceden kenar çubuğunun DİBİNDE "Switch workspace" adlı bir
 * bağlantıydı ve her gün gidilen hedeflerin arasına karışıyordu.
 */
export function WorkspaceSwitcherTrigger({
    workspaceName,
    onSwitchWorkspace,
}: WorkspaceSwitcherTriggerProps) {
    if (workspaceName === undefined || onSwitchWorkspace === undefined) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={onSwitchWorkspace}
            className="mb-[var(--space-fluid-sm)] flex min-h-[var(--density-hit-area-min)] w-full flex-col items-start gap-0.5 rounded-md border border-border px-3 py-2 text-start hover:bg-surface-hover"
        >
            <span className="text-body font-semibold text-fg">{workspaceName}</span>
            <span className="text-meta text-fg-muted">{t('workspace.current.switch')}</span>
        </button>
    );
}
