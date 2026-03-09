@php
    $focusPlan = $focusPlan ?? ['total_planned_minutes' => 0, 'available_minutes' => 120];
    $durationSuggestion = isset($task) && $task->id ? (app(\App\Services\TaskDurationLearningService::class)->getPredictedMinutes($task->id) ?? 30) : 30;
    $ctx = $taskCreationContext ?? null;
    $showExecutionInsight = !isset($task) && $ctx && ($ctx['focus_window'] !== '—' || $ctx['workload_pct'] > 0 || ($ctx['capacity_remaining_minutes'] ?? 0) > 0 || $ctx['suggested_priority'] || $ctx['best_time'] || $ctx['overload_hint'] || isset($ctx['task_fit_score']));
@endphp
<form action="{{ isset($task) ? route('cong-viec.tasks.update', $task->id) : route('cong-viec.tasks.store') }}" method="POST" data-similar-url="{{ route('cong-viec.tasks.similar') }}" @submit.prevent="(function(f){ var ed = f.querySelector('[contenteditable=true]'); var h = f.querySelector('input[name=description_html]'); if (ed && h) h.value = ed.innerHTML; var meta = document.querySelector('meta[name=csrf-token]'); var token = meta ? meta.content : (f.querySelector('input[name=_token]') && f.querySelector('input[name=_token]').value); if (!token) { alert('Phiên đăng nhập hết hạn. Vui lòng tải lại trang.'); return; } var fd = new FormData(f); var isCreate = !f.action.match(/\/update\/?$/); fetch(f.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(function(r){ if (r.status === 419) { alert('Phiên hết hạn. Vui lòng tải lại trang rồi thử lại.'); return; } if (r.ok) { return r.json().catch(function(){ return {}; }).then(function(data){ showAddTask = false; var panel = document.getElementById('today-panel') || document.getElementById('tong-quan-panel'); if (isCreate && panel && panel.dataset.partialUrl) { fetch(panel.dataset.partialUrl, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } }).then(function(pr){ if (pr.ok) return pr.text(); }).then(function(html){ if (html && panel.parentNode) panel.innerHTML = html; }); } }); } if (r.redirected) { window.location.href = r.url; return; } return r.json().catch(function(){ return null; }).then(function(data){ var msg = 'Có lỗi xảy ra. Vui lòng thử lại.'; if (data && data.errors) { var arr = []; for (var k in data.errors) { if (data.errors[k] && data.errors[k].length) arr.push(data.errors[k].join(' ')); } if (arr.length) msg = arr.join('\n'); } else if (data && data.message) msg = data.message; alert(msg); }); }).catch(function(){ alert('Lỗi kết nối. Vui lòng thử lại.'); }); })($el)"
        @csrf
        @if(isset($task)) @method('PUT') @endif
    <div x-data="{ plannedMinutes: {{ (int)($focusPlan['total_planned_minutes'] ?? 0) }}, availableMinutes: {{ (int)($focusPlan['available_minutes'] ?? 120) }}, workloadWarning: false, init() { const form = this.$el.closest('form'); if (!form) return; const check = () => { const due = form.querySelector('input[name=task_due_date]')?.value; const dur = parseInt(form.querySelector('input[name=estimated_duration]')?.value, 10) || 30; const today = new Date().toISOString().slice(0,10); this.workloadWarning = due === today && (this.plannedMinutes + dur) > this.availableMinutes; }; setInterval(check, 600); check(); } }" x-show="workloadWarning" x-cloak class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/25 dark:text-amber-200">⚠ Hôm nay đã đầy workload</div>
    @if($showExecutionInsight)
    <div class="mb-3 rounded-xl border border-brand-200 bg-brand-50/60 dark:border-brand-800 dark:bg-brand-900/20 px-3.5 py-2.5 text-sm" data-execution-insight>
        <p class="font-semibold text-brand-800 dark:text-brand-200 mb-2">🧠 Execution Insight</p>
        @if(isset($ctx['task_fit_score']))
        <p class="mb-2 text-gray-700 dark:text-gray-300">Task phù hợp với hôm nay: <span class="font-semibold text-brand-700 dark:text-brand-300">{{ $ctx['task_fit_score'] }}%</span></p>
        @endif
        @if(isset($ctx['capacity_remaining_minutes']) && $ctx['capacity_remaining_minutes'] > 0)
        <p class="mb-2 text-gray-700 dark:text-gray-300">⚡ Bạn còn <span class="font-semibold">{{ $ctx['capacity_remaining_minutes'] }}</span> phút trống hôm nay.</p>
        @endif
        <ul class="space-y-1 text-gray-700 dark:text-gray-300">
            <li>⏰ Cửa sổ tập trung: <span class="font-medium">{{ $ctx['focus_window'] }}</span></li>
            <li>📊 Workload hôm nay: <span class="font-medium">{{ $ctx['workload_pct'] }}%</span></li>
            @if($ctx['suggested_priority'])
            <li>⚡ Priority đề xuất: <span class="font-medium">{{ $ctx['suggested_priority'] }}</span>
                <button type="button" @click="$dispatch('apply-suggested-priority', { value: {{ (int)($ctx['suggested_priority_value']) }} })" class="ml-1 text-brand-600 dark:text-brand-400 hover:underline">Áp dụng</button>
            </li>
            @endif
            @if($ctx['best_time'])
            <li>📅 Đề xuất làm lúc: <span class="font-medium">{{ $ctx['best_time'] }}</span>
                <button type="button" @click="var i = document.querySelector('input[name=task_due_time]'); if (i) i.value = '{{ $ctx['best_time'] }}';" class="ml-1 text-brand-600 dark:text-brand-400 hover:underline">Áp dụng</button>
            </li>
            @endif
            @if($ctx['overload_hint'])
            <li class="text-amber-700 dark:text-amber-300">⚠ {{ $ctx['overload_hint'] }}</li>
            @endif
        </ul>
        @if(($ctx['best_time'] ?? null) && ($ctx['suggested_priority'] ?? null))
        <p class="mt-2 pt-2 border-t border-brand-200 dark:border-brand-700 text-gray-700 dark:text-gray-300">
            <span class="font-medium">⚡ Đề xuất:</span> đặt task vào {{ $ctx['best_time'] }} · priority {{ $ctx['suggested_priority'] }}
            <button type="button" @click="(function(){ var t = '{{ $ctx['best_time'] }}'; var v = {{ (int)($ctx['suggested_priority_value']) }}; var i = document.querySelector('input[name=task_due_time]'); if (i) i.value = t; window.dispatchEvent(new CustomEvent('smart-parse-apply', { detail: { dueTime: t }, bubbles: true })); window.dispatchEvent(new CustomEvent('apply-suggested-priority', { detail: { value: v }, bubbles: true })); })()" class="ml-1.5 rounded bg-brand-600 px-2 py-0.5 text-xs font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600">Áp dụng tất cả</button>
        </p>
        @endif
    </div>
    @endif
    <div x-data="{
        parsed: null,
        similarSuggestion: null,
        _similarT: null,
        parseFromForm() {
            var form = this.$el.closest('form');
            if (!form) return;
            var title = (form.querySelector('input[name=title]') && form.querySelector('input[name=title]').value) || '';
            var ed = form.querySelector('[contenteditable=true]');
            var desc = (ed && (ed.innerText || ed.textContent || '').trim()) || '';
            var combined = (title + ' ' + desc).trim();
            this.parsed = (typeof window.taskParse === 'function' ? window.taskParse(combined) : null) || null;
            var self = this;
            this.similarSuggestion = null;
            clearTimeout(this._similarT);
            if (title.trim().length >= 2) {
                this._similarT = setTimeout(function() { self.doFetchSimilar(title.trim()); }, 400);
            }
        },
        parseTitle(v) {
            this.parseFromForm();
        },
        doFetchSimilar(title) {
            var form = this.$el.closest('form');
            var url = form && form.dataset && form.dataset.similarUrl;
            if (!url) return;
            var self = this;
            fetch(url + '?title=' + encodeURIComponent(title), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) { if (data.suggestions && data.suggestions[0]) self.similarSuggestion = data.suggestions[0]; })
                .catch(function() {});
        },
        applySimilar() {
            var s = this.similarSuggestion;
            if (!s) return;
            var form = this.$el.closest('form');
            if (!form) return;
            if (s.estimated_duration != null) { var e = form.querySelector('input[name=estimated_duration]'); if (e) e.value = String(s.estimated_duration); }
            var detail = {};
            if (s.due_time) detail.dueTime = s.due_time;
            if (s.repeat && s.repeat !== 'none') detail.repeat = s.repeat;
            if (Object.keys(detail).length) window.dispatchEvent(new CustomEvent('smart-parse-apply', { detail: detail }));
            if (s.repeat && s.repeat !== 'none') { var r = form.querySelector('input[name=task_repeat]'); if (r) r.value = s.repeat; }
            if (s.due_time) { var t = form.querySelector('input[name=task_due_time]'); if (t) t.value = s.due_time; }
            this.similarSuggestion = null;
        },
        dueDateLabel() {
            if (!this.parsed || !this.parsed.dueDate) return '';
            var fmt = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit' });
            var g = function(t) { return (fmt.formatToParts(new Date()).find(function(p) { return p.type === t; }) || {}).value || '0'; };
            var today = g('year') + '-' + g('month') + '-' + g('day');
            var tomorrowParts = fmt.formatToParts(new Date(Date.now() + 86400000));
            var g2 = function(t) { return (tomorrowParts.find(function(p) { return p.type === t; }) || {}).value || '0'; };
            var tomorrow = g2('year') + '-' + g2('month') + '-' + g2('day');
            if (this.parsed.dueDate === today) return 'Hôm nay';
            if (this.parsed.dueDate === tomorrow) return 'Ngày mai';
            return this.parsed.dueDate;
        },
        repeatLabel() {
            if (!this.parsed || !this.parsed.repeat) return '';
            return { weekly: 'Hàng tuần', daily: 'Hàng ngày', monthly: 'Hàng tháng' }[this.parsed.repeat] || this.parsed.repeat;
        },
        priorityLabel() {
            if (!this.parsed || this.parsed.priority == null) return '';
            return { 1: 'Khẩn cấp', 2: 'Cao', 3: 'Trung bình', 4: 'Thấp' }[this.parsed.priority] || '';
        },
        remindLabel() {
            if (!this.parsed || this.parsed.remindMinutesBefore == null) return '';
            return { 0: 'Đúng giờ', 5: '5 phút trước', 15: '15 phút trước', 30: '30 phút trước', 60: '1 giờ trước', 120: '2 giờ trước', 1440: '1 ngày trước' }[this.parsed.remindMinutesBefore] || (this.parsed.remindMinutesBefore + ' phút trước');
        },
        parsedSummary() {
            if (!this.parsed || !this.parsed.hasParsed) return '';
            var parts = [];
            if (this.parsed.dueDate) parts.push(this.dueDateLabel());
            if (this.parsed.dueTime) parts.push(this.parsed.dueTime);
            if (this.parsed.duration != null) parts.push(this.parsed.duration + ' phút');
            if (this.parsed.repeat) parts.push(this.repeatLabel());
            if (this.parsed.priority != null) parts.push(this.priorityLabel());
            if (this.parsed.remindMinutesBefore != null) parts.push('Nhắc: ' + this.remindLabel());
            return parts.join(' · ');
        },
        applyParsed() {
            if (!this.parsed || !this.parsed.hasParsed) return;
            var form = this.$el.closest('form');
            var titleInp = form && form.querySelector('input[name=title]');
            if (titleInp && this.parsed.title !== undefined) titleInp.value = this.parsed.title;
            if (this.parsed.dueDate) { var d = form.querySelector('input[name=task_due_date]'); if (d) d.value = this.parsed.dueDate; }
            if (this.parsed.dueTime) { var t = form.querySelector('input[name=task_due_time]'); if (t) t.value = this.parsed.dueTime; }
            if (this.parsed.duration != null) { var e = form.querySelector('input[name=estimated_duration]'); if (e) e.value = String(this.parsed.duration); }
            if (this.parsed.repeat) { var r = form.querySelector('input[name=task_repeat]'); if (r) r.value = this.parsed.repeat; }
            if (this.parsed.priority != null) { window.dispatchEvent(new CustomEvent('apply-suggested-priority', { detail: { value: this.parsed.priority }, bubbles: true })); var p = form.querySelector('input[name=priority]'); if (p) p.value = String(this.parsed.priority); }
            if (this.parsed.remindMinutesBefore !== null) { window.dispatchEvent(new CustomEvent('apply-remind', { detail: { value: String(this.parsed.remindMinutesBefore) }, bubbles: true })); var rm = form.querySelector('input[name=remind_minutes_before]'); if (rm) rm.value = String(this.parsed.remindMinutesBefore); }
            window.dispatchEvent(new CustomEvent('smart-parse-apply', { detail: { dueDate: this.parsed.dueDate || null, dueTime: this.parsed.dueTime || null, repeat: this.parsed.repeat || null } }));
            this.parsed = null;
        }
    }" @parse-form.window="parseFromForm()">
        <input type="text" name="title" value="{{ isset($task) ? e($task->title) : '' }}" placeholder="Nhập tên công việc (vd: mai 9h họp team 30p hàng tuần)"
            @input.debounce.300ms="parseFromForm()"
            class="block w-full border-0 bg-transparent py-1.5 text-base font-semibold text-gray-900 placeholder-gray-400 focus:ring-0 focus:outline-none dark:bg-transparent dark:text-white dark:placeholder-gray-500">
        <div x-show="parsed && parsed.hasParsed" x-cloak class="mt-1.5 flex flex-wrap items-center gap-2 rounded-lg border border-brand-200 bg-brand-50/80 dark:border-brand-800 dark:bg-brand-900/20 px-2.5 py-1.5 text-xs text-gray-700 dark:text-gray-300">
            <span class="font-medium">⚡ Đề xuất từ tiêu đề + mô tả</span>
            <span x-text="(parsed && parsed.title ? parsed.title + ' · ' : '') + parsedSummary()"></span>
            <button type="button" @click="applyParsed()" class="rounded bg-brand-600 px-2 py-0.5 font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600">Áp dụng</button>
        </div>
        @if(!isset($task))
        <div x-show="similarSuggestion && (similarSuggestion.estimated_duration || similarSuggestion.repeat !== 'none' || similarSuggestion.due_time)" x-cloak class="mt-1.5 flex flex-wrap items-center gap-2 rounded-lg border border-blue-200 bg-blue-50/80 dark:border-blue-800 dark:bg-blue-900/20 px-2.5 py-1.5 text-xs text-gray-700 dark:text-gray-300">
            <span class="font-medium">⚡ Giống task trước</span>
            <template x-if="similarSuggestion">
                <span class="flex flex-wrap items-center gap-x-2 gap-y-0">
                    <span x-show="similarSuggestion.estimated_duration" x-text="'Duration: ' + similarSuggestion.estimated_duration + ' phút'"></span>
                    <span x-show="similarSuggestion.repeat && similarSuggestion.repeat !== 'none'" x-text="'Repeat: ' + ({ daily: 'Hàng ngày', weekly: 'Hàng tuần', monthly: 'Hàng tháng' }[similarSuggestion.repeat] || similarSuggestion.repeat)"></span>
                    <span x-show="similarSuggestion.due_time" x-text="'Time: ' + similarSuggestion.due_time"></span>
                </span>
            </template>
            <button type="button" @click="applySimilar()" class="rounded bg-blue-600 px-2 py-0.5 font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">Áp dụng</button>
        </div>
        @endif
        <div x-data="{
            showToolbar: false,
            toolbarTop: 0,
            toolbarLeft: 0,
            exec(cmd, val) { $refs.editor.focus(); document.execCommand(cmd, false, val || null); },
            link() { const u = prompt('URL:'); if (u) { $refs.editor.focus(); document.execCommand('createLink', false, u); } },
            checkSelection() {
                const sel = document.getSelection();
                if (!sel || sel.rangeCount === 0) { this.showToolbar = false; return; }
                const range = sel.getRangeAt(0);
                if (range.collapsed) { this.showToolbar = false; return; }
                const node = sel.anchorNode;
                if (!$refs.editor || !$refs.editor.contains(node)) { this.showToolbar = false; return; }
                const rect = range.getBoundingClientRect();
                this.toolbarTop = rect.top - 8;
                this.toolbarLeft = rect.left + (rect.width / 2);
                this.showToolbar = true;
            }
        }" @click.outside="showToolbar = false">
            <div x-show="showToolbar" x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed z-[100] flex flex-wrap items-center gap-0.5 rounded-lg border border-gray-200/80 bg-white/85 p-1.5 shadow-lg backdrop-blur-md dark:border-gray-600/80 dark:bg-gray-800/85"
                :style="'top: ' + toolbarTop + 'px; left: ' + toolbarLeft + 'px; transform: translate(-50%, -100%);'">
                <button type="button" @click="exec('bold')" class="rounded p-1.5 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" title="Đậm"><span class="text-sm font-bold">B</span></button>
                <button type="button" @click="exec('italic')" class="rounded p-1.5 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" title="Nghiêng"><span class="text-sm italic font-semibold">I</span></button>
                <button type="button" @click="exec('strikeThrough')" class="rounded p-1.5 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" title="Gạch ngang"><span class="text-sm font-medium line-through">S</span></button>
                <button type="button" @click="exec('formatBlock','h1')" class="rounded px-2 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">H1</button>
                <button type="button" @click="exec('formatBlock','h2')" class="rounded px-2 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">H2</button>
                <button type="button" @click="exec('formatBlock','blockquote')" class="rounded p-1.5 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" title="Trích dẫn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6 17h3l2-4V7H5v6h3l-2 4zm8 0h3l2-4V7h-6v6h3l-2 4z"/></svg>
                </button>
                <button type="button" @click="exec('formatBlock','pre')" class="rounded p-1.5 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" title="Code">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/></svg>
                </button>
                <button type="button" @click="exec('insertUnorderedList')" class="rounded p-1.5 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" title="Danh sách">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </button>
                <button type="button" @click="exec('insertOrderedList')" class="rounded p-1.5 text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" title="Danh sách số">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
                </button>
                <button type="button" @click="link()" class="ml-1 flex items-center gap-1 rounded border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    Link
                </button>
            </div>
            <div x-ref="editor" contenteditable="true" data-placeholder="Ghi mô tả công việc càng chi tiết càng tốt"
                @if(isset($task) && $task->description_html) data-initial-html-base64="{{ base64_encode($task->description_html) }}" @endif
                x-init="(function(){ var b = $el.getAttribute('data-initial-html-base64'); if (b) try { var bin = atob(b); var bytes = new Uint8Array(bin.length); for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i); var s = new TextDecoder('utf-8').decode(bytes); var ta = document.createElement('textarea'); ta.innerHTML = s; s = ta.value; $el.innerHTML = s || ''; } catch(e) {} })()"
                class="min-h-[72px] resize-none border-0 bg-transparent py-1.5 pt-0 text-base text-gray-900 focus:ring-0 focus:outline-none dark:bg-transparent dark:text-white empty:before:content-[attr(data-placeholder)] empty:before:text-gray-400 dark:empty:before:text-gray-500"
                @mouseup="checkSelection()"
                @input="$el.closest('form').querySelector('input[name=description_html]').value = $el.innerHTML"
                @input.debounce.300ms="window.dispatchEvent(new CustomEvent('parse-form'))"></div>
        </div>
        <input type="hidden" name="description_html" value="{{ isset($task) ? e($task->description_html) : '' }}">
        <input type="hidden" name="location" :value="locationText">
        <template x-for="id in selectedLabelIds" :key="id">
            <input type="hidden" name="label_ids[]" :value="id">
        </template>
        <input type="hidden" name="project_id" :value="selectedProjectId || ''">
        <input type="hidden" name="program_id" :value="selectedProgramId || ''">
        @if(!isset($task))<input type="hidden" name="kanban_status" :value="typeof addTaskKanbanStatus !== 'undefined' ? (addTaskKanbanStatus || 'backlog') : 'backlog'">@endif
        <input type="hidden" name="category" value="{{ isset($task) ? e($task->category ?? '') : '' }}">
        <input type="hidden" name="impact" value="{{ isset($task) ? e($task->impact ?? '') : '' }}">
        <div x-data="{
            clarityPct: 0,
            tips: [],
            update() {
                var form = this.$el.closest('form');
                if (!form) return;
                var title = (form.querySelector('input[name=title]')?.value || '').trim();
                var duration = (form.querySelector('input[name=estimated_duration]')?.value || '').trim();
                var due = (form.querySelector('input[name=task_due_date]')?.value || '').trim();
                var priority = (form.querySelector('input[name=priority]')?.value || '').trim();
                var n = (title ? 25 : 0) + (duration ? 25 : 0) + (due ? 25 : 0) + (priority ? 25 : 0);
                this.clarityPct = n;
                this.tips = [];
                if (!due) this.tips.push('thêm deadline');
                if (!duration) this.tips.push('thêm duration');
                if (!priority) this.tips.push('thêm priority');
            }
        }" x-init="update(); setInterval(() => update(), 500)" class="mt-2 rounded-lg border border-gray-200 bg-gray-50/80 dark:border-gray-700 dark:bg-gray-800/50 px-2.5 py-1.5 text-xs text-gray-600 dark:text-gray-400">
            <span class="font-medium">Task clarity:</span> <span x-text="clarityPct + '%'"></span>
            <template x-if="tips.length">
                <span class="ml-1"> · Gợi ý: <span x-text="tips.join(' · ')"></span></span>
            </template>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2 py-1.5">
        @include('pages.cong-viec.partials.date-picker-dropdown', ['initialDueDate' => isset($task) ? $task->due_date?->format('Y-m-d') : null, 'initialDueTime' => isset($task) && $task->due_time ? substr($task->due_time, 0, 5) : null, 'initialRepeat' => isset($task) ? ($task->repeat ?? 'none') : 'none', 'initialRepeatUntil' => isset($task) && $task->repeat_until ? $task->repeat_until->format('Y-m-d') : '', 'initialRepeatInterval' => isset($task) && $task->repeat_interval ? (int) $task->repeat_interval : 1])
        <div class="relative" x-data="{ showPriority: false, selectedPriority: {{ isset($task) && $task->priority !== null ? (int)$task->priority : 'null' }}, suggestedLabel: null, suggestedPriority: null, priorityLabels: { 1: 'Khẩn cấp', 2: 'Cao', 3: 'Trung bình', 4: 'Thấp' }, priorityLabel() { return this.selectedPriority ? this.priorityLabels[this.selectedPriority] : null }, priorityColorClass() { if (!this.selectedPriority) return 'text-gray-600 dark:text-gray-400'; return { 1: 'text-red-500 dark:text-red-400', 2: 'text-orange-500 dark:text-orange-400', 3: 'text-blue-500 dark:text-blue-400', 4: 'text-gray-500 dark:text-gray-400' }[this.selectedPriority] || 'text-gray-600'; }, updateSuggestion() { const form = this.$el.closest('form'); const due = form?.querySelector('input[name=task_due_date]')?.value; const impact = form?.querySelector('input[name=impact]')?.value; const today = new Date().toISOString().slice(0,10); if (due === today && impact === 'high') { this.suggestedLabel = 'Khẩn cấp'; this.suggestedPriority = 1; } else if (due === today || impact === 'high') { this.suggestedLabel = 'Cao'; this.suggestedPriority = 2; } else if (impact === 'medium') { this.suggestedLabel = 'Trung bình'; this.suggestedPriority = 3; } else if (impact === 'low') { this.suggestedLabel = 'Thấp'; this.suggestedPriority = 4; } else { this.suggestedLabel = null; this.suggestedPriority = null; } } }" @form-dropdown-close-others.window="if ($event.detail !== 'priority') showPriority = false" @apply-suggested-priority.window="if ($event.detail && $event.detail.value) { selectedPriority = $event.detail.value; showPriority = false; }">
            <input type="hidden" name="priority" :value="selectedPriority || ''">
            <button type="button" @click="if (!showPriority) { updateSuggestion(); $dispatch('form-dropdown-close-others', 'priority'); } showPriority = !showPriority" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm transition-colors hover:opacity-80" :class="priorityColorClass()">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                <span x-text="priorityLabel() || 'Độ ưu tiên'"></span>
            </button>
            <div x-show="showPriority" x-cloak @click.outside="showPriority = false"
                class="absolute left-0 top-full z-[60] mt-1 min-w-[260px] max-w-[calc(100vw-2rem)] overflow-x-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <p x-show="suggestedLabel" class="px-2.5 py-1.5 text-xs text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-600">Gợi ý: <span x-text="suggestedLabel" class="font-medium text-gray-700 dark:text-gray-300"></span> <button type="button" @click="selectedPriority = suggestedPriority; showPriority = false" class="ml-1 text-brand-600 dark:text-brand-400 hover:underline">Áp dụng</button></p>
                <button type="button" @click="selectedPriority = 1; showPriority = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg class="shrink-0 text-red-500" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/></svg>
                    <span>Khẩn cấp</span>
                    <span x-show="selectedPriority === 1" class="ml-auto text-red-500">✓</span>
                </button>
                <button type="button" @click="selectedPriority = 2; showPriority = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg class="shrink-0 text-orange-500" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/></svg>
                    <span>Cao</span>
                    <span x-show="selectedPriority === 2" class="ml-auto text-red-500">✓</span>
                </button>
                <button type="button" @click="selectedPriority = 3; showPriority = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg class="shrink-0 text-blue-500" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/></svg>
                    <span>Trung bình</span>
                    <span x-show="selectedPriority === 3" class="ml-auto text-red-500">✓</span>
                </button>
                <button type="button" @click="selectedPriority = 4; showPriority = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg class="shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                    <span>Thấp</span>
                    <span x-show="selectedPriority === 4" class="ml-auto text-red-500">✓</span>
                </button>
            </div>
        </div>
        <div class="relative" x-data="{ showRemind: false, selectedRemind: '{{ isset($task) && $task->remind_minutes_before !== null ? (string)$task->remind_minutes_before : '' }}', remindOptions: [ { v: '', l: 'Không nhắc' }, { v: '0', l: 'Đúng giờ' }, { v: '5', l: '5 phút trước' }, { v: '15', l: '15 phút trước' }, { v: '30', l: '30 phút trước' }, { v: '60', l: '1 giờ trước' }, { v: '120', l: '2 giờ trước' }, { v: '1440', l: '1 ngày trước' } ], remindLabel() { const o = this.remindOptions.find(x => x.v === this.selectedRemind); return o ? o.l : 'Nhắc nhở'; } }" @form-dropdown-close-others.window="if ($event.detail !== 'remind') showRemind = false" @apply-remind.window="if ($event.detail && $event.detail.value !== undefined) { selectedRemind = $event.detail.value; }">
            <input type="hidden" name="remind_minutes_before" :value="selectedRemind === null || selectedRemind === '' ? '' : selectedRemind">
            <button type="button" @click="if (!showRemind) $dispatch('form-dropdown-close-others', 'remind'); showRemind = !showRemind" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <span x-text="remindLabel()"></span>
            </button>
            <div x-show="showRemind" x-cloak @click.outside="showRemind = false"
                class="absolute left-0 top-full z-[60] mt-1 min-w-[240px] max-w-[calc(100vw-2rem)] overflow-x-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <template x-for="opt in remindOptions" :key="opt.v">
                    <button type="button" @click="selectedRemind = opt.v; showRemind = false" class="flex w-full items-center justify-between gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" :class="selectedRemind === opt.v && 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400'">
                        <span x-text="opt.l"></span>
                        <span x-show="selectedRemind === opt.v" class="text-brand-600 dark:text-brand-400">✓</span>
                    </button>
                </template>
            </div>
        </div>
        <div class="relative">
            <button type="button" @click="if (!showMoreOptions) $dispatch('form-dropdown-close-others', 'more'); showMoreOptions = !showMoreOptions" class="inline-flex items-center justify-center rounded-md p-1 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="6" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="18" r="1.5"/></svg>
            </button>
            <div x-show="showMoreOptions" x-cloak @click.outside="showMoreOptions = false"
                class="absolute left-0 top-full z-[60] mt-1 min-w-[240px] max-w-[calc(100vw-2rem)] overflow-x-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <button type="button" @click="$dispatch('form-dropdown-close-others', 'labels'); showLabelsPanel = true; showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/></svg>
                    Nhãn
                </button>
                <button type="button" @click="$dispatch('form-dropdown-close-others', 'location'); showLocationPanel = true; showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Địa điểm
                </button>
                <button type="button" @click="$dispatch('open-date-picker'); showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>
                    Hạn chót
                </button>
                <div class="border-t border-gray-100 px-2.5 pt-1.5 dark:border-gray-600">
                    <p class="px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">Kanban</p>
                    <button type="button" @click="document.querySelector('input[name=category]').value = 'revenue'; showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"><span class="h-2 w-2 rounded-full bg-green-500"></span> Revenue</button>
                    <button type="button" @click="document.querySelector('input[name=category]').value = 'growth'; showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"><span class="h-2 w-2 rounded-full bg-purple-500"></span> Growth</button>
                    <button type="button" @click="document.querySelector('input[name=category]').value = 'maintenance'; showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"><span class="h-2 w-2 rounded-full bg-gray-500"></span> Maintenance</button>
                    <button type="button" @click="document.querySelector('input[name=category]').value = ''; showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">Bỏ chọn</button>
                </div>
                <div class="border-t border-gray-100 dark:border-gray-600 px-2.5 py-1.5">
                    <p class="px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">Ước lượng (phút)</p>
                    <input type="number" name="estimated_duration" min="0" placeholder="Phút" value="{{ isset($task) ? (int)($task->estimated_duration ?? 0) : '' }}" class="w-full rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <p class="mt-1 px-2.5 text-xs text-gray-500 dark:text-gray-400">Đề xuất: {{ $durationSuggestion }} phút</p>
                </div>
                <div class="border-t border-gray-100 dark:border-gray-600 px-2.5 py-1">
                    <p class="px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">Impact</p>
                    <button type="button" @click="document.querySelector('input[name=impact]').value = 'high'; showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Cao</button>
                    <button type="button" @click="document.querySelector('input[name=impact]').value = 'medium'; showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-blue-700 hover:bg-gray-100 dark:text-blue-300 dark:hover:bg-gray-700">TB</button>
                    <button type="button" @click="document.querySelector('input[name=impact]').value = 'low'; showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">Thấp</button>
                    <button type="button" @click="document.querySelector('input[name=impact]').value = ''; showMoreOptions = false" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">Bỏ chọn</button>
                </div>
            </div>
            <div x-show="showLabelsPanel" x-cloak @click.outside="showLabelsPanel = false"
                class="absolute left-0 top-full z-[60] mt-1 min-w-[260px] max-w-[calc(100vw-2rem)] max-h-[min(320px,70vh)] overflow-x-hidden overflow-y-auto rounded-lg border border-gray-200 bg-white py-1.5 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <p class="px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">Nhãn</p>
                <template x-for="label in userLabels" :key="label.id">
                    <button type="button" @click="selectedLabelIds.includes(label.id) ? selectedLabelIds = selectedLabelIds.filter(i => i !== label.id) : selectedLabelIds = [...selectedLabelIds, label.id]"
                        class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                        <span class="h-3 w-3 shrink-0 rounded-full" :style="'background:' + (label.color || '#6b7280')"></span>
                        <span x-text="label.name"></span>
                        <span x-show="selectedLabelIds.includes(label.id)" class="ml-auto text-brand-600 dark:text-brand-400">✓</span>
                    </button>
                </template>
                <template x-if="showNewLabelForm">
                    <div class="border-t border-gray-200 px-2.5 py-1.5 dark:border-gray-600">
                        <input type="text" x-model="newLabelName" placeholder="Tên nhãn" @keydown.enter.prevent="addLabel()"
                            class="mb-1.5 w-full rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <div class="flex flex-wrap gap-1">
                            <template x-for="c in labelColors" :key="c">
                                <button type="button" @click="newLabelColor = c" class="h-6 w-6 rounded-full border-2 transition" :style="'background:' + c" :class="newLabelColor === c ? 'border-gray-900 dark:border-white' : 'border-transparent'"></button>
                            </template>
                        </div>
                        <div class="mt-1.5 flex gap-1">
                            <button type="button" @click="addLabel()" class="rounded bg-red-500 px-2 py-1 text-xs text-white hover:bg-red-600">Thêm</button>
                            <button type="button" @click="showNewLabelForm = false; newLabelName = ''; newLabelColor = '#6b7280'" class="rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-600">Hủy</button>
                        </div>
                    </div>
                </template>
                <button x-show="!showNewLabelForm" type="button" @click="showNewLabelForm = true" class="mt-0.5 flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                    <span class="text-lg">+</span> Thêm nhãn
                </button>
                <p x-show="userLabels.length === 0 && !showNewLabelForm" class="px-2.5 py-1.5 text-sm text-gray-500 dark:text-gray-400">Chưa có nhãn.</p>
            </div>
            <div x-show="showLocationPanel" x-cloak @click.outside="showLocationPanel = false"
                class="absolute left-0 top-full z-[60] mt-1 w-[min(100vw-2rem,560px)] max-w-[100vw] overflow-x-hidden rounded-lg border border-gray-200 bg-white p-2.5 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Địa điểm</p>
                <input type="text" x-model="locationText" placeholder="Nhập địa điểm"
                    class="min-h-[44px] w-full rounded-lg border-2 border-gray-300 bg-white px-3 py-2.5 text-lg text-gray-900 placeholder-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500">
            </div>
        </div>
    </div>
    <hr class="border-0 border-t border-gray-200/80 dark:border-gray-600/80">
    <div class="flex items-center justify-between py-1.5">
        <div class="relative">
            <button type="button" @click="if (!showInboxDropdown) $dispatch('form-dropdown-close-others', 'inbox'); showInboxDropdown = !showInboxDropdown" class="inline-flex items-center gap-1.5 rounded-md py-1 pl-1.5 pr-1.5 text-sm text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                <span class="flex items-center justify-center p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
                </span>
                <span x-text="inboxDisplayLabel()"></span>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div x-show="showInboxDropdown" x-cloak @click.outside="showInboxDropdown = false"
                class="absolute bottom-full left-0 z-[60] mb-1 w-[min(100vw-2rem,560px)] max-w-[100vw] max-h-[min(70vh,480px)] overflow-x-hidden overflow-y-auto rounded-lg border border-gray-200 bg-white py-2 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <div class="px-2.5 pb-2">
                    <input type="text" x-model="newProjectName" placeholder="Nhập tên dự án" @keydown.enter.prevent="newProjectName.trim() && addProject()"
                        class="w-full rounded border border-gray-300 px-2.5 py-1.5 text-sm text-gray-900 placeholder-gray-400 focus:border-red-500 focus:ring-1 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500">
                </div>
                <div x-show="newProjectName.trim()" x-cloak class="border-b border-gray-100 px-2.5 pb-2 dark:border-gray-600">
                    <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">Không tìm thấy dự án.</p>
                    <button type="button" @click="addProject()"
                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                        <span class="text-base">+</span>
                        <span>Tạo "<span x-text="newProjectName.trim()"></span>"</span>
                    </button>
                </div>
                <button type="button" @click="selectedProjectId = null; showInboxDropdown = false"
                    class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
                    <span>Hộp thư đến</span>
                    <span x-show="selectedProjectId === null" class="ml-auto text-red-500">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                </button>
                <div class="border-t border-gray-100 px-2.5 pt-2 dark:border-gray-600">
                    <div class="flex items-center gap-2 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:text-gray-400">
                        @if(auth()->user() && auth()->user()->avatar_url)
                            <img src="{{ auth()->user()->avatar_url }}" alt="" class="h-6 w-6 shrink-0 rounded-full object-cover" />
                        @else
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-300 text-xs font-medium text-gray-600 dark:bg-gray-600 dark:text-gray-300">{{ auth()->user() ? strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) : 'U' }}</span>
                        @endif
                        Các dự án của tôi
                    </div>
                    <template x-for="project in userProjects" :key="project.id">
                        <button type="button" @click="selectedProjectId = project.id; showInboxDropdown = false"
                            class="flex w-full items-center gap-2 px-2.5 py-1.5 pl-9 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-gray-500 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                            </span>
                            <span class="min-w-0 flex-1 truncate" x-text="project.name"></span>
                            <span x-show="selectedProjectId === project.id" class="shrink-0 text-red-500">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                        </button>
                    </template>
                    <p x-show="userProjects.length === 0" class="px-2.5 py-1.5 pl-9 text-xs text-gray-500 dark:text-gray-400">Chưa có dự án. Nhập tên trên để tạo.</p>
                </div>
            </div>
        </div>
        @if(isset($userPrograms) && $userPrograms->isNotEmpty())
        <div class="relative" x-data="{ showProgramDropdown: false }" @form-dropdown-close-others.window="if ($event.detail !== 'program') showProgramDropdown = false">
            <button type="button" @click="if (!showProgramDropdown) $dispatch('form-dropdown-close-others', 'program'); showProgramDropdown = !showProgramDropdown" class="inline-flex items-center gap-1.5 rounded-md py-1 pl-1.5 pr-1.5 text-sm text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                <span x-text="selectedProgramId ? (userPrograms.find(p => p.id == selectedProgramId)?.title || 'Chương trình') : 'Chương trình'"></span>
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div x-show="showProgramDropdown" x-cloak @click.outside="showProgramDropdown = false"
                class="absolute left-0 top-full z-[60] mt-1 min-w-[200px] rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <button type="button" @click="selectedProgramId = null; showProgramDropdown = false" class="flex w-full items-center justify-between gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    <span>Không gắn chương trình</span>
                    <span x-show="selectedProgramId === null" class="text-red-500">✓</span>
                </button>
                <template x-for="prog in userPrograms" :key="prog.id">
                    <button type="button" @click="selectedProgramId = prog.id; showProgramDropdown = false" class="flex w-full items-center justify-between gap-2 px-2.5 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                        <span x-text="prog.title"></span>
                        <span x-show="selectedProgramId === prog.id" class="text-red-500">✓</span>
                    </button>
                </template>
            </div>
        </div>
        @endif
        <div class="flex items-center gap-1">
            <button type="button" @click="showAddTask = false; if (window.location.search.includes('edit=')) window.location = '{{ route('cong-viec') }}';" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-500 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-red-500 text-white hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-500">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            </button>
        </div>
    </div>
</form>
