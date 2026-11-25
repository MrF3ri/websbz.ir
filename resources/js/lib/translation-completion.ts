export type TranslationCompletionInput = {
    menuTitle: string;
    categories: { name: string }[];
    products: {
        name: string;
        description: string;
        sourceDescription: string | null;
    }[];
};

export type TranslationCompletion = {
    completed: number;
    total: number;
    missing: number;
    percent: number;
};

export function getTranslationCompletion(input: TranslationCompletionInput): TranslationCompletion {
    const descriptionsToTranslate = input.products.filter(
        (product) => product.sourceDescription?.trim().length,
    ).length;
    const total = 1 + input.categories.length + input.products.length + descriptionsToTranslate;
    const completed = Number(input.menuTitle.trim().length > 0)
        + input.categories.filter((category) => category.name.trim().length > 0).length
        + input.products.reduce((count, product) => {
            const nameComplete = Number(product.name.trim().length > 0);
            const descriptionRequired = Boolean(product.sourceDescription?.trim().length);
            const descriptionComplete = descriptionRequired
                ? Number(product.description.trim().length > 0)
                : 0;

            return count + nameComplete + descriptionComplete;
        }, 0);

    return {
        completed,
        total,
        missing: total - completed,
        percent: total === 0 ? 100 : Math.round((completed / total) * 100),
    };
}
