/* Photo Backup Organizer front-end behaviour
 * - drag-and-drop uploads with per-file progress (XHR)
 * - delete with undo toast (short recovery window)
 * - bulk select + delete
 * - dialogs (native <dialog>), rename / delete confirmations
 * - photo zoom controls and debounced filters
 */

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const flashRegion = document.getElementById('flash-region');

function postJson(url, method = 'POST', body = {}) {
    return fetch(url, {
        method,
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
    }).then(async (res) => {
        const isJson = (res.headers.get('content-type') || '').includes('application/json');

        if (!res.ok) {
            const data = isJson ? await res.json() : {};
            const errorMessages = (() => {
                if (!data?.errors) return [];
                return Array.isArray(data.errors) ? data.errors : Object.values(data.errors).flat();
            })();
            const message = errorMessages[0] ?? data?.message ?? 'Something went wrong.';
            throw new Error(message);
        }

        return isJson ? res.json() : {};
    });
}

/* ---------------- Toasts ---------------- */

function showToast(message, { actionLabel, actionUrl, actionMethod = 'POST', onAction, tone = '', duration = 12000 } = {}) {
    if (!flashRegion) return;

    const toast = document.createElement('div');
    toast.className = `toast${tone ? ` toast--${tone}` : ''}`;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');

    const text = document.createElement('p');
    text.className = 'toast__message';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast__close';
    close.innerHTML = '&times;';
    close.setAttribute('aria-label', 'Dismiss message');
    close.addEventListener('click', () => toast.remove());

    toast.append(text);

    if (actionLabel && actionUrl) {
        const action = document.createElement('button');
        action.type = 'button';
        action.className = 'btn btn--sm toast__action';
        action.textContent = actionLabel;
        action.addEventListener('click', () => {
            action.disabled = true;
            postJson(actionUrl, actionMethod)
                .then(() => {
                    toast.remove();
                    if (typeof onAction === 'function') onAction();
                })
                .catch((err) => {
                    action.disabled = false;
                    showToast(err.message, { tone: 'error' });
                });
        });
        toast.append(action);
    }

    toast.append(close);
    flashRegion.append(toast);

    const timeout = window.setTimeout(() => toast.remove(), duration);
    toast.addEventListener('mouseenter', () => window.clearTimeout(timeout));
}

/* ---------------- Uploads ---------------- */

/* A batch is uploaded in chunks (max 10 files or ~96MB per request) so large
 * selections never exceed PHP's post_max_size, and each file reports its own
 * result. Failures keep their reason and a Retry button re-uploads just them. */
const UPLOAD_CHUNK_SIZE = 10;
const UPLOAD_CHUNK_BYTES = 96 * 1024 * 1024;

