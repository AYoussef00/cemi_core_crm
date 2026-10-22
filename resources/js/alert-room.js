/**
 * Floating alert room — shows in-app notifications (no chat input).
 * Polls the authenticated feed; plays a soft chime on new arrivals.
 */

const STORAGE_OPEN = 'cemi.alertRoom.open';
const STORAGE_MUTE = 'cemi.alertRoom.mute';

function csrfToken(root) {
    return root.dataset.csrf
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

let sharedAudioCtx = null;

function unlockAudio() {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (! Ctx) return null;
        if (! sharedAudioCtx) {
            sharedAudioCtx = new Ctx();
        }
        if (sharedAudioCtx.state === 'suspended') {
            sharedAudioCtx.resume().catch(() => {});
        }
        return sharedAudioCtx;
    } catch {
        return null;
    }
}

/**
 * Soft glassy tri-tone (Apple-like notification preview).
 * Synthesized — no third-party audio assets.
 */
function playNotifyChime() {
    try {
        const ctx = unlockAudio();
        if (! ctx) return;

        const now = ctx.currentTime;
        const master = ctx.createGain();
        master.gain.setValueAtTime(0.2, now);
        master.connect(ctx.destination);

        // Ascending glass notes similar to classic Apple tri-tone character.
        const notes = [
            { freq: 1046.5, at: 0 },
            { freq: 1318.5, at: 0.11 },
            { freq: 1568.0, at: 0.22 },
        ];

        notes.forEach(({ freq, at }) => {
            const start = now + at;

            const fundamental = ctx.createOscillator();
            const fundGain = ctx.createGain();
            fundamental.type = 'sine';
            fundamental.frequency.setValueAtTime(freq, start);
            fundGain.gain.setValueAtTime(0.0001, start);
            fundGain.gain.exponentialRampToValueAtTime(0.42, start + 0.012);
            fundGain.gain.exponentialRampToValueAtTime(0.0001, start + 0.52);
            fundamental.connect(fundGain);
            fundGain.connect(master);
            fundamental.start(start);
            fundamental.stop(start + 0.55);

            const overtone = ctx.createOscillator();
            const overGain = ctx.createGain();
            overtone.type = 'sine';
            overtone.frequency.setValueAtTime(freq * 2.01, start);
            overGain.gain.setValueAtTime(0.0001, start);
            overGain.gain.exponentialRampToValueAtTime(0.09, start + 0.01);
            overGain.gain.exponentialRampToValueAtTime(0.0001, start + 0.3);
            overtone.connect(overGain);
            overGain.connect(master);
            overtone.start(start);
            overtone.stop(start + 0.35);
        });
    } catch {
        // Audio may be blocked until a user gesture; ignore.
    }
}

function renderItems(stream, items, readUrlTemplate) {
    if (! items.length) {
        stream.innerHTML = `
            <div class="alert-room-empty" role="status">
                <strong>لا تنبيهات بعد</strong>
                <p>ستظهر هنا إشعارات حسابك فور وصولها — الأحدث أولًا.</p>
            </div>`;
        return;
    }

    stream.innerHTML = items.map((item) => {
        const unread = item.unread ? 'is-unread' : 'is-read';
        const category = item.category
            ? `<span class="alert-room-chip">${escapeHtml(item.category)}</span>`
            : '';
        const body = item.body_ar
            ? `<p class="alert-room-item-body">${escapeHtml(item.body_ar)}</p>`
            : '';
        const action = item.action_url
            ? `<a class="alert-room-item-link" href="${escapeHtml(item.action_url)}">عرض التفاصيل</a>`
            : '';
        const markBtn = item.unread
            ? `<button type="button" class="alert-room-mark" data-id="${escapeHtml(item.id)}">تعليم كمقروء</button>`
            : '';

        return `
            <article class="alert-room-item ${unread}" data-id="${escapeHtml(item.id)}">
                <div class="alert-room-item-rail" aria-hidden="true"></div>
                <div class="alert-room-item-main">
                    <div class="alert-room-item-meta">
                        <span class="alert-room-chip ${item.unread ? 'is-new' : ''}">${item.unread ? 'جديد' : 'مقروء'}</span>
                        ${category}
                        <time datetime="${escapeHtml(item.created_at || '')}">${escapeHtml(item.created_label || '')}</time>
                    </div>
                    <h3 class="alert-room-item-title">${escapeHtml(item.title_ar)}</h3>
                    ${body}
                    <div class="alert-room-item-actions">
                        ${action}
                        ${markBtn}
                    </div>
                </div>
            </article>`;
    }).join('');

    stream.querySelectorAll('.alert-room-mark').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            if (id) {
                markRead(stream.closest('#alert-room'), id, readUrlTemplate);
            }
        });
    });
}

function updateUnreadUi(root, count) {
    const badge = root.querySelector('#alert-room-fab-badge');
    const unreadEl = root.querySelector('#alert-room-unread');
    const readAll = root.querySelector('#alert-room-read-all');
    const fab = root.querySelector('#alert-room-fab');
    const n = Math.max(0, Number(count) || 0);

    if (unreadEl) {
        unreadEl.textContent = n > 0 ? `${n} غير مقروء` : 'الكل مقروء';
    }
    if (readAll) {
        readAll.disabled = n < 1;
    }
    if (badge) {
        if (n < 1) {
            badge.hidden = true;
        } else {
            badge.hidden = false;
            badge.textContent = String(Math.min(n, 99));
        }
    }
    if (fab) {
        fab.setAttribute(
            'aria-label',
            n > 0 ? `فتح غرفة التنبيهات، ${n} غير مقروء` : 'فتح غرفة التنبيهات',
        );
    }
}

