<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRoomMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:150'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('user_id') || ! $this->filled('user_id')) {
                return;
            }

            $room = $this->route('room');
            $roomId = $room instanceof Room ? $room->id : $room;
            $alreadyLinked = Room::query()->find($roomId)?->members()
                ->where('user_id', $this->integer('user_id'))
                ->exists();

            if ($alreadyLinked) {
                $validator->errors()->add('user_id', 'این حساب قبلاً به عضوی از همین اتاق وصل شده است.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'display_name.required' => 'نام عضو را وارد کنید.',
            'display_name.max' => 'نام عضو نباید بیشتر از ۱۵۰ نویسه باشد.',
            'user_id.exists' => 'حساب کاربری انتخاب‌شده وجود ندارد.',
        ];
    }
}
