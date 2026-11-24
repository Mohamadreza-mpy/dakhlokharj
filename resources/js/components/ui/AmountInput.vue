<script setup>
import { normalizeDigits } from '../../utils/number';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, required: true },
    placeholder: { type: String, default: 'مثلاً ۱۲۵۰۰۰' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    groupThousands: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function groupDigits(digits) {
    const firstGroupLength = digits.length % 3 || 3;
    return digits.slice(0, firstGroupLength) + digits.slice(firstGroupLength).replace(/\d{3}/g, ',$&');
}

function formatAmount(value) {
    if (!props.groupThousands) return value;

    const digits = normalizeDigits(value);
    return /^\d*$/.test(digits) ? groupDigits(digits) : value;
}

function updateAmount(event) {
    if (props.groupThousands && event.isComposing) return;

    const input = event.target;
    const formatted = formatAmount(input.value);

    if (formatted !== input.value) {
        const digitsAfterCaret = normalizeDigits(input.value.slice(input.selectionEnd));
        let caret = formatted.length - groupDigits(digitsAfterCaret).length;
        if (event.inputType === 'deleteContentBackward' && formatted[caret - 1] === ',') {
            caret--;
        }

        input.value = formatted;
        input.setSelectionRange(caret, caret);
    }

    emit('update:modelValue', formatted);
}
</script>

<template>
    <label class="block space-y-2">
        <span class="block text-sm font-semibold text-slate-700">{{ label }}<span v-if="required" class="mr-1 text-rose-600">*</span></span>
        <input
            :value="formatAmount(modelValue)"
            type="text"
            inputmode="numeric"
            dir="ltr"
            :placeholder="placeholder"
            class="field-input text-left"
            :class="error ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : ''"
            :aria-invalid="Boolean(error)"
            @input="updateAmount"
            @compositionend="groupThousands && updateAmount($event)"
        >
        <span v-if="hint && !error" class="block text-xs text-slate-500">{{ hint }}</span>
        <span v-if="error" class="block text-xs font-medium text-rose-700">{{ error }}</span>
    </label>
</template>
