<script setup>
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import draggable from 'vuedraggable';
import AddCardForm from '@/components/AddCardForm.vue';
import TaskCard from '@/components/TaskCard.vue';

// Một cột: tiêu đề + số thẻ + danh sách thẻ (cuộn dọc trong cột).
// Người có quyền sửa (canEdit) thêm được: bấm tên để đổi tại chỗ, menu "..." để lưu trữ/xóa, ô "Thêm thẻ" cuối cột.
// Bấm một thẻ để mở modal chi tiết (ai xem được bảng cũng mở được).
// Kéo thả (bước 10): kéo thẻ để đổi thứ tự/chuyển cột (danh sách thẻ luôn được vẽ, kể cả cột rỗng, để có chỗ thả);
// kéo tiêu đề cột để đổi thứ tự cột. Logic dữ liệu nằm ở useBoardDrag (inject 'boardDrag').
const props = defineProps({
    list: { type: Object, required: true },
});

// Do BoardPage cung cấp. Có giá trị mặc định để component vẫn vẽ được khi đứng riêng.
const permissions = inject('boardPermissions', { canEdit: computed(() => false) });
const canEdit = permissions.canEdit;
const lists = inject('boardLists', null);
const notify = inject('notify', () => {});
const taskActions = inject('boardTasks', null);
const openTask = inject('openTask', () => {});
const drag = inject('boardDrag', null);
const moving = drag ? drag.moving : ref(false);
// Chỉ người sửa được bảng mới kéo; đang chờ server xác nhận một lần kéo thì tạm khóa.
const dragDisabled = computed(() => !drag || !canEdit.value || moving.value);

// ---------- Thẻ ----------
// Vùng cuộn dọc của cột (phần tử thật, không phải component kéo thả).
const cardList = ref(null);

// Thêm thẻ xong thì cuộn xuống cuối cột để thấy thẻ mới.
async function scrollCardsToEnd() {
    await nextTick();
    cardList.value?.scrollTo?.({ top: cardList.value.scrollHeight, behavior: 'smooth' });
}

// ---------- Đổi tên tại chỗ ----------
const editing = ref(false);
const saving = ref(false);
const draft = ref('');
const titleInput = ref(null);

// Trong lúc chờ server, hiện tên mới (mờ) để không bị "giật" về tên cũ.
const shownName = computed(() => (saving.value ? draft.value : props.list.name));

async function startEdit() {
    if (!canEdit.value || saving.value || !lists) return;
    draft.value = props.list.name;
    editing.value = true;
    await nextTick();
    titleInput.value?.focus();
    titleInput.value?.select();
}

function cancelEdit() {
    editing.value = false;
}

function onTitleEnter(event) {
    // Đang gõ dở bằng bộ gõ tiếng Việt (Telex/VNI): Enter chỉ để chốt chữ, chưa phải lưu.
    if (event.isComposing) return;
    event.preventDefault();
    commitEdit();
}

async function commitEdit() {
    if (!editing.value) return; // Esc/Enter đã xử lý, tránh lưu hai lần khi ô nhập mất focus
    editing.value = false;

    const name = draft.value.trim();
    if (name === '' || name === props.list.name) return; // rỗng hoặc không đổi: giữ tên cũ

    draft.value = name;
    saving.value = true;
    const failure = await lists.renameList(props.list, name);
    saving.value = false;

    if (failure) notify(failure);
}

// ---------- Menu lưu trữ / xóa ----------
const menuOpen = ref(false);
const confirmingDelete = ref(false);
const busy = ref(false);
const menuRoot = ref(null);
const menuButton = ref(null);

function toggleMenu() {
    menuOpen.value = !menuOpen.value;
    confirmingDelete.value = false;
}

function closeMenu() {
    menuOpen.value = false;
    confirmingDelete.value = false;
}

function onDocumentPointerDown(event) {
    if (menuOpen.value && menuRoot.value && !menuRoot.value.contains(event.target)) closeMenu();
}

function onDocumentKeydown(event) {
    if (event.key === 'Escape' && menuOpen.value) {
        closeMenu();
        menuButton.value?.focus();
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onDocumentKeydown);
});

async function runMenuAction(action) {
    if (busy.value) return;
    busy.value = true;
    const failure = await action(props.list);
    busy.value = false;

    // Thành công: cột đã bị gỡ khỏi bảng (component này sẽ bị hủy). Lỗi: báo và đóng menu.
    if (failure) {
        closeMenu();
        notify(failure);
    }
}

const archive = () => runMenuAction(lists.archiveList);
const remove = () => runMenuAction(lists.deleteList);
</script>

