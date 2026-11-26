<script setup>
import { computed, nextTick, onBeforeUnmount, provide, ref } from 'vue';
import AddListForm from '@/components/AddListForm.vue';
import ListColumn from '@/components/ListColumn.vue';
import TaskModal from '@/components/TaskModal.vue';
import { useBoardLists } from '@/composables/useBoardLists';
import { useBoardTasks } from '@/composables/useBoardTasks';

// Props do BoardController@show truyền qua <x-vue-mount> (xem BoardService::kanbanProps).
// Phải khai báo đủ: prop không khai báo sẽ bị gắn thành thuộc tính HTML của phần tử gốc (vd. role="admin").
const props = defineProps({
    board: { type: Object, required: true },
    // 'admin' | 'member' | 'viewer' | null (null = khách xem bảng public)
    role: { type: String, default: null },
    // Do BoardPolicy::editCards quyết định: member trở lên, xem được bảng, bảng chưa lưu trữ.
    // Chỉ để ẩn/hiện nút; mọi thao tác vẫn được server kiểm tra lại bằng Policy.
    canEdit: { type: Boolean, default: false },
    lists: { type: Array, default: () => [] },
});

// Cột đang hiển thị + thao tác (thêm, đổi tên, lưu trữ, xóa) gọi backend không tải lại trang.
const { columns, createList, renameList, archiveList, deleteList } = useBoardLists(props.board.id, props.lists);

// Thao tác thẻ (thêm, xem chi tiết, sửa, lưu trữ, xóa) trên cùng danh sách cột.
const { createTask, loadTask, updateTask, archiveTask, deleteTask } = useBoardTasks(columns);

// Component con inject để ẩn nút sửa với viewer/khách mà không phải truyền props xuyên nhiều tầng:
//   const permissions = inject('boardPermissions');  // permissions.canEdit (ref)
//   const lists = inject('boardLists');              // createList, renameList, archiveList, deleteList
//   const tasks = inject('boardTasks');              // createTask, loadTask, updateTask, archiveTask, deleteTask
//   const openTask = inject('openTask');             // openTask(taskId) => mở modal chi tiết thẻ
//   const notify = inject('notify');                 // notify('Nội dung lỗi') => thông báo nổi
provide('boardPermissions', {
    role: computed(() => props.role),
    canEdit: computed(() => props.canEdit),
});
provide('boardLists', { createList, renameList, archiveList, deleteList });
provide('boardTasks', { createTask, loadTask, updateTask, archiveTask, deleteTask });

// Thẻ đang mở trong modal. Tìm theo id trong columns: thẻ bị lưu trữ/xóa (kể cả cả cột bị gỡ) thì
// activeTask thành null và modal tự đóng.
const openTaskId = ref(null);
const activeTask = computed(() => {
    if (openTaskId.value === null) return null;
    for (const list of columns.value) {
        const task = list.tasks.find((item) => item.id === openTaskId.value);
        if (task) return task;
    }
    return null;
});

provide('openTask', (taskId) => {
    openTaskId.value = taskId;
});

// Thông báo nổi (lỗi thao tác nền), tự tắt sau 6 giây.
const toast = ref('');
let toastTimer = null;

function notify(message) {
    toast.value = message;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toast.value = ''), 6000);
}

provide('notify', notify);
onBeforeUnmount(() => clearTimeout(toastTimer));

// Thêm cột xong thì cuộn sang phải để thấy cột mới.
const scroller = ref(null);

async function scrollToEnd() {
    await nextTick();
    scroller.value?.scrollTo?.({ left: scroller.value.scrollWidth, behavior: 'smooth' });
}
</script>

<template>
    <div
        ref="scroller"
        class="flex h-full items-start gap-3 overflow-x-auto overflow-y-hidden p-4 sm:p-6 [scrollbar-width:thin]"
    >
        <ListColumn v-for="list in columns" :key="list.id" :list="list" />

        <p
            v-if="!columns.length"
            class="rounded-lg bg-white/80 px-4 py-3 text-sm text-gray-700 shadow-2xs backdrop-blur-sm"
        >
            Bảng này chưa có cột nào.
        </p>

        <AddListForm v-if="canEdit" @added="scrollToEnd" />

        <TaskModal v-if="activeTask" :key="activeTask.id" :task="activeTask" @close="openTaskId = null" />

        <div
            v-if="toast"
            role="alert"
            class="fixed bottom-4 left-1/2 z-50 flex max-w-[90vw] -translate-x-1/2 items-start gap-3 rounded-lg bg-red-600 px-4 py-3 text-sm text-white shadow-lg"
        >
            <span>{{ toast }}</span>
            <button type="button" class="shrink-0 font-bold leading-none" aria-label="Đóng thông báo" @click="toast = ''">
                &times;
            </button>
        </div>
    </div>
</template>
