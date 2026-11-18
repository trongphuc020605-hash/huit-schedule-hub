/**
 * HUIT Schedule Hub — Live Intelligent Scheduler Engine
 * Debounced AJAX (250ms) teacher ranking (0-100), real-time conflict & travel inspector, Top-5 optimal slot finder
 */
(function () {
    'use strict';

    const SHIFTS = {
        '1': { start: 1,  end: 3,  label: 'Ca 1 (Tiết 1-3)' },
        '2': { start: 4,  end: 6,  label: 'Ca 2 (Tiết 4-6)' },
        '3': { start: 7,  end: 9,  label: 'Ca 3 (Tiết 7-9)' },
        '4': { start: 10, end: 12, label: 'Ca 4 (Tiết 10-12)' },
        '5': { start: 13, end: 15, label: 'Ca 5 (Tiết 13-15)' }
    };

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function debounce(fn, delay) {
        let timer = null;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('schedule-form');
        if (!form) return;

        const baseUrl         = form.getAttribute('data-base-url') || '';
        const excludeId       = form.getAttribute('data-exclude-id') || '';
        const subjectEl       = document.getElementById('subject_name');
        const branchEl        = document.getElementById('branch_id');
        const roomEl          = document.getElementById('room');
        const teacherEl       = document.getElementById('teacher_id');
        const dayEl           = document.getElementById('day_of_week');
        const shiftPresetEl   = document.getElementById('shift_preset');
        const startPeriodEl   = document.getElementById('start_period');
        const endPeriodEl     = document.getElementById('end_period');
        const shiftLabelEl    = document.getElementById('shift_or_periods');
        const startDateEl     = document.getElementById('start_date');
        const endDateEl       = document.getElementById('end_date');
        const submitBtn       = document.getElementById('submit-schedule-btn');

        const conflictBox     = document.getElementById('live-conflict-box');
        const recListEl       = document.getElementById('recommended-teachers-list');
        const unavailListEl   = document.getElementById('unavailable-teachers-list');
        const recCountEl      = document.getElementById('rec-count');
        const unavailCountEl  = document.getElementById('unavail-count');
        const suggestSlotsBtn = document.getElementById('btn-suggest-slots');
        const slotsResultBox  = document.getElementById('optimal-slots-box');
        const roomsBox        = document.getElementById('available-rooms-box');

        document.querySelectorAll('[data-rec-tab]').forEach(function (tabBtn) {
            tabBtn.addEventListener('click', function () {
                const target = tabBtn.getAttribute('data-rec-tab');
                document.querySelectorAll('[data-rec-tab]').forEach(b => b.classList.remove('active'));
                tabBtn.classList.add('active');
                if (target === 'recommended') {
                    recListEl.style.display = 'flex';
                    unavailListEl.style.display = 'none';
                } else {
                    recListEl.style.display = 'none';
                    unavailListEl.style.display = 'flex';
                }
            });
        });

        function syncShiftLabelFromPeriods(overwriteLabel = true) {
            const sp = Math.max(1, Math.min(15, parseInt(startPeriodEl.value, 10) || 1));
            const ep = Math.max(sp, Math.min(15, parseInt(endPeriodEl.value, 10) || sp));
            let matchedKey = 'custom';
            let label = 'Tiết ' + sp + '-' + ep;
            Object.keys(SHIFTS).forEach(function (k) {
                if (SHIFTS[k].start === sp && SHIFTS[k].end === ep) {
                    matchedKey = k;
                    label = SHIFTS[k].label;
                }
            });
            shiftPresetEl.value = matchedKey;
            if (overwriteLabel || shiftLabelEl.value.trim() === '') {
                shiftLabelEl.value = label;
            }
        }

        shiftPresetEl.addEventListener('change', function () {
            const preset = SHIFTS[shiftPresetEl.value];
            if (preset) {
                startPeriodEl.value = preset.start;
                endPeriodEl.value = preset.end;
                shiftLabelEl.value = preset.label;
                triggerLiveInspectors();
            }
        });

        startPeriodEl.addEventListener('change', function () {
            const sp = Math.max(1, Math.min(15, parseInt(startPeriodEl.value, 10) || 1));
            startPeriodEl.value = String(sp);
            if ((parseInt(endPeriodEl.value, 10) || 1) < sp) {
                endPeriodEl.value = String(sp);
            }
            syncShiftLabelFromPeriods(true);
            triggerLiveInspectors();
        });

        endPeriodEl.addEventListener('change', function () {
            const sp = Math.max(1, Math.min(15, parseInt(startPeriodEl.value, 10) || 1));
            const ep = Math.max(sp, Math.min(15, parseInt(endPeriodEl.value, 10) || sp));
            endPeriodEl.value = String(ep);
            syncShiftLabelFromPeriods(true);
            triggerLiveInspectors();
        });

        function buildQuery(extra = {}) {
            const params = new URLSearchParams({
                subject:      subjectEl.value.trim(),
                branch_id:    branchEl.value || '0',
                room:         roomEl.value.trim(),
                teacher_id:   teacherEl.value || '0',
                day_of_week:  dayEl.value || '2',
                start_period: startPeriodEl.value || '1',
                end_period:   endPeriodEl.value || '3',
                start_date:   startDateEl.value || '',
                end_date:     endDateEl.value || '',
                exclude_id:   excludeId
            });
            Object.keys(extra).forEach(k => params.set(k, extra[k]));
            return params.toString();
        }

        async function runConflictInspector() {
            if (!startDateEl.value || !endDateEl.value) return;
            if (endDateEl.value < startDateEl.value) {
                submitBtn.disabled = true;
                conflictBox.innerHTML =
                    '<div class="alert alert-error" style="margin:0;"><div>' +
                    '<strong>Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.</strong>' +
                    '</div></div>';
                return;
            }
            const hasTeacher = (parseInt(teacherEl.value, 10) || 0) > 0;
            const hasRoomTarget = (parseInt(branchEl.value, 10) || 0) > 0 && roomEl.value.trim() !== '';
            if (!hasTeacher && !hasRoomTarget) {
                submitBtn.disabled = false;
                conflictBox.innerHTML =
                    '<div class="alert alert-info" style="margin:0;"><div>' +
                    'Chọn Cơ sở, Phòng học và Giảng viên để kiểm tra xung đột theo thời gian thực.' +
                    '</div></div>';
                return;
            }
            try {
                const res = await window.HuitApp.apiFetch(baseUrl + '/api/check-conflict?' + buildQuery());
                const d = res.data;

                if (d.has_conflict) {
                    submitBtn.disabled = true;
                    const msgs = (d.messages || []).map(m => '<li>' + escapeHtml(m) + '</li>').join('');
                    conflictBox.innerHTML =
                        '<div class="alert alert-error" style="margin:0;"><div>' +
                        '<strong>PHÁT HIỆN XUNG ĐỘT CỨNG (Không thể lưu)</strong>' +
                        '<ul style="margin:0.4rem 0 0 1.1rem;padding:0;">' + msgs + '</ul>' +
                        '</div></div>';
                    return;
                }

                submitBtn.disabled = false;

                if (d.warnings && d.warnings.length > 0) {
                    const hasCritical = d.warnings.some(w => w.severity === 'critical');
                    const cls = hasCritical ? 'alert-error' : 'alert-warning';
                    const title = hasCritical
                        ? 'Cảnh báo nghiêm trọng: Không kịp di chuyển giữa 2 cơ sở'
                        : 'Cảnh báo tối ưu di chuyển / khoảng trống chết';
                    const items = d.warnings.map(w => '<li>' + escapeHtml(w.message) + '</li>').join('');
                    conflictBox.innerHTML =
                        '<div class="alert ' + cls + '" style="margin:0;"><div>' +
                        '<strong>' + title + '</strong>' +
                        '<ul style="margin:0.35rem 0 0 1.1rem;padding:0;">' + items + '</ul>' +
                        '</div></div>';
                } else {
                    const badgeHtml = (d.badges || []).map(b => '<span class="badge badge-success">' + escapeHtml(b) + '</span>').join(' ');
                    conflictBox.innerHTML =
                        '<div class="alert alert-success" style="margin:0;"><div>' +
                        '<strong>Khung giờ hợp lệ — Không xung đột giảng viên và phòng học</strong>' +
                        (badgeHtml ? '<div class="mt-1 flex gap-1 flex-wrap">' + badgeHtml + '</div>' : '') +
                        '</div></div>';
                }
            } catch (err) {
                conflictBox.innerHTML =
                    '<div class="alert alert-warning" style="margin:0;">' + escapeHtml(err.message) + '</div>';
            }
        }

        async function runTeacherSuggestions() {
            if (!startDateEl.value || !endDateEl.value) return;
            recListEl.innerHTML = '<div class="text-muted text-sm">Đang tính toán điểm gợi ý 0–100...</div>';
            try {
                const res = await window.HuitApp.apiFetch(baseUrl + '/api/suggest-teachers?' + buildQuery());
                const rec = res.data.recommended || [];
                const unavail = res.data.unavailable || [];
                const selectedTid = parseInt(teacherEl.value, 10) || 0;

                recCountEl.textContent = rec.length;
                unavailCountEl.textContent = unavail.length;

                if (rec.length === 0) {
                    recListEl.innerHTML = '<div class="text-muted text-sm">Không có giảng viên rảnh trong khung giờ này.</div>';
                } else {
                    recListEl.innerHTML = rec.map(function (t) {
                        const isSelected = t.teacher_id === selectedTid;
                        const scoreCls = t.tier === 'excellent' ? 'score-excellent' : (t.tier === 'good' ? 'score-good' : 'score-fair');
                        const barColor = t.tier === 'excellent' ? '#059669' : (t.tier === 'good' ? '#1d4ed8' : '#b45309');
                        const badgesHtml = (t.badges || []).map(b => '<span class="badge badge-primary">' + escapeHtml(b) + '</span>').join(' ');
                        const warnHtml = (t.warnings || []).map(w => {
                            const wCls = w.severity === 'critical' ? 'badge-danger' : 'badge-warning';
                            return '<div class="mt-1"><span class="badge ' + wCls + '">Lưu ý: ' + escapeHtml(w.message) + '</span></div>';
                        }).join('');

                        const dayCtx = (t.day_context && t.day_context.length > 0)
                            ? '<div class="text-xs text-muted mt-1"><strong>Lịch trong ngày:</strong> ' +
                              t.day_context.map(c =>
                                  '<span class="badge badge-neutral" style="margin-right:4px;">' +
                                  escapeHtml(c.branch_code + ' | ' + c.shift + ' (' + c.subject_name + ')') +
                                  '</span>'
                              ).join('') + '</div>'
                            : '<div class="text-xs text-muted mt-1">Lịch trong ngày: Chưa có ca nào</div>';

                        return (
                            '<div class="rec-card ' + (isSelected ? 'selected' : '') + '">' +
                                '<div class="rec-header">' +
                                    '<div>' +
                                        '<strong>' + escapeHtml(t.full_name) + '</strong>' +
                                        '<div class="text-xs text-muted">' + escapeHtml(t.expertise) + '</div>' +
                                    '</div>' +
                                    '<div class="flex items-center gap-1">' +
                                        '<span class="score-pill ' + scoreCls + '">' + t.score + '/100</span>' +
                                        '<button type="button" class="btn btn-xs ' + (isSelected ? 'btn-success' : 'btn-primary') + ' btn-pick-teacher" data-tid="' + t.teacher_id + '">' +
                                            (isSelected ? 'Đang chọn' : 'Chọn nhanh') +
                                        '</button>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="score-bar-track"><div class="score-bar-fill" style="width:' + t.score + '%;background:' + barColor + ';"></div></div>' +
                                '<div class="breakdown-grid">' +
                                    '<div class="breakdown-cell"><span>Chuyên môn</span><strong>' + t.breakdown.expertise + '/40</strong></div>' +
                                    '<div class="breakdown-cell"><span>Tiết trống</span><strong>' + t.breakdown.availability + '/25</strong></div>' +
                                    '<div class="breakdown-cell"><span>Tối ưu ĐC</span><strong>' + (t.breakdown.optimization >= 0 ? '+' : '') + t.breakdown.optimization + '</strong></div>' +
                                    '<div class="breakdown-cell"><span>Tải (' + t.assigned_periods + '/' + t.max_periods_per_week + 't)</span><strong>' + t.breakdown.workload + '/15</strong></div>' +
                                '</div>' +
                                (badgesHtml ? '<div class="flex gap-1 flex-wrap">' + badgesHtml + '</div>' : '') +
                                warnHtml +
                                dayCtx +
                            '</div>'
                        );
                    }).join('');

                    recListEl.querySelectorAll('.btn-pick-teacher').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            teacherEl.value = btn.getAttribute('data-tid');
                            triggerLiveInspectors();
                            window.HuitApp.toast('Đã chọn giảng viên từ danh sách gợi ý.');
                        });
                    });
                }

                if (unavail.length === 0) {
                    unavailListEl.innerHTML = '<div class="text-muted text-sm">Không có giảng viên nào bị bận trong khung giờ này.</div>';
                } else {
                    unavailListEl.innerHTML = unavail.map(function (u) {
                        return (
                            '<div class="rec-card" style="background:#fef2f2;border-color:#fecaca;">' +
                                '<div class="flex justify-between items-center">' +
                                    '<strong>' + escapeHtml(u.full_name) + '</strong>' +
                                    '<span class="badge badge-danger">Đang bận</span>' +
                                '</div>' +
                                '<div class="text-xs text-muted mt-1">' + escapeHtml(u.reason) + '</div>' +
                            '</div>'
                        );
                    }).join('');
                }
            } catch (err) {
                recListEl.innerHTML = '<div class="alert alert-warning">' + escapeHtml(err.message) + '</div>';
            }
        }

        async function runAvailableRooms() {
            if (!roomsBox) return;
            const bid = parseInt(branchEl.value, 10) || 0;
            if (!bid) {
                roomsBox.innerHTML = '<span class="text-xs text-muted">Chọn cơ sở để tải danh sách phòng trống...</span>';
                return;
            }
            try {
                const res = await window.HuitApp.apiFetch(baseUrl + '/api/available-rooms?' + buildQuery());
                const avail = res.data.available || [];
                const occ   = res.data.occupied || [];
                const currentRoom = roomEl.value.trim().toUpperCase();

                let html = avail.map(function (r) {
                    const activeCls = r.room === currentRoom ? ' active' : '';
                    return '<button type="button" class="room-chip' + activeCls + '" data-room="' + escapeHtml(r.room) + '" title="' + escapeHtml(r.type) + ' (Trống)">' +
                        escapeHtml(r.room) + ' <span style="opacity:.7;font-weight:500;">(' + (r.type === 'Phòng máy' ? 'PM' : 'LT') + ')</span></button>';
                }).join('');

                if (occ.length > 0) {
                    html += occ.map(function (r) {
                        return '<span class="room-chip occupied" title="Đang bận: ' + escapeHtml(r.subject_name + ' - ' + r.teacher_name) + '">' + escapeHtml(r.room) + ' (Bận)</span>';
                    }).join('');
                }

                roomsBox.innerHTML = html || '<span class="text-xs text-muted">Không có dữ liệu phòng tại cơ sở này.</span>';
                roomsBox.querySelectorAll('button.room-chip').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        roomEl.value = btn.getAttribute('data-room');
                        triggerLiveInspectors();
                    });
                });
            } catch (err) {
                roomsBox.innerHTML = '<span class="text-xs text-muted">' + escapeHtml(err.message) + '</span>';
            }
        }

        const triggerLiveInspectors = debounce(function () {
            runConflictInspector();
            runTeacherSuggestions();
            runAvailableRooms();
        }, 250);

        [subjectEl, branchEl, roomEl, teacherEl, dayEl, startDateEl, endDateEl].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input', triggerLiveInspectors);
            el.addEventListener('change', triggerLiveInspectors);
        });

        if (suggestSlotsBtn) {
            suggestSlotsBtn.addEventListener('click', async function () {
                const tid = parseInt(teacherEl.value, 10) || 0;
                const bid = parseInt(branchEl.value, 10) || 0;
                if (!tid || !bid) {
                    window.HuitApp.toast('Vui lòng chọn Giảng viên và Cơ sở trước khi tìm 5 ô tối ưu.');
                    return;
                }
                const duration = Math.max(1, (parseInt(endPeriodEl.value, 10) || 3) - (parseInt(startPeriodEl.value, 10) || 1) + 1);
                slotsResultBox.innerHTML = '<div class="text-sm text-muted">Đang tìm Top 5 khung tiết tối ưu...</div>';
                try {
                    const res = await window.HuitApp.apiFetch(baseUrl + '/api/suggest-slots?' + buildQuery({ duration: String(duration) }));
                    const slots = res.data.slots || [];
                    if (slots.length === 0) {
                        slotsResultBox.innerHTML = '<div class="text-sm text-muted">Không tìm thấy khung giờ trống phù hợp.</div>';
                        return;
                    }
                    slotsResultBox.innerHTML = slots.map(function (s, idx) {
                        return (
                            '<div class="rec-card" style="padding:0.65rem 0.8rem;">' +
                                '<div class="flex justify-between items-center gap-2">' +
                                    '<div>' +
                                        '<strong>#' + (idx + 1) + ' | ' + escapeHtml(s.day_label) + ' — ' + escapeHtml(s.shift_label) + '</strong>' +
                                        '<div class="text-xs text-muted">' + escapeHtml(s.time_range) + ' | ' + escapeHtml((s.reasons || []).join(' | ')) + '</div>' +
                                    '</div>' +
                                    '<div class="flex items-center gap-1">' +
                                        '<span class="badge badge-success">' + s.score + 'đ</span>' +
                                        '<button type="button" class="btn btn-xs btn-primary btn-apply-slot" ' +
                                            'data-day="' + s.day_of_week + '" data-sp="' + s.start_period + '" data-ep="' + s.end_period + '">Áp dụng</button>' +
                                    '</div>' +
                                '</div>' +
                            '</div>'
                        );
                    }).join('');

                    slotsResultBox.querySelectorAll('.btn-apply-slot').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            dayEl.value = btn.getAttribute('data-day');
                            startPeriodEl.value = btn.getAttribute('data-sp');
                            endPeriodEl.value = btn.getAttribute('data-ep');
                            syncShiftLabelFromPeriods();
                            triggerLiveInspectors();
                            window.HuitApp.toast('Đã điền khung tiết tối ưu vào biểu mẫu.');
                        });
                    });
                } catch (err) {
                    slotsResultBox.innerHTML = '<div class="alert alert-error">' + escapeHtml(err.message) + '</div>';
                }
            });
        }

        syncShiftLabelFromPeriods(false);
        runConflictInspector();
        runTeacherSuggestions();
        runAvailableRooms();
    });
})();
