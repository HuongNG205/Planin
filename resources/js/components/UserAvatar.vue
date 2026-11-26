<script setup>
import { ref } from 'vue';

// user: { name, initials, avatar_url }. Không có ảnh (hoặc ảnh lỗi) thì hiện chữ cái viết tắt.
defineProps({
    user: { type: Object, required: true },
});

const imageFailed = ref(false);
</script>

<template>
    <span
        class="inline-flex w-6 h-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#AA6ADA] text-[10px] font-bold uppercase text-white ring-2 ring-white"
        :title="user.name"
    >
        <img
            v-if="user.avatar_url && !imageFailed"
            :src="user.avatar_url"
            :alt="user.name"
            class="h-full w-full object-cover"
            @error="imageFailed = true"
        />
        <template v-else>{{ user.initials }}</template>
    </span>
</template>
