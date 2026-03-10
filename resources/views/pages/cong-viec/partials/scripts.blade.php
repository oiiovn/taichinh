@php
    $_focusStartUrl = route('cong-viec.focus.start', ['instance' => 0]);
    $_focusStartUrlBase = \Illuminate\Support\Str::beforeLast($_focusStartUrl, '/');
@endphp
<script>
var __congViecDestroyUrlTemplate = @json($destroyUrlTemplate);
var __congViecToggleCompleteUrlTemplate = @json($toggleCompleteUrl);
var __congViecConfirmCompleteUrlTemplate = @json($confirmCompleteUrl);
var __congViecBehaviorEventsUrl = @json($behaviorEventsUrl);
var __congViecFocusStartUrlBase = @json($_focusStartUrlBase);
var __congViecFocusStopUrl = @json(route('cong-viec.focus.stop'));
var __congViecBreakStartUrl = @json(route('cong-viec.focus.break.start'));
var __congViecFocusActivityUrl = @json(route('cong-viec.focus.activity'));
@php $_durPatchUrl = route('cong-viec.tasks.estimated-duration', ['id' => 999999]); $_editDataUrl = route('cong-viec.tasks.edit-data', ['id' => '__ID__']); @endphp
var __congViecEstimatedDurationUrlTemplate = @json(str_replace('999999', '__ID__', $_durPatchUrl));
var __congViecEditTaskDataUrlTemplate = @json($_editDataUrl);
var __congViecFromSchedulePayloadUrl = @json(route('cong-viec.from-schedule-payload'));
var __congViecStoreUrl = @json(route('cong-viec.tasks.store'));
var __fromScheduleId = {{ request('from_schedule') ? (int)request('from_schedule') : 'null' }};
@php $_instDurUrl = route('cong-viec.instances.actual-duration', ['id' => 999999]); @endphp
var __congViecInstanceActualDurationUrlTemplate = @json(str_replace('999999', '__ID__', $_instDurUrl));
var __congViecMissedWindowPrompt = @json($missedWindowPrompt ?? null);
var __focusIdleMs = {{ (int) config('behavior_intelligence.focus_duration.idle_seconds', 300) * 1000 }};
function __congViecFocusPing() {
    var token = document.querySelector('meta[name=csrf-token]');
    if (!token || !__congViecFocusActivityUrl) return;
    fetch(__congViecFocusActivityUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token.content }, body: JSON.stringify({ _token: token.content }) }).catch(function() {});
}
function __congViecFocusActivityBoot() {
    if (window.__focusActivityInterval) { clearInterval(window.__focusActivityInterval); window.__focusActivityInterval = null; }
    if (window.__focusIdleCheckInterval) { clearInterval(window.__focusIdleCheckInterval); window.__focusIdleCheckInterval = null; }
    window.__focusLastUserActivity = Date.now();
    function mark() { window.__focusLastUserActivity = Date.now(); __congViecFocusPing(); }
    ['click','keydown','scroll','touchstart'].forEach(function(ev) {
        document.addEventListener(ev, function() { window.__focusLastUserActivity = Date.now(); }, { passive: true });
    });
    document.addEventListener('visibilitychange', function() { if (!document.hidden) mark(); });
    window.__focusActivityInterval = setInterval(function() {
        if (document.hidden) return;
        if (Date.now() - window.__focusLastUserActivity < 120000) __congViecFocusPing();
    }, 60000);
    window.__focusIdleCheckInterval = setInterval(function() {
        if (document.hidden) return;
        var banner = document.getElementById('focus-session-banner');
        if (!banner || banner.style.display === 'none') return;
        if (Date.now() - window.__focusLastUserActivity > __focusIdleMs) {
            __congViecFocusStop();
        }
    }, 30000);
}
function __congViecFocusApplyUi(session) {
    if (!session && document.getElementById('focus-session-banner')) {
        var bid = document.getElementById('focus-session-banner').getAttribute('data-instance-id');
        if (bid) { var w = document.getElementById('focus-start-wrap-' + bid); if (w) w.style.display = ''; }
    }
    var banner = document.getElementById('focus-session-banner');
    var titleEl = document.getElementById('focus-session-title');
    var timeEl = document.getElementById('focus-session-time');
    if (session && banner) {
        banner.setAttribute('data-instance-id', session.instance_id || '');
        banner.style.display = '';
        banner.classList.remove('hidden');
        if (titleEl) titleEl.textContent = session.title || '';
        if (window.__focusElapsedTimer) clearInterval(window.__focusElapsedTimer);
        function tick() {
            if (!session.started_at || !timeEl) return;
            var sec = Math.max(0, Math.floor(Date.now() / 1000) - session.started_at);
            var m = Math.floor(sec / 60), r = sec % 60;
            timeEl.textContent = (m < 10 ? '0' : '') + m + ':' + (r < 10 ? '0' : '') + r;
        }
        tick();
        window.__focusElapsedTimer = setInterval(tick, 1000);
    } else if (banner) {
        banner.style.display = 'none';
        banner.classList.add('hidden');
        if (window.__focusElapsedTimer) { clearInterval(window.__focusElapsedTimer); window.__focusElapsedTimer = null; }
    }
    try {
        var rootEl = document.querySelector('[x-data*="congViecPage"]');
        if (rootEl && rootEl._x_dataStack && rootEl._x_dataStack[0]) {
            var d = rootEl._x_dataStack[0];
            d.focusSession = session || null;
            d.focusElapsedSec = session && session.started_at ? Math.max(0, Math.floor(Date.now() / 1000) - session.started_at) : 0;
        }
    } catch (e) {}
}
function __congViecFocusStart(instanceId) {
    var url = __congViecFocusStartUrlBase + '/' + instanceId;
    var token = document.querySelector('meta[name=csrf-token]');
    if (!token) { alert('Thiếu CSRF. Tải lại trang.'); return; }
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token.content }, body: JSON.stringify({ _token: token.content }) })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, status: r.status, d: d }; }); })
        .then(function(x) {
            if (!x.ok) {
                alert(x.d && x.d.message ? x.d.message : 'Không bắt đầu được (' + x.status + '). Tải lại trang rồi thử lại.');
                return;
            }
            if (!x.d || !x.d.instance_id) return;
            var session = { instance_id: x.d.instance_id, started_at: x.d.started_at, title: x.d.title };
            __congViecFocusApplyUi(session);
            __congViecFocusActivityBoot();
            if (x.d.ghost_completion && x.d.ghost_completion.instance_id) {
                __congViecShowGhostCompletion(x.d.ghost_completion);
            }
            var wrap = document.getElementById('focus-start-wrap-' + x.d.instance_id);
            if (wrap) wrap.style.display = 'none';
        })
        .catch(function() { alert('Lỗi mạng. Thử lại.'); });
}
function __congViecFocusStop() {
    var token = document.querySelector('meta[name=csrf-token]');
    if (!token) return;
    fetch(__congViecFocusStopUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token.content }, body: JSON.stringify({ _token: token.content }) })
        .then(function() {
            if (window.__focusActivityInterval) { clearInterval(window.__focusActivityInterval); window.__focusActivityInterval = null; }
            if (window.__focusIdleCheckInterval) { clearInterval(window.__focusIdleCheckInterval); window.__focusIdleCheckInterval = null; }
            __congViecFocusApplyUi(null);
        });
}
function __congViecShowGhostCompletion(g) {
    var el = document.getElementById('ghost-completion-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'ghost-completion-toast';
        el.className = 'fixed bottom-4 left-1/2 z-[60] max-w-lg -translate-x-1/2 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 shadow-lg dark:border-violet-800 dark:bg-violet-900/30';
        document.body.appendChild(el);
    }
    var title = (g.title || '').replace(/</g, '&lt;');
    el.innerHTML = '<p class="text-sm text-gray-800 dark:text-gray-200">Bạn có vừa hoàn thành <strong>' + title + '</strong>?</p>' +
        '<div class="mt-2 flex flex-wrap gap-2">' +
        '<button type="button" class="ghost-yes rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white">✓ Đúng</button>' +
        '<button type="button" class="ghost-no rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800">Chưa</button></div>';
    el.style.display = 'block';
    var hide = function() { el.style.display = 'none'; };
    el.querySelector('.ghost-no').onclick = hide;
    el.querySelector('.ghost-yes').onclick = function() {
        var token = document.querySelector('meta[name=csrf-token]');
        var url = __congViecInstanceActualDurationUrlTemplate.replace(/999999/g, g.instance_id);
        fetch(url, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token.content }, body: JSON.stringify({ actual_duration: g.elapsed_minutes, ghost_confirm: true, _token: token.content }) })
            .then(function() { hide(); }).catch(function() { hide(); });
    };
    setTimeout(hide, 25000);
}
function __congViecShowDurationConfirm(c, onDismiss) {
    var wrap = document.getElementById('duration-confirm-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'duration-confirm-toast-wrap';
        wrap.className = 'fixed inset-0 z-[59] flex items-center justify-center bg-black/40';
        wrap.style.display = 'none';
        document.body.appendChild(wrap);
    }
    var el = document.getElementById('duration-confirm-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'duration-confirm-toast';
        el.className = 'relative z-[60] mx-4 max-w-lg rounded-xl border-2 border-sky-400 px-5 py-4 shadow-2xl dark:border-sky-500';
        el.style.backgroundColor = '#ffffff';
        wrap.appendChild(el);
    }
    wrap.style.display = 'flex';
    var opts = (c.options || []).slice(0, 6);
    if (c.raw_minutes && opts.indexOf(c.raw_minutes) < 0) opts.push(c.raw_minutes);
    var msg = (c.message || '').replace(/</g, '&lt;');
    var btns = opts.map(function(m) {
        return '<button type="button" class="dur-pick rounded-lg border-2 border-sky-500 bg-sky-100 px-3 py-2 text-sm font-semibold text-sky-900 hover:bg-sky-200 dark:border-sky-400 dark:bg-slate-600 dark:text-white dark:hover:bg-slate-500" data-m="' + m + '">' + m + ' phút</button>';
    }).join(' ');
    var isDark = document.documentElement.classList.contains('dark');
    if (isDark) el.style.backgroundColor = '#1e293b';
    el.innerHTML = '<p style="color:' + (isDark ? '#f1f5f9' : '#1f2937') + ';font-size:1.0625rem;font-weight:600;line-height:1.5;margin:0;" class="duration-confirm-msg">' + msg + '</p>' +
        '<div class="mt-3 flex flex-wrap gap-2">' + btns + '</div>';
    var msgEl = el.querySelector('.duration-confirm-msg');
    if (msgEl) msgEl.style.color = isDark ? '#f1f5f9' : '#1f2937';
    el.style.display = 'block';
    var hide = function() { wrap.style.display = 'none'; if (onDismiss) onDismiss(); };
    var token = document.querySelector('meta[name=csrf-token]');
    var baseUrl = __congViecInstanceActualDurationUrlTemplate;
    el.querySelectorAll('.dur-pick').forEach(function(btn) {
        btn.onclick = function() {
            var m = parseInt(btn.getAttribute('data-m'), 10);
            var url = baseUrl.replace(/999999/g, c.instance_id);
            fetch(url, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token.content }, body: JSON.stringify({ actual_duration: m, _token: token.content }) })
                .then(function() { hide(); }).catch(function() { hide(); });
        };
    });
    setTimeout(function() { if (wrap.style.display !== 'none') hide(); }, 20000);
}
if (typeof window !== 'undefined') {
    window.__congViecFocusStart = __congViecFocusStart;
    window.__congViecFocusStop = __congViecFocusStop;
}
function __congViecShowDurationSuggestion(s, onDismiss) {
    var el = document.getElementById('duration-suggestion-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'duration-suggestion-toast';
        el.className = 'fixed bottom-4 left-1/2 z-[60] max-w-lg -translate-x-1/2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 shadow-lg dark:border-amber-800 dark:bg-amber-900/30';
        el.style.display = 'none';
        document.body.appendChild(el);
    }
    el.innerHTML = '<p class="text-sm text-gray-800 dark:text-gray-200">' + (s.message || '').replace(/</g, '&lt;') + '</p>' +
        '<div class="mt-2 flex flex-wrap gap-2">' +
        '<button type="button" class="dur-sugg-yes rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700">Có</button>' +
        '<button type="button" class="dur-sugg-no rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800">Không</button></div>';
    el.style.display = 'block';
    var hide = function() { el.style.display = 'none'; if (onDismiss) onDismiss(); };
    el.querySelector('.dur-sugg-no').onclick = hide;
    el.querySelector('.dur-sugg-yes').onclick = function() {
        var token = document.querySelector('meta[name=csrf-token]');
        var url = __congViecEstimatedDurationUrlTemplate.replace('__ID__', s.task_id);
        fetch(url, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token.content }, body: JSON.stringify({ estimated_duration: s.suggested, _token: token.content }) })
            .then(function() { hide(); }).catch(function() { hide(); });
    };
    setTimeout(function() { if (el.style.display !== 'none') hide(); }, 14000);
}
function __congViecShowBreakSuggestion(s, onDismiss) {
    var el = document.getElementById('break-suggestion-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'break-suggestion-toast';
        el.className = 'fixed bottom-4 left-1/2 z-[60] max-w-lg -translate-x-1/2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-lg dark:border-emerald-800 dark:bg-emerald-900/30';
        el.style.display = 'none';
        document.body.appendChild(el);
    }
    var mins = s.break_minutes || 5;
    el.innerHTML = '<p class="text-sm font-medium text-gray-800 dark:text-gray-200">☕ ' + (s.message || '').replace(/</g, '&lt;') + '</p>' +
        '<div class="mt-2 flex flex-wrap gap-2">' +
        '<button type="button" class="break-sugg-yes rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">Nghỉ</button>' +
        '<button type="button" class="break-sugg-no rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-800">Bỏ qua</button></div>' +
        '<p id="break-countdown" class="mt-1 text-xs text-emerald-700 dark:text-emerald-300"></p>';
    el.style.display = 'block';
    var hide = function() { el.style.display = 'none'; if (window.__breakCountdownTimer) { clearInterval(window.__breakCountdownTimer); window.__breakCountdownTimer = null; } if (onDismiss) onDismiss(); };
    el.querySelector('.break-sugg-no').onclick = hide;
    el.querySelector('.break-sugg-yes').onclick = function() {
        var token = document.querySelector('meta[name=csrf-token]');
        if (token) fetch(__congViecBreakStartUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token.content }, body: JSON.stringify({ _token: token.content }) }).catch(function() {});
        var cd = el.querySelector('#break-countdown');
        var left = mins * 60;
        function tick() {
            if (!cd) return;
            var m = Math.floor(left / 60), r = left % 60;
            cd.textContent = 'Nghỉ ' + m + ':' + (r < 10 ? '0' : '') + r + ' — xong tự tiếp tục.';
            left--;
            if (left < 0) hide();
        }
        tick();
        window.__breakCountdownTimer = setInterval(tick, 1000);
    };
    setTimeout(function() { if (el.style.display !== 'none') hide(); }, 18000);
}
function __congViecAfterCompleteToasts(data, onAllDone) {
    function done() { if (onAllDone) onAllDone(); }
    function chainSuggestions() {
        if (data.duration_suggestion && data.duration_suggestion.show) {
            __congViecShowDurationSuggestion(data.duration_suggestion, function() {
                if (data.break_suggestion && data.break_suggestion.show) __congViecShowBreakSuggestion(data.break_suggestion, done);
                else done();
            });
        } else if (data.break_suggestion && data.break_suggestion.show) {
            __congViecShowBreakSuggestion(data.break_suggestion, done);
        } else done();
    }
    if (data.duration_confirm && data.duration_confirm.instance_id) {
        __congViecShowDurationConfirm(data.duration_confirm, chainSuggestions);
        return;
    }
    chainSuggestions();
}
function __congViecSendBehaviorEvents(events) {
    if (!__congViecBehaviorEventsUrl || !events || !events.length) return;
    var token = document.querySelector('meta[name=csrf-token]');
    if (!token) return;
    fetch(__congViecBehaviorEventsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token.content },
        body: JSON.stringify({ events: events, _token: token.content })
    }).catch(function() {});
}
function __congViecSendPolicyFeedback(type, mode) {
    __congViecSendBehaviorEvents([{ event_type: 'policy_feedback', payload: { type: type, mode: mode || null } }]);
}
function __congViecPolicyFeedbackClick(type) {
    var banner = document.getElementById('policy-feedback-banner');
    var mode = banner ? (banner.getAttribute('data-policy-mode') || '') : '';
    __congViecSendPolicyFeedback(type, mode || null);
    if (banner) banner.style.display = 'none';
}
if (typeof window !== 'undefined') {
    window.__congViecSendPolicyFeedback = __congViecSendPolicyFeedback;
    window.__congViecPolicyFeedbackClick = __congViecPolicyFeedbackClick;
}
function __congViecShowMissedWindowPrompt(p) {
    if (!p || !p.instance_id || !p.title) return;
    var wrap = document.getElementById('missed-window-prompt-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'missed-window-prompt-wrap';
        wrap.className = 'fixed inset-0 z-[59] flex items-center justify-center bg-black/40';
        document.body.appendChild(wrap);
    }
    var title = String(p.title).replace(/</g, '&lt;').replace(/"/g, '&quot;');
    wrap.innerHTML = '<div class="relative z-[60] mx-4 max-w-md rounded-xl border-2 border-rose-300 bg-white px-5 py-4 shadow-2xl dark:border-rose-500 dark:bg-slate-800" style="color:#1f2937"><p style="font-size:1.125rem;font-weight:700;color:#1f2937;line-height:1.4" class="dark:text-white">Việc <strong>« ' + title + ' »</strong></p><p class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">Cửa sổ thực thi đã trễ.</p><p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Bạn đã làm xong chưa? Tick &quot;Đã xong&quot; nếu đã hoàn thành.</p><div class="mt-4 flex flex-wrap gap-2"><button type="button" class="missed-done rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700">✓ Đã xong</button><button type="button" class="missed-not rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">⏳ Chưa làm</button></div></div>';
    wrap.style.display = 'flex';
    var hide = function() { wrap.style.display = 'none'; };
    wrap.querySelector('.missed-not').onclick = hide;
    wrap.querySelector('.missed-done').onclick = function() {
        var token = document.querySelector('meta[name=csrf-token]');
        if (!token) { hide(); return; }
        fetch(p.toggle_url, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token.content }, body: JSON.stringify({ _token: token.content }) })
            .then(function(r) { if (r.ok) { hide(); var panel = document.getElementById('today-panel'); if (panel && panel.getAttribute('data-partial-url')) fetch(panel.getAttribute('data-partial-url'), { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } }).then(function(re) { return re.text(); }).then(function(html) { if (panel && html) { panel.innerHTML = html; if (window.__congViecInitTodaySortable) window.__congViecInitTodaySortable(); } }).catch(function() {}); } })
            .catch(function() { hide(); });
    };
    setTimeout(function() { if (wrap.style.display !== 'none') hide(); }, 30000);
}
function __congViecInitTodaySortable() {
    var todayEl = document.querySelector('[data-today]');
    var date = todayEl ? todayEl.getAttribute('data-today') : '';
    if (!date) return;
    var lists = document.querySelectorAll('.today-sortable-list');
    lists.forEach(function(list) {
        if (list._sortableInit) return;
        list._sortableInit = true;
        var section = list.getAttribute('data-today-sortable') || 'list';
        var storageKey = 'congViecTodayOrder_' + date + '_' + section;
        function getOrder() {
            try {
                var raw = localStorage.getItem(storageKey);
                return raw ? JSON.parse(raw) : [];
            } catch (e) { return []; }
        }
        function saveOrder(instanceIds) {
            try { localStorage.setItem(storageKey, JSON.stringify(instanceIds)); } catch (e) {}
        }
        function getRowNode(innerLi) {
            return innerLi && innerLi.parentNode !== list && innerLi.parentNode.tagName === 'LI' ? innerLi.parentNode : innerLi;
        }
        function applySavedOrder() {
            var order = getOrder();
            if (order.length === 0) return;
            var items = Array.from(list.querySelectorAll('li[data-instance-id]'));
            var byId = {};
            items.forEach(function(li) { byId[li.getAttribute('data-instance-id')] = li; });
            order.forEach(function(id) {
                if (byId[id]) { list.appendChild(getRowNode(byId[id])); }
            });
        }
        applySavedOrder();
        list.addEventListener('dragstart', function(e) {
            var handle = e.target.closest('.today-drag-handle');
            if (!handle) return;
            var li = handle.closest('li');
            if (!li) return;
            var id = li.getAttribute('data-instance-id');
            if (!id) return;
            e.dataTransfer.setData('text/plain', id);
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('application/x-instance-id', id);
            try { e.dataTransfer.setDragImage(li, 20, 20); } catch (err) {}
            li.classList.add('opacity-50');
        });
        list.addEventListener('dragend', function(e) {
            var li = e.target.closest('li');
            if (li) li.classList.remove('opacity-50');
        });
        list.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var li = e.target.closest('li[data-instance-id]');
            if (li) li.classList.add('ring-2', 'ring-brand-500', 'ring-inset');
        });
        list.addEventListener('dragleave', function(e) {
            var li = e.target.closest('li[data-instance-id]');
            if (li) li.classList.remove('ring-2', 'ring-brand-500', 'ring-inset');
        });
        list.addEventListener('drop', function(e) {
            e.preventDefault();
            list.querySelectorAll('li[data-instance-id]').forEach(function(el) { el.classList.remove('ring-2', 'ring-brand-500', 'ring-inset'); });
            var id = e.dataTransfer.getData('application/x-instance-id') || e.dataTransfer.getData('text/plain');
            if (!id) return;
            var dragged = list.querySelector('li[data-instance-id="' + id + '"]');
            var target = e.target.closest('li[data-instance-id]');
            if (!dragged || dragged === target) return;
            var draggedRow = getRowNode(dragged);
            var targetRow = target ? getRowNode(target) : null;
            if (targetRow) {
                list.insertBefore(draggedRow, targetRow);
            } else {
                list.appendChild(draggedRow);
            }
            var order = Array.from(list.children).map(function(outer) {
                var inner = outer.querySelector && outer.querySelector('li[data-instance-id]');
                return inner ? inner.getAttribute('data-instance-id') : null;
            }).filter(Boolean);
            saveOrder(order);
        });
    });
}
document.addEventListener('DOMContentLoaded', function() {
    __congViecSendBehaviorEvents([{ event_type: 'page_view', payload: { path: 'cong-viec' } }]);
    if (__congViecMissedWindowPrompt && __congViecMissedWindowPrompt.instance_id) __congViecShowMissedWindowPrompt(__congViecMissedWindowPrompt);
    __congViecInitTodaySortable();
});
if (typeof window !== 'undefined') {
    window.__congViecInitTodaySortable = __congViecInitTodaySortable;
}
/**
 * Smart parsing: "mai 9h họp team 30p" → { title, dueDate, dueTime, duration }.
 * Chạy client-side, ~1ms, regex + JS.
 */