<template>
    <section
        class="flex max-h-full w-72 shrink-0 flex-col rounded-xl bg-[#F1F2F4] shadow-sm"
        :aria-label="`Cột ${list.name}`"
    >
        <header
            class="column-handle flex items-center justify-between gap-2 px-2 pt-3 pb-2"
            :class="{ 'cursor-grab': !dragDisabled }"
        >
            <h2 class="min-w-0 flex-1 text-sm font-bold text-[#091E42]">
                <input
                    v-if="editing"
                    ref="titleInput"
                    v-model="draft"
                    type="text"
                    maxlength="150"
                    aria-label="Tên cột"
                    class="no-drag w-full rounded border-2 border-[#AA6ADA] bg-white px-1.5 py-0.5 text-sm font-bold outline-none"
                    @keydown.enter="onTitleEnter"
                    @keydown.esc.prevent="cancelEdit"
                    @blur="commitEdit"
                />
                <button
                    v-else-if="canEdit && lists"
                    type="button"
                    class="block w-full truncate rounded px-1.5 py-0.5 text-left transition hover:bg-black/5"
                    :class="{ 'opacity-60': saving }"
                    :title="`${shownName} (bấm để đổi tên)`"
                    @click="startEdit"
                >
                    {{ shownName }}
                </button>
                <span v-else class="block truncate px-1.5 py-0.5" :title="list.name">{{ list.name }}</span>
            </h2>

            <span
                class="shrink-0 rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-500"
                :title="`${list.tasks.length} thẻ`"
            >
                {{ list.tasks.length }}
            </span>

            <div v-if="canEdit && lists" ref="menuRoot" class="no-drag relative shrink-0">
                <button
                    ref="menuButton"
                    type="button"
                    class="rounded p-1 text-gray-500 transition hover:bg-black/5 hover:text-gray-700"
                    aria-haspopup="menu"
                    :aria-expanded="menuOpen"
                    aria-label="Thao tác với cột"
                    @click="toggleMenu"
                >
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </button>

                <div
                    v-if="menuOpen"
                    role="menu"
                    class="absolute right-0 z-20 mt-1 w-56 rounded-lg bg-white p-1 text-sm shadow-lg ring-1 ring-black/10"
                >
                    <template v-if="!confirmingDelete">
                        <button
                            type="button"
                            role="menuitem"
                            :disabled="busy"
                            class="block w-full rounded px-3 py-2 text-left text-[#091E42] transition hover:bg-gray-100 disabled:opacity-60"
                            @click="archive"
                        >
                            Lưu trữ cột
                        </button>
                        <button
                            type="button"
                            role="menuitem"
                            class="block w-full rounded px-3 py-2 text-left text-red-600 transition hover:bg-red-50"
                            @click="confirmingDelete = true"
                        >
                            Xóa cột
                        </button>
                    </template>

                    <div v-else class="p-2">
                        <p class="text-[#091E42]">Xóa cột này? Các thẻ trong cột cũng sẽ không còn hiển thị trên bảng.</p>
                        <div class="mt-2 flex gap-2">
                            <button
                                type="button"
                                :disabled="busy"
                                class="rounded bg-red-600 px-3 py-1.5 font-semibold text-white transition hover:bg-red-700 disabled:opacity-60"
                                @click="remove"
                            >
                                {{ busy ? 'Đang xóa...' : 'Xóa' }}
                            </button>
                            <button
                                type="button"
                                class="rounded px-3 py-1.5 text-gray-600 transition hover:bg-gray-100"
                                @click="confirmingDelete = false"
                            >
                                Hủy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Vùng cuộn dọc. Danh sách thẻ luôn có mặt (min-h) để cột rỗng vẫn thả được; dòng "Chưa có thẻ nào"
             nằm đè lên, không bắt chuột. Nhóm "tasks" cho phép kéo thẻ qua lại giữa các cột. -->
        <div ref="cardList" class="min-h-0 flex-1 overflow-y-auto px-2 pb-2 [scrollbar-width:thin]">
            <div class="relative">
                <p
                    v-if="!list.tasks.length"
                    class="pointer-events-none absolute inset-x-1 top-1 text-xs text-gray-400"
                >
                    Chưa có thẻ nào
                </p>

                <draggable
                    tag="ul"
                    item-key="id"
                    group="tasks"
                    class="min-h-10 space-y-2"
                    :model-value="list.tasks"
                    :disabled="dragDisabled"
                    :animation="150"
                    :delay="150"
                    :delay-on-touch-only="true"
                    ghost-class="planin-ghost"
                    chosen-class="planin-chosen"
                    @start="drag?.start()"
                    @update:model-value="(tasks) => drag?.setTasks(list.id, tasks)"
                    @change="(event) => drag?.taskChanged(list.id, event)"
                >
                    <template #item="{ element }">
                        <li>
                            <TaskCard :task="element" @open="openTask(element.id)" />
                        </li>
                    </template>
                </draggable>
            </div>
        </div>

        <AddCardForm v-if="canEdit && taskActions" :list="list" @added="scrollCardsToEnd" />
    </section>
</template>
