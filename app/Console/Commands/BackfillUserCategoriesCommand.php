<?php

namespace App\Console\Commands;

use App\Models\SystemCategory;
use App\Models\User;
use App\Models\UserCategory;
use Illuminate\Console\Command;

class BackfillUserCategoriesCommand extends Command
{
    protected $signature = 'user:backfill-categories
                            {--user= : Chỉ backfill cho user_id cụ thể}
                            {--dry-run : Chỉ in ra, không tạo}';

    protected $description = 'Tạo user_categories từ system_categories cho user chưa có danh mục (để dropdown phân loại không trống).';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $userId = $this->option('user');

        if (SystemCategory::count() === 0) {
            $this->error('Chưa có system_categories. Chạy: php artisan db:seed --class=SystemCategorySeeder');
            return self::FAILURE;
        }

        $query = User::query();
        if ($userId !== null) {
            $query->where('id', (int) $userId);
        }

        $users = $query->get();
        $systemCategories = SystemCategory::orderBy('type')->orderBy('id')->get();

        $created = 0;
        $skipped = 0;
        $usersAffected = 0;

        foreach ($users as $user) {
            $existing = UserCategory::where('user_id', $user->id)->count();
            if ($existing > 0) {
                $skipped++;
                continue;
            }

            $usersAffected++;
            if (! $dryRun) {
                foreach ($systemCategories as $sc) {
                    UserCategory::create([
                        'user_id' => $user->id,
                        'name' => $sc->name,
                        'type' => $sc->type,
                        'based_on_system_category_id' => $sc->id,
                    ]);
                    $created++;
                }
            } else {
                $created += $systemCategories->count();
            }
        }

        $this->info($dryRun
            ? "Dry-run: sẽ tạo {$created} user_categories cho {$usersAffected} user, bỏ qua {$skipped} user đã có danh mục."
            : "Đã tạo {$created} user_categories cho {$usersAffected} user. Bỏ qua {$skipped} user đã có danh mục."
        );

        return self::SUCCESS;
    }
}
