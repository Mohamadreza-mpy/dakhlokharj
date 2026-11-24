<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreConsumptionRequest extends FormRequest
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
            'request_id' => ['required', 'uuid'],
            'consumer_member_id' => ['required', 'integer', $this->memberExistsRule()],
            'quantity_milliunits' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'consumed_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'شناسه درخواست الزامی است.',
            'request_id.uuid' => 'شناسه درخواست معتبر نیست.',
            'consumer_member_id.required' => 'مصرف‌کننده را انتخاب کنید.',
            'consumer_member_id.exists' => 'مصرف‌کننده باید عضو فعال همین اتاق باشد.',
            'quantity_milliunits.required' => 'مقدار مصرف را وارد کنید.',
            'quantity_milliunits.integer' => 'مقدار مصرف باید با دقت هزارم وارد شود.',
            'quantity_milliunits.min' => 'مقدار مصرف باید بیشتر از صفر باشد.',
            'quantity_milliunits.max' => 'مقدار مصرف از سقف مجاز بیشتر است.',
            'consumed_at.required' => 'تاریخ مصرف را وارد کنید.',
            'consumed_at.date' => 'تاریخ مصرف معتبر نیست.',
            'note.max' => 'توضیحات نباید بیشتر از ۵۰۰۰ نویسه باشد.',
        ];
    }

    private function memberExistsRule(): Exists
    {
        $room = $this->route('room');
        $roomId = $room instanceof Room ? $room->id : $room;

        return Rule::exists('room_members', 'id')
            ->where('room_id', $roomId)
            ->where('is_active', true);
    }
}
