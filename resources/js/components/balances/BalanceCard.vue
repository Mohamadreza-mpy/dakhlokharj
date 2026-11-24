<script setup>
import { RouterLink } from 'vue-router';
import { formatToman } from '../../utils/number';

defineProps({ balance: { type: Object, required: true } });
</script>

<template>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-slate-500">{{ balance.debtor_name }} باید به {{ balance.creditor_name }} بدهد</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ formatToman(balance.amount_toman) }}</p>
                <p class="mt-1 text-xs text-slate-500">مانده پس از تهاتر دوطرفه</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <RouterLink class="btn-secondary" :to="{ name: 'member-balance', params: { memberId: balance.debtor_member_id } }">ریزحساب</RouterLink>
                <RouterLink
                    class="btn-primary"
                    :to="{ name: 'settlements', query: { from: balance.debtor_member_id, to: balance.creditor_member_id, amount: balance.amount_toman } }"
                >ثبت تسویه</RouterLink>
            </div>
        </div>
    </article>
</template>
