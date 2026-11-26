<script setup>
import { computed, inject, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { dueInfo, toInputValue } from '@/lib/dates';

// Modal chi tiết thẻ (khung ban đầu: tiêu đề, mô tả, hạn chót). Mọi người xem được bảng đều mở được;
// chỉ người có quyền sửa (canEdit) thấy ô nhập và các nút lưu/lưu trữ/xóa, còn lại chỉ đọc.
// `task` là thẻ trên bảng (reactive): khi thẻ bị lưu trữ/xóa, BoardPage tự đóng modal.
const props = defineProps({
    task: { type: Object, required: true },
});
const emit = defineEmits(['close']);

const permissions = inject('boardPermissions');
const canEdit = permissions.canEdit;
const { loadTask, updateTask, archiveTask, deleteTask } = inject('boardTasks');

const loading = ref(true);
const loadError = ref('');
const listName = ref('');

// form = giá trị đang sửa; base = giá trị đã lưu trên server (để biết có thay đổi chưa và chỉ gửi trường đổi).
const form = reactive({ title: props.task.title, description: '', due: toInputValue(props.task.due_date) });
const base = reactive({ title: props.task.title, description: '', due: toInputValue(props.task.due_date) });

const saving = ref(false);
const saved = ref(false);
const actionError = ref('');
const confirmDiscard = ref(false);
const confirmDelete = ref(false);
const busy = ref(false);

const dialog = ref(null);
const titleInput = ref(null);
let savedTimer = null;
let opener = null;

const dirty = computed(
    () => form.title !== base.title || form.description !== base.description || form.due !== base.due,
);
const dueText = computed(() => dueInfo(props.task)?.title ?? 'Chưa đặt hạn chót');

function apply(detail) {
    base.title = form.title = detail.title;
    base.description = form.description = detail.description ?? '';
    base.due = form.due = toInputValue(detail.due_date);
    listName.value = detail.list?.name ?? '';
}

async function load() {
    const { data, error } = await loadTask(props.task.id);
    loading.value = false;

    if (error) {
        loadError.value = error;
        return;
    }

    apply(data);
    await nextTick();
    if (canEdit.value) titleInput.value?.focus();
}

async function save() {
    if (!canEdit.value || saving.value || loading.value || !dirty.value) return;

    const title = form.title.trim();
    if (title === '') {
        actionError.value = 'Vui lòng nhập tiêu đề thẻ.';
        titleInput.value?.focus();
        return;
    }

    // Chỉ gửi trường đã đổi. Hạn chót rỗng = bỏ hạn chót (null).
    const fields = {};
    if (title !== base.title) fields.title = title;
    if (form.description !== base.description) fields.description = form.description;
    if (form.due !== base.due) fields.due_date = form.due === '' ? null : form.due;

    if (Object.keys(fields).length === 0) {
        form.title = title; // chỉ khác ở khoảng trắng đầu/cuối tiêu đề: không cần gọi server
        return;
    }

    saving.value = true;
    actionError.value = '';
    confirmDiscard.value = false;
    const { data, error } = await updateTask(props.task, fields);
    saving.value = false;

    if (error) {
        actionError.value = error;
        return;
    }

    apply(data);
    saved.value = true;
    clearTimeout(savedTimer);
    savedTimer = setTimeout(() => (saved.value = false), 3000);
}

function requestClose() {
    // Có thay đổi chưa lưu: hỏi lại trước khi đóng.
    if (canEdit.value && dirty.value && !confirmDiscard.value) {
        confirmDiscard.value = true;
        return;
    }
    emit('close');
}

async function runDanger(action) {
    if (busy.value) return;
    busy.value = true;
    actionError.value = '';
    const failure = await action(props.task);
    busy.value = false;

    // Thành công: thẻ đã bị gỡ khỏi bảng nên BoardPage tự đóng modal. Lỗi: báo ngay trong modal.
    if (failure) {
        confirmDelete.value = false;
        actionError.value = failure;
    }
}

const archive = () => runDanger(archiveTask);
const remove = () => runDanger(deleteTask);

function onTitleEnter(event) {
    // Đang gõ dở bằng bộ gõ tiếng Việt (Telex/VNI): Enter chỉ để chốt chữ, chưa phải lưu.
    if (event.isComposing) return;
    event.preventDefault();
    save();
}

function onDocumentKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        requestClose();
    }
}

// Giữ phím Tab trong modal (không nhảy ra các thẻ phía sau).
function onDialogKeydown(event) {
    if (event.key !== 'Tab' || !dialog.value) return;

    const items = [
        ...dialog.value.querySelectorAll('button:not([disabled]), input:not([disabled]), textarea:not([disabled])'),
    ];
    if (items.length === 0) {
        event.preventDefault();
        return;
    }

    const first = items[0];
    const last = items[items.length - 1];
    const current = document.activeElement;

    if (event.shiftKey && (current === first || current === dialog.value)) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && current === last) {
        event.preventDefault();
        first.focus();
    }
}

