import { createTranslator } from './translator';
import { overridesFor } from './generated-overrides';

const en = {
    'theme.group_label': 'Theme',
    'theme.system': 'System',
    'theme.light': 'Light',
    'theme.dark': 'Dark',
} as const;

type TranslationKey = keyof typeof en;

export const t: (key: TranslationKey) => string = createTranslator(en, overridesFor('theme'));

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const themeTranslations: Record<string, string> = en;
