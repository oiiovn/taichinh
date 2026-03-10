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
@php $_durPatchUrl = route('cong-viec.tasks.estimated-duration', ['id' => 999999]); @endphp
var __congViecEstimatedDurationUrlTemplate = @json(str_replace('999999', '__ID__', $_durPatchUrl));
@php $_instDurUrl = route('cong-viec.instances.actual-duration', ['id' => 999999]); @endphp
var __congViecInstanceActualDurationUrlTemplate = @json(str_replace('999999', '__ID__', $_instDurUrl));
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
    var el = document.getElementById('duration-confirm-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'duration-confirm-toast';
        el.className = 'fixed bottom-4 left-1/2 z-[60] max-w-lg -translate-x-1/2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 shadow-lg dark:border-sky-800 dark:bg-sky-900/30';
        document.body.appendChild(el);
    }
    var opts = (c.options || []).slice(0, 6);
    if (c.raw_minutes && opts.indexOf(c.raw_minutes) < 0) opts.push(c.raw_minutes);
    var btns = opts.map(function(m) {
        return '<button type="button" class="dur-pick rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-800" data-m="' + m + '">' + m + ' phút</button>';
    }).join(' ');
    el.innerHTML = '<p class="text-sm text-gray-800 dark:text-gray-200">' + (c.message || '').replace(/</g, '&lt;') + '</p>' +
        '<div class="mt-2 flex flex-wrap gap-2">' + btns + '</div>';
    el.style.display = 'block';
    var hide = function() { el.style.display = 'none'; if (onDismiss) onDismiss(); };
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
    setTimeout(function() { if (el.style.display !== 'none') hide(); }, 20000);
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
document.addEventListener('DOMContentLoaded', function() {
    __congViecSendBehaviorEvents([{ event_type: 'page_view', payload: { path: 'cong-viec' } }]);
});
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
            // #region agent log
            fetch('http://127.0.0.1:7242/ingest/c4e6556a-4f65-43a2-a0c6-442b6960c7db',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'b25103'},body:JSON.stringify({sessionId:'b25103',location:'scripts:deleteTaskSubmit:entry',message:'delete handler entry',data:{id:id,url:url,hasToken:!!token},timestamp:Date.now(),hypothesisId:'A'})}).catch(function(){});
            // #endregion
            if (!id || url === '#') return;
            if (!token) { alert('Phiên đăng nhập hết hạn. Vui lòng tải lại trang.'); return; }
            this.closeDeleteModal();
            fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(function(r) {
                // #region agent log
                fetch('http://127.0.0.1:7242/ingest/c4e6556a-4f65-43a2-a0c6-442b6960c7db',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'b25103'},body:JSON.stringify({sessionId:'b25103',location:'scripts:deleteTaskSubmit:response',message:'delete response',data:{status:r.status,ok:r.ok},timestamp:Date.now(),hypothesisId:'B'})}).catch(function(){});
                // #endregion
                if (r.ok) {
                    var rows = document.querySelectorAll('.task-row[data-task-id="' + id + '"]');
                    rows.forEach(function(el) { el.style.transition = 'opacity 0.3s'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 300); });
                    var cards = document.querySelectorAll('.kanban-card[data-task-id="' + id + '"]');
                    cards.forEach(function(el) { el.style.transition = 'opacity 0.3s'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 300); });
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
        openConfirmCompleteModal(taskId, payload, p, instanceId, confirmInstanceUrl) { this.confirmTaskId = taskId; this.confirmInstanceId = instanceId || null; this.confirmInstanceUrl = confirmInstanceUrl || null; this.confirmPayload = payload || {}; this.confirmP = p; this.showConfirmCompleteModal = true; },
        closeConfirmCompleteModal() { this.showConfirmCompleteModal = false; this.confirmTaskId = null; this.confirmInstanceId = null; this.confirmInstanceUrl = null; this.confirmPayload = null; this.confirmP = null; },
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
            if (this.focusSession && this.focusSession.started_at) {
                __congViecFocusApplyUi(this.focusSession);
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
            window.dispatchEvent(new CustomEvent('cong-viec-require-confirm', { detail: { taskId: cb.dataset.taskId ? parseInt(cb.dataset.taskId, 10) : null, payload: payload, p: data.p, instanceId: instanceId, confirmInstanceUrl: confirmInstanceUrl } }));
        } else if (data.completed !== undefined) {
            if (data.completed) {
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
                                        if (panel && html) panel.innerHTML = html;
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
                                if (panel && html) panel.innerHTML = html;
                            }).catch(function() {});
                        }
                    }, 300);
                }
            } else {
                var panel = document.getElementById('today-panel');
                var url = panel && panel.getAttribute && panel.getAttribute('data-partial-url');
                if (url) {
                    fetch(url, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } }).then(function(r) { return r.text(); }).then(function(html) {
                        if (panel && html) panel.innerHTML = html;
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
