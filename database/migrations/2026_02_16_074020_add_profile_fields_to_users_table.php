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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->text('bio')->nullable()->after('phone');
            $table->string('facebook_url')->nullable()->after('bio');
            $table->string('x_url')->nullable()->after('facebook_url');
            $table->string('linkedin_url')->nullable()->after('x_url');
            $table->string('instagram_url')->nullable()->after('linkedin_url');
            $table->string('country')->nullable()->after('instagram_url');
            $table->string('city_state')->nullable()->after('country');
            $table->string('postal_code')->nullable()->after('city_state');
            $table->string('tax_id')->nullable()->after('postal_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'bio', 'facebook_url', 'x_url', 'linkedin_url', 'instagram_url',
                'country', 'city_state', 'postal_code', 'tax_id'
            ]);
        });
    }
};