function initUploads() {
    const zones = document.querySelectorAll('[data-upload-zone]');
    if (!zones.length) return;

    zones.forEach((zone) => {
        const target = zone.querySelector('#drop-target');
        const input = zone.querySelector('#photo-input');
        const picker = zone.querySelector('#pick-files');
        const list = zone.querySelector('#upload-list');
        const albumId = zone.dataset.album || '';
        const url = zone.dataset.url;

        if (!target || !input || !picker || !list) return;

        const state = {
            queue: [],
            draining: false,
            batch: null,
            keySeq: 0,
        };

        const openPicker = () => input.click();

        picker.addEventListener('click', openPicker);
        target.addEventListener('click', (e) => {
            if (e.target.closest('button')) return;
            openPicker();
        });
        target.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openPicker();
            }
        });

        ['dragenter', 'dragover'].forEach((evt) => {
            target.addEventListener(evt, (e) => {
                e.preventDefault();
                target.classList.add('is-dragging');
            });
        });
        ['dragleave', 'drop'].forEach((evt) => {
            target.addEventListener(evt, (e) => {
                e.preventDefault();
                target.classList.remove('is-dragging');
            });
        });
        target.addEventListener('drop', (e) => {
            const files = Array.from(e.dataTransfer?.files || []);
            if (files.length) upload(files);
        });

        input.addEventListener('change', () => {
            const files = Array.from(input.files || []);
            if (files.length) upload(files);
            input.value = '';
        });

        function upload(files) {
            const batch = state.batch || (state.batch = { total: 0, entries: new Set() });

            files.forEach((file) => {
                const entry = createUploadItem(file, list, {
                    nextKey: () => String(++state.keySeq),
                    onRetry: (e) => retryEntry(e),
                });
                entry.batch = batch;
                batch.total += 1;
                batch.entries.add(entry);
                state.queue.push(entry);
            });

            drain();
        }

        function retryEntry(entry) {
            if (!state.batch) {
                state.batch = { total: 0, entries: new Set() };
            }
            const batch = state.batch;
            entry.settled = null;
            if (entry.batch !== batch) {
                batch.total += 1;
                batch.entries.add(entry);
                entry.batch = batch;
            }
            state.queue.push(entry);
            entry.setQueued();
            drain();
        }

        async function drain() {
            if (state.draining) return;
            state.draining = true;

            try {
                while (state.queue.length) {
                    const chunk = [];
                    let size = 0;

                    while (state.queue.length) {
                        const entry = state.queue[0];
                        if (chunk.length >= UPLOAD_CHUNK_SIZE) break;
                        if (chunk.length && size + entry.file.size > UPLOAD_CHUNK_BYTES) break;
                        state.queue.shift();
                        chunk.push(entry);
                        size += entry.file.size;
                    }

                    await uploadChunk(chunk);
                }
            } finally {
                state.draining = false;
            }

            if (!state.queue.length && state.batch) showSummary(state.batch);
        }

        function settleEntry(entry, ok, message) {
            if (entry.settled) return;
            entry.settled = ok ? 'done' : 'failed';
            if (ok) entry.setDone();
            else entry.setError(message || 'Upload failed.');
        }

        function uploadChunk(entries) {
            return new Promise((resolve) => {
                if (!entries.length) {
                    resolve();
                    return;
                }

                const formData = new FormData();
                entries.forEach((entry) => {
                    formData.append('photos[]', entry.file);
                    formData.append('keys[]', entry.key);
                });
                if (albumId) formData.append('album_id', albumId);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', url);
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                xhr.setRequestHeader('Accept', 'application/json');

                xhr.upload.addEventListener('progress', (e) => {
                    if (!e.lengthComputable) return;
                    const pct = Math.round((e.loaded / e.total) * 100);
                    entries.forEach((entry) => entry.setProgress(pct));
                });

                xhr.addEventListener('load', () => {
                    let data = null;
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch {
                        data = null;
                    }

                    if (xhr.status >= 200 && xhr.status < 300 && data) {
                        (data.uploaded || []).forEach((p) => {
                            const entry = entries.find((e) => e.key === p.key);
                            if (!entry) return;
                            settleEntry(entry, true);
                            refreshGrid([p]);
                        });

                        (data.failed || []).forEach((f) => {
                            const entry = entries.find((e) => e.key === f.key);
                            if (!entry) return;
                            settleEntry(entry, false, f.error || data.message || 'Upload failed.');
                        });

                        (data.duplicates || []).forEach((f) => {
                            const entry = entries.find((e) => e.key === f.key);
                            if (!entry) return;
                            settleEntry(entry, false, f.error || 'This photo is already backed up.');
                        });

                        entries.forEach((entry) => {
                            if (!entry.settled) {
                                settleEntry(entry, false, data.message || 'Upload failed.');
                            }
                        });
                    } else {
                        const message = data?.message
                            || (xhr.status === 422 ? 'The upload was rejected. Please try again.'
                                : `Upload failed (HTTP ${xhr.status}). Please try again.`);
                        entries.forEach((entry) => {
                            if (!entry.settled) settleEntry(entry, false, message);
                        });
                        if (data?.message) showToast(data.message, { tone: 'error' });
                    }

                    resolve();
                });

                xhr.addEventListener('error', () => {
                    entries.forEach((entry) => {
                        if (!entry.settled) {
                            settleEntry(entry, false, 'Upload failed. Check your connection and try again.');
                        }
                    });
                    resolve();
                });

                xhr.send(formData);
            });
        }

        function showSummary(batch) {
            state.batch = null;

            let ok = 0;
            let fail = 0;
            batch.entries.forEach((entry) => {
                if (entry.settled === 'done') ok++;
                else if (entry.settled === 'failed') fail++;
            });

            const total = batch.total;
            const message = `${ok} of ${total} uploaded successfully${fail ? `, ${fail} failed` : ''}.`;
            showToast(message, { tone: fail > 0 ? 'error' : '' });
        }
    });
}

