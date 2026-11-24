<script setup>
import { formatQuantity } from '../../utils/number';

defineProps({ quantity: { type: Number, default: 0 }, unitLabel: { type: String, default: '' } });
</script>

<template>
    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="quantity < 5000 ? 'bg-amber-50 text-amber-800' : 'bg-brand-50 text-brand-700'">
        موجودی {{ formatQuantity(quantity, unitLabel) }}
    </span>
</template>
