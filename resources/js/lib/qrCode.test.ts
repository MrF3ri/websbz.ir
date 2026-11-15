import { describe, expect, it } from 'vitest';
import { createQrSvg } from './qrCode';

describe('createQrSvg', () => {
    it('creates a scalable SVG QR code for a public menu URL', () => {
        const svg = createQrSvg('https://websbz.ir/menu/example/main');

        expect(svg).toContain('<svg');
        expect(svg).toContain('viewBox');
        expect(svg).toContain('</svg>');
    });

    it('rejects an empty URL', () => {
        expect(() => createQrSvg('  ')).toThrow('A non-empty URL is required');
    });
});
