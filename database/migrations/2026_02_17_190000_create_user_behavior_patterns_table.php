<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'ubp_user_merchant_dir_bucket';

    public function up(): void
    {
        if (Schema::hasTable('user_behavior_patterns')) {
            $this->addUniqueIndexIfMissing();
            return;
        }

        Schema::create('user_behavior_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('merchant_group', 255)->index();
            $table->string('direction', 10);
            $table->string('amount_bucket', 20);
            $table->foreignId('user_category_id')->nullable()->constrained('user_categories')->nullOnDelete();
            $table->unsignedInteger('usage_count')->default(1);
            $table->float('confidence_score')->default(0.1);
            $table->timestamps();

            $table->unique(['user_id', 'merchant_group', 'direction', 'amount_bucket'], self::UNIQUE_INDEX);
        });
    }

    private function addUniqueIndexIfMissing(): void
    {
        $indexes = DB::select("SHOW INDEX FROM user_behavior_patterns WHERE Key_name = ?", [self::UNIQUE_INDEX]);
        if (empty($indexes)) {
            Schema::table('user_behavior_patterns', function (Blueprint $table) {
                $table->unique(['user_id', 'merchant_group', 'direction', 'amount_bucket'], self::UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_behavior_patterns');
    }
};
