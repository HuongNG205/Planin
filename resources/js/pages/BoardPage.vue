<script setup>
import { computed, nextTick, onBeforeUnmount, provide, ref } from 'vue';
import draggable from 'vuedraggable';
import AddListForm from '@/components/AddListForm.vue';
import ListColumn from '@/components/ListColumn.vue';
import TaskModal from '@/components/TaskModal.vue';
import { useBoardDrag } from '@/composables/useBoardDrag';
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
//   const drag = inject('boardDrag');               // kéo thả (bước 10): moving (ref), start, setTasks, taskChanged
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
const activeLocation = computed(() => {
    if (openTaskId.value === null) return null;
    for (const list of columns.value) {
        const task = list.tasks.find((item) => item.id === openTaskId.value);
        if (task) return { list, task };
    }
    return null;
});
const activeTask = computed(() => activeLocation.value?.task ?? null);
// Tên cột hiện tại của thẻ đang mở: lấy từ columns nên luôn đúng sau khi kéo thả (kể cả khi rollback).
const activeListName = computed(() => activeLocation.value?.list.name ?? '');

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

// Kéo thả cột/thẻ (bước 10): optimistic update + rollback khi server từ chối. Đặt SAU notify vì cần gọi nó.
const drag = useBoardDrag(columns, notify);
provide('boardDrag', drag);
const { moving } = drag;
// Chỉ người sửa được bảng mới kéo; đang chờ server xác nhận một lần kéo thì tạm khóa kéo tiếp.
const dragDisabled = computed(() => !props.canEdit || moving.value);

// Thêm cột xong thì cuộn sang phải để thấy cột mới.
const scroller = ref(null);

async function scrollToEnd() {
    await nextTick();
    // scroller là component vuedraggable: phần tử cuộn thật là $el.
    const el = scroller.value?.$el;
    el?.scrollTo?.({ left: el.scrollWidth, behavior: 'smooth' });
}
</script>

<template>
    <div class="h-full">
        <!-- Kéo cột: chỉ bắt đầu từ vùng .column-handle (tiêu đề cột). AddListForm và dòng "chưa có cột" nằm ở
             slot footer nên không phải phần tử kéo được. Nhóm "lists" khác nhóm "tasks" của thẻ. -->
        <draggable
            ref="scroller"
            tag="div"
            item-key="id"
            group="lists"
            handle=".column-handle"
            filter=".no-drag"
            :prevent-on-filter="false"
            :model-value="columns"
            :disabled="dragDisabled"
            :animation="150"
            :delay="150"
            :delay-on-touch-only="true"
            ghost-class="planin-ghost"
            chosen-class="planin-chosen"
            class="flex h-full items-start gap-3 overflow-x-auto overflow-y-hidden p-4 sm:p-6 [scrollbar-width:thin]"
            @start="drag.start"
            @update:model-value="drag.setColumns"
            @change="drag.listChanged"
        >
            <template #item="{ element }">
                <ListColumn :list="element" />
            </template>

            <template #footer>
                <p
                    v-if="!columns.length"
                    class="rounded-lg bg-white/80 px-4 py-3 text-sm text-gray-700 shadow-2xs backdrop-blur-sm"
                >
                    Bảng này chưa có cột nào.
                </p>

                <AddListForm v-if="canEdit" @added="scrollToEnd" />
            </template>
        </draggable>

        <TaskModal
            v-if="activeTask"
            :key="activeTask.id"
            :task="activeTask"
            :column-name="activeListName"
            @close="openTaskId = null"
        />

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

<style>
/* Kéo thả (bước 10): lớp của SortableJS gắn lên phần tử đang kéo; dùng chung cho cột và thẻ. */
.planin-ghost {
    opacity: 0.4;
}

.planin-chosen {
    cursor: grabbing;
}
</style>
