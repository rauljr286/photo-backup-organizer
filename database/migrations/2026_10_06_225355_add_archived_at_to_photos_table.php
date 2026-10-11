<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('deleted_at');
            $table->index(['user_id', 'archived_at']);
        });

        DB::table('photos')
            ->whereNotNull('deleted_at')
            ->update(['archived_at' => DB::raw('deleted_at'), 'deleted_at' => null]);
    }

    public function down(): void
    {
        DB::table('photos')
            ->whereNotNull('archived_at')
            ->update(['deleted_at' => DB::raw('archived_at'), 'archived_at' => null]);

        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
