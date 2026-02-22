<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tribeos_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribeos_group_id')->constrained('tribeos_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 64); // post_created, member_added, wallet_added, event_created...
            $table->string('subject_type', 128)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::table('tribeos_activities', function (Blueprint $table) {
            $table->index(['tribeos_group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tribeos_activities');
    }
};
