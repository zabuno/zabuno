import type { ReactNode } from 'react';

/**
 * CİHAZA ÖZGÜ ÇİZİCİYİ ÇAĞIRAN TEK YER — `docs/151`.
 *
 * Paylaşılan sayfa bir çizici alır (`renderQueue`, `renderLibrary`,
 * `renderMemberList`, `renderRatingList`) ve onu bağlamla çağırır. Çağrı
 * doğrudan JSX'in içinde yapıldığında `react-hooks/refs` haklı bir uyarı
 * veriyor: bağlam nesnesi, içinde `useRef` okuyan geri çağrılar taşıyabilir
 * ve "çizim sırasında bir işleve nesne geçirmek" o işlevin ref okuduğu
 * anlamına GELEBİLİR.
 *
 * Uyarıyı susturmak yerine çağrıyı doğru yere taşıdık: bağlam artık bir
 * BİLEŞENE PROP olarak geçiyor ve işlev o bileşenin çiziminde çalışıyor.
 * Davranış aynı, ama sınır React'in kendi kurallarının içinde kalıyor —
 * ve bir gün gerçek bir ref sızarsa kural yine konuşabilir.
 *
 * Bileşen CİHAZ TANIMAZ ve tanımamalı: adında da türünde de "desktop"
 * geçmez. Yarın mobil giriş kendi çizicisini verirse aynı sözleşme çalışır.
 */
export function DeviceSurface<T>({
    render,
    context,
}: {
    render: (context: T) => ReactNode;
    context: T;
}): ReactNode {
    return <>{render(context)}</>;
}

export default DeviceSurface;
