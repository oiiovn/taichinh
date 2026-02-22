@php
    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $daysShort = ['T2','T3','T4','T5','T6','T7','CN'];
@endphp
<div class="relative" x-data="{
    TZ: 'Asia/Ho_Chi_Minh',
    hcmNow() {
        const p = new Intl.DateTimeFormat('en-CA', { timeZone: this.TZ, year: 'numeric', month: '2-digit', day: '2-digit', weekday: 'short' }).formatToParts(new Date());
        const g = (t) => (p.find(x => x.type === t) || {}).value;
        const wdMap = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
        const month1 = parseInt(g('month') || '1', 10);
        return { year: parseInt(g('year'),10) || new Date().getFullYear(), month: Math.max(0, Math.min(11, month1 - 1)), day: parseInt(g('day'),10) || 1, dayOfWeek: wdMap[g('weekday')] || 0 };
    },
    showDatePicker: false,
    calendarMonth: 0,
    calendarYear: new Date().getFullYear(),
    selectedDate: null,
    grid: [],
    init() {
        const h = this.hcmNow();
        this.calendarMonth = h.month;
        this.calendarYear = h.year;
        this.updateGrid();
    },
    updateGrid() {
        const d = new Date(this.calendarYear, this.calendarMonth, 1);
        const firstDay = (d.getDay() + 6) % 7;
        const daysInMonth = new Date(this.calendarYear, this.calendarMonth + 1, 0).getDate();
        this.grid = [];
        let week = [];
        for (let i = 0; i < firstDay; i++) week.push(null);
        for (let i = 1; i <= daysInMonth; i++) {
            week.push(i);
            if (week.length === 7) { this.grid.push(week); week = []; }
        }
        if (week.length) { while (week.length < 7) week.push(null); this.grid.push(week); }
    },
    prevMonth() { this.calendarMonth--; if (this.calendarMonth < 0) { this.calendarMonth = 11; this.calendarYear--; } this.updateGrid(); },
    nextMonth() { this.calendarMonth++; if (this.calendarMonth > 11) { this.calendarMonth = 0; this.calendarYear++; } this.updateGrid(); },
    goToday() { const h = this.hcmNow(); this.calendarYear = h.year; this.calendarMonth = h.month; this.updateGrid(); },
    dateInHcm(d) { const p = new Intl.DateTimeFormat('en-CA', { timeZone: this.TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(d); const g = (t) => parseInt((p.find(x => x.type === t) || {}).value || 0, 10); const month1 = g('month'); return { d: g('day'), m: Math.max(0, Math.min(11, month1 - 1)), y: g('year') }; },
    pickToday() { const h = this.hcmNow(); this.selectedDate = { d: h.day, m: h.month, y: h.year }; this.$refs.taskDueDate.value = this.selectedDate.y + '-' + String(this.selectedDate.m+1).padStart(2,'0') + '-' + String(this.selectedDate.d).padStart(2,'0'); this.showDatePicker = false; },
    pickTomorrow() { const h = this.hcmNow(); const next = new Date(Date.now() + 86400000); this.selectedDate = this.dateInHcm(next); this.calendarYear = this.selectedDate.y; this.calendarMonth = this.selectedDate.m; this.updateGrid(); this.$refs.taskDueDate.value = this.selectedDate.y + '-' + String(this.selectedDate.m+1).padStart(2,'0') + '-' + String(this.selectedDate.d).padStart(2,'0'); this.showDatePicker = false; },
    pickNextWeek() { const next = new Date(Date.now() + 7 * 86400000); this.selectedDate = this.dateInHcm(next); this.calendarYear = this.selectedDate.y; this.calendarMonth = this.selectedDate.m; this.updateGrid(); this.$refs.taskDueDate.value = this.selectedDate.y + '-' + String(this.selectedDate.m+1).padStart(2,'0') + '-' + String(this.selectedDate.d).padStart(2,'0'); this.showDatePicker = false; },
    pickNoDate() { this.selectedDate = null; this.$refs.taskDueDate.value = ''; this.showDatePicker = false; },
    selectDay(day) { if (!day || this.isPastDate(day)) return; this.selectedDate = { d: day, m: this.calendarMonth, y: this.calendarYear }; this.$refs.taskDueDate.value = this.selectedDate.y + '-' + String(this.selectedDate.m+1).padStart(2,'0') + '-' + String(this.selectedDate.d).padStart(2,'0'); this.showDatePicker = false; },
    isSelected(day) { return this.selectedDate && this.selectedDate.d === day && this.selectedDate.m === this.calendarMonth && this.selectedDate.y === this.calendarYear; },
    isToday(day) { const h = this.hcmNow(); return day === h.day && this.calendarMonth === h.month && this.calendarYear === h.year; },
    isPastDate(day) { if (!day) return true; const h = this.hcmNow(); if (this.calendarYear < h.year) return true; if (this.calendarYear > h.year) return false; if (this.calendarMonth < h.month) return true; if (this.calendarMonth > h.month) return false; return day < h.day; },
    monthLabel() { const idx = Math.max(0, Math.min(11, this.calendarMonth)); const m = ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12']; return m[idx] + ' ' + this.calendarYear; },
    selectedLabel() { if (!this.selectedDate) return ''; const idx = Math.max(0, Math.min(11, this.selectedDate.m)); return this.selectedDate.d + ' ' + ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'][idx]; },
    pickThisWeekend() { const h = this.hcmNow(); const daysToSat = h.dayOfWeek === 6 ? 0 : (6 - h.dayOfWeek + 7) % 7; const satMoment = new Date(Date.now() + daysToSat * 86400000); this.selectedDate = this.dateInHcm(satMoment); this.calendarYear = this.selectedDate.y; this.calendarMonth = this.selectedDate.m; this.updateGrid(); if (this.$refs.taskDueDate) this.$refs.taskDueDate.value = this.selectedDate.y + '-' + String(this.selectedDate.m+1).padStart(2,'0') + '-' + String(this.selectedDate.d).padStart(2,'0'); this.showDatePicker = false; },
    showTimePanel: false,
    showRepeatPanel: false,
    selectedTime: '',
    timeInput: '',
    selectedRepeat: 'none',
    timeOptions() { const o = []; for (let h = 0; h < 24; h++) for (let m = 0; m < 60; m += 15) o.push(String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')); return o; },
    repeatOptions: [{ value: 'none', label: 'Không lặp' }, { value: 'daily', label: 'Hàng ngày' }, { value: 'weekly', label: 'Hàng tuần' }, { value: 'monthly', label: 'Hàng tháng' }, { value: 'custom', label: 'Tùy chỉnh' }],
    hcmTimeNow() { const p = new Intl.DateTimeFormat('en-CA', { timeZone: this.TZ, hour: '2-digit', hour12: false, minute: '2-digit' }).formatToParts(new Date()); const g = (t) => (p.find(x => x.type === t) || {}).value || '0'; return g('hour') + ':' + g('minute'); },
    isSelectedDateToday() { if (!this.selectedDate) return false; const h = this.hcmNow(); return this.selectedDate.y === h.year && this.selectedDate.m === h.month && this.selectedDate.d === h.day; },
    isPastTime(t) { if (!t || !this.isSelectedDateToday()) return false; const now = this.hcmTimeNow(); return t <= now; },
    getDefaultTimeForInput() { const now = this.hcmTimeNow(); const [h, m] = now.split(':').map(Number); let nm = m % 15 === 0 ? m + 15 : Math.ceil(m / 15) * 15; let nh = h; if (nm >= 60) { nm = 0; nh++; } if (nh >= 24) nh = 0; const nextSlot = String(nh).padStart(2,'0') + ':' + String(nm).padStart(2,'0'); if (this.isSelectedDateToday() && this.isPastTime(nextSlot)) { const opts = this.timeOptions(); for (const t of opts) { if (!this.isPastTime(t)) return t; } return '00:00'; } return nextSlot; },
    openTimePanel() { this.timeInput = this.selectedTime ? this.selectedTime : this.getDefaultTimeForInput(); this.showRepeatPanel = false; this.showTimePanel = true; },
    parseTimeInput(v) { if (!v || !v.trim()) return null; const m = v.trim().match(/^(\d{1,2}):(\d{2})$/); if (!m) return null; const h = parseInt(m[1],10), min = parseInt(m[2],10); if (h >= 0 && h <= 23 && min >= 0 && min <= 59) return String(h).padStart(2,'0')+':'+String(min).padStart(2,'0'); return null; },
    applyTimeInput() { const t = this.parseTimeInput(this.timeInput); if (t !== null && !this.isPastTime(t)) { this.selectedTime = t; if (this.$refs.taskDueTime) this.$refs.taskDueTime.value = t; this.timeInput = t; } else { if (t !== null && this.isPastTime(t)) this.timeInput = this.selectedTime || ''; else this.timeInput = this.selectedTime || ''; } },
    setTime(t) { if (t && this.isPastTime(t)) return; this.selectedTime = t || ''; this.timeInput = this.selectedTime || ''; if (this.$refs.taskDueTime) this.$refs.taskDueTime.value = this.selectedTime || ''; this.showTimePanel = false; this.showDatePicker = false; },
    setRepeat(r) { this.selectedRepeat = r; if (this.$refs.taskRepeat) this.$refs.taskRepeat.value = r; this.showRepeatPanel = false; this.showDatePicker = false; },
    timeLabel() { return this.selectedTime || 'Chọn giờ'; },
    repeatLabel() { const o = this.repeatOptions.find(x => x.value === this.selectedRepeat); return o ? o.label : 'Lặp lại'; },
    badgeLabel() { if (!this.selectedDate) return 'Chọn ngày'; const h = this.hcmNow(); const isToday = this.selectedDate.d === h.day && this.selectedDate.m === h.month && this.selectedDate.y === h.year; const s = isToday && !this.selectedTime ? 'Hôm nay' : this.selectedLabel(); return this.selectedTime ? s + ', ' + this.selectedTime : s; }
}" x-init="const id = $el.dataset.initialDate; const it = $el.dataset.initialTime; if (id) { const parts = id.split('-'); const y = parseInt(parts[0],10), m = parseInt(parts[1],10)-1, d = parseInt(parts[2],10); selectedDate = { d: d, m: m, y: y }; calendarYear = y; calendarMonth = m; updateGrid(); if ($refs.taskDueDate) $refs.taskDueDate.value = id; } if (it) { selectedTime = it; if ($refs.taskDueTime) $refs.taskDueTime.value = it; } if (!id) pickToday();" @open-date-picker.window="showDatePicker = true; $dispatch('form-dropdown-close-others', 'date'); if (!selectedDate) { const h = hcmNow(); calendarYear = h.year; calendarMonth = h.month; updateGrid(); }" @form-dropdown-close-others.window="if ($event.detail !== 'date') showDatePicker = false; showTimePanel = false; showRepeatPanel = false" data-initial-date="{{ $initialDueDate ?? '' }}" data-initial-time="{{ $initialDueTime ?? '' }}">
    <input type="hidden" name="task_due_date" x-ref="taskDueDate" value="{{ $initialDueDate ?? '' }}">
    <input type="hidden" name="task_due_time" x-ref="taskDueTime" value="{{ $initialDueTime ?? '' }}">
    <input type="hidden" name="task_repeat" x-ref="taskRepeat" value="none">
    <input type="hidden" name="task_repeat_until" x-ref="taskRepeatUntil" value="">
    <button type="button" @click="if (!showDatePicker) $dispatch('form-dropdown-close-others', 'date'); if (!showDatePicker) { if (selectedDate) { calendarYear = selectedDate.y; calendarMonth = selectedDate.m; } else { const h = hcmNow(); calendarYear = h.year; calendarMonth = h.month; } updateGrid(); } showDatePicker = !showDatePicker" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium transition-colors" :class="selectedDate ? 'text-brand-600 dark:text-brand-400' : 'text-gray-500 dark:text-gray-400'">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>
        <span x-text="badgeLabel()"></span>
    </button>
    <div x-show="showDatePicker" x-cloak @click.outside="showDatePicker = false"
        class="absolute left-0 top-full z-[60] mt-1 w-[min(100vw-2rem,400px)] max-w-[100vw] overflow-x-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-xl dark:border-gray-700 dark:bg-gray-800">
        <div class="rounded-lg bg-gray-100 px-2 py-1.5 text-sm font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200" x-text="selectedLabel() || 'Hôm nay'"></div>
        <div class="mt-3 space-y-1">
            <button type="button" @click="pickTomorrow()" class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                <span class="shrink-0 text-orange-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" fill="currentColor"/><path fill="none" stroke="currentColor" stroke-width="1.5" d="M12 1v3M12 20v3M3 12h3M18 12h3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12"/></svg>
                </span>
                <span>Ngày mai</span>
                <span class="ml-auto text-xs text-gray-400" x-text="new Date(new Date().setDate(new Date().getDate()+1)).toLocaleDateString('vi-VN',{weekday:'short'})"></span>
            </button>
            <button type="button" @click="pickThisWeekend()" class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                <span class="shrink-0 text-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M4 18v-4h16v4h-2v2H6v-2H4zm0-6V8c0-1.1.9-2 2-2h12c1.1 0 2 .9 2 2v4H4zm2-4v2h12V8H6z"/></svg>
                </span>
                <span>Cuối tuần này</span>
                <span class="ml-auto text-xs text-gray-400">T7</span>
            </button>
            <button type="button" @click="pickNextWeek()" class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm text-purple-600 hover:bg-gray-100 dark:text-purple-400 dark:hover:bg-gray-700">
                <span class="shrink-0 text-purple-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </span>
                <span>Tuần sau</span>
                <span class="ml-auto text-xs text-gray-400" x-text="new Date(new Date().setDate(new Date().getDate()+7)).toLocaleDateString('vi-VN',{weekday:'short',day:'numeric',month:'short'})"></span>
            </button>
            <button type="button" @click="pickNoDate()" class="flex w-full items-center gap-2 rounded-lg bg-gray-100 px-2 py-2 text-left text-sm text-gray-500 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600">
                <span class="shrink-0 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg>
                </span>
                <span>Không chọn</span>
            </button>
        </div>
        <hr class="my-3 border-gray-200 dark:border-gray-600">
        <div class="flex items-center justify-between">
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200" x-text="monthLabel()"></span>
            <div class="flex items-center gap-0.5">
                <button type="button" @click="prevMonth()" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">‹</button>
                <button type="button" @click="goToday()" class="rounded p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">○</button>
                <button type="button" @click="nextMonth()" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">›</button>
            </div>
        </div>
        <div class="mt-2 grid grid-cols-7 gap-0.5 text-center text-xs text-gray-500 dark:text-gray-400">
            <template x-for="d in ['T2','T3','T4','T5','T6','T7','CN']" :key="d"><span x-text="d" class="py-1"></span></template>
        </div>
        <div class="grid grid-cols-7 gap-0.5 text-center text-sm">
            <template x-for="(week, wi) in grid" :key="wi">
                <template x-for="(day, di) in week" :key="wi+'-'+di">
                    <div class="flex aspect-square w-8 items-center justify-center">
                        <button type="button" x-show="day !== null" x-text="day"
                            @click="!isPastDate(day) && selectDay(day)"
                            :disabled="isPastDate(day)"
                            :class="isPastDate(day) ? 'cursor-not-allowed text-gray-300 dark:text-gray-600' : (isSelected(day) ? 'bg-red-500 text-white rounded-full hover:bg-red-600' : (isToday(day) ? 'text-red-500 font-semibold' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'))"
                            class="aspect-square w-8 rounded-full py-0.5 disabled:opacity-60"></button>
                        <span x-show="day === null" class="aspect-square w-8 py-0.5 block"></span>
                    </div>
                </template>
            </template>
        </div>
        <hr class="my-3 border-gray-200 dark:border-gray-600">
        <div class="flex gap-2">
            <div class="relative flex-1">
                <button type="button" @click="openTimePanel()" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-200 py-2 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-400" :class="selectedTime && 'border-brand-500 text-brand-700 dark:border-brand-500 dark:text-brand-400'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <span x-text="timeLabel()"></span>
                </button>
                <div x-show="showTimePanel" x-cloak @click.outside="showTimePanel = false; applyTimeInput()"
                    class="absolute bottom-full left-0 right-0 z-10 mb-1 w-full min-w-0 max-w-[calc(100vw-2rem)] overflow-x-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 p-2 dark:border-gray-600">
                        <input type="text" x-model="timeInput" @blur="applyTimeInput()" @keydown.enter="applyTimeInput(); showTimePanel = false"
                            placeholder="HH:mm" maxlength="5"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div class="max-h-[10rem] overflow-y-auto py-1">
                        <button type="button" @click="setTime('')" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">Không đặt giờ</button>
                        <template x-for="t in timeOptions().filter(t => t && !isPastTime(t))" :key="t">
                            <button type="button" @click="setTime(t)"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm"
                                :class="selectedTime === t ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">
                                <span x-text="t"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <div class="relative flex-1">
                <button type="button" @click="showTimePanel = false; showRepeatPanel = !showRepeatPanel" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-200 py-2 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-400" :class="selectedRepeat !== 'none' && 'border-brand-500 text-brand-700 dark:border-brand-500 dark:text-brand-400'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                    <span x-text="repeatLabel()"></span>
                </button>
                <div x-show="showRepeatPanel" x-cloak @click.outside="showRepeatPanel = false"
                    class="absolute bottom-full left-0 right-0 z-10 mb-1 min-w-0 max-w-[calc(100vw-2rem)] overflow-x-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                    <template x-for="opt in repeatOptions" :key="opt.value">
                        <button type="button" @click="setRepeat(opt.value)" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" :class="selectedRepeat === opt.value && 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400'">
                            <span x-text="opt.label"></span>
                            <span x-show="selectedRepeat === opt.value" class="text-brand-600 dark:text-brand-400">✓</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