function createUploadItem(file, list, { nextKey, onRetry }) {
    const entry = {
        key: nextKey(),
        file,
        state: 'queued',
        settled: null,
        batch: null,
    };

    const li = document.createElement('li');
    li.className = 'upload-item';

    const name = document.createElement('span');
    name.className = 'upload-item__name';
    name.textContent = file.name;
    name.title = file.name;

    const track = document.createElement('div');
    track.className = 'upload-item__progress';
    const bar = document.createElement('div');
    bar.className = 'upload-item__bar';
    track.append(bar);

    const status = document.createElement('span');
    status.className = 'upload-item__status';
    status.textContent = 'Queued…';

    li.append(name, track, status);
    list.prepend(li);

    entry.el = li;
    entry.bar = bar;
    entry.status = status;

    entry.setProgress = (pct) => {
        entry.bar.style.width = `${pct}%`;
        entry.status.textContent = `${pct}%`;
    };

    entry.setQueued = () => {
        entry.bar.style.width = '0';
        entry.status.textContent = 'Queued…';
        entry.status.title = file.name;
        li.classList.remove('is-failed', 'is-done');
        bar.classList.remove('is-failed');
    };

    entry.setDone = () => {
        entry.status.textContent = 'Done';
        entry.status.title = file.name;
        entry.bar.style.width = '100%';
        li.classList.add('is-done');
        window.setTimeout(() => li.remove(), 2500);
    };

    entry.setError = (message) => {
        entry.status.textContent = String(message);
        entry.status.title = String(message);
        li.classList.add('is-failed');
        bar.classList.add('is-failed');

        if (entry.retryBtn) return;

        const retryBtn = document.createElement('button');
        retryBtn.type = 'button';
        retryBtn.className = 'btn btn--ghost btn--sm upload-item__retry';
        retryBtn.textContent = 'Retry';
        retryBtn.setAttribute('aria-label', `Retry upload of ${file.name}`);
        retryBtn.addEventListener('click', (e) => {
            e.preventDefault();
            retryBtn.disabled = true;
            retryBtn.classList.add('is-disabled');
            onRetry(entry);
        });
        li.append(retryBtn);
        entry.retryBtn = retryBtn;
    };

    return entry;
}

/* When photos are uploaded we rebuild the grid from the latest server data. */
function refreshGrid(uploaded) {
    const grid = document.getElementById('photo-grid');
    const emptyState = document.querySelector('.empty-state');
    const counter = document.querySelector('.photo-section__count');
    if (grid) grid.insertAdjacentHTML('afterbegin', uploaded.map((photo) => photoCardHtml(photo)).join(''));
    if (emptyState) emptyState.remove();
    if (counter) counter.textContent = `${Number((counter.textContent.match(/\d+/) || [0])[0]) + uploaded.length} photo(s)`;
}

