<script setup lang="ts">
defineProps<{
    message?: string;
    id?: string;
}>();
</script>

<template>
    <div v-show="message" aria-live="polite">
        <p :id="id" class="text-sm text-danger-text">
            {{ message }}
        </p>
    </div>
</template>
