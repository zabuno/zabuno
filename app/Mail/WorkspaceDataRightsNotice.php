<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Veri hakkı bildirimi — FF-226 (`docs/138` §6).
 *
 * TEK MAILABLE, İKİ OLAY: arşiv hazır ve silme planlandı. Cümleler
 * çağıranın katalogdan çözdüğü dizelerdir; Mailable ne katalog ne
 * yapılandırma bilir (`SupportRequestAcknowledged` ile aynı desen).
 * İki ayrı sınıf olsaydı, aynı düz metin şablonu iki kez yazılırdı.
 */
final class WorkspaceDataRightsNotice extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{subject:string,greeting:string,body:string,detail:?string,action:?string,note:?string}  $text
     */
    public function __construct(
        public readonly array $text,
        public readonly ?string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->text['subject']);
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.workspace-data-rights-notice',
            with: [
                'greeting' => $this->text['greeting'],
                'body' => $this->text['body'],
                'detail' => $this->text['detail'],
                'action' => $this->text['action'],
                'actionUrl' => $this->actionUrl,
                'note' => $this->text['note'],
            ],
        );
    }
}