function photoCardHtml(photo) {
    return `
    <figure class="photo-card" data-photo-id="${photo.id}">
      <a class="photo-card__media" href="/photos/${photo.id}">
        <img class="photo-card__img" src="${photo.thumbnail_url || photo.url}" alt="${escapeHtml(photo.description)}" loading="lazy">
      </a>
      <figcaption class="photo-card__caption">
        <span class="photo-card__date">Uploaded just now</span>
      </figcaption>
      <div class="photo-card__actions">
        <a class="btn btn--ghost btn--sm" href="/photos/${photo.id}">View &amp; zoom</a>
        <button type="button" class="btn btn--danger-ghost btn--sm js-delete-photo"
          data-url="/photos/${photo.id}" data-restore-url="/photos/${photo.id}/restore"
          aria-label="Move ${escapeHtml(photo.description)} to trash">Delete</button>
      </div>
    </figure>`;
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

/* ---------------- Delete with undo ---------------- */

function initDeletes() {
    document.addEventListener('click', (e) => {
        const button = e.target.closest('.js-delete-photo');
        if (!button) return;

        const card = button.closest('.photo-card');
        if (!card) return;

        e.preventDefault();
        button.disabled = true;

        postJson(button.dataset.url, 'DELETE')
            .then((data) => {
                card.classList.add('is-trashed');
                card.style.opacity = '0.5';
                card.querySelectorAll('button, a').forEach((el) => {
                    if (!el.closest('.js-delete-photo')) el.style.pointerEvents = 'none';
                });

                showToast(data?.message || 'Photo moved to trash.', {
                    actionLabel: 'Undo',
                    actionUrl: button.dataset.restoreUrl,
                    actionMethod: 'POST',
                    onAction: () => window.location.reload(),
                });
            })
            .catch((err) => {
                button.disabled = false;
                showToast(err.message, { tone: 'error' });
            });
    });
}

/* ---------------- Bulk selection ---------------- */

function initBulkSelect() {
    const bar = document.getElementById('bulk-bar');
    const count = document.getElementById('bulk-count');
    const grid = document.getElementById('photo-grid');
    if (!bar || !count) return;

    let hideTimer = null;

    const update = () => {
        const boxes = grid ? grid.querySelectorAll('.js-select-photo:checked') : [];
        const n = boxes.length;
        count.textContent = n;
        window.clearTimeout(hideTimer);

        if (n > 0) {
            const wasHidden = bar.hidden;
            bar.hidden = false;
            if (wasHidden) void bar.offsetWidth;
            bar.classList.add('is-visible');
        } else {
            bar.classList.remove('is-visible');
            hideTimer = window.setTimeout(() => {
                if (!bar.classList.contains('is-visible')) bar.hidden = true;
            }, 250);
        }
    };

    const clear = () => {
        grid?.querySelectorAll('.js-select-photo').forEach((box) => { box.checked = false; });
        update();
    };

    document.addEventListener('change', (e) => {
        if (e.target.classList?.contains('js-select-photo')) update();
    });

    document.getElementById('clear-selected')?.addEventListener('click', clear);

    document.getElementById('delete-selected')?.addEventListener('click', () => {
        const boxes = grid ? Array.from(grid.querySelectorAll('.js-select-photo:checked')) : [];
        if (!boxes.length) return;

        const dialog = document.getElementById('bulk-delete-dialog');
        const form = document.getElementById('bulk-delete-form');
        const name = document.getElementById('bulk-delete-name');

        if (dialog && dialog.showModal) {
            if (name) name.textContent = boxes.length;
            dialog.showModal();
            if (form) {
                form.onsubmit = (e) => {
                    e.preventDefault();
                    const urls = boxes.map((box) => box.closest('.photo-card')?.querySelector('.js-delete-photo')?.dataset.url).filter(Boolean);
                    Promise.all(urls.map((url) => postJson(url, 'DELETE')))
                        .then(() => {
                            boxes.forEach((box) => box.closest('.photo-card')?.remove());
                            dialog.close();
                            clear();
                            window.location.reload();
                        })
                        .catch((err) => showToast(err.message, { tone: 'error' }));
                };
            }
        } else {
            if (!window.confirm(`Delete ${boxes.length} selected photo(s)?`)) return;
            const urls = boxes.map((box) => box.closest('.photo-card')?.querySelector('.js-delete-photo')?.dataset.url).filter(Boolean);
            Promise.all(urls.map((url) => postJson(url, 'DELETE')))
                .then(() => {
                    boxes.forEach((box) => box.closest('.photo-card')?.remove());
                    clear();
                    window.location.reload();
                })
                .catch((err) => showToast(err.message, { tone: 'error' }));
        }
    });
}

/* ---------------- Dialogs ---------------- */

function initDialogs() {
    document.querySelectorAll('.js-dialog-close').forEach((btn) => {
        btn.addEventListener('click', () => {
            const dialog = document.getElementById(btn.dataset.target);
            if (dialog?.open) dialog.close();
        });
    });

    document.querySelectorAll('.js-rename-album').forEach((btn) => {
        btn.addEventListener('click', () => {
            const dialog = document.getElementById('rename-dialog');
            const form = document.getElementById('rename-form');
            const name = document.getElementById('rename-name');
            if (!dialog || !form || !name) return;
            form.action = btn.dataset.url;
            name.value = btn.dataset.name;
            dialog.showModal();
            name.focus();
            name.select();
        });
    });

    document.querySelectorAll('.js-delete-album').forEach((btn) => {
        btn.addEventListener('click', () => {
            const dialog = document.getElementById('delete-dialog');
            const form = document.getElementById('delete-form');
            const name = document.getElementById('delete-dialog-name');
            if (!dialog || !form || !name) return;
            form.action = btn.dataset.url;
            name.textContent = `"${btn.dataset.name}"`;
            dialog.showModal();
        });
    });

    document.querySelectorAll('.js-purge-photo').forEach((btn) => {
        btn.addEventListener('click', () => {
            const dialog = document.getElementById('purge-dialog');
            const form = document.getElementById('purge-form');
            const name = document.getElementById('purge-dialog-name');
            if (!dialog || !form || !name) return;
            form.action = btn.dataset.url;
            name.textContent = `"${btn.dataset.name}"`;
            dialog.showModal();
        });
    });

    document.querySelectorAll('.dialog').forEach((dialog) => {
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => {
            const focusTarget = dialog.querySelector('[data-return-focus]')
                || flashRegion;
            if (focusTarget) focusTarget.focus();
        });

        if (dialog.hasAttribute('data-force-open')) {
            dialog.showModal();
        }
    });
}

