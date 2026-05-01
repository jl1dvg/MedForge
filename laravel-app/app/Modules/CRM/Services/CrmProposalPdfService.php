<?php

declare(strict_types=1);

namespace App\Modules\CRM\Services;

use App\Modules\Reporting\Services\PdfRenderer;

class CrmProposalPdfService
{
    public function __construct(
        private readonly CrmProposalService $proposals = new CrmProposalService(),
        private readonly PdfRenderer $renderer = new PdfRenderer(),
    ) {
    }

    /**
     * @param array<string,mixed>|null $proposal
     * @return array{content:string,filename:string,proposal:array<string,mixed>}
     */
    public function generate(int $proposalId, ?array $proposal = null): array
    {
        $proposal ??= $this->proposals->find($proposalId);
        $html = view('crm.proposals.pdf', [
            'proposal' => $proposal,
            'items' => $proposal['items'] ?? [],
            'publicUrl' => $proposal['public_url'] ?? $this->proposals->publicUrl($proposal),
        ])->render();

        $filename = $this->proposals->filename($proposal);
        $content = $this->renderer->renderHtml($html, [
            'filename' => $filename,
            'destination' => 'S',
            'mpdf' => [
                'format' => 'A4',
                'margin_top' => 12,
                'margin_bottom' => 12,
                'margin_left' => 10,
                'margin_right' => 10,
            ],
        ]);

        return [
            'content' => $content,
            'filename' => $filename,
            'proposal' => $proposal,
        ];
    }
}
