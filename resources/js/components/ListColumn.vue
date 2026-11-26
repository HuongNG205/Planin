<script setup>
import TaskCard from '@/components/TaskCard.vue';

// Một cột: tiêu đề + số thẻ + danh sách thẻ (cuộn dọc trong cột). Chỉ hiển thị.
defineProps({
    list: { type: Object, required: true },
});
</script>

<template>
    <section
        class="flex max-h-full w-72 shrink-0 flex-col rounded-xl bg-[#F1F2F4] shadow-sm"
        :aria-label="`Cột ${list.name}`"
    >
        <header class="flex items-center justify-between gap-2 px-3 pt-3 pb-2">
            <h2 class="min-w-0 truncate text-sm font-bold text-[#091E42]" :title="list.name">{{ list.name }}</h2>
            <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-500" :title="`${list.tasks.length} thẻ`">
                {{ list.tasks.length }}
            </span>
        </header>

        <ul v-if="list.tasks.length" class="min-h-0 flex-1 space-y-2 overflow-y-auto px-2 pb-2 [scrollbar-width:thin]">
            <li v-for="task in list.tasks" :key="task.id">
                <TaskCard :task="task" />
            </li>
        </ul>
        <p v-else class="px-3 pb-4 text-xs text-gray-400">Chưa có thẻ nào</p>
    </section>
</template>
