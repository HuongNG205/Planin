<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sửa thẻ (TaskController@update, PUT /tasks/{task}). Mọi trường đều TÙY CHỌN ("sometimes"): chỉ trường nào
 * có trong request mới được sửa, trường vắng mặt giữ nguyên.
 *
 *   title       : chuỗi, bắt buộc nếu có, tối đa 255 ký tự
 *   description : chuỗi hoặc null (xóa mô tả), tối đa 10000 ký tự
 *   due_date    : null (bỏ hạn chót) hoặc 'YYYY-MM-DDTHH:mm' (giờ theo múi giờ của ứng dụng, đúng định
 *                 dạng ô <input type="datetime-local">). Cố ý KHÔNG nhận chuỗi có múi giờ (vd. '...Z'):
 *                 cột datetime lưu nguyên giờ, không đổi múi giờ, nên chuỗi UTC sẽ bị lệch giờ.
 *   completed   : true/false => đặt/bỏ trạng thái hoàn thành (completed_at)
 *
 * Quyền: TaskPolicy@update (member trở lên, thẻ/cột/bảng chưa lưu trữ) kiểm tra ở authorize().
 * Không nhận task_list_id, position (việc của kéo thả, bước 10), user_id, archived_at...
 */
class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user()?->can('update', $task) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->title)) {
            $this->merge(['title' => trim($this->title)]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i'],
            'completed' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề thẻ.',
            'title.string' => 'Tiêu đề thẻ không hợp lệ.',
            'title.max' => 'Tiêu đề thẻ không được vượt quá :max ký tự.',
            'description.string' => 'Mô tả không hợp lệ.',
            'description.max' => 'Mô tả không được vượt quá :max ký tự.',
            'due_date.date_format' => 'Hạn chót không hợp lệ.',
            'completed.boolean' => 'Trạng thái hoàn thành không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Tiêu đề thẻ',
            'description' => 'Mô tả',
            'due_date' => 'Hạn chót',
            'completed' => 'Trạng thái hoàn thành',
        ];
    }
}
