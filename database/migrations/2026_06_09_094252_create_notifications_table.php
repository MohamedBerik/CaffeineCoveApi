<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();

            $table->unsignedBigInteger('user_id');

            $table->string('title');
            $table->text('message')->nullable();

            $table->string('type')->default('info');
            $table->string('priority')->default('medium');

            $table->json('data')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('company_id');
            $table->index('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}
