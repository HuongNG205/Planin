<?php

namespace App\Http\Requests;

use App\Enums\BoardVisibility;
use App\Models\Board;
use App\Support\BoardBackground;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tạo bảng (BoardController@store, route lồng /workspaces/{workspace}/boards).
 * Chỉ nhận name, background, visibility. workspace_id lấy từ URL; user_id do server gán.
 * Quyền: BoardPolicy@create (admin của workspace) kiểm tra ở authorize().
 */
class StoreBoardRequest extends FormRequest
{
    /** Lỗi vào error bag riêng để modal tạo bảng hiện đúng lỗi của nó. */
    protected $errorBag = 'createBoard';

    public function authorize(): bool
    {
        return $this->user()?->can('create', [Board::class, $this->route('workspace')]) ?? false;
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
            // Chỉ preset hoặc mã màu hex (chặn chèn CSS vào style); không gửi = nền mặc định (BoardService).
            'background' => ['nullable', 'string', 'max:255', BoardBackground::rule()],
            // Không gửi = mặc định (BoardService).
            'visibility' => ['nullable', Rule::in(BoardVisibility::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tiêu đề bảng.',
            'name.max' => 'Tiêu đề bảng không được vượt quá :max ký tự.',
            'background.max' => 'Phông nền của bảng không hợp lệ.',
            'visibility.in' => 'Quyền xem không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tiêu đề bảng',
            'background' => 'Phông nền',
            'visibility' => 'Quyền xem',
        ];
    }
}
