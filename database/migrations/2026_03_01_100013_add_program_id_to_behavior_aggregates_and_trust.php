<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('behavior_programs')) {
            return;
        }

        // Trust: global (program_id null) + per-program
        if (Schema::hasTable('behavior_trust_gradients')) {
            Schema::table('behavior_trust_gradients', function (Blueprint $table) {
                if (! Schema::hasColumn('behavior_trust_gradients', 'program_id')) {
                    $table->foreignId('program_id')->nullable()->after('user_id')->constrained('behavior_programs')->nullOnDelete();
                }
            });
            try {
                Schema::table('behavior_trust_gradients', function (Blueprint $table) {
                    $table->dropUnique(['user_id']);
                });
            } catch (\Throwable $e) {
            }
            Schema::table('behavior_trust_gradients', function (Blueprint $table) {
                $table->unique(['user_id', 'program_id']);
            });
        }

        // Temporal: per (user, program, period)
        if (Schema::hasTable('behavior_temporal_aggregates')) {
            Schema::table('behavior_temporal_aggregates', function (Blueprint $table) {
                if (! Schema::hasColumn('behavior_temporal_aggregates', 'program_id')) {
                    $table->foreignId('program_id')->nullable()->after('user_id')->constrained('behavior_programs')->nullOnDelete();
                }
            });
            Schema::table('behavior_temporal_aggregates', function (Blueprint $table) {
                $table->unique(['user_id', 'program_id', 'period_start', 'period_end'], 'behavior_temporal_user_program_period');
            });
        }

        // Cognitive: per (user, program, date)
        if (Schema::hasTable('behavior_cognitive_snapshots')) {
            Schema::table('behavior_cognitive_snapshots', function (Blueprint $table) {
                if (! Schema::hasColumn('behavior_cognitive_snapshots', 'program_id')) {
                    $table->foreignId('program_id')->nullable()->after('user_id')->constrained('behavior_programs')->nullOnDelete();
                }
            });
            try {
                Schema::table('behavior_cognitive_snapshots', function (Blueprint $table) {
                    $table->dropUnique(['user_id', 'snapshot_date']);
                });
            } catch (\Throwable $e) {
            }
            Schema::table('behavior_cognitive_snapshots', function (Blueprint $table) {
                $table->unique(['user_id', 'program_id', 'snapshot_date'], 'behavior_cognitive_user_program_date');
            });
        }

        // Recovery: per (user, program)
        if (Schema::hasTable('behavior_recovery_state')) {
            Schema::table('behavior_recovery_state', function (Blueprint $table) {
                if (! Schema::hasColumn('behavior_recovery_state', 'program_id')) {
                    $table->foreignId('program_id')->nullable()->after('user_id')->constrained('behavior_programs')->nullOnDelete();
                }
            });
            try {
                Schema::table('behavior_recovery_state', function (Blueprint $table) {
                    $table->dropUnique(['user_id']);
                });
            } catch (\Throwable $e) {
            }
            Schema::table('behavior_recovery_state', function (Blueprint $table) {
                $table->unique(['user_id', 'program_id'], 'behavior_recovery_user_program');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('behavior_trust_gradients') && Schema::hasColumn('behavior_trust_gradients', 'program_id')) {
            Schema::table('behavior_trust_gradients', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'program_id']);
                $table->dropForeign(['program_id']);
            });
            Schema::table('behavior_trust_gradients', function (Blueprint $table) {
                $table->unique('user_id');
            });
        }
        if (Schema::hasTable('behavior_temporal_aggregates') && Schema::hasColumn('behavior_temporal_aggregates', 'program_id')) {
            Schema::table('behavior_temporal_aggregates', function (Blueprint $table) {
                $table->dropUnique('behavior_temporal_user_program_period');
                $table->dropForeign(['program_id']);
            });
        }
        if (Schema::hasTable('behavior_cognitive_snapshots') && Schema::hasColumn('behavior_cognitive_snapshots', 'program_id')) {
            Schema::table('behavior_cognitive_snapshots', function (Blueprint $table) {
                $table->dropUnique('behavior_cognitive_user_program_date');
                $table->dropForeign(['program_id']);
            });
            Schema::table('behavior_cognitive_snapshots', function (Blueprint $table) {
                $table->unique(['user_id', 'snapshot_date']);
            });
        }
        if (Schema::hasTable('behavior_recovery_state') && Schema::hasColumn('behavior_recovery_state', 'program_id')) {
            Schema::table('behavior_recovery_state', function (Blueprint $table) {
                $table->dropUnique('behavior_recovery_user_program');
                $table->dropForeign(['program_id']);
            });
            Schema::table('behavior_recovery_state', function (Blueprint $table) {
                $table->unique('user_id');
            });
        }
    }
};
