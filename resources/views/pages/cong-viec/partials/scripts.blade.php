<script>
var __congViecDestroyUrlTemplate = @json($destroyUrlTemplate);
var __congViecToggleCompleteUrlTemplate = @json($toggleCompleteUrl);
var __congViecConfirmCompleteUrlTemplate = @json($confirmCompleteUrl);
var __congViecBehaviorEventsUrl = @json($behaviorEventsUrl);
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
    if (!raw || typeof raw !== 'string') return { title: '', dueDate: null, dueTime: null, duration: null, hasParsed: false };
    let s = raw.trim();
    let dueDate = null;
    let dueTime = null;
    let duration = null;
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

    var title = s.replace(/\s+/g, ' ').trim();
    return { title: title, dueDate: dueDate, dueTime: dueTime, duration: duration, hasParsed: !!(dueDate || dueTime || duration) };
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
                if (row && data.completed) { row.style.transition = 'opacity 0.3s'; row.style.opacity = '0'; setTimeout(function() { row.remove(); }, 300); }
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
