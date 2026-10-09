<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import EnumBadge from '@/components/EnumBadge.vue';
import MemberAvatar from '@/components/members/MemberAvatar.vue';
import { useOrganization } from '@/composables/useOrganization';
import { formatDateTime, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as itemRoute } from '@/routes/inventory/items';
import { show as showPurchase } from '@/routes/purchase-requests';
import type { MovementData } from '@/types/inventory';

/**
 * Rows of the stock ledger: what moved, where, by whom, and the balance after.
 */
withDefaults(
    defineProps<{
        movements: MovementData[];
        /** Show which item moved (the ledger); off on an item's own page. */
        showItem?: boolean;
    }>(),
    { showItem: false },
);

const { organization } = useOrganization();

/** Where it happened: every movement has at least one location. */
function place(movement: MovementData): string {
    return movement.to ?? movement.from ?? '';
}
</script>

<template>
    <ol class="divide-y">
        <li
            v-for="movement in movements"
            :key="movement.id"
            class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-5"
        >
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="figures text-xs text-muted-foreground">{{
                        movement.reference
                    }}</span>
                    <EnumBadge :option="movement.type" variant="plain" />
                    <Link
                        v-if="showItem && movement.item"
                        :href="itemRoute({ item: movement.item.id })"
                        class="min-w-0 truncate text-sm font-medium hover:underline"
                    >
                        <span
                            class="figures text-xs font-normal text-muted-foreground"
                            >{{ movement.item.sku }}</span
                        >
                        {{ movement.item.name }}
                    </Link>
                </div>
                <p
                    class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                >
                    <span
                        v-if="movement.from && movement.to"
                        class="inline-flex items-center gap-1"
                    >
                        {{ movement.from }}
                        <ArrowRight class="size-3" aria-label="to" />
                        {{ movement.to }}
                    </span>
                    <span v-else>{{ place(movement) }}</span>
                    <template v-if="movement.performer !== undefined">
                        <span aria-hidden="true">·</span>
                        <span
                            v-if="movement.performer"
                            class="inline-flex items-center gap-1.5"
                        >
                            <MemberAvatar
                                :name="movement.performer.name"
                                :avatar="movement.performer.avatar"
                                class="size-4 text-[0.5rem]"
                            />
                            {{ movement.performer.name }}
                        </span>
                        <span v-else>By a workflow</span>
                    </template>
                    <span aria-hidden="true">·</span>
                    <time
                        :datetime="movement.occurred_at"
                        :title="
                            formatDateTime(
                                movement.occurred_at,
                                organization?.timezone,
                            )
                        "
                        >{{ timeAgo(movement.occurred_at) }}</time
                    >
                    <template v-if="movement.reference_note">
                        <span aria-hidden="true">·</span>
                        <span>Ref {{ movement.reference_note }}</span>
                    </template>
                    <template v-if="movement.purchase_request_id">
                        <span aria-hidden="true">·</span>
                        <Link
                            :href="
                                showPurchase({
                                    purchaseRequest:
                                        movement.purchase_request_id,
                                })
                            "
                            class="font-medium text-primary hover:underline"
                            >Purchase request</Link
                        >
                    </template>
                </p>
                <p v-if="movement.notes" class="mt-1 text-sm text-pretty">
                    {{ movement.notes }}
                </p>
            </div>
            <div
                class="flex items-baseline gap-3 sm:flex-col sm:items-end sm:gap-0.5"
            >
                <span
                    :class="
                        cn(
                            'figures text-sm font-semibold whitespace-nowrap',
                            movement.change > 0
                                ? 'text-success-text'
                                : movement.change < 0
                                  ? 'text-danger-text'
                                  : 'text-muted-foreground',
                        )
                    "
                    >{{ movement.change_label }}</span
                >
                <span
                    class="figures text-xs whitespace-nowrap text-muted-foreground"
                    >{{ movement.stock_after_label }} after</span
                >
            </div>
        </li>
    </ol>
</template>
