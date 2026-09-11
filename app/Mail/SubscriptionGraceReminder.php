<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Ödemesiz süre hatırlatması — `docs/107` Faz 1.3, `docs/134`.
 *
 * MAILABLE NE KATALOG NE YAPILANDIRMA BİLİR: cümleler çağıranın katalogdan
 * çözdüğü dizelerdir, tarihler çağıranın kayıttan okuduğu tarihlerdir
 * (`WorkspaceDataRightsNotice` ile aynı desen). Buraya bir `SiteText`
 * çağrısı girseydi, gövde zamanlayıcının o anki diline bağlanırdı.
 */
final class SubscriptionGraceReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{subject:string,greeting:string,body:string,periodEnded:string,graceEnds:string,safety:string,action:string}  $text
     */
    public function __construct(
        public readonly array $text,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->text['subject']);
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.subscription-grace-reminder',
            with: [
                'greeting' => $this->text['greeting'],
                'body' => $this->text['body'],
                'periodEnded' => $this->text['periodEnded'],
                'graceEnds' => $this->text['graceEnds'],
                'safety' => $this->text['safety'],
                'action' => $this->text['action'],
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
