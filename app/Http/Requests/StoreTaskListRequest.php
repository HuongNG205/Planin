<?php

namespace App\Http\Requests;

use App\Models\Board;
use App\Models\TaskList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Thêm cột vào bảng (TaskListController@store, POST /boards/{board}/lists).
 * Chỉ nhận name. board_id lấy từ URL, position do TaskListService tính; không nhận từ input.
 * Quyền: TaskListPolicy@create (member trở lên, bảng chưa lưu trữ) kiểm tra ở authorize(),
 * nên viewer nhận 403 trước khi bị validate.
 */
class StoreTaskListRequest extends FormRequest
{
    public function authorize(): bool
    {
        // {board} đã được route model binding thành model trước khi Request này chạy.
        $board = $this->route('board');

        return $board instanceof Board
            && ($this->user()?->can('create', [TaskList::class, $board]) ?? false);
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
