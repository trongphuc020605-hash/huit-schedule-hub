/**
 * HUIT Schedule Hub — Shared UI Utilities
 * Toast notifications, modal controllers, ESC close, copy-to-clipboard, CSRF fetch wrapper, mobile drawer
 */
(function () {
    'use strict';

    window.HuitApp = {
        getCsrfToken: function () {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },

        apiFetch: async function (url, options = {}) {
            const headers = Object.assign(
                { 'Accept': 'application/json', 'X-CSRF-Token': this.getCsrfToken() },
                options.headers || {}
            );
            const res = await fetch(url, Object.assign({}, options, { headers }));
            const contentType = res.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('Phản hồi máy chủ không hợp lệ (HTTP ' + res.status + '). Vui lòng tải lại trang.');
            }
            const data = await res.json();
            if (!res.ok || data.ok === false) {
                throw new Error(data.error || ('Lỗi HTTP ' + res.status));
            }
            return data;
        },

        toast: function (message, duration = 3000) {
            let container = document.getElementById('huit-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'huit-toast-container';
                container.className = 'toast-container';
                document.body.appendChild(container);
            }
            const el = document.createElement('div');
            el.className = 'toast';
            el.textContent = message;
            container.appendChild(el);
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transition = 'opacity 0.25s';
                setTimeout(() => el.remove(), 260);
            }, duration);
        },

        openModal: function (id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('open');
        },

        closeModal: function (id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.remove('open');
        },

        copyText: async function (text, successMsg = 'Đã sao chép vào bộ nhớ tạm.') {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    ta.remove();
                }
                this.toast(successMsg);
            } catch (err) {
                this.toast('Không thể sao chép tự động. Vui lòng copy thủ công.');
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const text = btn.getAttribute('data-copy') || '';
                const msg = btn.getAttribute('data-copy-msg') || 'Đã sao chép đường dẫn Portal cá nhân.';
                window.HuitApp.copyText(text, msg);
            });
        });

        document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                window.HuitApp.openModal(btn.getAttribute('data-open-modal'));
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = btn.getAttribute('data-close-modal');
                if (target) {
                    window.HuitApp.closeModal(target);
                } else {
                    const backdrop = btn.closest('.modal-backdrop');
                    if (backdrop) backdrop.classList.remove('open');
                }
            });
        });

        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) backdrop.classList.remove('open');
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.open').forEach(function (m) {
                    m.classList.remove('open');
                });
                const sidebar = document.getElementById('app-sidebar');
                if (sidebar) sidebar.classList.remove('open');
            }
        });

        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                const msg = form.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn thực hiện thao tác này?';
                if (!window.confirm(msg)) {
                    e.preventDefault();
                }
            });
        });

        const toggleBtn = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('app-sidebar');
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                sidebar.classList.toggle('open');
            });
            document.addEventListener('click', function (e) {
                if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggleBtn) {
                    sidebar.classList.remove('open');
                }
            });
        }
    });
})();
