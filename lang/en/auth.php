<?php

declare(strict_types=1);

return [

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many attempts. Please try again in :seconds seconds.',

    // Posta taşıyıcısı arızalıyken verilen tek cevap: girilen adresin
    // kayıtlı olup olmadığını ele vermez ve "gönderdik" demez.
    'password_reset_unavailable' => 'We cannot send password reset emails right now. Please try again later.',

    'registration_generic_failure' => 'We were unable to complete this registration. Please check your details and try again.',
    'terms_required' => 'Please accept the Terms of Service to create an account.',
    // Aydınlatma metni ONAYLANMAZ, okunduğu BEYAN EDİLİR (2026/347).
    'privacy_acknowledgement_required' => 'Please confirm that you have read the Privacy Policy to create an account.',
    'verify_email_subject' => 'Verify your email address',
    'verify_email_greeting' => 'Hello :name,',
    'verify_email_body' => 'Please click the button below to verify your email address.',
    'verify_email_action' => 'Verify Email Address',
    'verify_email_footer' => 'If you did not create an account, no further action is required.',

    'team_invitation_subject' => 'You are invited to join :workspace',
    'team_invitation_greeting' => 'You have been invited to join :workspace as a(n) :role.',
    'team_invitation_action' => 'View Invitation',
    'team_invitation_expires' => 'This invitation link expires on :date.',

];
