<?php

declare(strict_types=1);

return [
    'failed' => 'Bu bilgiler kayıtlarımızla eşleşmiyor.',
    'password' => 'Girilen şifre yanlış.',
    'throttle' => 'Çok fazla deneme yapıldı. Lütfen :seconds saniye sonra tekrar deneyin.',
    // Posta taşıyıcısı arızalıyken verilen tek cevap: girilen adresin
    // kayıtlı olup olmadığını ele vermez ve "gönderdik" demez.
    'password_reset_unavailable' => 'Şu anda şifre sıfırlama e-postası gönderemiyoruz. Lütfen daha sonra tekrar deneyin.',

    'registration_generic_failure' => 'Kaydınızı tamamlayamadık. Lütfen bilgilerinizi kontrol edip tekrar deneyin.',
    'terms_required' => 'Hesap oluşturmak için lütfen Hizmet Koşullarını kabul edin.',
    // Aydınlatma metni ONAYLANMAZ, okunduğu BEYAN EDİLİR (2026/347).
    'privacy_acknowledgement_required' => 'Hesap oluşturmak için lütfen Gizlilik Politikasını okuduğunuzu onaylayın.',
    'verify_email_subject' => 'E-posta adresinizi doğrulayın',
    'verify_email_greeting' => 'Merhaba :name,',
    'verify_email_body' => 'E-posta adresinizi doğrulamak için lütfen aşağıdaki düğmeye tıklayın.',
    'verify_email_action' => 'E-posta Adresini Doğrula',
    'verify_email_footer' => 'Hesabı siz oluşturmadıysanız herhangi bir işlem yapmanız gerekmez.',
    'team_invitation_subject' => ':workspace çalışma alanına davet edildiniz',
    'team_invitation_greeting' => ':workspace çalışma alanına :role rolüyle katılmaya davet edildiniz.',
    'team_invitation_action' => 'Daveti Görüntüle',
    'team_invitation_expires' => 'Bu davet bağlantısının süresi :date tarihinde dolar.',
];
