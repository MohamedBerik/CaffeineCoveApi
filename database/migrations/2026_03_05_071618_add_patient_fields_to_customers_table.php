<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            // NOTE: نضيف فقط اللي ناقص عشان ما يحصلش conflicts مع أي تعديلات سابقة
            if (!Schema::hasColumn('customers', 'phone')) {
                $table->string('phone', 50)->nullable()->after('email');
            }

            if (!Schema::hasColumn('customers', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('customers', 'gender')) {
                $table->enum('gender', ['male', 'female'])->nullable()->after('date_of_birth');
            }

            if (!Schema::hasColumn('customers', 'address')) {
                $table->string('address', 255)->nullable()->after('gender');
            }

            if (!Schema::hasColumn('customers', 'notes')) {
                $table->text('notes')->nullable()->after('address');
            }

            // اختياري: رقم ملف/كود داخلي للعيادة
            if (!Schema::hasColumn('customers', 'patient_code')) {
                $table->string('patient_code', 50)->nullable()->after('name');
                $table->index('patient_code');
            }
        });
    }

    public function down(): void
    {
        // Safe rollback (اختياري) - خلي بالك: MySQL يسمح، SQLite ممكن يحتاج rebuild.
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            foreach (['patient_code', 'phone', 'date_of_birth', 'gender', 'address', 'notes'] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
