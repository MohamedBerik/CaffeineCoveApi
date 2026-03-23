<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReminderFieldsToAppointmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('reminder_status')->default('pending')->after('status');
            $table->timestamp('last_reminder_at')->nullable()->after('reminder_status');
            $table->timestamp('next_reminder_at')->nullable()->after('last_reminder_at');
            $table->unsignedInteger('reminder_sent_count')->default(0)->after('next_reminder_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            //
        });
    }
}
