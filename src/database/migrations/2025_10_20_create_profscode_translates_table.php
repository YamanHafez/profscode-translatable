<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profscode_translates', function (Blueprint $table) {
            $table->id();
            $table->uuid('translatable_id');
            $table->string('translatable_type');
            $table->string('locale', 5);
            $table->string('key');
            $table->longText('value')->nullable();
            $table->index(["translatable_id", "translatable_type"]);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profscode_translates');
    }
};