/* ---------------- Photo zoom ---------------- */

function initZoom() {
    const img = document.getElementById('zoom-image');
    const level = document.getElementById('zoom-level');
    if (!img || !level) return;

    const max = 4;
    let scale = 1;

    const apply = () => {
        img.style.transform = `scale(${scale})`;
        level.textContent = `${Math.round(scale * 100)}%`;
        level.setAttribute('aria-label', `Zoom level ${Math.round(scale * 100)} percent`);
    };

    document.getElementById('zoom-in')?.addEventListener('click', () => {
        scale = Math.min(scale + 0.5, max);
        apply();
    });
    document.getElementById('zoom-out')?.addEventListener('click', () => {
        scale = Math.max(scale - 0.5, 1);
        apply();
    });
    document.getElementById('zoom-reset')?.addEventListener('click', () => {
        scale = 1;
        apply();
    });

    document.addEventListener('keydown', (e) => {
        if (e.target.closest('input, textarea, select')) return;
        if (e.key === '+' || e.key === '=') {
            e.preventDefault();
            scale = Math.min(scale + 0.5, max);
            apply();
        } else if (e.key === '-') {
            e.preventDefault();
            scale = Math.max(scale - 0.5, 1);
            apply();
        } else if (e.key === '0') {
            scale = 1;
            apply();
        }
    });
}

/* ---------------- Debounced filters ---------------- */

