<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { update } from '@/routes/members';
import type { MemberRow, RoleOption } from '@/types/members';

defineProps<{
    member: MemberRow;
    roles: RoleOption[];
}>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Change role for {{ member.name }}</DialogTitle>
                <DialogDescription>
                    Their access changes as soon as you save. You can only give
                    roles below your own.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="update.form({ member: member.id })"
                :options="{ preserveScroll: true }"
                class="grid gap-4"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <fieldset class="grid gap-2">
                    <legend class="sr-only">Role</legend>
                    <label
                        v-for="role in roles.filter((role) => role.assignable)"
                        :key="role.value"
                        :class="
                            cn(
                                'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors hover:bg-accent/60',
                                'has-[:checked]:border-primary has-[:checked]:bg-info-soft/60',
                                'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                            )
                        "
                    >
                        <input
                            type="radio"
                            name="role"
                            :value="role.value"
                            :checked="role.value === member.role"
                            class="mt-0.5 size-4 accent-primary"
                        />
                        <span class="grid gap-0.5">
                            <span class="text-sm font-medium">{{
                                role.label
                            }}</span>
                            <span class="text-xs text-muted-foreground">{{
                                role.description
                            }}</span>
                        </span>
                    </label>
                </fieldset>
                <InputError :message="errors.role" />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save role
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
