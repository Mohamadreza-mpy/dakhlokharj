<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

class StorePurchaseRequest extends FormRequest
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
            'payer_member_id' => ['required', 'integer', $this->memberExistsRule(true)],
            'title' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::in(['inventory', 'shared'])],
            'amount_toman' => ['required', 'integer', 'min:1', 'max:9000000000'],
            'unit_label' => ['nullable', 'required_if:kind,inventory', Rule::in(['عدد', 'کیلوگرم', 'گرم', 'لیتر', 'بسته'])],
            'total_quantity_milliunits' => ['nullable', 'required_if:kind,inventory', 'integer', 'min:1', 'max:1000000000'],
            'share_mode' => ['nullable', 'required_if:kind,shared', 'prohibited_unless:kind,shared', Rule::in(['equal', 'custom'])],
            'participants' => ['nullable', 'required_if:share_mode,equal', 'prohibited_unless:share_mode,equal', 'array', 'min:1', 'max:100'],
            'participants.*' => ['required', 'integer', 'distinct', $this->memberExistsRule(true)],
            'allocations' => ['nullable', 'required_if:share_mode,custom', 'prohibited_unless:share_mode,custom', 'array', 'min:1', 'max:100'],
            'allocations.*.room_member_id' => ['required', 'integer', 'distinct', $this->memberExistsRule(true)],
            'allocations.*.amount_toman' => ['required', 'integer', 'min:1', 'max:9000000000'],
            'purchased_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('share_mode') === 'custom' && ! $validator->errors()->hasAny(['amount_toman', 'allocations'])) {
                $sum = collect($this->input('allocations', []))->sum('amount_toman');
                if ((int) $sum !== $this->integer('amount_toman')) {
                    $validator->errors()->add('allocations', 'جمع سهم‌ها باید دقیقاً برابر مبلغ کل خرید باشد.');
                }
            }

            if ($this->input('kind') === 'inventory' && ! $validator->errors()->hasAny(['unit_label', 'total_quantity_milliunits'])) {
                $quantity = $this->integer('total_quantity_milliunits');
                if (in_array($this->input('unit_label'), ['عدد', 'بسته'], true) && $quantity % 1000 !== 0) {
                    $validator->errors()->add('total_quantity_milliunits', 'برای واحد عدد و بسته، مقدار باید کامل باشد.');
                }
            }

            if ($this->input('kind') === 'shared' && $this->filled('total_quantity_milliunits')) {
                if (! $this->filled('unit_label')) {
                    $validator->errors()->add('unit_label', 'برای ثبت مقدار توصیفی، واحد را هم انتخاب کنید.');
                } elseif (in_array($this->input('unit_label'), ['عدد', 'بسته'], true) && $this->integer('total_quantity_milliunits') % 1000 !== 0) {
                    $validator->errors()->add('total_quantity_milliunits', 'برای واحد عدد و بسته، مقدار باید کامل باشد.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'شناسه درخواست الزامی است.',
            'request_id.uuid' => 'شناسه درخواست معتبر نیست.',
            'payer_member_id.required' => 'خریدار را انتخاب کنید.',
            'title.required' => 'عنوان خرید را وارد کنید.',
            'title.max' => 'عنوان خرید نباید بیشتر از ۲۰۰ نویسه باشد.',
            'kind.required' => 'نوع خرید را انتخاب کنید.',
            'kind.in' => 'نوع خرید معتبر نیست.',
            'amount_toman.required' => 'مبلغ خرید را وارد کنید.',
            'amount_toman.integer' => 'مبلغ باید عدد صحیح تومان باشد.',
            'amount_toman.min' => 'مبلغ باید بیشتر از صفر باشد.',
            'amount_toman.max' => 'مبلغ از سقف مجاز بیشتر است.',
            'unit_label.required_if' => 'واحد کالا را انتخاب کنید.',
            'unit_label.in' => 'واحد کالا معتبر نیست.',
            'total_quantity_milliunits.required_if' => 'مقدار کل کالا را وارد کنید.',
            'total_quantity_milliunits.integer' => 'مقدار باید با دقت هزارم وارد شود.',
            'total_quantity_milliunits.min' => 'مقدار باید بیشتر از صفر باشد.',
            'share_mode.required_if' => 'روش تقسیم هزینه را انتخاب کنید.',
            'share_mode.in' => 'روش تقسیم هزینه معتبر نیست.',
            'participants.required_if' => 'حداقل یک شریک خرج را انتخاب کنید.',
            'participants.min' => 'حداقل یک شریک خرج را انتخاب کنید.',
            'participants.*.distinct' => 'یک عضو را دوبار انتخاب نکنید.',
            'participants.*.exists' => 'همه افراد انتخاب‌شده باید عضو فعال همین اتاق باشند.',
            'allocations.required_if' => 'سهم هر عضو را وارد کنید.',
            'allocations.min' => 'حداقل یک سهم لازم است.',
            'allocations.*.room_member_id.distinct' => 'برای هر عضو فقط یک سهم ثبت کنید.',
            'allocations.*.room_member_id.exists' => 'همه افراد انتخاب‌شده باید عضو فعال همین اتاق باشند.',
            'allocations.*.amount_toman.integer' => 'سهم باید عدد صحیح تومان باشد.',
            'allocations.*.amount_toman.min' => 'سهم هر عضو باید بیشتر از صفر باشد.',
            'purchased_at.required' => 'تاریخ خرید را وارد کنید.',
            'purchased_at.date' => 'تاریخ خرید معتبر نیست.',
            'note.max' => 'توضیحات نباید بیشتر از ۵۰۰۰ نویسه باشد.',
        ];
    }

    private function memberExistsRule(bool $active): Exists
    {
        $room = $this->route('room');
        $roomId = $room instanceof Room ? $room->id : $room;

        $rule = Rule::exists('room_members', 'id')->where('room_id', $roomId);

        if ($active) {
            $rule->where('is_active', true);
        }

        return $rule;
    }
}
