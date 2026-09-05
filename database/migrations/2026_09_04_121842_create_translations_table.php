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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('locale_id')
                ->constrained('locales')
                ->cascadeOnDelete();
            $table->string('translation_key');
            $table->text('content');
            $table->timestamps();
            $table->unique([
                'locale_id',
                'translation_key',
            ]);
            $table->index([
                'locale_id',
                'id',
            ]);
            $table->index('translation_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
