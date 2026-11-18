/**
 * HUIT Schedule Hub — Dashboard Chart.js 4 & Dynamic Timeline Visualizations
 * Dynamic Horizontal Bar (Teacher Workload + Quota), Doughnut (Branch Colors), Stacked Bar (Day x Session), Gantt Timeline
 */
(function () {
    'use strict';

    let teacherChart = null;
    let branchChart = null;
    let daySessionChart = null;

    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderCharts(chartsData) {
        if (typeof Chart === 'undefined' || !chartsData) return;

        // 1. Horizontal Bar: Teacher Workload vs Quota
        const tCanvas = document.getElementById('chart-teacher-workload');
        if (tCanvas) {
            if (teacherChart) teacherChart.destroy();
            const tw = chartsData.teacher_workload;
            teacherChart = new Chart(tCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: tw.labels,
                    datasets: [
                        {
                            label: 'Số tiết đã phân công',
                            data: tw.assigned,
                            backgroundColor: tw.assigned.map((v, idx) =>
                                v > (tw.max[idx] || 18) ? 'rgba(220, 38, 38, 0.85)' : 'rgba(29, 78, 216, 0.85)'
                            ),
                            borderRadius: 6,
                            barThickness: 14
                        },
                        {
                            label: 'Định mức tuần tối đa',
                            data: tw.max,
                            backgroundColor: 'rgba(148, 163, 184, 0.35)',
                            borderColor: 'rgba(100, 116, 139, 0.7)',
                            borderWidth: 1,
                            borderRadius: 6,
                            barThickness: 14
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 12 } } }
                    },
                    scales: {
                        x: { beginAtZero: true, title: { display: true, text: 'Số tiết / tuần' } },
                        y: { ticks: { font: { size: 11 } } }
                    }
                }
            });
        }

        // 2. Doughnut: Branch Distribution with campus color_hex
        const bCanvas = document.getElementById('chart-branch-distribution');
        if (bCanvas) {
            if (branchChart) branchChart.destroy();
            const bd = chartsData.branch_distribution;
            branchChart = new Chart(bCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: bd.labels,
                    datasets: [{
                        label: 'Số lớp học phần',
                        data: bd.counts,
                        backgroundColor: bd.colors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11.5 } } },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    const idx = ctx.dataIndex;
                                    const clsCount = bd.counts[idx] || 0;
                                    const pCount = bd.periods[idx] || 0;
                                    return ` ${ctx.label}: ${clsCount} lớp (${pCount} tiết)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // 3. Stacked Bar: Day x Session Density
        const dCanvas = document.getElementById('chart-day-sessions');
        if (dCanvas) {
            if (daySessionChart) daySessionChart.destroy();
            const ds = chartsData.day_sessions;
            daySessionChart = new Chart(dCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ds.labels,
                    datasets: [
                        { label: 'Buổi Sáng (T1–6)',   data: ds.morning,   backgroundColor: '#2563eb', borderRadius: 4 },
                        { label: 'Buổi Chiều (T7–12)', data: ds.afternoon, backgroundColor: '#f59e0b', borderRadius: 4 },
                        { label: 'Buổi Tối (T13–15)',  data: ds.evening,   backgroundColor: '#7c3aed', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 12 } } }
                    },
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: 'Số lớp học' } }
                    }
                }
            });
        }
    }

    function renderTimeline(timeline, baseUrl) {
        const bodyEl = document.getElementById('timeline-card-body');
        const titleEl = document.getElementById('timeline-teacher-title');
        const portalLinkEl = document.getElementById('timeline-portal-link');
        if (!bodyEl || !timeline || !timeline.days) return;

        if (titleEl) {
            titleEl.textContent = 'Dòng thời gian Phân bổ (07:00 – 20:30): ' + (timeline.teacher_name || '');
        }
        if (portalLinkEl && timeline.teacher_id) {
            portalLinkEl.href = baseUrl + '/portal?teacher_id=' + encodeURIComponent(timeline.teacher_id);
        }

        const ticksHtml = (timeline.ticks || []).map(tk =>
            '<span class="timeline-tick align-' + esc(tk.align) + '" style="left:' + esc(tk.left_pct) + '%;">' + esc(tk.label) + '</span>'
        ).join('');

        const gridlinesHtml = (timeline.ticks || [])
            .filter(tk => tk.left_pct > 0 && tk.left_pct < 100)
            .map(tk => '<span class="timeline-gridline" style="left:' + esc(tk.left_pct) + '%;"></span>')
            .join('');

        const rowsHtml = timeline.days.map(function (tDay) {
            const gapsHtml = (tDay.gaps || []).map(function (g) {
                if (g.is_narrow) {
                    return '<div class="timeline-gap-marker ' + esc(g.type) + '" style="left:' + esc(g.left_pct) + '%;width:' + esc(g.width_pct) + '%;" title="' + esc(g.label) + '"></div>';
                }
                return '<div class="timeline-gap ' + esc(g.type) + '" style="left:' + esc(g.left_pct) + '%;width:' + esc(g.width_pct) + '%;" title="' + esc(g.label) + '">' + esc(g.label) + '</div>';
            }).join('');

            const blocksHtml = (tDay.blocks || []).map(function (blk) {
                const title = blk.subject_name + ' | ' + blk.branch_code + ' P.' + blk.room + ' (' + blk.time_range + ')';
                return '<a href="' + esc(baseUrl + '/schedules/edit/' + blk.id) + '" class="timeline-block" ' +
                    'style="left:' + esc(blk.left_pct) + '%;width:' + esc(blk.width_pct) + '%;background:' + esc(blk.branch_color) + ';" title="' + esc(title) + '">' +
                    '<strong>' + esc(blk.branch_code + ' · ' + blk.room) + '</strong>' +
                    '<span style="opacity:.92;">' + esc(blk.subject_name) + '</span></a>';
            }).join('');

            const narrowGaps = (tDay.gaps || []).filter(g => g.is_narrow);
            const notesHtml = narrowGaps.length > 0
                ? '<div class="timeline-day-notes"><div></div><div class="flex gap-1 flex-wrap">' +
                  narrowGaps.map(ng => '<span class="badge ' + (ng.type === 'impossible_travel' ? 'badge-danger' : 'badge-warning') + '">' + esc(ng.label) + '</span>').join('') +
                  '</div></div>'
                : '';

            return '<div class="timeline-row-group">' +
                '<div class="timeline-row">' +
                    '<div class="timeline-day-label">' + esc(tDay.label) + '</div>' +
                    '<div class="timeline-track">' + gridlinesHtml + gapsHtml + blocksHtml + '</div>' +
                '</div>' +
                notesHtml +
            '</div>';
        }).join('');

        bodyEl.innerHTML =
            '<div class="timeline-header"><div class="text-xs text-muted font-bold">Thứ / Giờ</div><div class="timeline-ticks">' + ticksHtml + '</div></div>' +
            rowsHtml;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.HUIT_INITIAL_CHARTS) {
            renderCharts(window.HUIT_INITIAL_CHARTS);
        }

        const liveBtn = document.getElementById('btn-refresh-charts');
        const filterForm = document.getElementById('dashboard-filter-form');
        if (liveBtn && filterForm) {
            liveBtn.addEventListener('click', async function () {
                const baseUrl = filterForm.getAttribute('data-base-url') || '';
                const params = new URLSearchParams(new FormData(filterForm));
                try {
                    const res = await window.HuitApp.apiFetch(baseUrl + '/api/dashboard-stats?' + params.toString());
                    renderCharts(res.data.charts);
                    if (res.data.timeline) {
                        renderTimeline(res.data.timeline, baseUrl);
                    }
                    if (res.data.kpi) {
                        const k = res.data.kpi;
                        const schedEl = document.getElementById('kpi-schedules');
                        const perEl   = document.getElementById('kpi-periods');
                        const hlthEl  = document.getElementById('kpi-health');
                        const alertEl = document.getElementById('kpi-alerts');

                        if (schedEl) schedEl.textContent = String(k.total_schedules);
                        if (perEl) perEl.textContent = k.total_periods + ' tiết / tuần';
                        if (hlthEl) {
                            hlthEl.textContent = k.health_score + '%';
                            hlthEl.style.color = k.health_score >= 80 ? '#059669' : (k.health_score >= 60 ? '#b45309' : '#dc2626');
                        }
                        if (alertEl) {
                            alertEl.textContent = String(k.alert_count);
                            alertEl.style.color = k.alert_count > 0 ? '#dc2626' : '#059669';
                        }
                    }
                    window.HuitApp.toast('Đã làm mới biểu đồ, dòng thời gian và chỉ số tối ưu.');
                } catch (err) {
                    window.HuitApp.toast('Lỗi tải biểu đồ: ' + err.message);
                }
            });
        }
    });
})();
