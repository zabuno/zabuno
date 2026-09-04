import { createTranslator } from './translator';
import { overridesFor } from './generated-overrides';

const en = {
    'theme.group_label': 'Theme',
    'theme.system': 'System',
    'theme.light': 'Light',
    'theme.dark': 'Dark',
    /*
        YOĞUNLUK, tema ile aynı ailede durur: ikisi de kişiseldir, ikisi de
        tarayıcıda saklanır ve ikisi de "bu ekran bana nasıl görünsün"
        sorusunu cevaplar (FF-128).

        Etiketler boyut değil İŞ anlatır. "Rahat" parmakla dokunulacak bir
        tablette, "sıkışık" gün boyu tabloya bakan bir masaüstünde işe
        yarar; kullanıcı piksel seçmez, çalışma biçimini seçer.
    */
    'density.group_label': 'Density',
    'density.comfortable': 'Comfortable',
    'density.standard': 'Standard',
    'density.compact': 'Compact',
} as const;

type TranslationKey = keyof typeof en;

export const t: (key: TranslationKey) => string = createTranslator(en, overridesFor('theme'));

/** Bu alanın İngilizce kaynak kataloğu — PO/MO/JSON zincirinin girdisi (CORE-08). */
export const themeTranslations: Record<string, string> = en;
