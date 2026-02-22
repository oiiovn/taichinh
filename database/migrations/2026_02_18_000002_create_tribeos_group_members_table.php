<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tribeos_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribeos_group_id')->constrained('tribeos_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('member'); // owner, admin, member
            $table->timestamps();
            $table->unique(['tribeos_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tribeos_group_members');
    }
};
