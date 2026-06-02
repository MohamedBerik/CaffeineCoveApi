<?php

namespace App\Services;

use App\Models\Supply;
use App\Models\StockMovement;
use App\Services\Tenant;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Issue stock (decrease inventory)
     */
    public static function issue(
        int $supplyId,
        int $qty,
        string $referenceType,
        int $referenceId,
        ?int $userId = null,
        ?string $notes = null  // سيتم تجاهله لعدم وجود عمود notes
    ): StockMovement {
        if ($qty <= 0) {
            throw new \InvalidArgumentException("Quantity must be > 0");
        }

        return DB::transaction(function () use ($supplyId, $qty, $referenceType, $referenceId, $userId) {
            // ✅ الـ Scope هيضيف company_id تلقائيًا
            $supply = Supply::lockForUpdate()->findOrFail($supplyId);

            $onHand = (int) $supply->stock_quantity;

            if ($onHand < $qty) {
                throw new \Exception("Insufficient stock for supply {$supply->name}. Available: {$onHand}, Requested: {$qty}");
            }

            $supply->decrement('stock_quantity', $qty);

            return StockMovement::create([
                'company_id'     => $supply->company_id,
                'branch_id'      => Tenant::branchId(),
                'supply_id'      => $supply->id,
                'type'           => StockMovement::TYPE_OUT,
                'quantity'       => $qty,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'created_by'     => $userId,
            ]);
        });
    }

    /**
     * Receive stock (increase inventory)
     */
    public static function receive(
        int $supplyId,
        int $qty,
        string $referenceType,
        int $referenceId,
        ?int $userId = null,
        ?string $notes = null  // سيتم تجاهله
    ): StockMovement {
        if ($qty <= 0) {
            throw new \InvalidArgumentException("Quantity must be > 0");
        }

        return DB::transaction(function () use ($supplyId, $qty, $referenceType, $referenceId, $userId) {
            $supply = Supply::lockForUpdate()->findOrFail($supplyId);

            $supply->increment('stock_quantity', $qty);

            return StockMovement::create([
                'company_id'     => $supply->company_id,
                'branch_id'      => Tenant::branchId(),
                'supply_id'      => $supply->id,
                'type'           => StockMovement::TYPE_IN,
                'quantity'       => $qty,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'created_by'     => $userId,
            ]);
        });
    }

    /**
     * Transfer stock between supplies
     */
    public static function transfer(
        int $fromSupplyId,
        int $toSupplyId,
        int $qty,
        ?int $userId = null,
        ?string $notes = null  // سيتم تجاهله
    ): array {
        if ($qty <= 0) {
            throw new \InvalidArgumentException("Quantity must be > 0");
        }

        if ($fromSupplyId === $toSupplyId) {
            throw new \InvalidArgumentException("Source and destination supplies cannot be the same");
        }

        return DB::transaction(function () use ($fromSupplyId, $toSupplyId, $qty, $userId) {
            $fromSupply = Supply::lockForUpdate()->findOrFail($fromSupplyId);
            $toSupply = Supply::lockForUpdate()->findOrFail($toSupplyId);

            // ✅ التحقق من نفس الشركة
            if ($fromSupply->company_id !== $toSupply->company_id) {
                throw new \Exception("Cannot transfer stock between different companies");
            }

            $onHand = (int) $fromSupply->stock_quantity;

            if ($onHand < $qty) {
                throw new \Exception("Insufficient stock for supply {$fromSupply->name}");
            }

            $fromSupply->decrement('stock_quantity', $qty);
            $toSupply->increment('stock_quantity', $qty);

            $branchId = Tenant::branchId();

            $outMovement = StockMovement::create([
                'company_id'     => $fromSupply->company_id,
                'branch_id'      => $branchId,
                'supply_id'      => $fromSupply->id,
                'type'           => StockMovement::TYPE_OUT,
                'quantity'       => $qty,
                'reference_type' => 'transfer',
                'reference_id'   => $toSupply->id,
                'created_by'     => $userId,
            ]);

            $inMovement = StockMovement::create([
                'company_id'     => $toSupply->company_id,
                'branch_id'      => $branchId,
                'supply_id'      => $toSupply->id,
                'type'           => StockMovement::TYPE_IN,
                'quantity'       => $qty,
                'reference_type' => 'transfer',
                'reference_id'   => $fromSupply->id,
                'created_by'     => $userId,
            ]);

            // ✅ تسجيل النشاط
            ActivityLogger::log(
                $fromSupply->company_id,
                $userId ? \App\Models\User::find($userId) : null,
                'inventory.transfer',
                Supply::class,
                $fromSupply->id,
                [
                    'from_supply' => $fromSupply->name,
                    'to_supply' => $toSupply->name,
                    'quantity' => $qty,
                ]
            );

            return [
                'out_movement' => $outMovement,
                'in_movement' => $inMovement,
            ];
        });
    }

    /**
     * Adjust stock (positive or negative)
     */
    public static function adjust(
        int $supplyId,
        int $adjustment,
        string $reason,
        ?int $userId = null
    ): StockMovement {
        if ($adjustment === 0) {
            throw new \InvalidArgumentException("Adjustment cannot be zero");
        }

        return DB::transaction(function () use ($supplyId, $adjustment, $reason, $userId) {
            $supply = Supply::lockForUpdate()->findOrFail($supplyId);

            $newStock = $supply->stock_quantity + $adjustment;

            if ($newStock < 0) {
                throw new \Exception("Adjustment would result in negative stock");
            }

            $supply->stock_quantity = $newStock;
            $supply->save();

            $type = $adjustment > 0 ? StockMovement::TYPE_IN : StockMovement::TYPE_OUT;
            $quantity = abs($adjustment);

            $movement = StockMovement::create([
                'company_id'     => $supply->company_id,
                'branch_id'      => Tenant::branchId(),
                'supply_id'      => $supply->id,
                'type'           => $type,
                'quantity'       => $quantity,
                'reference_type' => 'adjustment',
                'reference_id'   => $supply->id,
                'created_by'     => $userId,
            ]);

            ActivityLogger::log(
                $supply->company_id,
                $userId ? \App\Models\User::find($userId) : null,
                'inventory.adjust',
                Supply::class,
                $supply->id,
                [
                    'adjustment' => $adjustment,
                    'reason' => $reason,
                    'new_stock' => $newStock,
                ]
            );

            return $movement;
        });
    }

    /**
     * Get current stock for a supply
     */
    public static function getStock(int $supplyId): int
    {
        $supply = Supply::query()->findOrFail($supplyId);
        return (int) $supply->stock_quantity;
    }

    /**
     * Get stock movements for a supply
     */
    public static function getMovements(int $supplyId, ?int $limit = 50): array
    {
        $movements = StockMovement::query()
            ->where('supply_id', $supplyId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return [
            'supply_id' => $supplyId,
            'movements' => $movements,
            'total_in' => $movements->where('type', StockMovement::TYPE_IN)->sum('quantity'),
            'total_out' => $movements->where('type', StockMovement::TYPE_OUT)->sum('quantity'),
        ];
    }

    /**
     * Get low stock supplies
     */
    public static function getLowStockSupplies(?int $threshold = 10): array
    {
        $companyId = Tenant::id();

        if (!$companyId) {
            return [];
        }

        return Supply::query()
            ->where('stock_quantity', '<=', $threshold)
            ->where('stock_quantity', '>', 0)
            ->orderBy('stock_quantity', 'asc')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'current_stock' => $s->stock_quantity,
                'threshold' => $threshold,
            ])
            ->toArray();
    }

    /**
     * Get out of stock supplies
     */
    public static function getOutOfStockSupplies(): array
    {
        $companyId = Tenant::id();

        if (!$companyId) {
            return [];
        }

        return Supply::query()
            ->where('stock_quantity', '<=', 0)
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'current_stock' => 0,
            ])
            ->toArray();
    }
}
