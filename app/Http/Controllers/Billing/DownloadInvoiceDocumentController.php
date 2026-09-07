<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Billing\Port\InvoiceDocumentRendererPort;
use App\Application\Billing\UseCase\ManageInvoices;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Belgenin KÂĞIT hâli — indirilebilir A4 PDF (docs/130 §K6).
 *
 * Başka bir çalışma alanının belgesi kendi adresinden bile okunamaz: kimlik
 * hem yetkiden hem sahiplikten geçer ve ikisi de başarısızsa cevap 404'tür.
 */
final class DownloadInvoiceDocumentController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ManageInvoices $invoices,
        private readonly InvoiceDocumentRendererPort $renderer,
    ) {}

    public function __invoke(Request $request, int $workspace, int $invoice): Response
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::BillingView, $workspace)) {
            return response('Not Found.', 404);
        }

        $document = $this->invoices->findFor($workspace, $invoice);

        if ($document === null) {
            return response('Not Found.', 404);
        }

        try {
            $bytes = $this->renderer->render($document);
        } catch (RuntimeException $exception) {
            report($exception);

            return response('Invoice document generation failed.', 500);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->kind->value.'-'.$document->documentNumber.'.pdf"',
        ]);
    }
}
