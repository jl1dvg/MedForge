<?php

declare(strict_types=1);

namespace App\Modules\Codes\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class CodeHistoryService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $codeId): array
    {
        $row = DB::table('tarifario_2014')
            ->where('id', $codeId)
            ->first();

        $snapshot = $row !== null ? (array) $row : [];

        $prices = DB::table('prices')
            ->where('code_id', $codeId)
            ->get(['level_key', 'price'])
            ->map(static fn (object $priceRow): array => [
                'level_key' => (string) ($priceRow->level_key ?? ''),
                'price' => (float) ($priceRow->price ?? 0),
            ])
            ->all();

        $snapshot['prices'] = $prices;

        return $snapshot;
    }

    /**
     * @param array<string, mixed>|null $snapshot
     */
    public function saveHistory(string $action, string $user, int $codeId, ?array $snapshot = null): void
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('codes_history')) {
                return;
            }

            DB::table('codes_history')->insert([
                'action_at' => now(),
                'action_type' => $action,
                'user' => $user !== '' ? $user : 'system',
                'code_id' => $codeId > 0 ? $codeId : null,
                'snapshot' => json_encode(
                    $snapshot ?? $this->snapshot($codeId),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);
        } catch (Throwable) {
            // Best-effort logging only: no bloquea flujo principal.
        }
    }
}

