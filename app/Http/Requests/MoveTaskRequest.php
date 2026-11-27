<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kéo thẻ (TaskMoveController, PATCH /tasks/{task}/move): đổi thứ tự trong cột hoặc chuyển sang cột khác.
 *
 *   to_list_id : id cột đích (có thể là chính cột hiện tại), phải là cột chưa bị xóa
 *   position   : vị trí đích 0-based trong danh sách thẻ ĐANG HIỂN THỊ của cột đích, tính sau khi bỏ thẻ
 *                đang kéo ra (đúng newIndex của Vue). Vượt quá cuối danh sách => đưa xuống cuối.
 *
 * Quyền ở hai tầng: authorize() kiểm tra TaskPolicy@update trên thẻ (viewer/khách/người ngoài nhận 403 trước khi
 * bị validate); sau khi validate, controller kiểm tra tiếp TaskPolicy@move với cột đích (quyền sửa bảng của
 * cột đích, cột đích chưa lưu trữ). Việc cột đích CÙNG BẢNG với thẻ do ReorderService kiểm tra (422).
 * Không nhận task_id từ body: thẻ lấy từ URL (route model binding) để không thể đổi id vượt quyền.
 */
class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user()?->can('update', $task) ?? false);
    }

    public function rules(): array
    {
        return [
            'to_list_id' => [
                'required',
                'integer',
                Rule::exists('task_lists', 'id')->whereNull('deleted_at'),
            ],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_list_id.required' => 'Vui lòng chọn cột đích.',
            'to_list_id.integer' => 'Cột đích không hợp lệ.',
            'to_list_id.exists' => 'Cột đích không tồn tại hoặc đã bị xóa.',
            'position.required' => 'Thiếu vị trí đích.',
            'position.integer' => 'Vị trí đích không hợp lệ.',
            'position.min' => 'Vị trí đích không hợp lệ.',
            'position.max' => 'Vị trí đích không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'to_list_id' => 'Cột đích',
            'position' => 'Vị trí',
        ];
    }
}