function __taskParse(raw) {
    if (!raw || typeof raw !== 'string') return { title: '', dueDate: null, dueTime: null, duration: null, repeat: null, priority: null, remindMinutesBefore: null, hasParsed: false };
    let s = raw.trim();
    let dueDate = null;
    let dueTime = null;
    let duration = null;
    let repeat = null;
    let priority = null;
    let remindMinutesBefore = null;
    const tz = 'Asia/Ho_Chi_Minh';
    const fmt = new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' });
    const parts = fmt.formatToParts(new Date());
    const g = function(t) { return (parts.find(function(p) { return p.type === t; }) || {}).value || '0'; };
    const y = parseInt(g('year'), 10);
    const m = parseInt(g('month'), 10);
    const d = parseInt(g('day'), 10);
    function toYmdUtc(yr, mo, day) {
        var u = new Date(Date.UTC(yr, mo - 1, day));
        return u.getUTCFullYear() + '-' + String(u.getUTCMonth() + 1).padStart(2, '0') + '-' + String(u.getUTCDate()).padStart(2, '0');
    }

    if (/(?:ngày\s+)?mốt\b|(?:ngày\s+)?mot\b/i.test(s)) {
        dueDate = toYmdUtc(y, m, d + 2);
        s = s.replace(/(?:ngày\s+)?mốt\b|(?:ngày\s+)?mot\b/gi, '').trim();
    } else if (/(?:ngày\s+)?mai\b/i.test(s)) {
        dueDate = toYmdUtc(y, m, d + 1);
        s = s.replace(/(?:ngày\s+)?mai\b/gi, '').trim();
    } else if (/(?:hôm\s+)?nay\b|hn\b/i.test(s)) {
        dueDate = y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        s = s.replace(/(?:hôm\s+)?nay\b|hn\b/gi, '').trim();
    } else {
        var ngayThangSau = s.match(/\bngày\s+(\d{1,2})\s+tháng\s+sau\b/i);
        var ngayThangNay = s.match(/\bngày\s+(\d{1,2})\s+tháng\s+này\b/i);
        var ngayX = s.match(/\bngày\s+(\d{1,2})\b(?!\s+tháng)/i);
        if (ngayThangSau) {
            var dayNum = Math.max(1, Math.min(31, parseInt(ngayThangSau[1], 10)));
            dueDate = toYmdUtc(y, m + 1, dayNum);
            s = s.replace(/\bngày\s+\d{1,2}\s+tháng\s+sau\b/gi, '').trim();
        } else if (ngayThangNay) {
            var dayNum = Math.max(1, Math.min(31, parseInt(ngayThangNay[1], 10)));
            dueDate = toYmdUtc(y, m, dayNum);
            s = s.replace(/\bngày\s+\d{1,2}\s+tháng\s+này\b/gi, '').trim();
        } else if (ngayX) {
            var dayNum = Math.max(1, Math.min(31, parseInt(ngayX[1], 10)));
            dueDate = toYmdUtc(y, m, dayNum);
            s = s.replace(/\bngày\s+\d{1,2}\b(?!\s+tháng)/gi, '').trim();
        }
    }

    var timeMatch = s.match(/(\d{1,2})(?::(\d{2}))?\s*(?:h|g|giờ)?(?:\s*(\d{2}))?\b/i);
    if (timeMatch) {
        var hour = parseInt(timeMatch[1], 10);
        var min = parseInt(timeMatch[2] || timeMatch[3] || '0', 10);
        if (hour >= 0 && hour <= 23 && min >= 0 && min <= 59) {
            dueTime = String(hour).padStart(2, '0') + ':' + String(min).padStart(2, '0');
        }
        s = s.replace(/(\d{1,2})(?::(\d{2}))?\s*(?:h|g|giờ)?(?:\s*(\d{2}))?\b/gi, '').trim();
    }

    var durPhut = s.match(/(\d+)\s*(?:p|phút|m)\b/i);
    var durGio = s.match(/(\d+)\s*(?:h|g|giờ)(?:\s*(\d+)\s*(?:p|phút|m))?\b/i);
    if (durPhut) {
        duration = parseInt(durPhut[1], 10);
        s = s.replace(/(\d+)\s*(?:p|phút|m)\b/gi, '').trim();
    } else if (durGio) {
        var h = parseInt(durGio[1], 10);
        var p = parseInt(durGio[2] || '0', 10);
        duration = h * 60 + p;
        s = s.replace(/(\d+)\s*(?:h|g|giờ)(?:\s*(\d+)\s*(?:p|phút|m))?\b/gi, '').trim();
    }

    if (/(?:lặp\s+lại\s+)?hàng\s+tuần|mỗi\s+tuần|weekly\b/i.test(s)) {
        repeat = 'weekly';
        s = s.replace(/(?:lặp\s+lại\s+)?hàng\s+tuần|mỗi\s+tuần|weekly\b/gi, '').trim();
    } else if (/(?:lặp\s+lại\s+)?hàng\s+ngày|mỗi\s+ngày|daily\b/i.test(s)) {
        repeat = 'daily';
        s = s.replace(/(?:lặp\s+lại\s+)?hàng\s+ngày|mỗi\s+ngày|daily\b/gi, '').trim();
    } else if (/(?:lặp\s+lại\s+)?hàng\s+tháng|mỗi\s+tháng|monthly\b/i.test(s)) {
        repeat = 'monthly';
        s = s.replace(/(?:lặp\s+lại\s+)?hàng\s+tháng|mỗi\s+tháng|monthly\b/gi, '').trim();
    }

    if (/\bkhẩn\s+cấp\b/i.test(s)) {
        priority = 1;
        s = s.replace(/\bkhẩn\s+cấp\b/gi, '').trim();
    } else if (/\b(?:ưu\s+tiên|mức\s+độ|độ\s+ưu\s+tiên)\s+cao\b|priority\s+high\b/i.test(s)) {
        priority = 2;
        s = s.replace(/\b(?:ưu\s+tiên|mức\s+độ|độ\s+ưu\s+tiên)\s+cao\b|priority\s+high\b/gi, '').trim();
    } else if (/\b(?:ưu\s+tiên|mức\s+độ)\s+trung\s+bình\b|\btrung\s+bình\b|priority\s+medium\b/i.test(s)) {
        priority = 3;
        s = s.replace(/\b(?:ưu\s+tiên|mức\s+độ)\s+trung\s+bình\b|\btrung\s+bình\b|priority\s+medium\b/gi, '').trim();
    } else if (/\b(?:ưu\s+tiên|mức\s+độ)\s+thấp\b|priority\s+low\b/i.test(s)) {
        priority = 4;
        s = s.replace(/\b(?:ưu\s+tiên|mức\s+độ)\s+thấp\b|priority\s+low\b/gi, '').trim();
    }

    var remindPhut = s.match(/\bbáo\s+trước\s+(\d+)\s*(?:phút|p|m)\b/i);
    var remindGio = s.match(/\bbáo\s+trước\s+(\d+)\s*(?:giờ|h|g)\b/i);
    var remindNgay = s.match(/\bbáo\s+trước\s+(\d+)\s*ngày\b/i);
    var remindDungGio = s.match(/\bbáo\s+đúng\s+giờ\b|nhắc\s+đúng\s+giờ\b/i);
    if (remindDungGio) {
        remindMinutesBefore = 0;
        s = s.replace(/\bbáo\s+đúng\s+giờ\b|nhắc\s+đúng\s+giờ\b/gi, '').trim();
    } else if (remindPhut) {
        var p = parseInt(remindPhut[1], 10);
        remindMinutesBefore = [5, 15, 30, 60].reduce(function(a, b) { return Math.abs(a - p) <= Math.abs(b - p) ? a : b; });
        s = s.replace(/\bbáo\s+trước\s+\d+\s*(?:phút|p|m)\b/gi, '').trim();
    } else if (remindGio) {
        var h = parseInt(remindGio[1], 10);
        remindMinutesBefore = h <= 1 ? 60 : (h <= 2 ? 120 : 1440);
        s = s.replace(/\bbáo\s+trước\s+\d+\s*(?:giờ|h|g)\b/gi, '').trim();
    } else if (remindNgay) {
        remindMinutesBefore = 1440;
        s = s.replace(/\bbáo\s+trước\s+\d+\s*ngày\b/gi, '').trim();
    }

    var title = s.replace(/\s+/g, ' ').trim();
    return { title: title, dueDate: dueDate, dueTime: dueTime, duration: duration, repeat: repeat, priority: priority, remindMinutesBefore: remindMinutesBefore, hasParsed: !!(dueDate || dueTime || duration || repeat || priority || remindMinutesBefore !== null) };
}
if (typeof window !== 'undefined') { window.taskParse = __taskParse; }

