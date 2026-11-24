<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseVoidRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'شناسه درخواست الزامی است.',
            'request_id.uuid' => 'شناسه درخواست معتبر نیست.',
            'note.max' => 'توضیحات نباید بیشتر از ۵۰۰۰ نویسه باشد.',
        ];
    }
}
