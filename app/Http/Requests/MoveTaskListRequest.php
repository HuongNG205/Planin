<?php

namespace App\Http\Requests;

use App\Models\TaskList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Kéo cột đổi thứ tự (TaskListReorderController, PATCH /lists/{list}/move).
 *
 *   position : vị trí đích 0-based trong danh sách cột ĐANG HIỂN THỊ của bảng, tính sau khi bỏ cột đang kéo
 *              ra (đúng newIndex của Vue). Vượt quá cuối danh sách => đưa xuống cuối.
 *
 * Quyền: TaskListPolicy@update (member trở lên, xem được bảng, bảng và cột chưa lưu trữ) kiểm tra ở
 * authorize(), nên viewer/khách nhận 403 trước khi bị validate. Cột lấy từ URL; không nhận board_id từ input.
 */
class MoveTaskListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $list = $this->route('list');

        return $list instanceof TaskList
            && ($this->user()?->can('update', $list) ?? false);
    }

    public function rules(): array
    {
        return [
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'position.required' => 'Thiếu vị trí đích.',
            'position.integer' => 'Vị trí đích không hợp lệ.',
            'position.min' => 'Vị trí đích không hợp lệ.',
            'position.max' => 'Vị trí đích không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'position' => 'Vị trí',
        ];
    }
}
