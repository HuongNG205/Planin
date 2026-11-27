import { ref } from 'vue';
import { http } from '@/lib/http';
import { errorMessage } from '@/lib/errors';

/**
 * Kéo thả CỘT và THẺ (bước 10), dùng chung `columns` (nguồn dữ liệu duy nhất, do useBoardLists tạo).
 * Giao diện kéo thả do `vuedraggable` (SortableJS) lo; file này lo dữ liệu:
 *
 *   1. Optimistic update: vuedraggable báo mảng mới ngay khi thả (setTasks / setColumns) nên giao diện
 *      đổi tức thì, rồi mới gọi server (PATCH /tasks/{id}/move, PATCH /lists/{id}/move).
 *   2. Rollback: lúc BẮT ĐẦU kéo (start) chụp lại thứ tự hiện có; nếu server từ chối hoặc lỗi mạng thì
 *      khôi phục đúng thứ tự đó và báo lỗi bằng `notify`.
 *   3. Tuần tự: trong lúc một yêu cầu đang chạy (`moving` = true) không cho kéo tiếp, để rollback không
 *      bao giờ đè lên một thao tác khác đang bay.
 *   4. Thành công: ghi position do server trả về vào thẻ/cột (server đã đánh số lại, kể cả khi tab khác
 *      vừa đổi vị trí).
 *
 * Chỉ số gửi lên (position) là newIndex của vuedraggable: danh sách trên giao diện chỉ có phần tử đang hiển
 * thị (cột/thẻ đã lưu trữ không có mặt) nên khớp với quy ước của ReorderService.
 *
 * @param {import('vue').Ref<Array>} columns  cột (kèm tasks) đang hiển thị
 * @param {(message: string) => void} notify  thông báo lỗi nổi
 */
export function useBoardDrag(columns, notify = () => {}) {
    const moving = ref(false);
    let snapshot = null;

    /** Chụp thứ tự cột và thẻ hiện tại (giữ tham chiếu đối tượng, không sao chép sâu). */
    function takeSnapshot() {
        snapshot = {
            order: [...columns.value],
            tasks: columns.value.map((list) => ({ list, tasks: [...list.tasks] })),
        };
    }

    function rollback() {
        if (!snapshot) return;
        // Cột đã bị gỡ khỏi bảng trong lúc chờ (vd. lưu trữ ở thao tác khác) thì không đưa trở lại.
        const alive = new Set(columns.value);
        const stillThere = snapshot.order.filter((list) => alive.has(list));
        // Cột mới xuất hiện sau lúc chụp (thêm cột) giữ ở cuối.
        const known = new Set(stillThere);
        columns.value = [...stillThere, ...columns.value.filter((list) => !known.has(list))];
        snapshot.tasks.forEach(({ list, tasks }) => {
            if (alive.has(list)) list.tasks = tasks;
        });
    }

    /** vuedraggable: bắt đầu kéo (thẻ hoặc cột). */
    function start() {
        takeSnapshot();
    }

    /** vuedraggable: mảng thẻ mới của một cột (@update:model-value). */
    function setTasks(listId, tasks) {
        const list = columns.value.find((item) => item.id === listId);
        if (list) list.tasks = tasks;
    }

    /** vuedraggable: mảng cột mới (@update:model-value). */
    function setColumns(lists) {
        columns.value = lists;
    }

    /** Gửi một yêu cầu sắp xếp; lỗi => khôi phục + thông báo. `apply` nhận phản hồi của server khi thành công. */
    async function persist(send, apply) {
        moving.value = true;
        try {
            const data = await send();
            apply(data);
        } catch (error) {
            rollback();
            notify(errorMessage(error));
        } finally {
            moving.value = false;
            snapshot = null;
        }
    }

    /** Ghi position server trả về ({ id: position }) vào các thẻ đang có trên bảng. */
    function applyTaskPositions(positions = {}) {
        columns.value.forEach((list) => {
            list.tasks.forEach((task) => {
                if (positions[task.id] !== undefined) task.position = positions[task.id];
            });
        });
    }

    function applyListPositions(positions = {}) {
        columns.value.forEach((list) => {
            if (positions[list.id] !== undefined) list.position = positions[list.id];
        });
    }

    /**
     * vuedraggable: thay đổi trong MỘT cột thẻ (@change). `added` = thẻ được thả vào cột này (từ cột khác),
     * `moved` = đổi thứ tự trong cùng cột; `removed` bỏ qua vì cột đích sẽ báo `added` và gọi server.
     */
    function taskChanged(listId, event) {
        const info = event.added ?? event.moved;
        if (!info) return Promise.resolve();

        return persist(
            () => http.patch(`/tasks/${info.element.id}/move`, { to_list_id: listId, position: info.newIndex }),
            (data) => applyTaskPositions(data?.positions),
        );
    }

    /** vuedraggable: đổi thứ tự cột (@change của danh sách cột). */
    function listChanged(event) {
        const info = event.moved;
        if (!info) return Promise.resolve();

        return persist(
            () => http.patch(`/lists/${info.element.id}/move`, { position: info.newIndex }),
            (data) => applyListPositions(data?.positions),
        );
    }

    return { moving, start, setTasks, setColumns, taskChanged, listChanged };
}
