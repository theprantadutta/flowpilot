<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Ban,
    MoreHorizontal,
    RotateCcw,
    ShieldCheck,
    UserMinus,
} from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ChangeRoleDialog from '@/components/members/ChangeRoleDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { destroy, update } from '@/routes/members';
import type { MemberRow, RoleOption } from '@/types/members';

const props = defineProps<{
    member: MemberRow;
    roles: RoleOption[];
}>();

const roleDialogOpen = ref(false);
const suspendDialogOpen = ref(false);
const removeDialogOpen = ref(false);
const processing = ref(false);

function setStatus(status: 'active' | 'suspended') {
    router.visit(update({ member: props.member.id }), {
        data: { status },
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            suspendDialogOpen.value = false;
        },
    });
}

function remove() {
    router.visit(destroy({ member: props.member.id }), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            removeDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <div class="flex justify-end">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="`Actions for ${member.name}`"
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-48">
                <DropdownMenuItem
                    v-if="member.can.update"
                    @select="roleDialogOpen = true"
                >
                    <ShieldCheck />
                    Change role
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="member.can.update && member.status === 'active'"
                    @select="suspendDialogOpen = true"
                >
                    <Ban />
                    Suspend access
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="member.can.update && member.status === 'suspended'"
                    @select="setStatus('active')"
                >
                    <RotateCcw />
                    Restore access
                </DropdownMenuItem>
                <template v-if="member.can.delete">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        @select="removeDialogOpen = true"
                    >
                        <UserMinus />
                        Remove from organization
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <ChangeRoleDialog
            v-model:open="roleDialogOpen"
            :member="member"
            :roles="roles"
        />

        <ConfirmDialog
            v-model:open="suspendDialogOpen"
            :title="`Suspend ${member.name}?`"
            description="They keep their account but cannot open this organization until you restore their access. Work assigned to them stays where it is."
            confirm-label="Suspend access"
            destructive
            :processing="processing"
            @confirm="setStatus('suspended')"
        />

        <ConfirmDialog
            v-model:open="removeDialogOpen"
            :title="`Remove ${member.name}?`"
            description="They lose access to this organization straight away. Their past activity and the work they created are kept. You can invite them again later."
            confirm-label="Remove member"
            destructive
            :processing="processing"
            @confirm="remove"
        />
    </div>
</template>
