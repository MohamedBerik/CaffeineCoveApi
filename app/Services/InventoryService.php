<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Tenant;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Issue stock (decrease inventory)
     */
    public static function issue(
        int $productId,
        int $qty,
        string $referenceType,
        int $referenceId,
        ?int $userId = null,
        ?string $notes = null
    ): StockMovement {
        if ($qty <= 0) {
            throw new \InvalidArgumentException("Quantity must be > 0");
        }

        return DB::transaction(function () use ($productId, $qty, $referenceType, $referenceId, $userId, $notes) {
            // ✅ الـ Scope هيضيف company_id تلقائيًا
            $product = Product::lockForUpdate()->findOrFail($productId);

            $onHand = (int) $product->stock_quantity;

            if ($onHand < $qty) {
                throw new \Exception("Insufficient stock for product {$product->title_en}. Available: {$onHand}, Requested: {$qty}");
            }

            $product->decrement('stock_quantity', $qty);

            return StockMovement::create([
                'company_id'     => $product->company_id,
                'product_id'     => $product->id,
                'type'           => StockMovement::TYPE_OUT,
                'quantity'       => $qty,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'created_by'     => $userId,
                'notes'          => $notes,
            ]);
        });
    }

    /**
     * Receive stock (increase inventory)
     */
    public static function receive(
        int $productId,
        int $qty,
        string $referenceType,
        int $referenceId,
        ?int $userId = null,
        ?string $notes = null
    ): StockMovement {
        if ($qty <= 0) {
            throw new \InvalidArgumentException("Quantity must be > 0");
        }

        return DB::transaction(function () use ($productId, $qty, $referenceType, $referenceId, $userId, $notes) {
            // ✅ الـ Scope هيضيف company_id تلقائيًا
            $product = Product::lockForUpdate()->findOrFail($productId);

            $product->increment('stock_quantity', $qty);

            return StockMovement::create([
                'company_id'     => $product->company_id,
                'product_id'     => $product->id,
                'type'           => StockMovement::TYPE_IN,
                'quantity'       => $qty,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'created_by'     => $userId,
                'notes'          => $notes,
            ]);
        });
    }

    /**
     * Transfer stock between products
     */
    public static function transfer(
        int $fromProductId,
        int $toProductId,
        int $qty,
        ?int $userId = null,
        ?string $notes = null
    ): array {
        if ($qty <= 0) {
            throw new \InvalidArgumentException("Quantity must be > 0");
        }

        if ($fromProductId === $toProductId) {
            throw new \InvalidArgumentException("Source and destination products cannot be the same");
        }

        return DB::transaction(function () use ($fromProductId, $toProductId, $qty, $userId, $notes) {
            $fromProduct = Product::lockForUpdate()->findOrFail($fromProductId);
            $toProduct = Product::lockForUpdate()->findOrFail($toProductId);

            // ✅ التحقق من نفس الشركة
            if ($fromProduct->company_id !== $toProduct->company_id) {
                throw new \Exception("Cannot transfer stock between different companies");
            }

            $onHand = (int) $fromProduct->stock_quantity;

            if ($onHand < $qty) {
                throw new \Exception("Insufficient stock for product {$fromProduct->title_en}");
            }

            $fromProduct->decrement('stock_quantity', $qty);
            $toProduct->increment('stock_quantity', $qty);

            $outMovement = StockMovement::create([
                'company_id'     => $fromProduct->company_id,
                'product_id'     => $fromProduct->id,
                'type'           => StockMovement::TYPE_OUT,
                'quantity'       => $qty,
                'reference_type' => 'transfer',
                'reference_id'   => $toProduct->id,
                'created_by'     => $userId,
                'notes'          => $notes ?: "Transferred to {$toProduct->title_en}",
            ]);

            $inMovement = StockMovement::create([
                'company_id'     => $toProduct->company_id,
                'product_id'     => $toProduct->id,
                'type'           => StockMovement::TYPE_IN,
                'quantity'       => $qty,
                'reference_type' => 'transfer',
                'reference_id'   => $fromProduct->id,
                'created_by'     => $userId,
                'notes'          => $notes ?: "Transferred from {$fromProduct->title_en}",
            ]);

            // ✅ تسجيل النشاط
            ActivityLogger::log(
                $fromProduct->company_id,
                $userId ? \App\Models\User::find($userId) : null,
                'inventory.transfer',
                Product::class,
                $fromProduct->id,
                [
                    'from_product' => $fromProduct->title_en,
                    'to_product' => $toProduct->title_en,
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
        int $productId,
        int $adjustment,
        string $reason,
        ?int $userId = null
    ): StockMovement {
        if ($adjustment === 0) {
            throw new \InvalidArgumentException("Adjustment cannot be zero");
        }

        return DB::transaction(function () use ($productId, $adjustment, $reason, $userId) {
            $product = Product::lockForUpdate()->findOrFail($productId);

            $newStock = $product->stock_quantity + $adjustment;

            if ($newStock < 0) {
                throw new \Exception("Adjustment would result in negative stock");
            }

            $product->stock_quantity = $newStock;
            $product->save();

            $type = $adjustment > 0 ? StockMovement::TYPE_IN : StockMovement::TYPE_OUT;
            $quantity = abs($adjustment);

            $movement = StockMovement::create([
                'company_id'     => $product->company_id,
                'product_id'     => $product->id,
                'type'           => $type,
                'quantity'       => $quantity,
                'reference_type' => 'adjustment',
                'reference_id'   => $product->id,
                'created_by'     => $userId,
                'notes'          => "Adjustment: {$reason}",
            ]);

            ActivityLogger::log(
                $product->company_id,
                $userId ? \App\Models\User::find($userId) : null,
                'inventory.adjust',
                Product::class,
                $product->id,
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
     * Get current stock for a product
     */
    public static function getStock(int $productId): int
    {
        $product = Product::query()->findOrFail($productId);
        return (int) $product->stock_quantity;
    }

    /**
     * Get stock movements for a product
     */
    public static function getMovements(int $productId, ?int $limit = 50): array
    {
        $movements = StockMovement::query()
            ->where('product_id', $productId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return [
            'product_id' => $productId,
            'movements' => $movements,
            'total_in' => $movements->where('type', StockMovement::TYPE_IN)->sum('quantity'),
            'total_out' => $movements->where('type', StockMovement::TYPE_OUT)->sum('quantity'),
        ];
    }

    /**
     * Get low stock products
     */
    public static function getLowStockProducts(?int $threshold = 10): array
    {
        $companyId = Tenant::id();

        if (!$companyId) {
            return [];
        }

        return Product::query()
            ->where('stock_quantity', '<=', $threshold)
            ->where('stock_quantity', '>', 0)
            ->orderBy('stock_quantity', 'asc')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->title_en,
                'current_stock' => $p->stock_quantity,
                'threshold' => $threshold,
            ])
            ->toArray();
    }

    /**
     * Get out of stock products
     */
    public static function getOutOfStockProducts(): array
    {
        $companyId = Tenant::id();

        if (!$companyId) {
            return [];
        }

        return Product::query()
            ->where('stock_quantity', '<=', 0)
            ->orderBy('title_en', 'asc')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->title_en,
                'current_stock' => 0,
            ])
            ->toArray();
    }
}
