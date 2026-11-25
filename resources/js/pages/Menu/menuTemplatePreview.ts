export type MenuTemplateId = 'classic' | 'minimal' | 'editorial' | 'modern' | 'cafe' | 'contrast';
export type PreviewViewport = 'mobile' | 'desktop';

type MenuTemplatePreviewStyle = {
    frame: string;
    header: string;
    product: string;
};

const previewStyles: Record<MenuTemplateId, MenuTemplatePreviewStyle> = {
    classic: {
        frame: 'rounded-xl border border-stone-200 bg-white shadow-sm',
        header: 'rounded-lg bg-stone-100',
        product: 'rounded-lg border border-stone-200 bg-white shadow-sm',
    },
    minimal: {
        frame: 'rounded-none border-0 bg-white shadow-none',
        header: 'rounded-none border-b border-stone-200 bg-white',
        product: 'rounded-none border-0 bg-white shadow-none',
    },
    editorial: {
        frame: 'rounded-none border-y-2 border-stone-300 bg-white',
        header: 'rounded-none border-b-2 border-stone-300 bg-white',
        product: 'rounded-none border-b border-stone-300 bg-white shadow-none',
    },
    modern: {
        frame: 'rounded-2xl border-2 border-stone-200 bg-white shadow-lg',
        header: 'rounded-xl bg-stone-100',
        product: 'rounded-xl border border-stone-200 bg-white shadow-md',
    },
    cafe: {
        frame: 'rounded-[1.5rem] border border-amber-200 bg-amber-50 shadow-sm',
        header: 'rounded-2xl bg-amber-100',
        product: 'rounded-xl border border-amber-200 bg-white shadow-sm',
    },
    contrast: {
        frame: 'rounded-md border-2 border-stone-900 bg-white shadow-md',
        header: 'rounded-none border-b-2 border-stone-900 bg-white',
        product: 'rounded-md border-2 border-stone-900 bg-white shadow-none',
    },
};

export function getMenuTemplatePreview(
    template: string,
    viewport: PreviewViewport,
): MenuTemplatePreviewStyle & { viewport: string } {
    const templateId = Object.hasOwn(previewStyles, template)
        ? (template as MenuTemplateId)
        : 'classic';

    return {
        ...previewStyles[templateId],
        viewport: viewport === 'mobile' ? 'w-full max-w-40' : 'w-full max-w-full',
    };
}

export function getMenuTemplateIds(): MenuTemplateId[] {
    return Object.keys(previewStyles) as MenuTemplateId[];
}