onMounted(() => {
    opener = document.activeElement;
    document.addEventListener('keydown', onDocumentKeydown);
    dialog.value?.focus();
    load();
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onDocumentKeydown);
    clearTimeout(savedTimer);
    opener?.focus?.(); // trả focus về thẻ đã mở modal
});
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-8"
            @mousedown.self="requestClose"
        >
            <div
                ref="dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="task-modal-title"
                tabindex="-1"
                class="relative w-full max-w-2xl rounded-xl bg-[#F4F5F7] shadow-2xl outline-none"
                @keydown="onDialogKeydown"
            >
                <!-- Tiêu đề -->
                <header class="flex items-start gap-3 p-5 pb-2">
                    <div class="min-w-0 flex-1">
                        <input
                            v-if="canEdit && !loadError"
                            id="task-modal-title"
                            ref="titleInput"
                            v-model="form.title"
                            type="text"
                            maxlength="255"
                            aria-label="Tiêu đề thẻ"
                            :disabled="loading || saving"
                            class="w-full rounded-md border-2 border-transparent bg-transparent px-2 py-1 text-xl font-bold text-[#091E42] outline-none transition hover:bg-white focus:border-[#AA6ADA] focus:bg-white disabled:opacity-70"
                            @keydown.enter="onTitleEnter"
                        />
                        <h2 v-else id="task-modal-title" class="break-words px-2 py-1 text-xl font-bold text-[#091E42]">
                            {{ task.title }}
                        </h2>
                        <p class="px-2 text-sm text-gray-500">
                            trong cột <span class="font-medium text-gray-700">{{ listName || '...' }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg p-1.5 text-gray-500 transition hover:bg-black/5 hover:text-gray-700"
                        aria-label="Đóng"
                        @click="requestClose"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <!-- Nội dung -->
                <div class="space-y-5 p-5 pt-3">
                    <p v-if="loadError" role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ loadError }}
                    </p>

                    <template v-else>
                        <section>
                            <h3 class="mb-1.5 text-sm font-semibold text-[#091E42]">Hạn chót</h3>
                            <div v-if="canEdit" class="flex flex-wrap items-center gap-2">
                                <input
                                    v-model="form.due"
                                    type="datetime-local"
                                    aria-label="Hạn chót"
                                    :disabled="loading || saving"
                                    class="rounded-lg border-2 border-transparent bg-white px-2.5 py-1.5 text-sm text-[#091E42] shadow-sm outline-none focus:border-[#AA6ADA] disabled:opacity-60"
                                />
                                <button
                                    v-if="form.due"
                                    type="button"
                                    :disabled="loading || saving"
                                    class="rounded-lg px-2.5 py-1.5 text-sm text-gray-600 transition hover:bg-black/5 disabled:opacity-60"
                                    @click="form.due = ''"
                                >
                                    Bỏ hạn chót
                                </button>
                            </div>
                            <p v-else class="text-sm text-gray-700">{{ dueText }}</p>
                        </section>

                        <section>
                            <h3 class="mb-1.5 text-sm font-semibold text-[#091E42]">Mô tả</h3>
                            <textarea
                                v-if="canEdit"
                                v-model="form.description"
                                rows="6"
                                maxlength="10000"
                                aria-label="Mô tả"
                                :placeholder="loading ? 'Đang tải...' : 'Thêm mô tả chi tiết...'"
                                :disabled="loading || saving"
                                class="w-full rounded-lg border-2 border-transparent bg-white px-3 py-2 text-sm text-[#091E42] shadow-sm outline-none placeholder:text-gray-400 focus:border-[#AA6ADA] disabled:opacity-60"
                                @keydown.ctrl.enter.prevent="save"
                                @keydown.meta.enter.prevent="save"
                            ></textarea>
                            <p v-else-if="loading" class="text-sm text-gray-400">Đang tải...</p>
                            <p v-else class="whitespace-pre-wrap break-words text-sm text-gray-700">
                                {{ form.description || 'Chưa có mô tả.' }}
                            </p>
                        </section>
                    </template>
                </div>

                <!-- Chân modal: chỉ người có quyền sửa -->
                <footer v-if="canEdit && !loadError" class="border-t border-black/10 p-5 pt-4">
                    <div
                        v-if="confirmDiscard"
                        class="mb-3 flex flex-wrap items-center gap-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900"
                    >
                        <span class="flex-1">Bạn có thay đổi chưa lưu.</span>
                        <button type="button" class="font-semibold underline" @click="emit('close')">Bỏ thay đổi</button>
                        <button type="button" class="font-semibold underline" @click="confirmDiscard = false">Tiếp tục sửa</button>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            :disabled="!dirty || saving || loading"
                            class="rounded-lg bg-[#AA6ADA] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#9857c8] disabled:opacity-50"
                            @click="save"
                        >
                            {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
                        </button>
                        <span v-if="saved && !dirty" role="status" class="text-sm text-green-700">Đã lưu</span>

                        <span class="flex-1"></span>

                        <template v-if="!confirmDelete">
                            <button
                                type="button"
                                :disabled="busy || loading"
                                class="rounded-lg px-3 py-2 text-sm text-gray-700 transition hover:bg-black/5 disabled:opacity-50"
                                @click="archive"
                            >
                                Lưu trữ thẻ
                            </button>
                            <button
                                type="button"
                                :disabled="busy || loading"
                                class="rounded-lg px-3 py-2 text-sm text-red-600 transition hover:bg-red-50 disabled:opacity-50"
                                @click="confirmDelete = true"
                            >
                                Xóa thẻ
                            </button>
                        </template>
                        <template v-else>
                            <span class="text-sm text-[#091E42]">Xóa thẻ này?</span>
                            <button
                                type="button"
                                :disabled="busy"
                                class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60"
                                @click="remove"
                            >
                                {{ busy ? 'Đang xóa...' : 'Xóa' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-2 text-sm text-gray-600 transition hover:bg-black/5"
                                @click="confirmDelete = false"
                            >
                                Hủy
                            </button>
                        </template>
                    </div>

                    <p v-if="actionError" role="alert" class="mt-3 text-sm text-red-600">{{ actionError }}</p>
                </footer>
            </div>
        </div>
    </Teleport>
</template>
