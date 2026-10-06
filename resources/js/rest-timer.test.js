import { describe, expect, it } from 'vitest';

import { alertDue, formatClock, isDone, markAlerted, remainingSeconds, startRest } from './rest-timer';

describe('rest timer', () => {
    it('counts down from the duration using the start instant', () => {
        const state = startRest(90, 1_000);

        expect(remainingSeconds(state, 1_000)).toBe(90);
        expect(remainingSeconds(state, 31_000)).toBe(60);
        expect(remainingSeconds(state, 61_000)).toBe(30);
    });

    it('stops at zero and reports the rest as done', () => {
        const state = startRest(60, 1_000);

        expect(remainingSeconds(state, 61_000)).toBe(0);
        expect(isDone(state, 60_999)).toBe(false);
        expect(isDone(state, 61_000)).toBe(true);
        expect(isDone(state, 90_000)).toBe(true);
    });

    it('formats the clock as mm:ss', () => {
        expect(formatClock(0)).toBe('00:00');
        expect(formatClock(5)).toBe('00:05');
        expect(formatClock(90)).toBe('01:30');
        expect(formatClock(3600)).toBe('60:00');
    });

    it('asks for a single alert when the rest reaches zero', () => {
        const state = startRest(30, 1_000);

        expect(alertDue(state, 29_000)).toBe(false);
        expect(alertDue(state, 31_000)).toBe(true);

        markAlerted(state);

        expect(alertDue(state, 32_000)).toBe(false);
        expect(alertDue(state, 29_000)).toBe(false);
    });
});
