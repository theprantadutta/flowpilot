import type { Tone } from './ui';

export type ActivityEntry = {
    id: string;
    action: string;
    actor: string;
    actor_type: 'user' | 'system' | 'ai' | 'workflow' | 'platform';
    summary: string;
    subject: string | null;
    tone: Tone;
    icon: string;
    created_at: string;
    changes: Record<string, { from: unknown; to: unknown }>;
};

export type NotificationItem = {
    id: string;
    type: string;
    title: string;
    body: string | null;
    url: string | null;
    tone: Tone;
    actor: string | null;
    read: boolean;
    created_at: string | null;
};
