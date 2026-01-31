<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;

class FinanceDashboardController extends Controller
{
    public function index()
    {
        // إجمالي المبيعات (فواتير)
        $totalSales = Invoice::sum('total');

        // إجمالي المقبوض من العملاء
        $totalCollected = Payment::sum('amount');

        // إجمالي المشتريات
        $totalPurchases = PurchaseOrder::sum('total');

        // إجمالي المدفوع للموردين
        $totalPaidToSuppliers = SupplierPayment::sum('amount');

        // فلوس لسه لك على العملاء
        $receivables = Invoice::whereIn('status', ['unpaid', 'partial'])
            ->sum('total')
            -
            Payment::sum('amount');

        // فلوس لسه عليك للموردين
        $payables = PurchaseOrder::whereNotIn('status', ['cancelled'])
            ->sum('total')
            -
            SupplierPayment::sum('amount');

        return response()->json([
            'total_sales' => $totalSales,
            'total_collected' => $totalCollected,
            'total_purchases' => $totalPurchases,
            'total_paid_to_suppliers' => $totalPaidToSuppliers,
            'receivables' => max($receivables, 0),
            'payables' => max($payables, 0),
        ]);
    }
}
