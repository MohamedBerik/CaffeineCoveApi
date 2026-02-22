<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public static function issue(
        int $productId,
        int $qty,
        string $referenceType,
        int $referenceId,
        ?int $userId = null
    ): void {
        if ($qty <= 0) {
            throw new \InvalidArgumentException("Quantity must be > 0");
        }

        $product = Product::lockForUpdate()->findOrFail($productId);

        $onHand = (int) $product->stock_quantity;

        if ($onHand < $qty) {
            abort(422, "Insufficient stock for product {$product->id}");
        }

        $product->decrement('stock_quantity', $qty);

        StockMovement::create([
            'product_id'     => $product->id,
            'type'           => 'out',
            'quantity'       => $qty,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'created_by'     => $userId,
        ]);
    }

    public static function receive(
        int $productId,
        int $qty,
        string $referenceType,
        int $referenceId,
        ?int $userId = null
    ): void {
        if ($qty <= 0) {
            throw new \InvalidArgumentException("Quantity must be > 0");
        }

        $product = Product::lockForUpdate()->findOrFail($productId);

        $product->increment('stock_quantity', $qty);

        StockMovement::create([
            'product_id'     => $product->id,
            'type'           => 'in',
            'quantity'       => $qty,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'created_by'     => $userId,
        ]);
    }
}
