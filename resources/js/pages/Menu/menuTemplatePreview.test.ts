import { describe, expect, it } from 'vitest';
import { getMenuTemplateIds, getMenuTemplatePreview } from './menuTemplatePreview';

describe('menu template previews', () => {
    it('covers every registered template with a distinct visual frame', () => {
        const ids = getMenuTemplateIds();
        const frames = ids.map((id) => getMenuTemplatePreview(id, 'mobile').frame);

        expect(ids).toEqual(['classic', 'minimal', 'editorial', 'modern', 'cafe', 'contrast']);
        expect(new Set(frames).size).toBe(ids.length);

        for (const id of ids) {
            const preview = getMenuTemplatePreview(id, 'mobile');

            expect(preview.header).toBeTruthy();
            expect(preview.product).toBeTruthy();
        }
    });

    it('uses the compact mobile and fluid desktop preview widths', () => {
        for (const id of getMenuTemplateIds()) {
            expect(getMenuTemplatePreview(id, 'mobile').viewport).toBe('w-full max-w-40');
            expect(getMenuTemplatePreview(id, 'desktop').viewport).toBe('w-full max-w-full');
        }
    });

    it('falls back to the classic preview for unknown template identifiers', () => {
        expect(getMenuTemplatePreview('untrusted-template', 'mobile')).toEqual(
            getMenuTemplatePreview('classic', 'mobile'),
        );
    });
});
