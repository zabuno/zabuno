import { createElement, type ReactElement, type ReactNode } from 'react';
import { ThemeProvider } from 'flowbite-react';
import { render as baseRender, type RenderOptions } from '@testing-library/react/pure';

import { zabunoFlowbiteApplyTheme, zabunoFlowbiteTheme } from '../design-system/flowbite-theme';

/**
 * Testler, kullanıcının gördüğü temayı görsün.
 *
 * Flowbite'ın teması bir React context'i üzerinden dağıtılır ve uygulamada
 * `ThemeRoot` sağlar. Bileşen testleri ise bileşeni doğrudan render eder,
 * yani sağlayıcı olmadan Flowbite'ın VARSAYILAN temasını görür — ham palet ve
 * sabit yükseklik dâhil.
 *
 * Bu, testin doğruladığı görünümün kullanıcının gördüğü görünüm olmamasına yol
 * açar; bir testin söyleyebileceği en sessiz yalan budur. Bu modül
 * `@testing-library/react` yerine geçer (bkz. `vite.config.ts` alias) ve
 * `render`'ı tema sağlayıcısıyla sarar. Test dosyaları değişmez.
 *
 * Çağıran kendi `wrapper`'ını verirse ona saygı duyulur; yalnız tema
 * sağlayıcısının içine yerleşir.
 */
export * from '@testing-library/react/pure';

function ThemedWrapper({ children }: { children: ReactNode }) {
    return createElement(
        ThemeProvider,
        { theme: zabunoFlowbiteTheme, applyTheme: zabunoFlowbiteApplyTheme },
        children,
    );
}

export function render(ui: ReactElement, options?: RenderOptions) {
    const CallerWrapper = options?.wrapper;

    const wrapper = CallerWrapper
        ? ({ children }: { children: ReactNode }) =>
              createElement(ThemedWrapper, null, createElement(CallerWrapper, null, children))
        : ThemedWrapper;

    return baseRender(ui, { ...options, wrapper });
}
