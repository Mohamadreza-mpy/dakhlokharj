<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreSettlementRequest extends FormRequest
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
            'from_member_id' => ['required', 'integer', $this->memberExistsRule()],
            'to_member_id' => ['required', 'integer', 'different:from_member_id', $this->memberExistsRule()],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:9000000000'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'شناسه درخواست الزامی است.',
            'request_id.uuid' => 'شناسه درخواست معتبر نیست.',
            'from_member_id.required' => 'پرداخت‌کننده را انتخاب کنید.',
            'from_member_id.exists' => 'پرداخت‌کننده باید عضو همین اتاق باشد.',
            'to_member_id.required' => 'دریافت‌کننده را انتخاب کنید.',
            'to_member_id.exists' => 'دریافت‌کننده باید عضو همین اتاق باشد.',
            'to_member_id.different' => 'پرداخت‌کننده و دریافت‌کننده باید متفاوت باشند.',
            'amount_toman.required' => 'مبلغ پرداخت را وارد کنید.',
            'amount_toman.integer' => 'مبلغ باید عدد صحیح تومان باشد.',
            'amount_toman.min' => 'مبلغ پرداخت باید بیشتر از صفر باشد.',
            'amount_toman.max' => 'مبلغ از سقف مجاز بیشتر است.',
            'paid_at.required' => 'تاریخ پرداخت را وارد کنید.',
            'paid_at.date' => 'تاریخ پرداخت معتبر نیست.',
            'note.max' => 'توضیحات نباید بیشتر از ۵۰۰۰ نویسه باشد.',
        ];
    }

    private function memberExistsRule(): Exists
    {
        $room = $this->route('room');
        $roomId = $room instanceof Room ? $room->id : $room;

        return Rule::exists('room_members', 'id')->where('room_id', $roomId);
    }
}
