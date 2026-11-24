<script setup>
import { RouterLink } from 'vue-router';
import StockBadge from '../inventory/StockBadge.vue';
import { formatJalaliDay } from '../../utils/jalali';
import { formatQuantity, formatToman } from '../../utils/number';

defineProps({ purchase: { type: Object, required: true } });
</script>

<template>
    <RouterLink :to="{ name: 'purchase-details', params: { purchaseId: purchase.id } }" class="group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-100 hover:shadow-md">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-extrabold text-slate-900 group-hover:text-brand-700">{{ purchase.title }}</h3>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold" :class="purchase.kind === 'inventory' ? 'bg-sky-50 text-sky-700' : 'bg-violet-50 text-violet-700'">
                        {{ purchase.kind === 'inventory' ? 'موجودی‌دار' : 'خرج مشترک' }}
                    </span>
                    <span v-if="purchase.status === 'voided'" class="rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700">باطل‌شده</span>
                </div>
                <p class="mt-2 text-sm text-slate-500">پرداخت‌کننده: {{ purchase.payer?.display_name ?? purchase.payer_name }} · {{ formatJalaliDay(purchase.purchased_at) }}</p>
            </div>
            <span class="text-xs text-slate-400">←</span>
        </div>
        <div class="mt-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs text-slate-500">مبلغ خرید</p>
                <p class="mt-1 text-lg font-black text-slate-900">{{ formatToman(purchase.amount_toman) }}</p>
            </div>
            <StockBadge v-if="purchase.kind === 'inventory' && purchase.status === 'posted'" :quantity="purchase.remaining_quantity_milliunits" :unit-label="purchase.unit_label" />
            <span v-else-if="purchase.kind === 'inventory'" class="text-xs text-slate-400">{{ formatQuantity(purchase.total_quantity_milliunits, purchase.unit_label) }}</span>
            <span v-else class="text-xs text-slate-500">{{ purchase.allocations?.length ?? 0 }} شریک خرج</span>
        </div>
    </RouterLink>
</template>
