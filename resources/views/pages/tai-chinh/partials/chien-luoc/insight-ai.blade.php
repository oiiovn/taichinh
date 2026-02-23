@php
    $opt = $projectionOptimization ?? null;
    $narrativeResult = $narrativeResult ?? null;
    $rootCauses = $opt['root_causes'] ?? [];
    $state = $financialState ?? ($opt['financial_state'] ?? null);
    $mode = $priorityMode ?? ($opt['priority_mode'] ?? null);
    $frame = $contextualFrame ?? ($opt['contextual_frame'] ?? null);
    $objective = $objective ?? ($opt['objective'] ?? null);
    $strategicGuidance = $opt['strategic_guidance'] ?? [];
    $guidanceLines = $strategicGuidance['guidance_lines'] ?? [];
    $insufficientData = $insufficientData ?? false;
    $onboardingNarrative = $onboardingNarrative ?? null;
    $dualAxis = $dualAxis ?? null;
    $dataConfidence = $dualAxis['data_confidence'] ?? null;
    $financialHealth = $dualAxis['financial_health'] ?? null;
    $hasNarrative = $narrativeResult && !empty($narrativeResult['narrative']);
    $survivalProtocolActive = $survival_protocol_active ?? ($insightPayload['survival_protocol_active'] ?? false);
    $survivalDirective = $insightPayload['survival_directive'] ?? null;
    $hasContent = $insufficientData || $hasNarrative || !empty($rootCauses) || $state || $mode || !empty($guidanceLines);
    $maturityStage = $narrativeResult['maturity_stage'] ?? (isset($projection['sources']) ? ($projection['sources']['maturity_stage'] ?? null) : null);
    $trajectory = $narrativeResult['trajectory'] ?? (isset($projection['sources']) ? ($projection['sources']['trajectory'] ?? null) : null);
    $pillWarningKeys = ['limited', 'critical', 'fragile', 'crisis', 'defensive'];
    $isWarningPill = function($arr) use ($pillWarningKeys) {
        if (!$arr || !isset($arr['key'])) return false;
        return in_array($arr['key'], $pillWarningKeys, true);
    };
    $brainModeKey = isset($insightPayload['cognitive_input']['brain_mode']['key']) ? $insightPayload['cognitive_input']['brain_mode']['key'] : 'fragile_coaching';
    $brainModeLabel = isset($insightPayload['cognitive_input']['brain_mode']['label']) ? $insightPayload['cognitive_input']['brain_mode']['label'] : null;
    $uiTheme = isset($insightPayload['cognitive_input']['brain_mode']['ui_theme']) ? $insightPayload['cognitive_input']['brain_mode']['ui_theme'] : 'fragile';
    $isCrisis = $brainModeKey === 'crisis_directive';
    $isStableGrowth = $brainModeKey === 'stable_growth';
    $isBehaviorMismatch = $brainModeKey === 'behavior_mismatch_warning';
    $sectionClass = 'px-3 pt-0 pb-2 dark:text-white transition-colors insight-mode-' . $uiTheme;
    $narrativeMemory = isset($insightPayload['cognitive_input']['narrative_memory']) ? $insightPayload['cognitive_input']['narrative_memory'] : null;
    $trustLevel = $narrativeMemory['trust_level'] ?? null;
    $hasNarrativeMemory = $narrativeMemory && (isset($narrativeMemory['behavior_evolution_summary']) || isset($narrativeMemory['strategy_transition_summary']));
    $insightLevelLabel = 'Dựa trên số liệu hiện tại';
    $insightLevelHint = 'Insight dựa trên snapshot và đề xuất lần này.';
    if ($insufficientData) {
        $insightLevelLabel = 'Đang chờ dữ liệu';
        $insightLevelHint = 'Liên kết tài khoản và có giao dịch để insight chính xác hơn.';
    } elseif ($hasNarrativeMemory && $trustLevel === 'high') {
        $insightLevelLabel = 'Hiểu bạn tốt';
        $insightLevelHint = 'Đã có hành trình hành vi và tuân thủ đề xuất — insight điều chỉnh theo bạn.';
    } elseif ($hasNarrativeMemory && $trustLevel === 'medium') {
        $insightLevelLabel = 'Đang hiểu bạn';
        $insightLevelHint = 'Có lịch sử so sánh kỳ và phản hồi — insight đang học cách bạn phản hồi.';
    } elseif ($hasNarrativeMemory && $trustLevel === 'low') {
        $insightLevelLabel = 'Đang học cách bạn phản hồi';
        $insightLevelHint = 'Gợi ý nhẹ nhàng, không ép — hệ thống điều chỉnh theo phản hồi của bạn.';
    } elseif ($hasNarrativeMemory) {
        $insightLevelLabel = 'Có hành trình';
        $insightLevelHint = 'So sánh nhiều kỳ và pattern — insight có chiều sâu hơn.';
    }
    $stateKey = isset($state['key']) ? $state['key'] : null;
    $modeKey = isset($mode['key']) ? $mode['key'] : null;
    $narrativeText = $narrativeResult['narrative'] ?? '';
    $hasDebtFocus = ($stateKey && in_array($stateKey, ['debt_spiral_risk', 'debt_burden', 'debt_focus'], true)) || (stripos($narrativeText, 'trả nợ') !== false) || (stripos($narrativeText, 'ưu tiên nợ') !== false);
    $feedbackCategoryOptions = \App\Models\FinancialInsightFeedback::categoryOptionsForContext($brainModeKey, $stateKey, $modeKey, $survivalProtocolActive, $hasDebtFocus);
    $improveQuestion = \App\Models\FinancialInsightFeedback::improveQuestionForContext($brainModeKey, $modeKey, $survivalProtocolActive);
