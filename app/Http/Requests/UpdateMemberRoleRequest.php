<?php

namespace App\Http\Requests;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Đổi vai trò thành viên (WorkspaceMemberController@update). Admin; không áp dụng cho chủ sở hữu. */
class UpdateMemberRoleRequest extends FormRequest
{
    protected $errorBag = 'updateMemberRole';

    public function authorize(): bool
    {
        $workspace = $this->route('workspace');
        $member = $this->route('member');

        return $workspace instanceof Workspace
            && $member instanceof User
            && ($this->user()?->can('updateMemberRole', [$workspace, $member]) ?? false);
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(WorkspaceRole::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Vui lòng chọn vai trò.',
            'role.in' => 'Vai trò không hợp lệ.',
        ];
    }
}
