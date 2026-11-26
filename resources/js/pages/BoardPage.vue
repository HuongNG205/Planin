<script setup>
import { computed, provide } from 'vue';
import ListColumn from '@/components/ListColumn.vue';

// Props do BoardController@show truyền qua <x-vue-mount> (xem BoardService::kanbanProps).
// Phải khai báo đủ: prop không khai báo sẽ bị gắn thành thuộc tính HTML của phần tử gốc (vd. role="admin").
const props = defineProps({
    board: { type: Object, required: true },
    // 'admin' | 'member' | 'viewer' | null (null = khách xem bảng public)
    role: { type: String, default: null },
    // true chỉ khi người xem là member trở lên và bảng chưa lưu trữ
    canEdit: { type: Boolean, default: false },
    lists: { type: Array, default: () => [] },
});

// Component con (bước 8-11: thêm cột/thẻ, menu, nhãn...) inject để ẩn nút sửa với viewer/khách:
//   const permissions = inject('boardPermissions');  // permissions.canEdit
provide('boardPermissions', {
    role: computed(() => props.role),
    canEdit: computed(() => props.canEdit),
});
</script>

<template>
    <div class="flex h-full items-start gap-3 overflow-x-auto overflow-y-hidden p-4 sm:p-6 [scrollbar-width:thin]">
        <ListColumn v-for="list in lists" :key="list.id" :list="list" />

        <p
            v-if="!lists.length"
            class="rounded-lg bg-white/80 px-4 py-3 text-sm text-gray-700 shadow-2xs backdrop-blur-sm"
        >
            Bảng này chưa có cột nào.
        </p>
    </div>
</template>
