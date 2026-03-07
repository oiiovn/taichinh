<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            $table->json('merchant_vector')->nullable()->after('merchant_group');
        });

        Schema::create('merchant_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_key', 255)->unique();
            $table->json('vector')->comment('Embedding vector (float[])');
            $table->unsignedSmallInteger('dimension')->default(0);
            $table->timestamps();
        });
        Schema::table('merchant_embeddings', function (Blueprint $table) {
            $table->index('merchant_key');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_history', function (Blueprint $table) {
            $table->dropColumn('merchant_vector');
        });
        Schema::dropIfExists('merchant_embeddings');
    }
};
