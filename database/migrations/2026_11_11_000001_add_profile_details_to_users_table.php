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
            $table->string('gender', 20)->nullable()->after('password');
            $table->date('birthdate')->nullable()->after('gender');
            $table->string('country', 100)->nullable()->after('birthdate');
            $table->string('province', 100)->nullable()->after('country');
            $table->string('city_municipality', 100)->nullable()->after('province');
            $table->string('barangay', 100)->nullable()->after('city_municipality');
            $table->string('street', 255)->nullable()->after('barangay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'birthdate',
                'country',
                'province',
                'city_municipality',
                'barangay',
                'street',
            ]);
        });
    }
};
