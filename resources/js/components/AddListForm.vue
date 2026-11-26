<script setup>
import { inject, nextTick, ref } from 'vue';

// Ô "Thêm cột" cuối bảng. Chỉ được hiển thị khi người xem có quyền sửa (BoardPage kiểm tra canEdit).
// Enter = thêm (giữ form mở để thêm tiếp), Esc = đóng.
const emit = defineEmits(['added']);

const { createList } = inject('boardLists');

const open = ref(false);
const name = ref('');
const error = ref('');
const saving = ref(false);
const input = ref(null);

async function openForm() {
    open.value = true;
    error.value = '';
    await nextTick();
    input.value?.focus();
}

function close() {
    open.value = false;
    name.value = '';
    error.value = '';
}

async function submit() {
    if (saving.value) return;

    const value = name.value.trim();
    if (value === '') {
        error.value = 'Vui lòng nhập tên cột.';
        input.value?.focus();
        return;
    }

    saving.value = true;
    error.value = '';
    const failure = await createList(value);
    saving.value = false;

    if (failure) {
        error.value = failure;
        return;
    }

    name.value = '';
    emit('added');
    await nextTick();
    input.value?.focus();
}
</script>

<template>
    <div class="w-72 shrink-0">
        <button
            v-if="!open"
            type="button"
            class="flex w-full items-center gap-2 rounded-xl bg-white/70 px-4 py-3 text-left text-sm font-semibold text-[#091E42] shadow-sm backdrop-blur-sm transition hover:bg-white/90"
            @click="openForm"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
            </svg>
            Thêm cột
        </button>

        <form v-else class="rounded-xl bg-[#F1F2F4] p-2 shadow-sm" novalidate @submit.prevent="submit" @keydown.esc="close">
            <input
                ref="input"
                v-model="name"
                type="text"
                maxlength="150"
                placeholder="Nhập tên cột..."
                aria-label="Tên cột"
                :disabled="saving"
                class="w-full rounded-lg border-2 border-[#AA6ADA] bg-white px-2.5 py-1.5 text-sm text-[#091E42] outline-none placeholder:text-gray-400 disabled:opacity-60"
            />
            <p v-if="error" role="alert" class="mt-1.5 text-xs text-red-600">{{ error }}</p>
            <div class="mt-2 flex items-center gap-2">
                <button
                    type="submit"
                    :disabled="saving"
                    class="rounded-lg bg-[#AA6ADA] px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-[#9857c8] disabled:opacity-60"
                >
                    {{ saving ? 'Đang thêm...' : 'Thêm cột' }}
                </button>
                <button
                    type="button"
                    class="rounded-lg px-2 py-1.5 text-gray-500 transition hover:bg-black/5 hover:text-gray-700"
                    aria-label="Đóng"
                    @click="close"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
</template>