@endphp
<section class="{{ $sectionClass }}" data-brain-mode="{{ $brainModeKey }}" data-ui-theme="{{ $uiTheme }}">
    <div class="min-w-0 w-full">
        <div class="flex flex-wrap items-baseline gap-2 gap-y-1 mb-4">
            <h2 class="text-theme-xl font-semibold text-gray-900 dark:text-white">🧠 Insight</h2>
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400" title="{{ $insightLevelHint }}">{{ $insightLevelLabel }}</span>
        </div>
        @if($brainModeLabel && ($hasContent || $survivalProtocolActive))
            <div class="mb-4">
                <span class="text-xs font-medium uppercase tracking-wider brain-mode-badge @if($isCrisis) text-red-700 dark:text-red-300 @elseif($isStableGrowth) text-emerald-600 dark:text-emerald-400 @else text-gray-500 dark:text-gray-400 @endif" data-brain-mode="{{ $brainModeKey }}" title="Chế độ insight">{{ $survivalProtocolActive ? 'Giao thức sinh tồn' : $brainModeLabel }}</span>
            </div>
        @endif
        <div class="space-y-6">
                @if($survivalProtocolActive && $survivalDirective)
                    <div class="rounded-xl border-2 border-red-200 bg-red-50/80 p-4 dark:border-red-800 dark:bg-red-900/30">
                        @if(!empty($survivalDirective['subtitle']))
                            <p class="mt-2 text-theme-sm text-red-700 dark:text-red-300">{{ $survivalDirective['subtitle'] }}</p>
                        @endif
                        @if(!empty($survivalDirective['action_7_days']))
                            <p class="mt-4 mb-1.5 text-theme-xs font-semibold uppercase tracking-wider text-red-600 dark:text-red-400">Hành động trong 7 ngày</p>
                            <ul class="list-disc pl-5 space-y-1 text-theme-sm text-red-800 dark:text-red-200">
                                @foreach($survivalDirective['action_7_days'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if(!empty($survivalDirective['goal_30_45_days']))
                            <p class="mt-4 mb-1.5 text-theme-xs font-semibold uppercase tracking-wider text-red-600 dark:text-red-400">Mục tiêu 30–45 ngày</p>
                            <p class="text-theme-sm text-red-800 dark:text-red-200">{{ $survivalDirective['goal_30_45_days'] }}</p>
                        @endif
                    </div>
                @elseif($insufficientData)
                    <p class="text-base leading-7 text-gray-800 dark:text-gray-100">{{ $onboardingNarrative ?? 'Chúng tôi chưa có đủ dữ liệu giao dịch và tài khoản để đưa ra đánh giá tài chính. Hãy liên kết tài khoản và để hệ thống thu thập dữ liệu vài tháng, sau đó insight sẽ chính xác hơn.' }}</p>
                @elseif($hasContent)
                    {{-- 1 narrative thống nhất (Narrative Builder hoặc Cognitive) --}}
                    @if($hasNarrative)
                        @php
                            $raw = $narrativeResult['narrative'];
                            $withBold = preg_replace_callback('/\*\*(.+?)\*\*/s', function($m) {
                                $t = $m[1];
                                $isMoney = preg_match('/[\d.\s]+₫|[\d.\s]+VND|[\d]{1,3}(\.[\d]{3})+\s*₫/u', $t);
                                $cls = $isMoney ? 'font-semibold text-emerald-600 dark:text-emerald-400' : 'font-semibold text-gray-900 dark:text-gray-100';
                                return '<span class="'.$cls.'">'.e($t).'</span>';
                            }, $raw);
                            $lines = array_map('trim', preg_split('/\r\n|\r|\n/', $withBold));
                            $n = count($lines);
                            $narrativeHtml = '';
                            $i = 0;
                            $introBlocks = [];
                            $block = [];
                            while ($i < $n && !preg_match('/^[-–]\s+/', $lines[$i] ?? '')) {
                                if (($lines[$i] ?? '') === '') {
                                    if (!empty($block)) { $introBlocks[] = implode(' ', $block); $block = []; }
                                } else {
                                    $block[] = $lines[$i];
                                }
                                $i++;
                            }
                            if (!empty($block)) { $introBlocks[] = implode(' ', $block); }
                            if (!empty($introBlocks)) {
                                $narrativeHtml .= '<p class="text-lg leading-7 text-gray-900 dark:text-gray-100">'.$introBlocks[0].'</p>';
                                for ($k = 1; $k < count($introBlocks); $k++) {
                                    $narrativeHtml .= '<p class="mt-3 text-base leading-7 text-gray-800 dark:text-gray-100">'.$introBlocks[$k].'</p>';
                                }
                            }
                            $allBullets = [];
                            $splitAt = null;
                            while ($i < $n) {
                                if (preg_match('/^[-–]\s+(.+)$/s', $lines[$i], $m)) {
                                    $allBullets[] = $m[1];
                                } elseif (trim($lines[$i] ?? '') !== '' && stripos($lines[$i], 'Hành động') !== false) {
                                    $splitAt = count($allBullets);
                                }
                                $i++;
                            }
                            $blocks = isset($insightPayload['cognitive_input']['brain_mode']['narrative_blocks']) ? $insightPayload['cognitive_input']['brain_mode']['narrative_blocks'] : null;
                            $showLuaChon = $blocks === null || in_array('lua_chon_cai_thien', $blocks, true);
                            if (!empty($allBullets)) {
                                if ($splitAt !== null && $splitAt < count($allBullets) && ($showLuaChon && $splitAt > 0)) {
                                    $narrativeHtml .= '<p class="mt-5 mb-1.5 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Lựa chọn cải thiện</p>';
                                    $narrativeHtml .= '<ul class="list-disc pl-5 space-y-1.5 marker:text-gray-400 text-base leading-7 text-gray-800 dark:text-gray-100 border-l-2 border-gray-200 dark:border-gray-600 ml-1">';
                                    foreach (array_slice($allBullets, 0, $splitAt) as $item) { $narrativeHtml .= '<li>'.$item.'</li>'; }
                                    $narrativeHtml .= '</ul>';
                                    $narrativeHtml .= '<p class="mt-5 mb-1.5 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Hành động ngay</p>';
                                    $narrativeHtml .= '<ul class="list-none pl-0 space-y-1.5 text-base leading-7 text-gray-800 dark:text-gray-100">';
                                    foreach (array_slice($allBullets, $splitAt) as $item) { $narrativeHtml .= '<li class="pl-4 relative before:content-[\'–\'] before:absolute before:left-0 before:text-gray-400">'.$item.'</li>'; }
                                    $narrativeHtml .= '</ul>';
                                } elseif ($splitAt !== null && $splitAt < count($allBullets)) {
                                    $narrativeHtml .= '<p class="mt-5 mb-1.5 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Hành động ngay</p>';
                                    $narrativeHtml .= '<ul class="list-none pl-0 space-y-1.5 text-base leading-7 text-gray-800 dark:text-gray-100">';
                                    foreach (array_slice($allBullets, $splitAt) as $item) { $narrativeHtml .= '<li class="pl-4 relative before:content-[\'–\'] before:absolute before:left-0 before:text-gray-400">'.$item.'</li>'; }
                                    $narrativeHtml .= '</ul>';
                                } else {
                                    $narrativeHtml .= '<ul class="mt-5 list-disc pl-5 space-y-1.5 marker:text-gray-400 text-base leading-7 text-gray-800 dark:text-gray-100">';
                                    foreach ($allBullets as $item) { $narrativeHtml .= '<li>'.$item.'</li>'; }
                                    $narrativeHtml .= '</ul>';
                                }
                            }
                            $boldCls = 'font-semibold text-gray-900 dark:text-gray-100';
                            $narrativeHtml = preg_replace('/\.\s*(Bạn có một số lựa chọn để cải thiện tình hình:)\s*\.?/u', ' <span class="'.$boldCls.'">$1</span>', $narrativeHtml);
                            $narrativeHtml = preg_replace('/\.\s*(Hành động cụ thể bạn có thể thực hiện ngay:)\s*\.?/u', ' <span class="'.$boldCls.'">$1</span>', $narrativeHtml);
                            $narrativeHtml = preg_replace('/(?<=[\s>])(Bạn có một số lựa chọn để cải thiện tình hình:)\s*\.?/u', '<span class="'.$boldCls.'">$1</span>', $narrativeHtml);
                            $narrativeHtml = preg_replace('/(?<=[\s>])(Hành động cụ thể bạn có thể thực hiện ngay:)\s*\.?/u', '<span class="'.$boldCls.'">$1</span>', $narrativeHtml);
                            $narrativeHtml = preg_replace('/(?<!\d)\.\s+(?=\S)/u', ' ', $narrativeHtml);
                            if ($narrativeHtml === '') {
                                $narrativeHtml = '<p class="text-base leading-7 text-gray-800 dark:text-gray-100">'.nl2br(e($raw)).'</p>';
                            }
                        @endphp
                        <div class="narrative-content space-y-5 brain-mode-{{ $brainModeKey }}" data-brain-mode="{{ $brainModeKey }}">{!! $narrativeHtml !!}</div>
                        @if(!empty($insightHash))
                            @php
                                $contextSnapshotForEdit = [
                                    'structural_state' => isset($state['key']) ? $state['key'] : null,
                                    'priority_mode' => isset($mode['key']) ? $mode['key'] : null,
                                    'brain_mode' => $brainModeKey,
                                ];
                            @endphp
                            <script type="application/json" id="insight-edit-payload-{{ md5($insightHash ?? '') }}">{!! json_encode([
                                'rawText' => $raw ?? '',
                                'hash' => $insightHash,
                                'contextSnapshot' => $contextSnapshotForEdit,
                                'url' => route('tai-chinh.insight-feedback'),
                                'token' => csrf_token(),
                            ]) !!}</script>
                            <div class="mt-4 flex items-center gap-2" x-data="{
                                editOpen: false,
                                rawText: '',
                                editedText: '',
                                consentOpen: false,
                                learnSending: false,
                                learnSent: false,
                                hash: '',
                                contextSnapshot: {},
                                url: '',
                                token: '',
                                init() {
                                    const el = document.getElementById('insight-edit-payload-{{ md5($insightHash ?? '') }}');
                                    if (el) {
                                        const p = JSON.parse(el.textContent);
                                        this.rawText = p.rawText ?? '';
                                        this.hash = p.hash ?? '';
                                        this.contextSnapshot = p.contextSnapshot ?? {};
                                        this.url = p.url ?? '';
                                        this.token = p.token ?? '';
                                    }
                                },
                                submitLearn() {
                                    if (this.learnSending || this.learnSent) return;
                                    this.learnSending = true;
                                    fetch(this.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.token, 'Accept': 'application/json' }, body: JSON.stringify({ insight_hash: this.hash, feedback_type: 'learn_from_edit', edited_narrative: this.editedText, context_snapshot: this.contextSnapshot }) })
                                        .then(r => r.json())
                                        .then(() => { this.learnSent = true; })
                                        .catch(() => {})
                                        .finally(() => { this.learnSending = false; });
                                }
                            }" x-cloak>
                                <button type="button" @click="editOpen = true; editedText = rawText" class="text-theme-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Chỉnh sửa insight</button>
                                <template x-teleport="body">
                                    <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="editOpen = false">
                                        <div class="w-full max-w-2xl max-h-[90vh] flex flex-col rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900" @click.outside="editOpen = false">
                                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Chỉnh sửa nội dung insight</h3>
                                            </div>
                                            <div class="flex-1 overflow-auto p-4">
                                                <textarea x-model="editedText" rows="14" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" placeholder="Nội dung insight..."></textarea>
                                            </div>
                                            <div class="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
                                                <button type="button" @click="editOpen = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300">Hủy</button>
                                                <button type="button" @click="editOpen = false; consentOpen = true" class="rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600">Lưu chỉnh sửa</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-show="consentOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="consentOpen = false">
                                        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-900 dark:text-white" @click.outside="consentOpen = false">
                                            <p class="text-base font-medium text-gray-900 dark:text-white">Bạn muốn hệ thống học theo phiên bản bạn vừa chỉnh không?</p>
                                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Nếu đồng ý, lần sau insight có thể gần với cách diễn đạt của bạn hơn.</p>
                                            <div class="mt-6 flex justify-end gap-2">
                                                <button type="button" @click="consentOpen = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300">Không</button>
                                                <button type="button" @click="submitLearn(); consentOpen = false" :disabled="learnSending" class="rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 disabled:opacity-50">Có, học theo bản này</button>
                                            </div>
                                            <p x-show="learnSent" class="mt-3 text-sm text-success-600 dark:text-success-400">Đã ghi nhận. Hệ thống sẽ học theo phiên bản bạn chỉnh.</p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        @endif
                        @if($isBehaviorMismatch && $hasNarrative)
                            <div class="mt-4 rounded-lg border border-amber-300 dark:border-amber-600 bg-amber-50/50 dark:bg-amber-900/20 px-3 py-2.5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400 mb-1">Đề xuất trước chưa phù hợp?</p>
                                <p class="text-sm text-amber-800 dark:text-amber-300">Bạn có thể thử <strong>bản nhẹ hơn</strong> trong nội dung trên (mục đề xuất thay thế).</p>
                            </div>
                        @endif
                        @if($isStableGrowth && $hasNarrative)
                            <p class="mt-3 text-xs font-medium text-emerald-600 dark:text-emerald-400">→ Gợi ý: xem mục <strong>nâng cấp hệ thống</strong> hoặc <strong>thử nghiệm</strong> phía trên.</p>
                        @endif
                        @if(!empty($narrativeResult['tactical_suggestion']) && ($narrativeResult['tactical_suggestion'] ?? '') !== '')
                            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 dark:border-blue-800 dark:bg-blue-900/25">
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-300">→ {{ $narrativeResult['tactical_suggestion'] }}</p>
                            </div>
                        @endif
                    @endif

                    @if(!empty($rootCauses))
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Nguyên nhân (theo narrative)</p>
                            <ul class="list-disc pl-5 space-y-1.5 marker:text-gray-400 text-base leading-7 text-gray-700 dark:text-gray-300">
                                @foreach($rootCauses as $cause)
                                    <li>{{ $cause['label'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if(!empty($guidanceLines) && !$hasNarrative)
                        <ul class="list-disc pl-5 space-y-1.5 marker:text-gray-400 text-base leading-7 text-gray-700 dark:text-gray-300">
                            @foreach(array_slice($guidanceLines, 0, 3) as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="text-base leading-7 text-gray-600 dark:text-gray-400">Phân tích chiến lược chi tiết (GPT) sẽ tích hợp sau. Hiện hiển thị đề xuất từ Optimization Engine.</p>
                @endif

        {{-- Pills dưới nội dung (ẩn khi survival protocol) --}}
        @if(!$survivalProtocolActive && $hasContent && ($dataConfidence || $financialHealth || $mode || $state || $maturityStage || ($trajectory && ($trajectory['direction'] ?? '') !== 'stable') || $frame || $objective))
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-theme-xs">
                    @if($dataConfidence)
                        <span class="rounded-full px-2.5 py-0.5 font-medium {{ $isWarningPill($dataConfidence) ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">{{ $dataConfidence['label'] }}</span>
                    @endif
                    @if($financialHealth)
                        <span class="rounded-full px-2.5 py-0.5 font-medium {{ $isWarningPill($financialHealth) ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">{{ $financialHealth['label'] }}{{ !empty($financialHealth['sub_label']) ? ' · ' . $financialHealth['sub_label'] : '' }}</span>
                    @endif
                    @if($mode)
                        <span class="rounded-full px-2.5 py-0.5 font-medium {{ $isWarningPill($mode) ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">{{ $mode['label'] }}</span>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($maturityStage)
                        <span class="rounded-full px-2.5 py-0.5 text-theme-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Pha: {{ $maturityStage['label'] }}</span>
                    @endif
                    @if($trajectory && ($trajectory['direction'] ?? '') !== 'stable')
                        <span class="rounded-full px-2.5 py-0.5 text-theme-xs font-medium text-gray-600 dark:text-gray-400">{{ $trajectory['label'] }}</span>
                    @endif
                    @if($state)
                        <span class="rounded-full px-2.5 py-0.5 text-theme-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ $state['label'] }}</span>
                    @endif
                    @if($frame)
                        <span class="rounded-full px-2.5 py-0.5 text-theme-xs font-medium text-gray-600 dark:text-gray-400">{{ $frame['label'] }}</span>
                    @endif
                    @if($objective)
                        <span class="rounded-full px-2.5 py-0.5 text-theme-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ $objective['label'] }}</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Debt Intelligence (ẩn khi survival protocol) --}}
        @php $debtIntel = isset($insightPayload['debt_intelligence']) ? $insightPayload['debt_intelligence'] : null; @endphp
        @if(!$survivalProtocolActive && $hasContent && $debtIntel !== null && (($debtIntel['debt_priority_list'] ?? []) !== [] || ($debtIntel['debt_stress_index'] ?? null) !== null))
            <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="mb-3 text-theme-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Nợ & Ưu tiên trả</p>
                @if(isset($debtIntel['debt_stress_index']))
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Chỉ số stress nợ (DSI): <span class="font-semibold {{ ($debtIntel['debt_stress_index'] ?? 0) >= 70 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ $debtIntel['debt_stress_index'] }}/100</span>
                        @if(!empty($debtIntel['debt_stress_structural_warning']))
                            <span class="ml-1.5 rounded px-1.5 py-0.5 text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Cảnh báo cấu trúc</span>
                        @endif
                    </p>
                @endif
                @if(!empty($debtIntel['most_urgent_debt']['name']))
                    <p class="mt-1.5 text-sm text-gray-700 dark:text-gray-300">Nên trả gấp: <span class="font-medium">{{ $debtIntel['most_urgent_debt']['name'] }}</span>
                        @if(isset($debtIntel['most_urgent_debt']['days_to_due']) && $debtIntel['most_urgent_debt']['days_to_due'] !== null)
                            ({{ $debtIntel['most_urgent_debt']['days_to_due'] > 0 ? 'còn ' . $debtIntel['most_urgent_debt']['days_to_due'] . ' ngày' : 'quá hạn ' . abs($debtIntel['most_urgent_debt']['days_to_due']) . ' ngày' }})
                        @endif
                    </p>
                @endif
                @if(!empty($debtIntel['most_expensive_debt']['name']) && ($debtIntel['most_expensive_debt']['name'] ?? '') !== ($debtIntel['most_urgent_debt']['name'] ?? '') && ($debtIntel['show_most_expensive_as_highest_interest'] ?? true) && (($debtIntel['most_expensive_debt']['interest_rate_effective'] ?? 0) > 0))
                    <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">Lãi cao nhất: <span class="font-medium">{{ $debtIntel['most_expensive_debt']['name'] }}</span> ({{ number_format($debtIntel['most_expensive_debt']['interest_rate_effective'] ?? 0, 1) }}%/năm)</p>
                @endif
                @if(isset($debtIntel['shock_survival_months']))
                    <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400">Khi thu giảm 30%: runway <span class="font-medium">{{ $debtIntel['shock_survival_months'] ?? '—' }}</span> tháng</p>
                @endif
                @if(!empty($debtIntel['capital_misallocation_flag']))
                    <p class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-400">→ Không nên cho vay thêm khi DSI cao.</p>
                @endif
                @if(!empty($debtIntel['priority_alignment']) && empty($debtIntel['priority_alignment']['aligned']) && !empty($debtIntel['priority_alignment']['suggested_direction']))
                    <p class="mt-2 text-xs font-medium text-blue-700 dark:text-blue-400">→ {{ $debtIntel['priority_alignment']['suggested_direction'] }}</p>
                @endif
            </div>
        @endif

        {{-- Xu hướng các kỳ (drift_signals) + mini sparkline --}}
        @php
            $drift = isset($insightPayload['cognitive_input']['drift_signals']) ? $insightPayload['cognitive_input']['drift_signals'] : null;
            $sparkW = 80;
            $sparkH = 24;
            $dsiPoints = '';
            $bufPoints = '';
            if ($drift !== null && !empty($drift['dsi_series'])) {
                $dsiArr = array_map('intval', $drift['dsi_series']);
                $n = count($dsiArr);
                $pts = [];
                foreach ($dsiArr as $i => $v) {
                    $x = $n > 1 ? $i * ($sparkW - 1) / max(1, $n - 1) : $sparkW / 2;
                    $y = $sparkH - 1 - (min(100, max(0, $v)) / 100.0) * ($sparkH - 2);
                    $pts[] = round($x, 1) . ',' . round($y, 1);
                }
                $dsiPoints = implode(' ', $pts);
            }
            if ($drift !== null && !empty($drift['buffer_series'])) {
                $bufArr = array_map('intval', $drift['buffer_series']);
                $n = count($bufArr);
                $maxBuf = max(1, max($bufArr));
                $pts = [];
                foreach ($bufArr as $i => $v) {
                    $x = $n > 1 ? $i * ($sparkW - 1) / max(1, $n - 1) : $sparkW / 2;
                    $y = $sparkH - 1 - (min($maxBuf, max(0, $v)) / (float) $maxBuf) * ($sparkH - 2);
                    $pts[] = round($x, 1) . ',' . round($y, 1);
                }
                $bufPoints = implode(' ', $pts);
            }
        @endphp
        @if(!$survivalProtocolActive && $hasContent && $drift !== null && (!empty($drift['summary']) || !empty($drift['dsi_series']) || !empty($drift['buffer_series'])))
            <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="mb-3 text-theme-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Xu hướng các kỳ</p>
                @if(!empty($drift['summary']))
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $drift['summary'] }}</p>
                @endif
                @if(!empty($drift['dsi_series']))
                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-xs text-gray-600 dark:text-gray-400 shrink-0">DSI:</span>
                        @if($dsiPoints !== '')
                            <svg class="shrink-0 rounded" width="{{ $sparkW }}" height="{{ $sparkH }}" viewBox="0 0 {{ $sparkW }} {{ $sparkH }}" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="{{ $dsiPoints }}" class="{{ (!empty($drift['dsi_trend']) && $drift['dsi_trend'] === 'improving') ? 'stroke-emerald-500' : 'stroke-amber-500' }}" vector-effect="non-scaling-stroke"/>
                            </svg>
                        @endif
                        <span class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ implode(' → ', array_map('intval', $drift['dsi_series'])) }}</span>
                        @if(!empty($drift['dsi_trend']) && $drift['dsi_trend'] !== 'stable')
                            <span class="ml-0.5 text-xs {{ $drift['dsi_trend'] === 'improving' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">({{ $drift['dsi_trend'] === 'improving' ? 'cải thiện' : 'tăng' }})</span>
                        @endif
                    </div>
                @endif
                @if(!empty($drift['buffer_series']))
                    <div class="mt-1.5 flex items-center gap-2">
                        <span class="text-xs text-gray-600 dark:text-gray-400 shrink-0">Buffer (tháng):</span>
                        @if($bufPoints !== '')
                            <svg class="shrink-0 rounded" width="{{ $sparkW }}" height="{{ $sparkH }}" viewBox="0 0 {{ $sparkW }} {{ $sparkH }}" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="{{ $bufPoints }}" class="{{ (!empty($drift['buffer_trend']) && $drift['buffer_trend'] === 'improving') ? 'stroke-emerald-500' : 'stroke-amber-500' }}" vector-effect="non-scaling-stroke"/>
                            </svg>
                        @endif
                        <span class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ implode(' → ', array_map('intval', $drift['buffer_series'])) }}</span>
                        @if(!empty($drift['buffer_trend']) && $drift['buffer_trend'] !== 'stable')
                            <span class="ml-0.5 text-xs {{ $drift['buffer_trend'] === 'improving' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">({{ $drift['buffer_trend'] === 'improving' ? 'cải thiện' : 'giảm' }})</span>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Footer: Pha + Độ tin cậy (ẩn khi survival) --}}
        @if(!$survivalProtocolActive && $hasContent && ($maturityStage || isset($narrativeResult['narrative_confidence'])))
            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-theme-xs text-gray-500 dark:text-gray-400">
                @if($maturityStage)
                    <span>Pha: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $maturityStage['label'] }}</span></span>
                @endif
                @if(isset($narrativeResult['narrative_confidence']))
                    <span>Độ tin cậy dự báo: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ (int) $narrativeResult['narrative_confidence'] }}%</span></span>
                @endif
            </div>
        @endif
        </div>
    </div>

    @if(!empty($insightHash) && ($hasContent || $survivalProtocolActive))
        @php $firstRootCauseKey = !empty($rootCauses) ? ($rootCauses[0]['key'] ?? '') : ''; @endphp
        <div class="mt-6 pt-5 border-t border-gray-200 dark:border-gray-700" x-data="{
            submitted: false,
            infeasibleOpen: false,
            improveOpen: false,
            pendingType: null,
            pendingReasonCode: null,
            category: null,
            feedbackText: '',
            sending: false,
            hash: '{{ $insightHash }}',
            rootCause: '{{ $firstRootCauseKey }}',
            url: '{{ route('tai-chinh.insight-feedback') }}',
            token: '{{ csrf_token() }}',
            openImprove(type, reasonCode) {
                this.pendingType = type;
                this.pendingReasonCode = reasonCode || null;
                this.category = null;
                this.feedbackText = '';
                this.infeasibleOpen = false;
                this.improveOpen = true;
            },
            send(type, reasonCode, category, feedbackText) {
                if (this.sending || this.submitted) return;
                if (type === 'infeasible' && !reasonCode) { this.infeasibleOpen = true; return; }
                this.sending = true;
                const body = { insight_hash: this.hash, feedback_type: type, reason_code: reasonCode || null };
                if (this.rootCause && (type === 'incorrect' || type === 'alternative')) body.root_cause = this.rootCause;
                if (category) body.category = category;
                if (feedbackText) body.feedback_text = feedbackText;
                fetch(this.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.token, 'Accept': 'application/json' }, body: JSON.stringify(body) })
                    .then(r => r.json())
                    .then(() => { this.submitted = true; this.infeasibleOpen = false; this.improveOpen = false; })
                    .catch(() => { this.sending = false; })
                    .finally(() => { this.sending = false; });
            },
            sendImprove() {
                this.send(this.pendingType, this.pendingReasonCode, this.category, this.feedbackText);
            }
        }">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Phản hồi nhanh</p>
            <template x-if="!submitted">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="send('agree')" :disabled="sending" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-gray-50 px-2.5 py-1.5 text-theme-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 disabled:opacity-50">Hợp lý</button>
                    <div class="relative">
                        <button type="button" @click="infeasibleOpen = !infeasibleOpen" :disabled="sending" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-gray-50 px-2.5 py-1.5 text-theme-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 disabled:opacity-50">Không khả thi</button>
                        <div x-show="infeasibleOpen" x-cloak @click.outside="infeasibleOpen = false" class="absolute left-0 top-full z-10 mt-1 min-w-[180px] rounded-lg border border-gray-200 bg-white py-1 shadow-sm dark:border-gray-700 dark:bg-gray-800" x-transition>
                            @foreach(\App\Models\FinancialInsightFeedback::reasonCodeLabels() as $code => $label)
                                <button type="button" @click="openImprove('infeasible', '{{ $code }}')" class="block w-full px-3 py-1.5 text-left text-theme-xs text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <button type="button" @click="openImprove('incorrect')" :disabled="sending" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-gray-50 px-2.5 py-1.5 text-theme-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 disabled:opacity-50">Không đúng tình huống</button>
                    <button type="button" @click="openImprove('alternative')" :disabled="sending" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-gray-50 px-2.5 py-1.5 text-theme-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 disabled:opacity-50">Muốn phương án khác</button>
                </div>
            </template>
            <div x-show="improveOpen" x-cloak class="mt-4 rounded-xl border border-amber-200 bg-amber-50/80 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                <p class="mb-3 text-sm font-medium text-amber-900 dark:text-amber-100">{{ $improveQuestion }}</p>
                <div class="space-y-2 mb-3">
                    @foreach($feedbackCategoryOptions as $code => $label)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" x-model="category" value="{{ $code }}" name="improve_category" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                            <span class="text-sm text-gray-800 dark:text-gray-200">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="mb-3">
                    <label for="feedback_text" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Viết thêm nếu bạn muốn…</label>
                    <textarea id="feedback_text" x-model="feedbackText" rows="2" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" placeholder="Tùy chọn"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="improveOpen = false" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300">Hủy</button>
                    <button type="button" @click="sendImprove()" :disabled="sending" class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-600 dark:bg-amber-600 disabled:opacity-50">Gửi</button>
                </div>
            </div>
            <template x-if="submitted">
                <p class="text-theme-xs text-gray-600 dark:text-gray-400">Đã ghi nhận. Hệ thống sẽ điều chỉnh chiến lược.</p>
            </template>
        </div>
    @endif

</section>
