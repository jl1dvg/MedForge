<?php
namespace Helpers;

class SearchBuilder {
    public static function filtersFromRequest(array $req): array {
        return [
            'q' => trim($req['q'] ?? ''),
            'code_type' => $req['code_type'] ?? '',
            'superbill' => $req['superbill'] ?? '',
            'active' => !empty($req['active']) ? 1 : 0,
            'reportable' => !empty($req['reportable']) ? 1 : 0,
            'financial_reporting' => !empty($req['financial_reporting']) ? 1 : 0,
        ];
    }
}