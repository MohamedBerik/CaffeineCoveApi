<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBillingProductionFieldsToTables extends Migration
{
    public function up()
    {
        // ✅ إضافة حقول للـ subscriptions
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'grace_period_ends_at')) {
                $table->timestamp('grace_period_ends_at')->nullable()->after('ends_at');
            }
            if (!Schema::hasColumn('subscriptions', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->after('payment_token');
            }
            if (!Schema::hasColumn('subscriptions', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->before('ends_at');
            }
            if (!Schema::hasColumn('subscriptions', 'payment_gateway')) {
                $table->string('payment_gateway')->default('paymob')->after('status');
            }
        });

        // ✅ إضافة حقل transaction_id للـ webhook_logs
        Schema::table('webhook_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('webhook_logs', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->after('order_id');
            }
        });

        // ✅ إضافة جدول failed_jobs (Dead Letter Queue)
        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        // ✅ إضافة حقول للـ billing_invoices
        Schema::table('billing_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_invoices', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('status');
            }
            if (!Schema::hasColumn('billing_invoices', 'due_date')) {
                $table->timestamp('due_date')->nullable()->after('paid_at');
            }
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'grace_period_ends_at',
                'transaction_id',
                'starts_at',
                'payment_gateway',
            ]);
        });

        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropColumn(['transaction_id']);
        });

        Schema::dropIfExists('failed_jobs');

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'due_date']);
        });
    }
}
