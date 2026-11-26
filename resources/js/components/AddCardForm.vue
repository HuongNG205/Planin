<script setup>
import { inject, nextTick, ref } from 'vue';

// Ô "Thêm thẻ" cuối cột. Chỉ được hiển thị khi người xem có quyền sửa (ListColumn kiểm tra canEdit).
// Enter = thêm (form vẫn mở để thêm tiếp), Esc = đóng. Tiêu đề thẻ là một dòng: xuống dòng bị gộp thành dấu cách.
const props = defineProps({
    list: { type: Object, required: true },
});
const emit = defineEmits(['added']);

const { createTask } = inject('boardTasks');

const open = ref(false);
const title = ref('');
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
    title.value = '';
    error.value = '';
}

function onEnter(event) {
    // Đang gõ dở bằng bộ gõ tiếng Việt (Telex/VNI): Enter chỉ để chốt chữ, chưa phải gửi.
    if (event.isComposing) return;
    event.preventDefault();
    submit();
}

async function submit() {
    if (saving.value) return;

    const value = title.value.replace(/\s*[\r\n]+\s*/g, ' ').trim();
    if (value === '') {
        error.value = 'Vui lòng nhập tiêu đề thẻ.';
        input.value?.focus();
        return;
    }

    saving.value = true;
    error.value = '';
    const failure = await createTask(props.list, value);
    saving.value = false;

    if (failure) {
        error.value = failure;
        return;
    }

    title.value = '';
    emit('added');
    await nextTick();
    input.value?.focus();
}
</script>

<template>
    <div class="shrink-0 px-2 pb-2">
        <button
            v-if="!open"
            type="button"
            class="flex w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-left text-sm text-gray-600 transition hover:bg-black/5 hover:text-[#091E42]"
            @click="openForm"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
            </svg>
            Thêm thẻ
        </button>

        <form v-else novalidate @submit.prevent="submit" @keydown.esc="close">
            <textarea
                ref="input"
                v-model="title"
                rows="2"
                maxlength="255"
                placeholder="Nhập tiêu đề cho thẻ này..."
                aria-label="Tiêu đề thẻ"
                :disabled="saving"
                class="w-full resize-none rounded-lg border-2 border-[#AA6ADA] bg-white px-2.5 py-1.5 text-sm text-[#091E42] shadow-sm outline-none placeholder:text-gray-400 disabled:opacity-60"
                @keydown.enter="onEnter"
            ></textarea>
            <p v-if="error" role="alert" class="mt-1 text-xs text-red-600">{{ error }}</p>
            <div class="mt-1.5 flex items-center gap-2">
                <button
                    type="submit"
                    :disabled="saving"
                    class="rounded-lg bg-[#AA6ADA] px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-[#9857c8] disabled:opacity-60"
                >
                    {{ saving ? 'Đang thêm...' : 'Thêm thẻ' }}
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
