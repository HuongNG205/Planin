<?php

namespace App\Http\Requests;

use App\Models\TaskList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Đổi tên cột (TaskListController@update, PUT /lists/{list}).
 * Quyền: TaskListPolicy@update (member trở lên, bảng và cột chưa lưu trữ) kiểm tra ở authorize().
 */
class UpdateTaskListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $list = $this->route('list');

        return $list instanceof TaskList
            && ($this->user()?->can('update', $list) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên cột.',
            'name.string' => 'Tên cột không hợp lệ.',
            'name.max' => 'Tên cột không được vượt quá :max ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên cột',
        ];
    }
}
