<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tribeos_post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribeos_post_id')->constrained('tribeos_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32); // tham_gia, da_dong, theo_doi
            $table->timestamps();
            $table->unique(['tribeos_post_id', 'user_id'], 'tribeos_post_reactions_post_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tribeos_post_reactions');
    }
};
