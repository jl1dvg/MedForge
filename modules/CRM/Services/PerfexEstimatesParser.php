<?php

namespace Modules\CRM\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Modules\CRM\Models\ProposalModel;

class PerfexEstimatesParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);

        $estimates = $this->extractEstimates($xpath);
        $summary = $this->extractSummary($xpath);

        $totals = [
            'estimates' => count($estimates),
            'by_status' => $this->countStatuses($estimates),
        ];

        return [
            'summary' => $summary,
            'estimates' => $estimates,
            'totals' => $totals,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractSummary(DOMXPath $xpath): array
    {
        $cards = [];
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' quick-top-stats ')]//button");

        if (!$nodes) {
            return $cards;
        }

        foreach ($nodes as $button) {
            if (!$button instanceof DOMElement) {
                continue;
            }

            $labelNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' tw-inline-flex ')]", $button)?->item(0);
            $percentageNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' tw-text-xs ')]", $button)?->item(0);
            $countNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' tw-font-semibold ')]", $button)?->item(0);

            $fraction = $this->parseFraction($countNode?->textContent ?? '');

            $cards[] = [
                'label' => $this->normalizeText($labelNode?->textContent ?? ''),
                'percentage' => $this->parsePercentage($percentageNode?->textContent ?? ''),
                'count' => $fraction['count'],
                'total' => $fraction['total'],
            ];
        }

        return $cards;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractEstimates(DOMXPath $xpath): array
    {
        $rows = $xpath->query("//table[@id='estimates']//tbody/tr");
        $estimates = [];

        if (!$rows) {
            return $estimates;
        }

        foreach ($rows as $row) {
            if (!$row instanceof DOMElement) {
                continue;
            }

            $cells = $row->getElementsByTagName('td');
            if ($cells->length < 10) {
                continue;
            }

            $estimateLink = $cells->item(0)?->getElementsByTagName('a')->item(0);
            $customerLink = $cells->item(3)?->getElementsByTagName('a')->item(0);
            $projectLink = $cells->item(4)?->getElementsByTagName('a')->item(0);
            $statusLabel = $cells->item(9)?->getElementsByTagName('span')->item(0);

            $statusText = $this->normalizeText($statusLabel?->textContent ?? $cells->item(9)?->textContent ?? '');
            $estimate = [
                'estimate_number' => $estimateLink?->textContent ?? $cells->item(0)?->textContent ?? '',
                'estimate_url' => $estimateLink?->getAttribute('href'),
                'amount' => $this->parseAmount($cells->item(1)?->textContent ?? ''),
                'total_tax' => $this->parseAmount($cells->item(2)?->textContent ?? ''),
                'customer' => $this->normalizeText($customerLink?->textContent ?? $cells->item(3)?->textContent ?? ''),
                'customer_url' => $customerLink?->getAttribute('href'),
                'project' => $this->normalizeText($projectLink?->textContent ?? $cells->item(4)?->textContent ?? ''),
                'project_url' => $projectLink?->getAttribute('href'),
                'tags' => $this->extractTags($cells->item(5)),
                'date' => trim((string) $cells->item(6)?->textContent),
                'expiry_date' => trim((string) $cells->item(7)?->textContent),
                'reference' => $this->normalizeText($cells->item(8)?->textContent ?? ''),
                'status' => $statusText,
                'crm_status' => $this->mapStatus($statusText),
                'status_classes' => $statusLabel?->getAttribute('class') ?? '',
            ];

            $estimates[] = $estimate;
        }

        return $estimates;
    }

    /**
     * @return array<string>
     */
    private function extractTags(?DOMElement $cell): array
    {
        if (!$cell) {
            return [];
        }

        $tags = [];
        foreach ($cell->getElementsByTagName('span') as $span) {
            $class = $span->getAttribute('class');
            if (str_contains($class, 'label')) {
                $tags[] = $this->normalizeText($span->textContent ?? '');
            }
        }

        return $tags;
    }

    /**
     * @return array<string, int>
     */
    private function countStatuses(array $estimates): array
    {
        $counts = [];

        foreach ($estimates as $estimate) {
            $status = $estimate['status'] ?? '';
            if ($status === '') {
                continue;
            }
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array{count: ?int, total: ?int}
     */
    private function parseFraction(string $text): array
    {
        if (preg_match('~(\d+)\s*/\s*(\d+)~', $text, $matches)) {
            return [
                'count' => (int) $matches[1],
                'total' => (int) $matches[2],
            ];
        }

        return ['count' => null, 'total' => null];
    }

    private function parsePercentage(string $text): ?float
    {
        if (preg_match('~([-+]?\d+(?:\.\d+)?)~', $text, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    private function parseAmount(string $text): ?float
    {
        $clean = preg_replace('/[^0-9,.\-]/', '', $text);
        if ($clean === null || $clean === '') {
            return null;
        }

        $normalized = str_replace(',', '', $clean);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function mapStatus(string $status): string
    {
        $normalized = strtolower($status);

        if (str_contains($normalized, 'draft') || str_contains($normalized, 'borrador')) {
            return ProposalModel::STATUS_DRAFT;
        }

        if (str_contains($normalized, 'sent') || str_contains($normalized, 'enviado')) {
            return ProposalModel::STATUS_SENT;
        }

        if (str_contains($normalized, 'accepted') || str_contains($normalized, 'acept')) {
            return ProposalModel::STATUS_ACCEPTED;
        }

        if (str_contains($normalized, 'declined') || str_contains($normalized, 'rechaz')) {
            return ProposalModel::STATUS_DECLINED;
        }

        if (str_contains($normalized, 'expired') || str_contains($normalized, 'vencid')) {
            return ProposalModel::STATUS_EXPIRED;
        }

        return ProposalModel::STATUS_DRAFT;
    }
}
