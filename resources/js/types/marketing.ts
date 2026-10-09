/** The public site: plans, FAQs and legal pages. */

export type PlanCard = {
    value: string;
    label: string;
    tagline: string;
    price: string | null;
    monthly_price: number | null;
    rank: number;
    limits: { label: string; included: boolean }[];
    features: { value: string; label: string; included: boolean }[];
};

export type FaqItem = { question: string; answer: string };

export type Trial = { days: number; plan: string };

export type PlanComparison = {
    plans: string[];
    limits: { label: string; values: (string | null)[] }[];
    features: { label: string; included: boolean[] }[];
};

export type LegalDocument = {
    title: string;
    updated: string;
    html: string;
    sections: { id: string; title: string }[];
};
