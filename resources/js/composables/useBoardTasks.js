import { http, HttpError } from '@/lib/http';
import { errorMessage } from '@/lib/errors';

/**
 * Thao tác THẺ trên danh sách cột đang hiển thị (bước 9). Gọi backend bằng fetch (session + CSRF), KHÔNG tải
 * lại trang. `columns` là ref do useBoardLists tạo (nguồn dữ liệu duy nhất của bảng).
 *
 * Quy ước trả về:
 *   - createTask, archiveTask, deleteTask: `null` nếu thành công, hoặc chuỗi lỗi tiếng Việt.
 *   - loadTask, updateTask: `{ data, error }` (data là thẻ chi tiết từ server, error là chuỗi hoặc null).
 * Dữ liệu cục bộ chỉ đổi SAU khi server xác nhận. Thẻ đã không còn (404) thì gỡ khỏi giao diện.
 *
 * Bước sau (kéo thả thẻ, bước 10; nhãn/người, bước 11) dùng lại các hàm tìm thẻ ở đây.
 */
export function useBoardTasks(columns) {
    function locate(taskId) {
        for (const list of columns.value) {
            const task = list.tasks.find((item) => item.id === taskId);
            if (task) return { list, task };
        }
        return null;
    }

    function removeTask(taskId) {
        const found = locate(taskId);
        if (found) found.list.tasks = found.list.tasks.filter((item) => item.id !== taskId);
    }

    const isNotFound = (error) => error instanceof HttpError && error.status === 404;

    /** Thêm thẻ vào cuối cột. */
    async function createTask(list, title) {
        try {
            const data = await http.post(`/lists/${list.id}/tasks`, { title });
            const target = columns.value.find((item) => item.id === list.id);
            if (target) target.tasks.push(data.task);
            return null;
        } catch (error) {
            return errorMessage(error);
        }
    }

    /** Lấy chi tiết thẻ (mô tả, tên cột) để mở modal. */
    async function loadTask(taskId) {
        try {
            const data = await http.get(`/tasks/${taskId}`);
            return { data: data.task, error: null };
        } catch (error) {
            if (isNotFound(error)) removeTask(taskId);
            return { data: null, error: errorMessage(error) };
        }
    }

    /**
     * Sửa thẻ: `fields` chỉ gồm trường đổi (title, description, due_date, completed). Thành công thì cập nhật
     * thẻ trên bảng (tiêu đề, hạn chót, hoàn thành) theo dữ liệu server trả về.
     */
    async function updateTask(task, fields) {
        try {
            const data = await http.put(`/tasks/${task.id}`, fields);
            const card = locate(task.id)?.task;
            if (card) {
                card.title = data.task.title;
                card.due_date = data.task.due_date;
                card.completed_at = data.task.completed_at;
            }
            return { data: data.task, error: null };
        } catch (error) {
            if (isNotFound(error)) removeTask(task.id);
            return { data: null, error: errorMessage(error) };
        }
    }

    /** Lưu trữ thẻ (biến mất khỏi bảng). */
    async function archiveTask(task) {
        try {
            await http.post(`/tasks/${task.id}/archive`);
        } catch (error) {
            if (!isNotFound(error)) return errorMessage(error);
        }
        removeTask(task.id);
        return null;
    }

    /** Xóa thẻ. */
    async function deleteTask(task) {
        try {
            await http.delete(`/tasks/${task.id}`);
        } catch (error) {
            if (!isNotFound(error)) return errorMessage(error);
        }
        removeTask(task.id);
        return null;
    }

    return { createTask, loadTask, updateTask, archiveTask, deleteTask };
}
