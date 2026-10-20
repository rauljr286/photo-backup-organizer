<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('private_password')->nullable();
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->boolean('is_private')->default(false);
            $table->index(['user_id', 'is_private']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_private']);
            $table->dropColumn('is_private');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('private_password');
        });
    }
};
