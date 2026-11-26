import { HttpError } from '@/lib/http';

/** Chuỗi lỗi tiếng Việt để hiển thị. HttpError đã có sẵn thông báo (xem friendlyMessage trong http.js). */
export function errorMessage(error) {
    return error instanceof HttpError ? error.message : 'Đã có lỗi xảy ra. Vui lòng thử lại.';
}
