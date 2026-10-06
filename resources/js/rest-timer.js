export function startRest(duration, now = Date.now()) {
    return { duration: Math.max(0, Math.round(duration)), startedAt: now };
}

export function remainingSeconds(state, now = Date.now()) {
    const elapsed = (now - state.startedAt) / 1000;

    return Math.max(0, Math.ceil(state.duration - elapsed));
}

export function isDone(state, now = Date.now()) {
    return remainingSeconds(state, now) === 0;
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

        update() {
            if (this.state === null) {
                return;
            }

            this.remaining = remainingSeconds(this.state);

            if (isDone(this.state)) {
                window.clearInterval(this.interval);
                this.interval = null;
            }
        },

        get display() {
            return formatClock(this.remaining);
        },

        get done() {
            return this.state !== null && isDone(this.state);
        },
    };
}
