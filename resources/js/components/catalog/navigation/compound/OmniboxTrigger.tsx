import { IconButton } from '../micro/IconButton';

export type OmniboxTriggerProps = {
    label: string;
    onClick: () => void;
    className?: string;
};

/**
 * Compound: an icon-only launcher for the shell-level omnibox, built on
 * Micro/Navigation/IconButton. Route/fetch-agnostic — the caller owns what
 * opening it means.
 *
 * Bu bileşen eskiden `AiCommandTrigger` idi. Açtığı şey değişti: bağlı bir
 * AI sağlayıcısı olmadan çalışan bir "AI komut merkezi" değil, gerçekten iş
 * gören deterministik bir omnibox (`docs/65`).
 */
export function OmniboxTrigger({ label, onClick, className }: OmniboxTriggerProps) {
    return (
        <IconButton
            icon={
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    className="h-5 w-5"
                >
                    {/*
                        Simge de değişti: pırıltı AI vaat ediyordu. Bir
                        büyeç, kutunun gerçekten yaptığı şeyi söyler.
                    */}
                    <path
                        fillRule="evenodd"
                        d="M9 3.5a5.5 5.5 0 1 0 3.4 9.83l3.64 3.63a1 1 0 0 0 1.41-1.41l-3.63-3.64A5.5 5.5 0 0 0 9 3.5Zm-3.5 5.5a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0Z"
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
