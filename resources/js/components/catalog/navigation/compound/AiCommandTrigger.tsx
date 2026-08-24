import { IconButton } from '../micro/IconButton';

export type AiCommandTriggerProps = {
    label: string;
    onClick: () => void;
    className?: string;
};

/**
 * Compound: an icon-only launcher for the shell-level AI command center,
 * built on Micro/Navigation/IconButton. Route/fetch-agnostic — the caller
 * owns what opening the command center means.
 */
export function AiCommandTrigger({ label, onClick, className }: AiCommandTriggerProps) {
    return (
        <IconButton
            icon={
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    className="h-5 w-5"
                >
                    <path
                        fillRule="evenodd"
                        d="M10 2a1 1 0 0 1 1 1v1.05a7.002 7.002 0 0 1 5.95 5.95H18a1 1 0 1 1 0 2h-1.05a7.002 7.002 0 0 1-5.95 5.95V19a1 1 0 1 1-2 0v-1.05a7.002 7.002 0 0 1-5.95-5.95H2a1 1 0 1 1 0-2h1.05A7.002 7.002 0 0 1 9 4.05V3a1 1 0 0 1 1-1Zm0 4a5 5 0 1 0 0 10 5 5 0 0 0 0-10Z"
                        clipRule="evenodd"
                    />
                </svg>
            }
            label={label}
            onClick={onClick}
            className={className}
        />
    );
}
