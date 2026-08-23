const en = {
    'theme.group_label': 'Theme',
    'theme.system': 'System',
    'theme.light': 'Light',
    'theme.dark': 'Dark',
} as const;

type TranslationKey = keyof typeof en;

export function t(key: TranslationKey): string {
    return en[key] ?? key;
}
