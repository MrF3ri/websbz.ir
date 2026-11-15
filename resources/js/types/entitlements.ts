export type EntitlementFeatureValue = boolean | number;

export type Entitlements = {
    plan_code: string;
    status: string;
    trial_ends_at: string | null;
    features: Record<string, EntitlementFeatureValue>;
};
