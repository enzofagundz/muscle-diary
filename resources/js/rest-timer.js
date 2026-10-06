export const REST_MAX_SECONDS = 3600;

export function startRest(duration, now = Date.now()) {
    return {
        duration: clamp(Math.round(duration), 0, REST_MAX_SECONDS),
        startedAt: now,
        pausedRemaining: null,
        alerted: false,
    };
}

export function remainingSeconds(state, now = Date.now()) {
    if (state.pausedRemaining !== null) {
        return Math.max(0, state.pausedRemaining);
    }

    const elapsed = (now - state.startedAt) / 1000;

    return Math.max(0, Math.ceil(state.duration - elapsed));
}

export function isDone(state, now = Date.now()) {
    return remainingSeconds(state, now) === 0;
}

export function isPaused(state) {
    return state.pausedRemaining !== null;
}

export function pauseRest(state, now = Date.now()) {
    if (isPaused(state)) {
        return;
    }

    state.pausedRemaining = remainingSeconds(state, now);
}

export function resumeRest(state, now = Date.now()) {
    if (! isPaused(state)) {
        return;
    }

    state.startedAt = now - (state.duration - state.pausedRemaining) * 1000;
    state.pausedRemaining = null;
}

export function adjustRest(state, delta, now = Date.now()) {
    state.duration = clamp(state.duration + delta, 0, REST_MAX_SECONDS);

    if (isPaused(state)) {
        state.pausedRemaining = clamp(state.pausedRemaining + delta, 0, REST_MAX_SECONDS);
    }

    if (state.alerted && ! isDone(state, now)) {
        state.alerted = false;
    }
}

export function alertDue(state, now = Date.now()) {
    return isDone(state, now) && !state.alerted;
}

export function markAlerted(state) {
    state.alerted = true;
}

export function formatClock(seconds) {
    const safe = Math.max(0, Math.round(seconds));
    const minutes = Math.floor(safe / 60);
    const rest = safe % 60;

    return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

export function serializeRest({ state, label }) {
    return JSON.stringify({
        duration: state.duration,
        startedAt: state.startedAt,
        pausedRemaining: state.pausedRemaining,
        alerted: state.alerted,
        label: label ?? '',
    });
}

export function restoreRest(raw) {
    if (typeof raw !== 'string' || raw === '') {
        return null;
    }

    let data;

    try {
        data = JSON.parse(raw);
    } catch {
        return null;
    }

    if (typeof data !== 'object' || data === null) {
        return null;
    }

    if (! Number.isFinite(data.duration) || data.duration <= 0 || ! Number.isFinite(data.startedAt)) {
        return null;
    }

    if (data.pausedRemaining !== null && data.pausedRemaining !== undefined && ! Number.isFinite(data.pausedRemaining)) {
        return null;
    }

    return {
        state: {
            duration: clamp(data.duration, 0, REST_MAX_SECONDS),
            startedAt: data.startedAt,
            pausedRemaining: data.pausedRemaining === null || data.pausedRemaining === undefined
                ? null
                : clamp(data.pausedRemaining, 0, REST_MAX_SECONDS),
            alerted: data.alerted === true,
        },
        label: typeof data.label === 'string' ? data.label : '',
    };
}

function clamp(seconds, min, max) {
    return Math.min(Math.max(seconds, min), max);
}

export function restTimer({ sessionId } = {}) {
    return {
        running: false,
        label: '',
        state: null,
        remaining: 0,
        interval: null,
        storageKey: sessionId ? `rest-timer:${sessionId}` : null,

        init() {
            this.handleVisibility = () => {
                if (document.visibilityState === 'visible') {
                    this.refresh();
                }
            };

            document.addEventListener('visibilitychange', this.handleVisibility);
            window.addEventListener('pageshow', this.handleVisibility);

            const stored = this.read();

            if (stored === null) {
                return;
            }

            this.label = stored.label;
            this.state = stored.state;
            this.running = true;

            this.refresh();
        },

        destroy() {
            document.removeEventListener('visibilitychange', this.handleVisibility);
            window.removeEventListener('pageshow', this.handleVisibility);

            this.stopTicking();
        },

        start(duration, label = '') {
            if (! Number.isFinite(duration) || duration <= 0) {
                return;
            }

            this.label = label;
            this.state = startRest(duration);
            this.running = true;

            this.refresh();
            this.persist();
        },

        close() {
            this.running = false;
            this.state = null;

            this.stopTicking();
            this.clear();
        },

        togglePause() {
            if (this.state === null) {
                return;
            }

            if (isPaused(this.state)) {
                resumeRest(this.state);
            } else {
                pauseRest(this.state);
            }

            this.refresh();
            this.persist();
        },

        adjust(delta) {
            if (this.state === null) {
                return;
            }

            adjustRest(this.state, delta);

            this.refresh();
            this.persist();
        },

        refresh() {
            if (this.state === null) {
                return;
            }

            this.update();

            if (isDone(this.state)) {
                this.stopTicking();
            } else {
                this.ensureTicking();
            }
        },

        update() {
            if (this.state === null) {
                return;
            }

            this.remaining = remainingSeconds(this.state);

            if (isDone(this.state)) {
                this.alert();
            }
        },

        alert() {
            if (! alertDue(this.state)) {
                return;
            }

            markAlerted(this.state);

            navigator.vibrate?.(300);

            this.persist();
        },

        ensureTicking() {
            this.stopTicking();

            this.interval = window.setInterval(() => {
                this.update();

                if (isDone(this.state)) {
                    this.stopTicking();
                }
            }, 1000);
        },

        stopTicking() {
            window.clearInterval(this.interval);
            this.interval = null;
        },

        persist() {
            const storage = this.storage();

            if (storage === null || this.storageKey === null || this.state === null) {
                return;
            }

            storage.setItem(this.storageKey, serializeRest({ state: this.state, label: this.label }));
        },

        read() {
            const storage = this.storage();

            if (storage === null || this.storageKey === null) {
                return null;
            }

            return restoreRest(storage.getItem(this.storageKey));
        },

        clear() {
            const storage = this.storage();

            if (storage === null || this.storageKey === null) {
                return;
            }

            storage.removeItem(this.storageKey);
        },

        storage() {
            try {
                return window.sessionStorage;
            } catch {
                return null;
            }
        },

        get display() {
            return formatClock(this.remaining);
        },

        get paused() {
            return this.state !== null && isPaused(this.state);
        },

        get done() {
            return this.state !== null && this.remaining === 0;
        },
    };
}