function initFilters() {
    const form = document.getElementById('filter-form');
    if (!form) return;

    let timer = null;
    const submit = () => { timer = null; form.submit(); };

    ['from', 'to', 'tags'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(submit, 350);
        });
    });

    const search = document.getElementById('search');
    if (search) {
        search.addEventListener('search', () => form.submit());
    }
}

/* ---------------- Password visibility toggles ---------------- */

const EYE_ICON = '<svg class="password-toggle__icon password-toggle__icon--show" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
const EYE_OFF_ICON = '<svg class="password-toggle__icon password-toggle__icon--hide" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17.94 17.94A10.1 10.1 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.1 9.1 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

function initPasswordToggles() {
    document.querySelectorAll('input[type="password"]').forEach((input) => {
        if (input.dataset.passwordToggle === 'on' || input.disabled || input.readOnly) return;
        input.dataset.passwordToggle = 'on';

        if (!input.id) {
            input.id = `password-${Math.random().toString(36).slice(2, 8)}`;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'field__password';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-controls', input.id);
        button.setAttribute('aria-label', 'Show password');
        button.innerHTML = EYE_ICON + EYE_OFF_ICON;

        button.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            input.focus({ preventScroll: true });
        });

        wrapper.appendChild(button);
    });
}

/* ---------------- Mobile sidebar ---------------- */

function initSidebar() {
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!toggle || !sidebar) return;

    const mq = window.matchMedia('(max-width: 900px)');
    const openIcon = document.getElementById('sidebar-toggle-icon-open');
    const closeIcon = document.getElementById('sidebar-toggle-icon-close');
    let lastFocused = null;

    const setState = (isOpen) => {
        sidebar.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('sidebar-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        if (openIcon) openIcon.hidden = isOpen;
        if (closeIcon) closeIcon.hidden = !isOpen;
        backdrop?.classList.toggle('is-visible', isOpen);
    };

    const open = () => {
        if (sidebar.classList.contains('is-open')) return;
        lastFocused = document.activeElement;
        setState(true);
        sidebar.querySelector('.sidebar__nav a')?.focus({ preventScroll: true });
    };

    const close = (restoreFocus = true) => {
        if (!sidebar.classList.contains('is-open')) return;
        setState(false);
        if (restoreFocus && lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus({ preventScroll: true });
        }
    };

    toggle.addEventListener('click', () => {
        if (sidebar.classList.contains('is-open')) close();
        else open();
    });

    backdrop?.addEventListener('click', () => close());

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('is-open')) close();
    });

    sidebar.addEventListener('click', (e) => {
        if (e.target.closest('a') && mq.matches) close(false);
    });

    if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', (e) => {
            if (!e.matches) setState(false);
        });
    }
}

/* ---------------- Avatar upload ---------------- */

const AVATAR_MAX_BYTES = 5 * 1024 * 1024;
const AVATAR_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);

function initAvatar() {
    const input = document.querySelector('[data-avatar-input]');
    const form = document.getElementById('avatar-form');
    if (!input || !form) return;

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;

        if (!AVATAR_TYPES.has(file.type)) {
            showToast('Please choose a JPG, PNG, or WebP image.', { tone: 'error' });
            input.value = '';
            return;
        }

        if (file.size > AVATAR_MAX_BYTES) {
            showToast('The image must be 5MB or smaller.', { tone: 'error' });
            input.value = '';
            return;
        }

        form.submit();
    });
}

/* ---------------- Editable profile name ---------------- */

