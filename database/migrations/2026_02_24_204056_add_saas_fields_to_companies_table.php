
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('name');
            }

            if (!Schema::hasColumn('companies', 'status')) {
                $table->string('status')->default('trial')->after('slug');
                // trial | active | suspended
            }

            if (!Schema::hasColumn('companies', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('companies', 'branding')) {
                $table->json('branding')->nullable()->after('trial_ends_at');
                // { "app_name": "...", "logo_url": "...", "primary_color": "..." }
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'branding')) $table->dropColumn('branding');
            if (Schema::hasColumn('companies', 'trial_ends_at')) $table->dropColumn('trial_ends_at');
            if (Schema::hasColumn('companies', 'status')) $table->dropColumn('status');
            if (Schema::hasColumn('companies', 'slug')) $table->dropUnique(['slug']);
            if (Schema::hasColumn('companies', 'slug')) $table->dropColumn('slug');
        });
    }
};
