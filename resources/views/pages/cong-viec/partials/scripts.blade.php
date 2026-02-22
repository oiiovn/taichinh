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
document.addEventListener('alpine:init', () => {
    Alpine.data('congViecPage', () => ({
        layout: (() => { try { return localStorage.getItem('congViecLayout') || 'list'; } catch(e) { return 'list'; } })(),
        showDeleteModal: false,
        deleteTaskId: null,
        deleteTaskTitle: '',
        get deleteFormAction() { return this.deleteTaskId ? __congViecDestroyUrlTemplate.replace('__ID__', this.deleteTaskId) : '#'; },
        openDeleteModal(id, title) { this.deleteTaskId = id; this.deleteTaskTitle = title || ''; this.showDeleteModal = true; },
        closeDeleteModal() { this.showDeleteModal = false; this.deleteTaskId = null; this.deleteTaskTitle = ''; },
        showConfirmCompleteModal: false,
        confirmTaskId: null,
        confirmPayload: null,
        confirmP: null,
        openConfirmCompleteModal(taskId, payload, p) { this.confirmTaskId = taskId; this.confirmPayload = payload || {}; this.confirmP = p; this.showConfirmCompleteModal = true; },
        closeConfirmCompleteModal() { this.showConfirmCompleteModal = false; this.confirmTaskId = null; this.confirmPayload = null; this.confirmP = null; },
        async confirmCompleteSubmit() {
            if (!this.confirmTaskId) return;
            var url = __congViecConfirmCompleteUrlTemplate.replace('__ID__', this.confirmTaskId);
            var body = { _token: document.querySelector('meta[name=csrf-token]')?.content };
            if (this.confirmPayload.latency_ms != null) body.latency_ms = this.confirmPayload.latency_ms;
            if (this.confirmPayload.deadline_at) body.deadline_at = this.confirmPayload.deadline_at;
            var res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': body._token }, body: JSON.stringify(body) });
            if (res.ok) {
                var data = await res.json().catch(function() { return {}; });
                this.closeConfirmCompleteModal();
                var row = document.querySelector('.task-row[data-task-id="' + this.confirmTaskId + '"]');
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
            window.dispatchEvent(new CustomEvent('cong-viec-require-confirm', { detail: { taskId: cb.dataset.taskId ? parseInt(cb.dataset.taskId, 10) : null, payload: payload, p: data.p } }));
        } else if (data.completed !== undefined) {
            if (data.completed) {
                var row = document.querySelector('.task-row[data-task-id="' + cb.dataset.taskId + '"]');
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(function() { row.remove(); }, 300);
                }
            } else {
                window.location.reload();
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
