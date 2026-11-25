import { describe, expect, it } from 'vitest';
import { getTranslationCompletion } from './translation-completion';

describe('getTranslationCompletion', () => {
    it('counts the menu title, category names, product names, and source descriptions', () => {
        expect(getTranslationCompletion({
            menuTitle: 'Coffee menu',
            categories: [{ name: 'Coffee' }],
            products: [{
                name: 'Espresso',
                description: 'A concentrated drink',
                sourceDescription: 'قهوه غلیظ',
            }],
        })).toEqual({
            completed: 4,
            total: 4,
            missing: 0,
            percent: 100,
        });
    });

    it('marks missing descriptions only when the source product has a description', () => {
        expect(getTranslationCompletion({
            menuTitle: 'Coffee menu',
            categories: [],
            products: [
                { name: 'Espresso', description: '', sourceDescription: 'قهوه' },
                { name: 'Tea', description: '', sourceDescription: null },
            ],
        })).toEqual({
            completed: 3,
            total: 4,
            missing: 1,
            percent: 75,
        });
    });

    it('updates progress when translated values are completed', () => {
        expect(getTranslationCompletion({
            menuTitle: 'Coffee menu',
            categories: [{ name: 'Coffee' }, { name: '' }],
            products: [{ name: 'Espresso', description: 'Strong coffee', sourceDescription: 'قهوه غلیظ' }],
        })).toEqual({
            completed: 4,
            total: 5,
            missing: 1,
            percent: 80,
        });
    });

    it('treats whitespace-only text as incomplete', () => {
        expect(getTranslationCompletion({
            menuTitle: '   ',
            categories: [{ name: '  ' }],
            products: [{ name: 'Espresso', description: '', sourceDescription: 'Source text' }],
        })).toEqual({
            completed: 1,
            total: 4,
            missing: 3,
            percent: 25,
        });
    });
});
