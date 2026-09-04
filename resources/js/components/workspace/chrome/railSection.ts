import type React from 'react';
import type { ReactNode } from 'react';

/**
 * Rayın dibindeki sabit blokta duran hedef (FF-127).
 *
 * Tip AYRI bir modülde durur, `DesktopChrome`'un içinde değil: `WorkspaceApp`
 * bu tipi kullanır ve masaüstü kabuğundan `import type` yapsaydı okuyan biri
 * haklı olarak "telefon masaüstü kabuğunu mu indiriyor?" diye sorardı
 * (`docs/54`). Tip modülü hiçbir çalışma zamanı baytı taşımaz ve soruyu
 * tamamen ortadan kaldırır.
 */
export type RailSection = {
    key: string;
    label: string;
    href: string;
    icon?: ReactNode;
    active?: boolean;
    onSelect?: (event: React.MouseEvent<HTMLAnchorElement>) => void;
};
