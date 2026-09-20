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
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->text('alt_text')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->string('remote_path', 500)->nullable()->comment('Path on the configured cloud backup disk, if backed up');
            $table->timestamp('backed_up_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'deleted_at']);
            $table->index(['user_id', 'taken_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
