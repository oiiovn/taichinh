<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_insight_feedback', function (Blueprint $table) {
            $table->string('category', 64)->nullable()->after('reason_code')->comment('too_long, too_generic, tone_issue, off_focus, want_more_specific');
            $table->text('feedback_text')->nullable()->after('category');
            $table->text('edited_narrative')->nullable()->after('feedback_text')->comment('Bản user chỉnh khi consent học');
            $table->json('context_snapshot')->nullable()->after('edited_narrative')->comment('structural_state, priority_mode, brain_mode');
        });
    }

    public function down(): void
    {
        Schema::table('financial_insight_feedback', function (Blueprint $table) {
            $table->dropColumn(['category', 'feedback_text', 'edited_narrative', 'context_snapshot']);
        });
    }
};
