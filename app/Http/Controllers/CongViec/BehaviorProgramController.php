<?php

namespace App\Http\Controllers\CongViec;

use App\Http\Controllers\Controller;
use App\Models\BehaviorProgram;
use App\Services\BehaviorProgramProgressService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BehaviorProgramController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $programs = $user
            ? BehaviorProgram::where('user_id', $user->id)->orderByDesc('created_at')->get()
            : collect();

        return view('pages.cong-viec.programs.index', ['programs' => $programs]);
    }

    public function create(Request $request): View
    {
        return view('pages.cong-viec.programs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'daily_target_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'skip_policy' => ['nullable', 'string', 'in:abandon,allow_2_skips,reduce_difficulty'],
        ]);

        $start = Carbon::today();
        $end = $start->copy()->addDays((int) $validated['duration_days']);

        $program = new BehaviorProgram;
        $program->user_id = $user->id;
        $program->title = $validated['title'];
        $program->description = $validated['description'] ?? null;
        $program->start_date = $start;
        $program->end_date = $end;
        $program->duration_days = $validated['duration_days'];
        $program->archetype = ($validated['daily_target_count'] ?? 1) > 1 ? BehaviorProgram::ARCHETYPE_QUANTITATIVE : BehaviorProgram::ARCHETYPE_BINARY;
        $program->daily_target_count = $validated['daily_target_count'] ?? 1;
        $program->skip_policy = $validated['skip_policy'] ?? BehaviorProgram::SKIP_POLICY_ALLOW_2;
        $program->escalation_rule = $program->skip_policy;
        $program->status = BehaviorProgram::STATUS_ACTIVE;
        $program->difficulty_level = 1;
        $program->save();

        return redirect()->route('cong-viec.programs.show', $program->id)->with('success', 'Đã tạo chương trình.');
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $program = BehaviorProgram::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $progress = app(BehaviorProgramProgressService::class)->getProgressForUi($request->user()->id, $program->id);
        $tasks = $program->tasks()->orderBy('due_date')->orderBy('due_time')->get();

        return view('pages.cong-viec.programs.show', [
            'program' => $program,
            'progress' => $progress,
            'tasks' => $tasks,
        ]);
    }
}
