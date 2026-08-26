import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react/pure';

/**
 * `@testing-library/react` normalde her testten sonra kendini temizler; bu
 * davranış onun `index` girişinde kayıtlıdır. Yerel sarmalayıcı `pure`
 * girişinden dışa aktardığı için (bkz. `resources/js/test/testing-library.tsx`)
 * o kayıt kaybolur ve testler birbirinin DOM'unu görmeye başlar. Temizlik
 * burada açıkça geri konur.
 */
afterEach(() => {
    cleanup();
});
