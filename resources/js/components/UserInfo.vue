<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    showRole?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    showRole: false,
});

const { getInitials } = useInitials();

const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);

const roleLabel = computed(() => {
    const roles = usePage().props.auth?.roles ?? [];

    return roles
        .map((role) => role.charAt(0).toUpperCase() + role.slice(1))
        .join(', ');
});
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
        <span
            v-else-if="showRole && roleLabel"
            class="truncate text-xs text-muted-foreground"
        >
            {{ roleLabel }}
        </span>
    </div>
</template>
