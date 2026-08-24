/**
 * Typed contract for the shell-level AI command center while there is no
 * real AI gateway wired up. `status` is always 'disconnected' in this
 * package — a future package that connects a real gateway introduces its
 * own status values rather than this file guessing at them.
 */
export type AiAssistStatus = 'disconnected';

export type AiAssistQuickAction = {
    key: 'dashboard' | 'locations' | 'menu' | 'publication';
    label: string;
};

export const AI_ASSIST_STATUS: AiAssistStatus = 'disconnected';
