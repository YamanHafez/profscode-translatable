<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for the profscode_translates table.
 * 
 * This table stores a persistent copy of all translations for easier database-side
 * searching and as a fallback/backup for the file-based system.
 */
return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * @return void
     */
    public function up(): void
    {
        Schema::create('profscode_translates', function (Blueprint $table) {
            $table->id();
            // translatable_id holds the UUID or model ID, linking to the original model instance
            $table->uuid('translatable_id');
            // translatable_type holds the class name of the associated model
            $table->string('translatable_type');
            // locale holds the language code (e.g., 'en', 'tr', 'ar')
            $table->string('locale', 5);
            // key is the name of the translatable attribute (e.g., 'title', 'description')
            $table->string('key');
            // value is the actual translated content
            $table->longText('value')->nullable();

            // Index for faster lookups when retrieving translations for a specific model instance
            $table->index(["translatable_id", "translatable_type"]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     * 
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('profscode_translates');
    }
};