document.addEventListener('alpine:init', () => {
    Alpine.data('congViecPage', () => ({
        layout: (() => { try { return localStorage.getItem('congViecLayout') || 'list'; } catch(e) { return 'list'; } })(),
        showDeleteModal: false,
        deleteTaskId: null,
        deleteTaskTitle: '',
        get deleteFormAction() { return this.deleteTaskId ? __congViecDestroyUrlTemplate.replace('__ID__', this.deleteTaskId) : '#'; },
        openDeleteModal(id, title) { this.deleteTaskId = id; this.deleteTaskTitle = title || ''; this.showDeleteModal = true; },
        closeDeleteModal() { this.showDeleteModal = false; this.deleteTaskId = null; this.deleteTaskTitle = ''; },
        deleteTaskSubmit() {
            var id = this.deleteTaskId;
            var url = this.deleteFormAction;
            var token = document.querySelector('meta[name=csrf-token]')?.content;
            if (!id || url === '#') return;
            if (!token) { alert('Phiên đăng nhập hết hạn. Vui lòng tải lại trang.'); return; }
            this.closeDeleteModal();
            fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(function(r) {
                if (r.ok) {
                    var removeFade = function(el) { if (el && el.parentNode) { el.style.transition = 'opacity 0.3s'; el.style.opacity = '0'; setTimeout(function() { if (el.parentNode) el.remove(); }, 300); } };
                    document.querySelectorAll('.task-row[data-task-id="' + id + '"]').forEach(removeFade);
                    document.querySelectorAll('.kanban-card[data-task-id="' + id + '"]').forEach(removeFade);
                    document.querySelectorAll('.task-checkbox[data-task-id="' + id + '"]').forEach(function(cb) { var li = cb.closest('li'); if (li) removeFade(li); });
                    setTimeout(function() {
                        var panel = document.getElementById('du-kien-panel');
                        if (panel) panel.querySelectorAll('section').forEach(function(section) { var ul = section.querySelector('ul'); if (ul && ul.querySelectorAll('li').length === 0) section.remove(); });
                    }, 350);
                    return;
                }
                if (r.status === 419) { alert('Phiên hết hạn. Vui lòng tải lại trang.'); return; }
                alert('Không thể xoá. Vui lòng thử lại.');
            }).catch(function() { alert('Lỗi kết nối. Vui lòng thử lại.'); });
        },
        showConfirmCompleteModal: false,
        confirmTaskId: null,
        confirmInstanceId: null,
        confirmInstanceUrl: null,
        confirmPayload: null,
        confirmP: null,
        confirmTaskTitle: '',
        openConfirmCompleteModal(taskId, payload, p, instanceId, confirmInstanceUrl, taskTitle) { this.confirmTaskId = taskId; this.confirmInstanceId = instanceId || null; this.confirmInstanceUrl = confirmInstanceUrl || null; this.confirmPayload = payload || {}; this.confirmP = p; this.confirmTaskTitle = taskTitle || 'Việc này'; this.showConfirmCompleteModal = true; },
        closeConfirmCompleteModal() { this.showConfirmCompleteModal = false; this.confirmTaskId = null; this.confirmInstanceId = null; this.confirmInstanceUrl = null; this.confirmPayload = null; this.confirmP = null; this.confirmTaskTitle = ''; },
        editTaskId: {{ isset($editTask) ? (int)$editTask->id : 'null' }},
        editTaskDataUrl: __congViecEditTaskDataUrlTemplate,
        async openEditModal(taskId) {
            this.editTaskId = taskId;
            var url = this.editTaskDataUrl.replace('__ID__', taskId);
            var r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) { var p = window.location.pathname; var s = (window.location.search || '').replace(/[?&]edit=\d+&?/g, '').replace(/^&/, ''); window.location.href = window.location.origin + p + (s ? (s[0] === '?' ? s : '?' + s) : '') + (s ? '&' : '?') + 'edit=' + taskId; return; }
            var data = await r.json();
            this.addTaskKanbanStatus = data.kanban_status || 'backlog';
            this.selectedLabelIds = Array.isArray(data.label_ids) ? data.label_ids : [];
            this.selectedProjectId = data.project_id != null ? data.project_id : null;
            this.selectedProgramId = data.program_id != null ? data.program_id : null;
            this.locationText = data.location || '';
            this.showAddTask = true;
            var form = document.getElementById('form-add-edit-task');
            if (form) {
                form.action = url.replace(/\/edit-data\/?$/, '');
                var methodInput = form.querySelector('input[name="_method"]');
                if (!methodInput) { methodInput = document.createElement('input'); methodInput.setAttribute('type', 'hidden'); methodInput.setAttribute('name', '_method'); methodInput.value = 'PUT'; form.appendChild(methodInput); }
                else methodInput.value = 'PUT';
                var set = function(name, val) { var el = form.querySelector('[name="' + name + '"]'); if (el) el.value = val != null ? String(val) : ''; };
                set('title', data.title);
                set('task_due_date', data.due_date);
                set('task_due_time', data.due_time);
                set('task_repeat', data.repeat || 'none');
                set('task_repeat_until', data.repeat_until);
                set('task_repeat_interval', data.repeat_interval);
                set('priority', data.priority);
                set('remind_minutes_before', data.remind_minutes_before != null ? data.remind_minutes_before : '');
                set('category', data.category);
                set('impact', data.impact);
                set('estimated_duration', data.estimated_duration);
                set('location', data.location);
                var aft = form.querySelector('input[name="task_available_after"]'); if (aft) aft.value = data.available_after || '';
                var bef = form.querySelector('input[name="task_available_before"]'); if (bef) bef.value = data.available_before || '';
                var descHtml = form.querySelector('input[name="description_html"]'); if (descHtml) descHtml.value = data.description_html || '';
                var ed = form.querySelector('[contenteditable="true"]'); if (ed) { var html = data.description_html || ''; try { ed.innerHTML = html; } catch(e) {} }
                window.dispatchEvent(new CustomEvent('edit-form-data', { detail: { due_date: data.due_date, due_time: data.due_time, repeat: data.repeat, repeat_until: data.repeat_until, repeat_interval: data.repeat_interval, priority: data.priority, remind_minutes_before: data.remind_minutes_before } }));
            }
        },
        openAddTaskWithPayload(payload) {
            if (!payload) return;
            this.editTaskId = null;
            this.addTaskKanbanStatus = payload.kanban_status || 'backlog';
            this.selectedLabelIds = Array.isArray(payload.label_ids) ? payload.label_ids : [];
            this.selectedProjectId = payload.project_id != null ? payload.project_id : null;
            this.selectedProgramId = payload.program_id != null ? payload.program_id : null;
            this.locationText = payload.location || '';
            this.showAddTask = true;
            var form = document.getElementById('form-add-edit-task');
            if (form) {
                form.action = window.__congViecStoreUrl || '';
                var methodInput = form.querySelector('input[name="_method"]');
                if (methodInput) methodInput.remove();
                var set = function(name, val) { var el = form.querySelector('[name="' + name + '"]'); if (el) el.value = val != null ? String(val) : ''; };
                set('title', payload.title);
                set('task_due_date', payload.due_date);
                set('task_due_time', payload.due_time);
                set('task_repeat', payload.repeat || 'none');
                set('task_repeat_until', payload.repeat_until);
                set('task_repeat_interval', payload.repeat_interval);
                set('priority', payload.priority);
                set('remind_minutes_before', payload.remind_minutes_before != null ? payload.remind_minutes_before : '');
                set('category', payload.category);
                set('impact', payload.impact);
                set('estimated_duration', payload.estimated_duration);
                set('location', payload.location);
                var aft = form.querySelector('input[name="task_available_after"]'); if (aft) aft.value = payload.available_after || '';
                var bef = form.querySelector('input[name="task_available_before"]'); if (bef) bef.value = payload.available_before || '';
                var descHtml = form.querySelector('input[name="description_html"]'); if (descHtml) descHtml.value = payload.description_html || '';
                var ed = form.querySelector('[contenteditable="true"]'); if (ed) { try { ed.innerHTML = payload.description_html || ''; } catch(e) {} }
                var detail = { due_date: payload.due_date, due_time: payload.due_time, repeat: payload.repeat, repeat_until: payload.repeat_until, repeat_interval: payload.repeat_interval, priority: payload.priority, remind_minutes_before: payload.remind_minutes_before };
                setTimeout(function() { window.dispatchEvent(new CustomEvent('edit-form-data', { detail: detail })); }, 80);
            }
        },
        async confirmCompleteSubmit() {
            var url = this.confirmInstanceId && this.confirmInstanceUrl ? this.confirmInstanceUrl : (this.confirmTaskId ? __congViecConfirmCompleteUrlTemplate.replace('__ID__', this.confirmTaskId) : null);
            if (!url) return;
            var body = { _token: document.querySelector('meta[name=csrf-token]')?.content };
            if (this.confirmPayload.latency_ms != null) body.latency_ms = this.confirmPayload.latency_ms;
            if (this.confirmPayload.deadline_at) body.deadline_at = this.confirmPayload.deadline_at;
            var res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': body._token }, body: JSON.stringify(body) });
            if (res.ok) {
                var data = await res.json().catch(function() { return {}; });
                this.closeConfirmCompleteModal();
                if (data.completed && typeof __congViecFocusApplyUi === 'function') __congViecFocusApplyUi(null);
                var row = this.confirmInstanceId ? document.querySelector('.task-row[data-instance-id="' + this.confirmInstanceId + '"]') : document.querySelector('.task-row[data-task-id="' + this.confirmTaskId + '"]');
                if (row && data.completed) {
                    __congViecAfterCompleteToasts(data, function() {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(function() { row.remove(); }, 300);
                    });
                }
                if (data.program_progress) {
                    var p = data.program_progress;
                    var integrityEl = document.getElementById('program-integrity-value');
                    if (integrityEl) integrityEl.textContent = Math.round((p.integrity_score || 0) * 100);
                    var todayDoneEl = document.getElementById('program-today-done');
                    if (todayDoneEl) todayDoneEl.textContent = p.today_done != null ? p.today_done : todayDoneEl.textContent;
                    var todayTotalEl = document.getElementById('program-today-total');
                    if (todayTotalEl) todayTotalEl.textContent = p.today_total != null ? p.today_total : todayTotalEl.textContent;
                    var bar = document.getElementById('program-progress-bar');
                    if (bar && p.days_total) bar.style.width = (Math.min(100, Math.round((p.days_elapsed / p.days_total) * 100))) + '%';
                    var sideElapsed = document.getElementById('sidebar-program-elapsed');
                    if (sideElapsed) sideElapsed.textContent = p.days_elapsed;
                    var sideLeft = document.getElementById('sidebar-program-days-left');
                    if (sideLeft && p.days_total != null) sideLeft.textContent = Math.max(0, p.days_total - p.days_elapsed) + ' ngày';
                    var sideIntegrity = document.getElementById('sidebar-program-integrity');
                    if (sideIntegrity) sideIntegrity.textContent = Math.round((p.integrity_score || 0) * 100) + '%';
                }
            }
        },
        showAddTask: @json(isset($editTask)),
        addTaskKanbanStatus: @json(isset($editTask) ? $editTask->kanban_status : 'backlog'),
        showMoreOptions: false,
        showLabelsPanel: false,
        showLocationPanel: false,
        selectedLabelIds: @js(isset($editTask) ? $editTask->labels->pluck('id')->toArray() : []),
        locationText: @json(isset($editTask) ? ($editTask->location ?? '') : ''),
        userLabels: @js($userLabels->toArray()),
        showNewLabelForm: false,
        newLabelName: '',
        newLabelColor: '#6b7280',
        labelColors: ['#6b7280','#ef4444','#f97316','#eab308','#22c55e','#3b82f6','#8b5cf6','#ec4899'],
        labelsStoreUrl: @json(route('cong-viec.labels.store')),
        showInboxDropdown: false,
        selectedProjectId: {{ isset($editTask) && $editTask->project_id ? (int)$editTask->project_id : 'null' }},
        userProjects: @js($userProjects->toArray()),
        selectedProgramId: {{ isset($editTask) && $editTask->program_id ? (int)$editTask->program_id : 'null' }},
        userPrograms: @js(isset($userPrograms) ? $userPrograms->toArray() : []),
        newProjectName: '',
        projectsStoreUrl: @json(route('cong-viec.projects.store')),
        inboxDisplayLabel() { if (this.selectedProjectId === null) return 'Hộp thư đến'; const p = this.userProjects.find(x => x.id == this.selectedProjectId); return p ? p.name : 'Hộp thư đến'; },
        async addProject() {
            if (!this.newProjectName.trim()) return;
            const token = document.querySelector('meta[name=csrf-token]')?.content;
            const res = await fetch(this.projectsStoreUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ name: this.newProjectName.trim(), _token: token }) });
            const data = await res.json();
            this.userProjects.push({ id: data.id, name: data.name, color: data.color });
            this.selectedProjectId = data.id;
            this.showInboxDropdown = false;
            this.newProjectName = '';
        },
        focusSession: @json($focusSession ?? null),
        focusElapsedSec: 0,
        focusTimerId: null,
        focusStart(instanceId) { __congViecFocusStart(instanceId); },
        focusStop() { __congViecFocusStop(); },
        _focusTick() {
            if (!this.focusSession || !this.focusSession.started_at) return;
            this.focusElapsedSec = Math.max(0, Math.floor(Date.now() / 1000) - this.focusSession.started_at);
        },
        focusElapsedFormatted() {
            var s = this.focusElapsedSec || 0;
            var m = Math.floor(s / 60);
            var r = s % 60;
            return (m < 10 ? '0' : '') + m + ':' + (r < 10 ? '0' : '') + r;
        },
        init() {
            var self = this;
            if (typeof this.$watch === 'function') this.$watch('showAddTask', function(v) { if (!v) self.editTaskId = null; });
            if (this.focusSession && this.focusSession.started_at) {
                __congViecFocusApplyUi(this.focusSession);
            }
            if (window.__fromScheduleId) {
                var self = this;
                fetch(window.__congViecFromSchedulePayloadUrl + '?schedule_id=' + window.__fromScheduleId, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success && data.payload) {
                            self.openAddTaskWithPayload(data.payload);
                            if (history.replaceState) {
                                var u = new URL(window.location.href);
                                u.searchParams.delete('from_schedule');
                                history.replaceState({}, '', u.pathname + u.search + u.hash);
                            }
                        }
                    })
                    .catch(function() {});
            }
        },
        async addLabel() {
            if (!this.newLabelName.trim()) return;
            const token = document.querySelector('meta[name=csrf-token]')?.content;
            const res = await fetch(this.labelsStoreUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ name: this.newLabelName.trim(), color: this.newLabelColor, _token: token }) });
            const data = await res.json();
            this.userLabels.push({ id: data.id, name: data.name, color: data.color });
            this.selectedLabelIds.push(data.id);
            this.showNewLabelForm = false;
            this.newLabelName = '';
            this.newLabelColor = '#6b7280';
        }
    }));
    document.addEventListener('click', function(e) {
        var a = e.target.closest('[data-edit-task-id]');
        if (!a) return;
        var id = parseInt(a.getAttribute('data-edit-task-id'), 10);
        if (!id) return;
        var root = document.querySelector('[x-data*="congViecPage"]');
        if (root && root._x_dataStack && root._x_dataStack[0] && typeof root._x_dataStack[0].openEditModal === 'function') {
            e.preventDefault();
            e.stopPropagation();
            root._x_dataStack[0].openEditModal(id);
        }
    });
    document.addEventListener('click', function(e) {
        if (e.target.closest('a') || e.target.closest('button') || e.target.closest('input') || e.target.closest('.today-drag-handle')) return;
        var row = e.target.closest('.task-row');
        var body = e.target.closest('.task-row-body');
        var el = row || body;
        if (!el) return;
        var url = (row && row.getAttribute('data-task-detail-url')) || (body && body.getAttribute('data-task-detail-url'));
        if (!url) return;
        var panel = el.closest('[data-current-tab]');
        var tab = panel ? panel.getAttribute('data-current-tab') : 'tong-quan';
        try {
            sessionStorage.setItem('congViecScroll', String(window.scrollY || 0));
            sessionStorage.setItem('congViecTab', tab);
        } catch (err) {}
        window.location.href = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'tab=' + encodeURIComponent(tab);
    });
    var returnTab = new URLSearchParams(window.location.search).get('tab');
    if (returnTab && sessionStorage.getItem('congViecTab') === returnTab) {
        var savedScroll = sessionStorage.getItem('congViecScroll');
        if (savedScroll !== null) {
            var y = parseInt(savedScroll, 10);
            setTimeout(function() { window.scrollTo(0, y); }, 50);
        }
        try { sessionStorage.removeItem('congViecScroll'); sessionStorage.removeItem('congViecTab'); } catch (err) {}
    }
});
document.addEventListener('change', async function(e) {
    const cb = e.target.closest('.task-checkbox');
    if (!cb || !cb.dataset.url) return;
    var payload = {};
    if (cb.dataset.dueDate) {
        payload.deadline_at = cb.dataset.dueTime ? cb.dataset.dueDate + 'T' + cb.dataset.dueTime + ':00' : cb.dataset.dueDate;
        var deadline = new Date(payload.deadline_at);
        if (!isNaN(deadline.getTime())) payload.latency_ms = Date.now() - deadline.getTime();
    }
    cb.disabled = true;
    try {
        var body = { _token: document.querySelector('meta[name=csrf-token]').content };
        if (payload.latency_ms != null) body.latency_ms = payload.latency_ms;
        if (payload.deadline_at) body.deadline_at = payload.deadline_at;
        const res = await fetch(cb.dataset.url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify(body)
        });
        var data = res.ok ? await res.json().catch(function() { return {}; }) : {};
        if (data.require_confirmation) {
            cb.checked = false;
            var instanceId = cb.dataset.instanceId ? parseInt(cb.dataset.instanceId, 10) : null;
            var confirmInstanceUrl = cb.dataset.confirmUrl || null;
            var taskTitle = (cb.dataset.taskTitle || '').trim() || 'Việc này';
            window.dispatchEvent(new CustomEvent('cong-viec-require-confirm', { detail: { taskId: cb.dataset.taskId ? parseInt(cb.dataset.taskId, 10) : null, taskTitle: taskTitle, payload: payload, p: data.p, instanceId: instanceId, confirmInstanceUrl: confirmInstanceUrl } }));
        } else if (data.completed !== undefined) {
            if (data.completed) {
                if (typeof __congViecFocusApplyUi === 'function') __congViecFocusApplyUi(null);
                var row = cb.dataset.instanceId ? document.querySelector('.task-row[data-instance-id="' + cb.dataset.instanceId + '"]') : document.querySelector('.task-row[data-task-id="' + cb.dataset.taskId + '"]');
                var panel = row ? row.closest('[data-partial-url]') : null;
                var partialUrl = panel ? panel.getAttribute('data-partial-url') : null;
                if ((data.duration_suggestion && data.duration_suggestion.show) || (data.break_suggestion && data.break_suggestion.show) || (data.duration_confirm && data.duration_confirm.instance_id)) {
                    __congViecAfterCompleteToasts(data, function() {
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(function() {
                                row.remove();
                                if (partialUrl && panel && panel.parentNode) {
                                    fetch(partialUrl, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } }).then(function(r) { return r.ok ? r.text() : null; }).then(function(html) {
                                        if (panel && html) { panel.innerHTML = html; if (window.__congViecInitTodaySortable) window.__congViecInitTodaySortable(); }
                                    }).catch(function() {});
                                }
                            }, 300);
                        }
                    });
                } else if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(function() {
                        row.remove();
                        if (partialUrl && panel && panel.parentNode) {
                            fetch(partialUrl, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } }).then(function(r) { return r.ok ? r.text() : null; }).then(function(html) {
                                if (panel && html) { panel.innerHTML = html; if (window.__congViecInitTodaySortable) window.__congViecInitTodaySortable(); }
                            }).catch(function() {});
                        }
                    }, 300);
                }
            } else {
                var panel = document.getElementById('today-panel');
                var url = panel && panel.getAttribute && panel.getAttribute('data-partial-url');
                if (url) {
                    fetch(url, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } }).then(function(r) { return r.text(); }).then(function(html) {
                        if (panel && html) { panel.innerHTML = html; if (window.__congViecInitTodaySortable) window.__congViecInitTodaySortable(); }
                    }).catch(function() { window.location.reload(); });
                } else {
                    window.location.reload();
                }
            }
            if (data.completed && data.program_progress) {
                var p = data.program_progress;
                var integrityEl = document.getElementById('program-integrity-value');
                if (integrityEl) integrityEl.textContent = Math.round((p.integrity_score || 0) * 100);
                var todayDoneEl = document.getElementById('program-today-done');
                if (todayDoneEl) todayDoneEl.textContent = p.today_done != null ? p.today_done : todayDoneEl.textContent;
                var todayTotalEl = document.getElementById('program-today-total');
                if (todayTotalEl) todayTotalEl.textContent = p.today_total != null ? p.today_total : todayTotalEl.textContent;
                var bar = document.getElementById('program-progress-bar');
                if (bar && p.days_total) bar.style.width = (Math.min(100, Math.round((p.days_elapsed / p.days_total) * 100))) + '%';
                var sideElapsed = document.getElementById('sidebar-program-elapsed');
                if (sideElapsed) sideElapsed.textContent = p.days_elapsed;
                var sideLeft = document.getElementById('sidebar-program-days-left');
                if (sideLeft && p.days_total != null) sideLeft.textContent = Math.max(0, p.days_total - p.days_elapsed) + ' ngày';
                var sideIntegrity = document.getElementById('sidebar-program-integrity');
                if (sideIntegrity) sideIntegrity.textContent = Math.round((p.integrity_score || 0) * 100) + '%';
            }
        }
    } finally { cb.disabled = false; }
});
</script>
