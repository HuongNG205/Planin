<script setup>
import { computed } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { dueInfo } from '@/lib/dates';
import { readableTextColor } from '@/lib/colors';

// Thẻ chỉ để HIỂN THỊ (chưa kéo thả, chưa mở chi tiết). Nội dung luôn in bằng {{ }} (đã escape).
const props = defineProps({
    task: { type: Object, required: true },
});

const MAX_AVATARS = 3;

const due = computed(() => dueInfo(props.task));
const isDone = computed(() => !!props.task.completed_at);
const shownAssignees = computed(() => props.task.assignees.slice(0, MAX_AVATARS));
const extraAssignees = computed(() => Math.max(0, props.task.assignees.length - MAX_AVATARS));

const dueClass = computed(() => ({
    done: 'bg-green-600 text-white',
    overdue: 'bg-red-100 text-red-700',
    soon: 'bg-amber-100 text-amber-800',
    normal: 'bg-gray-100 text-gray-600',
}[due.value?.status ?? 'normal']));

const hasFooter = computed(() =>
    due.value || props.task.comments_count > 0 || props.task.attachments_count > 0 || props.task.assignees.length > 0,
);
</script>

<template>
    <article class="rounded-lg bg-white p-2.5 shadow-sm ring-1 ring-black/5 transition hover:ring-[#AA6ADA]/50">
        <!-- Nhãn -->
        <ul v-if="task.labels.length" class="mb-1.5 flex flex-wrap gap-1">
            <li
                v-for="label in task.labels"
                :key="label.id"
                class="max-w-full truncate rounded px-2 py-0.5 text-[10px] font-semibold leading-4"
                :style="{ backgroundColor: label.color, color: readableTextColor(label.color) }"
                :title="label.name"
            >
                {{ label.name }}
            </li>
        </ul>

        <!-- Tiêu đề (+ dấu hoàn thành) -->
        <h3 class="flex items-start gap-1.5 text-sm leading-snug text-[#091E42] break-words">
            <svg
                v-if="isDone"
                class="mt-0.5 h-4 w-4 shrink-0 text-green-600"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                aria-label="Đã hoàn thành"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span class="min-w-0" :class="{ 'text-gray-500 line-through': isDone }">{{ task.title }}</span>
        </h3>

        <!-- Hạn chót, số bình luận / tệp đính kèm, người được giao -->
        <div v-if="hasFooter" class="mt-2 flex items-center justify-between gap-2">
            <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                <span
                    v-if="due"
                    class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-medium"
                    :class="dueClass"
                    :title="due.title"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ due.label }}
                </span>

                <span v-if="task.comments_count > 0" class="inline-flex items-center gap-1" :title="`${task.comments_count} bình luận`">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6l3-3h11a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v14z" />
                    </svg>
                    {{ task.comments_count }}
                </span>

                <span v-if="task.attachments_count > 0" class="inline-flex items-center gap-1" :title="`${task.attachments_count} tệp đính kèm`">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                    {{ task.attachments_count }}
                </span>
            </div>

            <div v-if="task.assignees.length" class="flex shrink-0 -space-x-1.5">
                <UserAvatar v-for="user in shownAssignees" :key="user.id" :user="user" />
                <span
                    v-if="extraAssignees"
                    class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-gray-200 px-1 text-[10px] font-semibold text-gray-600 ring-2 ring-white"
                    :title="`${extraAssignees} người khác`"
                >
                    +{{ extraAssignees }}
                </span>
            </div>
        </div>
    </article>
</template>
