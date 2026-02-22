<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tribeos_group_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tribeos_group_id')->constrained('tribeos_groups')->cascadeOnDelete();
            $table->foreignId('inviter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('member');
            $table->string('status', 32)->default('pending'); // pending, accepted, rejected
            $table->timestamps();
            $table->unique(['tribeos_group_id', 'invitee_user_id'], 'tribeos_invitations_group_invitee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tribeos_group_invitations');
    }
};
