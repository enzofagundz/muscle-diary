import { describe, expect, it } from 'vitest';

import {
    adjustRest,
    alertDue,
    formatClock,
    isDone,
    isPaused,
    markAlerted,
    pauseRest,
    remainingSeconds,
    resumeRest,
    startRest,
} from './rest-timer';

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

    it('freezes the count while paused and resumes from where it stopped', () => {
        const state = startRest(90, 1_000);

        pauseRest(state, 31_000);

        expect(isPaused(state)).toBe(true);
        expect(remainingSeconds(state, 31_000)).toBe(60);
        expect(remainingSeconds(state, 45_000)).toBe(60);

        resumeRest(state, 45_000);

        expect(isPaused(state)).toBe(false);
        expect(remainingSeconds(state, 45_000)).toBe(60);
        expect(remainingSeconds(state, 50_000)).toBe(55);
    });

    it('adjusts the running rest in 15 second steps', () => {
        const state = startRest(90, 0);

        adjustRest(state, 15, 30_000);

        expect(remainingSeconds(state, 30_000)).toBe(75);

        adjustRest(state, -30, 30_000);

        expect(remainingSeconds(state, 30_000)).toBe(45);
    });

    it('adjusts the frozen remaining while paused', () => {
        const state = startRest(90, 0);

        pauseRest(state, 30_000);
        adjustRest(state, -15, 40_000);

        expect(remainingSeconds(state, 40_000)).toBe(45);

        adjustRest(state, 15, 40_000);

        expect(remainingSeconds(state, 40_000)).toBe(60);
    });

    it('keeps the duration inside the allowed range and ends at zero', () => {
        const state = startRest(90, 0);

        adjustRest(state, 100_000, 0);

        expect(remainingSeconds(state, 0)).toBe(3600);

        adjustRest(state, -100_000, 0);

        expect(remainingSeconds(state, 0)).toBe(0);
        expect(isDone(state, 0)).toBe(true);
    });

    it('asks for a new alert when the rest is extended after it ended', () => {
        const state = startRest(30, 0);

        expect(alertDue(state, 31_000)).toBe(true);

        markAlerted(state);
        adjustRest(state, 15, 31_000);

        expect(alertDue(state, 31_000)).toBe(false);
        expect(alertDue(state, 46_000)).toBe(true);
    });
});
