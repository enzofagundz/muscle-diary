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

function clamp(seconds, min, max) {
    return Math.min(Math.max(seconds, min), max);
}

export function formatClock(seconds) {
    const safe = Math.max(0, Math.round(seconds));
    const minutes = Math.floor(safe / 60);
    const rest = safe % 60;

    return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

export function restTimer() {
    return {
        running: false,
        label: '',
        state: null,
        remaining: 0,
        interval: null,

        start(duration, label = '') {
            if (!Number.isFinite(duration) || duration <= 0) {
                return;
            }

            this.label = label;
            this.state = startRest(duration);
            this.running = true;

            this.update();

            window.clearInterval(this.interval);
            this.interval = window.setInterval(() => this.update(), 1000);
        },

        close() {
            this.running = false;
            this.state = null;

            window.clearInterval(this.interval);
            this.interval = null;
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

            this.update();
        },

        adjust(delta) {
            if (this.state === null) {
                return;
            }

            adjustRest(this.state, delta);

            this.update();
        },

        update() {
            if (this.state === null) {
                return;
            }

            this.remaining = remainingSeconds(this.state);

            if (isDone(this.state)) {
                this.alert();

                window.clearInterval(this.interval);
                this.interval = null;
            }
        },

        alert() {
            if (!alertDue(this.state)) {
                return;
            }

            markAlerted(this.state);

            navigator.vibrate?.(300);
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
