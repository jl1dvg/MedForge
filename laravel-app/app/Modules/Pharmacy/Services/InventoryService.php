<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Services;

use App\Models\PharmacyInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    public function getLowStockItems(): Collection
    {
        return PharmacyInventory::where('estado', 'activo')
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->orderBy('stock')
            ->get();
    }

    public function adjustStock(PharmacyInventory $item, int $delta, string $reason): void
    {
        $newStock = max(0, $item->stock + $delta);
        $item->update(['stock' => $newStock]);

        Log::info('pharmacy.inventory.stock_adjusted', [
            'inventory_id' => $item->id,
            'nombre'       => $item->nombre,
            'delta'        => $delta,
            'new_stock'    => $newStock,
            'reason'       => $reason,
        ]);
    }
}
