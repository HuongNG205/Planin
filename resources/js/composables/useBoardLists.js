import { ref } from 'vue';
import { http, HttpError } from '@/lib/http';

/**
 * Trạng thái + thao tác CỘT của một bảng (bước 8). Gọi backend bằng fetch (session + CSRF), KHÔNG tải lại trang.
 *
 * Mỗi thao tác trả về `null` nếu thành công, hoặc một chuỗi thông báo lỗi tiếng Việt nếu thất bại (không ném
 * lỗi ra ngoài) để component tự quyết định hiển thị: dưới ô nhập hoặc thông báo nổi. Dữ liệu cục bộ chỉ đổi
 * SAU khi server xác nhận, nên khi lỗi giao diện luôn khớp với dữ liệu thật.
 *
 * Bước sau (kéo thả cột, bước 10) dùng lại `columns` làm nguồn dữ liệu duy nhất.
 */
export function useBoardLists(boardId, initialLists) {
    const columns = ref(initialLists.map((list) => ({ ...list })));

    async function run(action) {
        try {
            await action();
            return null;
        } catch (error) {
            return error instanceof HttpError ? error.message : 'Đã có lỗi xảy ra. Vui lòng thử lại.';
        }
    }

    function remove(id) {
        columns.value = columns.value.filter((list) => list.id !== id);
    }

    /** Thêm cột vào cuối bảng. */
    const createList = (name) =>
        run(async () => {
            const data = await http.post(`/boards/${boardId}/lists`, { name });
            columns.value.push({ ...data.list, tasks: [] });
        });

    /** Đổi tên cột. */
    const renameList = (list, name) =>
        run(async () => {
            const data = await http.put(`/lists/${list.id}`, { name });
            const target = columns.value.find((item) => item.id === list.id);
            if (target) target.name = data.list.name;
        });

    /** Lưu trữ cột (biến mất khỏi bảng). Cột đã không còn (404) thì coi như xong và bỏ khỏi giao diện. */
    const archiveList = (list) =>
        run(async () => {
            try {
                await http.post(`/lists/${list.id}/archive`);
            } catch (error) {
                if (!(error instanceof HttpError && error.status === 404)) throw error;
            }
            remove(list.id);
        });

    /** Xóa cột. */
    const deleteList = (list) =>
        run(async () => {
            try {
                await http.delete(`/lists/${list.id}`);
            } catch (error) {
                if (!(error instanceof HttpError && error.status === 404)) throw error;
            }
            remove(list.id);
        });

    return { columns, createList, renameList, archiveList, deleteList };
}
