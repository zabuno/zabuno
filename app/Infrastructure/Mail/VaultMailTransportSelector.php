<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Domain\Platform\Credential\CredentialProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Kasadan (yoksa env'den) Mailgun kimliğini çözer ve sürücüyü seçer.
 *
 * Resolver zaten KASA > env önceliğini uygular; burada yalnız çözülen değeri
 * `services.mailgun.*` config'ine yazıp `mailgun` sürücüsünü seçiyoruz.
 * Zorunlu alanlar (domain + secret) hiçbir kaynaktan gelmiyorsa varsayılan
 * sürücü (`mail.default` — genelde `log`) kalır: kimlik yokken gönderici
 * uydurmayız.
 */
final readonly class VaultMailTransportSelector implements MailTransportSelectorPort
{
    public function __construct(
        private CredentialResolverPort $resolver,
        private ConfigRepository $config,
    ) {}

    public function select(): string
    {
        $creds = $this->resolver->resolve(CredentialProvider::Mailgun);

        if (($creds['domain'] ?? '') !== '' && ($creds['secret'] ?? '') !== '') {
            $this->config->set('services.mailgun.domain', $creds['domain']);
            $this->config->set('services.mailgun.secret', $creds['secret']);
            $this->config->set('services.mailgun.endpoint', $creds['endpoint'] ?? 'api.mailgun.net');

            return 'mailgun';
        }

        return (string) $this->config->get('mail.default');
    }
}