function initNameEditor() {
    const view = document.querySelector('[data-name-view]');
    const form = document.querySelector('[data-name-form]');
    const trigger = document.querySelector('[data-name-trigger]');
    const cancel = document.querySelector('[data-name-cancel]');
    const input = document.querySelector('[data-name-input]');
    const display = document.querySelector('[data-name-display]');
    const error = document.querySelector('[data-name-error]');
    if (!view || !form || !trigger || !cancel || !input || !display) return;

    const currentName = () => display.textContent.trim();

    const showForm = () => {
        view.hidden = true;
        form.hidden = false;
        input.value = currentName();
        error.hidden = true;
        input.removeAttribute('aria-invalid');
        input.focus();
        input.select();
    };

    const cancelEdit = () => {
        form.hidden = true;
        view.hidden = false;
        error.hidden = true;
        input.removeAttribute('aria-invalid');
        input.value = currentName();
        trigger.focus();
    };

    const showError = (message) => {
        error.textContent = message;
        error.hidden = false;
        input.setAttribute('aria-invalid', 'true');
        input.focus();
    };

    trigger.addEventListener('click', showForm);

    cancel.addEventListener('click', cancelEdit);

    form.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cancelEdit();
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const name = input.value.trim();
        if (!name) {
            showError('Please enter your name.');
            return;
        }

        const save = form.querySelector('[data-name-save]');
        save.disabled = true;

        postJson(form.action, 'PATCH', { name })
            .then((data) => {
                const saved = data?.user?.name || name;
                display.textContent = saved;
                form.hidden = true;
                view.hidden = false;
                showToast(data?.message || 'Your name has been updated.');
                document.querySelectorAll('.topbar__name, .sidebar__name').forEach((el) => {
                    el.textContent = saved;
                });
                trigger.focus();
            })
            .catch((err) => {
                save.disabled = false;
                showError(err.message);
            });
    });
}

/* ---------------- Registration agreement step ---------------- */

function initRegisterAgreement() {
    const box = document.querySelector('[data-terms-scroll]');
    const agree = document.querySelector('[data-terms-agree]');
    const hint = document.querySelector('[data-terms-hint]');
    if (!box || !agree) return;

    const SCROLL_TOLERANCE = 2;

    const atBottom = () => box.scrollTop + box.clientHeight >= box.scrollHeight - SCROLL_TOLERANCE;

    const sync = () => {
        const reached = atBottom();
        agree.disabled = !reached;
        agree.setAttribute('aria-disabled', String(!reached));
        if (hint) hint.hidden = reached;
    };

    box.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);

    sync();
    box.focus({ preventScroll: true });
}

/* ---------------- Toast feedback from server flashes ---------------- */

/* Server-rendered success flashes (album created/renamed/deleted, trash/restore,
 * profile updated, password changed, backup summary…) are promoted into the
 * stackable toast system so every action gets consistent, auto-dismissing
 * feedback. Validation error lists stay inline so they can be addressed. */
function initFlashToasts() {
    document.querySelectorAll('.flash--success').forEach((flash) => {
        const text = flash.querySelector('p')?.textContent?.trim() || flash.textContent.trim();
        if (!text) return;
        showToast(text, { tone: 'success', duration: 5000 });
        flash.remove();
    });
}

/* ---------------- Empty-state CTA buttons ---------------- */

function initEmptyStates() {
    document.querySelectorAll('.js-open-picker').forEach((btn) => {
        btn.addEventListener('click', () => {
            const picker = document.querySelector('[data-upload-zone] #pick-files');
            if (picker) picker.click();
        });
    });

    document.querySelectorAll('.js-focus-album-name').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById('album-name');
            if (!input) return;
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
            input.focus();
        });
    });
}

/* ---------------- Skeleton loading for grid images ---------------- */

function initImageSkeletons() {
    document.querySelectorAll('.photo-card__img').forEach((img) => {
        const media = img.closest('.photo-card__media');
        if (!media) return;
        if (img.complete && img.naturalWidth > 0) return;
        media.classList.add('photo-card__media--loading');

        const done = () => media.classList.remove('photo-card__media--loading');
        img.addEventListener('load', done, { once: true });
        img.addEventListener('error', done, { once: true });
    });
}

/* ---------------- Loading states on action buttons / forms ---------------- */

function initLoadingButtons() {
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
            btn.disabled = true;
            btn.classList.add('is-loading');
        });
    }, true);

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-loading]');
        if (!trigger) return;
        window.setTimeout(() => {
            trigger.disabled = true;
            trigger.classList.add('is-loading');
        }, 0);
    }, true);
}

