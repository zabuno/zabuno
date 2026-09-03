<?php

declare(strict_types=1);

return [

    /*
     * Gelen iletişim mesajının bildirileceği adres — `docs/93`.
     *
     * BOŞ bırakılırsa hiçbir e-posta gönderilmez ve mesaj yalnız saklanır.
     * Bu bir hata değildir: sağlayıcı yokken "gönderildi" damgası atmak,
     * sahibin gelmeyen bir e-postayı beklemesine yol açardı.
     */
    'notify' => env('CONTACT_NOTIFICATION_ADDRESS'),

];