async function fetchFeed(root) {
    const url = root.dataset.feedUrl;
    if (! url) return null;

    const res = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (! res.ok) return null;
    return res.json();
}

async function markRead(root, id, readUrlTemplate) {
    const url = (readUrlTemplate || root.dataset.readUrlTemplate || '').replace('__ID__', encodeURIComponent(id));
    if (! url) return;

    await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(root),
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: '{}',
    });

    await refresh(root, { silent: true });
}

async function markAllRead(root) {
    const url = root.dataset.readAllUrl;
    if (! url) return;

    await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(root),
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: '{}',
    });

    await refresh(root, { silent: true });
}

async function refresh(root, { silent = false } = {}) {
    const stream = root.querySelector('#alert-room-stream');
    const loading = root.querySelector('#alert-room-loading');
    const readUrlTemplate = root.dataset.readUrlTemplate || '';

    try {
        const data = await fetchFeed(root);
        if (! data) return;

        const prevLatest = root.dataset.latestId || '';
        const nextLatest = data.latest_id || '';
        const muted = root.dataset.muted === '1';
        const hadBaseline = root.dataset.ready === '1';

        if (! silent && hadBaseline && nextLatest && nextLatest !== prevLatest && ! muted) {
            playNotifyChime();
            root.classList.add('has-pulse');
            window.setTimeout(() => root.classList.remove('has-pulse'), 1200);
        }

        root.dataset.latestId = nextLatest || '';
        root.dataset.ready = '1';
        updateUnreadUi(root, data.unread_count);
        if (loading) loading.remove();
        renderItems(stream, data.items || [], readUrlTemplate);
    } catch {
        if (loading) {
            loading.textContent = 'تعذر تحديث التنبيهات.';
        }
    }
}

function setOpen(root, open) {
    const panel = root.querySelector('#alert-room-panel');
    const fab = root.querySelector('#alert-room-fab');
    if (! panel || ! fab) return;

    if (open) {
        panel.hidden = false;
        root.classList.add('is-open');
        fab.setAttribute('aria-expanded', 'true');
        try { sessionStorage.setItem(STORAGE_OPEN, '1'); } catch {}
        refresh(root, { silent: true });
    } else {
        panel.hidden = true;
        root.classList.remove('is-open');
        fab.setAttribute('aria-expanded', 'false');
        try { sessionStorage.setItem(STORAGE_OPEN, '0'); } catch {}
    }
}

function setMuted(root, muted) {
    root.dataset.muted = muted ? '1' : '0';
    const btn = root.querySelector('#alert-room-mute');
    const sound = btn?.querySelector('.alert-room-ico-sound');
    const mute = btn?.querySelector('.alert-room-ico-mute');
    if (btn) {
        btn.setAttribute('aria-pressed', muted ? 'true' : 'false');
        btn.title = muted ? 'تشغيل الصوت ومعاينة التنبيه' : 'معاينة صوت التنبيه / كتم';
        btn.setAttribute(
            'aria-label',
            muted ? 'تشغيل صوت التنبيه ومعاينته' : 'معاينة صوت التنبيه أو كتمه',
        );
    }
    if (sound) sound.hidden = muted;
    if (mute) mute.hidden = ! muted;
    try { sessionStorage.setItem(STORAGE_MUTE, muted ? '1' : '0'); } catch {}
}

export function initAlertRoom() {
    const root = document.getElementById('alert-room');
    if (! root) return;

    const fab = root.querySelector('#alert-room-fab');
    const closeBtn = root.querySelector('#alert-room-close');
    const muteBtn = root.querySelector('#alert-room-mute');
    const readAllBtn = root.querySelector('#alert-room-read-all');
    const pollMs = Math.max(10000, Number(root.dataset.pollMs) || 20000);

    let preferOpen = true;
    let preferMute = false;
    try {
        const storedOpen = sessionStorage.getItem(STORAGE_OPEN);
        if (storedOpen === '0') preferOpen = false;
        if (storedOpen === '1') preferOpen = true;
        preferMute = sessionStorage.getItem(STORAGE_MUTE) === '1';
    } catch {}

    setMuted(root, preferMute);
    setOpen(root, preferOpen);
    refresh(root, { silent: true });

    const unlockOnce = () => unlockAudio();
    document.addEventListener('pointerdown', unlockOnce, { once: true, passive: true });

    fab?.addEventListener('click', () => {
        const open = ! root.classList.contains('is-open');
        setOpen(root, open);
        unlockAudio();
    });
    closeBtn?.addEventListener('click', () => setOpen(root, false));
    muteBtn?.addEventListener('click', () => {
        unlockAudio();
        // Preview the same chime used when a new alert arrives.
        playNotifyChime();
        setMuted(root, root.dataset.muted !== '1');
    });
    readAllBtn?.addEventListener('click', () => markAllRead(root));

    window.setInterval(() => refresh(root), pollMs);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refresh(root, { silent: true });
        }
    });
}
