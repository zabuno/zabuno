import { t } from '../../../i18n/workspace';
import type { RequestFailure } from '../../../lib/requestFailure';

/**
 * Bir arıza sınıfının KULLANICI cümlesi — `docs/67`.
 *
 * Bu eşleme `BrandEditForm`'un içinde özeldi ve orada kaldığı sürece diğer
 * formlar tek bir "bir şeyler ters gitti" cümlesine düşmeye devam ediyordu.
 * Dört form aynı sözlüğü kullanacaksa sözlük tek yerde durmalı: ayrı ayrı
 * kopyalansaydı biri düzeldiğinde diğerleri eski hâlinde kalırdı.
 */
export function messageForFailure(failure: RequestFailure): string {
    switch (failure.kind) {
        case 'permission':
            return t('workspace.form.error.permission');
        case 'conflict':
            return t('workspace.form.error.conflict');
        case 'notFound':
            return t('workspace.form.error.notFound');
        case 'network':
            return t('workspace.form.error.network');
        case 'validation':
        case 'server':
        default:
            /*
                İzleme kimliği VARSA gösterilir, yoksa UYDURULMAZ. Destek
                ekibinin arayamayacağı bir kod, hiç kod olmamasından kötüdür:
                kullanıcı onu okur, iletir ve karşılığında "böyle bir kayıt
                yok" cevabını alır.
            */
            return failure.correlationId !== null
                ? t('workspace.form.error.serverWithId', { id: failure.correlationId })
                : t('workspace.form.error.server');
    }
}
