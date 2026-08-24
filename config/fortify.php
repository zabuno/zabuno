<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

return [

    'guard' => 'web',

    'middleware' => ['web'],

    'auth_middleware' => 'auth',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'views' => true,

    /*
     * S1-WP02C CORE-01 destination: authenticated, verified traffic lands
     * on the workspace app shell (GET /app), not the foundation status page.
     */
    'home' => '/app',

    'prefix' => '',

    'domain' => null,

    'lowercase_usernames' => false,

    'limiters' => [
        'login' => 'login',
        'verification' => 'verification',
        'passkeys' => null,
    ],

    'paths' => [
        'login' => null,
        'logout' => null,
        'register' => null,
        'verification' => [
            'notice' => null,
            'verify' => null,
            'send' => null,
        ],
    ],

    'redirects' => [
        'login' => null,
        'logout' => '/',
        'register' => null,
        'email-verification' => '/',
    ],

    /*
     * S1-WP02A CORE-01 hard scope: registration + email verification.
     * S1-WP02D adds password reset. 2FA, passkeys, profile/password
     * updates remain explicit non-goals (docs/33 §3) and stay disabled.
     */
    'features' => [
        Features::registration(),
        Features::emailVerification(),
        Features::resetPasswords(),
    ],

];
