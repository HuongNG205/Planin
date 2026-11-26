<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\TaskList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Thêm nhanh thẻ vào cuối cột (TaskController@store, POST /lists/{list}/tasks).
 * Chỉ nhận title. task_list_id lấy từ URL, user_id (người tạo) và position do server gán; không nhận từ input.
 * Quyền: TaskPolicy@create (member trở lên, cột và bảng chưa lưu trữ) kiểm tra ở authorize(),
 * nên viewer nhận 403 trước khi bị validate.
 */
class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // {list} đã được route model binding thành model trước khi Request này chạy.
        $list = $this->route('list');

        return $list instanceof TaskList
            && ($this->user()?->can('create', [Task::class, $list]) ?? false);
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
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề thẻ.',
            'title.string' => 'Tiêu đề thẻ không hợp lệ.',
            'title.max' => 'Tiêu đề thẻ không được vượt quá :max ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Tiêu đề thẻ',
        ];
    }
}