/* ---------------- Top navigation progress bar ---------------- */

function initNavProgress() {
    let bar = document.getElementById('nav-progress');
    if (!bar) {
        bar = document.createElement('div');
        bar.id = 'nav-progress';
        bar.className = 'nav-progress';
        bar.innerHTML = '<span class="nav-progress__bar"></span>';
        document.body.prepend(bar);
    }

    let timer = null;
    const indicator = bar.querySelector('.nav-progress__bar');
    let progress = 0;

    const start = () => {
        if (indicator.classList.contains('is-active')) return;
        bar.classList.add('is-active');
        progress = 8;
        indicator.style.width = `${progress}%`;
        if (timer) window.clearInterval(timer);
        timer = window.setInterval(() => {
            progress = Math.min(progress + Math.random() * 14, 92);
            indicator.style.width = `${progress}%`;
        }, 220);
    };

    const finish = () => {
        if (timer) window.clearInterval(timer);
        indicator.style.width = '100%';
        window.setTimeout(() => {
            bar.classList.remove('is-active');
            progress = 0;
            indicator.style.width = '0';
        }, 320);
    };

    window.addEventListener('load', () => {
        if (timer) finish();
    });
    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) finish();
    });

    /* Any same-origin navigation attempt (internal links and form submits) gets
     * the subtle top bar while the browser loads the next page. */
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link || !link.href) return;
        if (link.target === '_blank' || link.download) return;
        if (link.hash && link.origin === window.location.origin) return;

        let url;
        try {
            url = new URL(link.href);
        } catch {
            return;
        }
        if (url.origin !== window.location.origin) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search) return;

        const uploading = document.querySelector('.upload-item:not(.is-done, .is-failed)');
        if (!uploading) start();
    }, true);

    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        start();
    });
}

/* ---------------- Storage warning dismissal ---------------- */

function initStorageWarning() {
    document.querySelectorAll('[data-storage-warning]').forEach((banner) => {
        const userId = banner.dataset.userId;
        const key = userId ? `storage-warning-dismissed-${userId}` : null;

        if (key && localStorage.getItem(key) === '1') {
            banner.hidden = true;
            banner.remove();
            return;
        }

        banner.querySelector('[data-storage-warning-dismiss]')?.addEventListener('click', () => {
            if (key) localStorage.setItem(key, '1');
            banner.remove();
        });
    });
}

/* ---------------- Download selected photos as ZIP ---------------- */

function initDownloadSelected() {
    const button = document.getElementById('download-selected');
    if (!button) return;

    button.addEventListener('click', () => {
        const grid = document.getElementById('photo-grid');
        const boxes = grid ? Array.from(grid.querySelectorAll('.js-select-photo:checked')) : [];

        if (!boxes.length) {
            showToast('Select at least one photo to download.', { tone: 'warning' });
            return;
        }

        const ids = boxes.map((box) => box.value).filter(Boolean);

        if (ids.length > 200) {
            showToast('Please select up to 200 photos at a time to download.', { tone: 'warning' });
            return;
        }

        button.disabled = true;
        button.classList.add('is-loading');

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = button.dataset.url;

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = csrfToken;
        form.append(csrf);

        ids.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.append(input);
        });

        form.style.display = 'none';
        document.body.append(form);
        form.submit();

        window.setTimeout(() => {
            button.disabled = false;
            button.classList.remove('is-loading');
            form.remove();
        }, 2500);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initUploads();
    initDeletes();
    initBulkSelect();
    initDialogs();
    initZoom();
    initFilters();
    initPasswordToggles();
    initAvatar();
    initNameEditor();
    initRegisterAgreement();
    initFlashToasts();
    initEmptyStates();
    initImageSkeletons();
    initLoadingButtons();
    initNavProgress();
    initStorageWarning();
    initDownloadSelected();
});