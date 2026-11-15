import { describe, expect, it } from 'vitest';
import { useJalaliDate } from './useJalaliDate';

describe('useJalaliDate', () => {
    const { formatDate, formatDateTime } = useJalaliDate();

    it.each([
        ['2025-03-20T00:00:00Z', '۱۴۰۳/۱۲/۳۰'],
        ['2025-03-21T00:00:00Z', '۱۴۰۴/۰۱/۰۱'],
        ['2026-03-21T00:00:00Z', '۱۴۰۵/۰۱/۰۱'],
    ])('formats %s using the Persian calendar', (value, expected) => {
        expect(formatDate(value)).toBe(expected);
    });

    it('uses Tehran time and Persian digits for date-time output', () => {
        expect(formatDateTime('2025-03-20T20:00:00Z')).toContain('۱۴۰۳/۱۲/۳۰');
    });

    it('returns an empty string for missing or invalid input', () => {
        expect(formatDate(null)).toBe('');
        expect(formatDate('not-a-date')).toBe('');
    });
});
