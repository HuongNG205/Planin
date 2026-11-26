/**
 * Gọi backend bằng fetch, dùng chung session + CSRF của Laravel (KHÔNG token/API).
 *
 *   import { http } from '@/lib/http';
 *   await http.post('/boards/1/lists', { name: 'To Do' });
 *   await http.patch('/tasks/5/move', { to_list_id: 2, position: 3 });
 */
/**
 * Thông báo lỗi tiếng Việt để hiển thị cho người dùng. Ưu tiên câu tiếng Việt do server trả
 * (validate, middleware); thay các câu mặc định tiếng Anh của Laravel bằng câu cố định theo mã trạng thái.
 */
function friendlyMessage(status, data) {
    const serverMessage = typeof data?.message === 'string' ? data.message : '';

    if (status === 0) return 'Không kết nối được máy chủ. Hãy kiểm tra mạng rồi thử lại.';

    if (status === 422) {
        // Laravel: { message, errors: { field: [câu lỗi, ...] } } => lấy câu lỗi đầu tiên
        const first = data?.errors ? Object.values(data.errors).flat()[0] : null;
        return first || serverMessage || 'Dữ liệu không hợp lệ.';
    }
    if (status === 401) return serverMessage || 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
    if (status === 403) {
        return serverMessage && serverMessage !== 'This action is unauthorized.'
            ? serverMessage
            : 'Bạn không có quyền thực hiện thao tác này.';
    }
    if (status === 404) return 'Không tìm thấy dữ liệu (có thể đã bị xóa). Hãy tải lại trang.';
    if (status === 419) return 'Phiên làm việc đã hết hạn. Hãy tải lại trang rồi thử lại.';
    if (status === 429) return 'Bạn thao tác quá nhanh. Vui lòng thử lại sau ít phút.';
    if (status >= 500) return 'Máy chủ gặp lỗi. Vui lòng thử lại sau.';

    return serverMessage || `Yêu cầu thất bại (${status})`;
}

export class HttpError extends Error {
    constructor(status, data) {
        super(friendlyMessage(status, data));
        this.name = 'HttpError';
        this.status = status;
        this.data = data;
    }
}

export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function request(method, url, body) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };

    const options = { method, headers, credentials: 'same-origin' };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(url, options);
    } catch {
        throw new HttpError(0, null); // mất mạng / máy chủ không phản hồi
    }

    // 204 No Content (hoặc thân rỗng)
    const text = await response.text();
    let data = null;

    if (text) {
        try {
            data = JSON.parse(text);
        } catch {
            data = null; // thân phản hồi không phải JSON (vd. trang HTML của 419/500)
        }
    }

    if (!response.ok) {
        throw new HttpError(response.status, data);
    }

    return data;
}

export const http = {
    get: (url) => request('GET', url),
    post: (url, body = {}) => request('POST', url, body),
    put: (url, body = {}) => request('PUT', url, body),
    patch: (url, body = {}) => request('PATCH', url, body),
    delete: (url) => request('DELETE', url),
};
