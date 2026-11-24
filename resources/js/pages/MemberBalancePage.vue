<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { apiError } from '../api/http';
import { getMemberBalance, getMemberLedger } from '../api/balances.api';
import { useToastStore } from '../stores/toast';
import { formatJalaliDate, formatToman } from '../utils/number';

const route = useRoute();
const toast = useToastStore();
const summary = ref(null);
const ledger = ref([]);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const [memberSummary, ledgerPage] = await Promise.all([
            getMemberBalance(route.params.roomId, route.params.memberId),
            getMemberLedger(route.params.roomId, route.params.memberId, { per_page: 100 }),
        ]);
        summary.value = memberSummary;
        ledger.value = ledgerPage.data;
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => [route.params.memberId, route.params.roomId], load);
</script>

<template>
    <section>
        <RouterLink :to="{ name: 'balances', params: { roomId: route.params.roomId } }" class="text-sm font-bold text-brand-700">← بازگشت به حساب‌ها</RouterLink>
        <div v-if="loading" class="mt-5 h-48 animate-pulse rounded-3xl bg-white"></div>
        <p v-else-if="error" class="mt-5 rounded-2xl bg-rose-50 p-5 text-rose-800">{{ error }}</p>
        <template v-else-if="summary">
            <header class="mt-4"><p class="text-sm font-bold text-brand-700">ریزحساب قابل ردیابی</p><h1 class="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">حساب {{ summary.member_name }}</h1><p class="mt-2 text-sm text-slate-500">هر ردیف به منشأ هزینه یا پرداخت واقعی وصل است.</p></header>
            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <article class="stat-card"><p class="stat-label">این عضو باید بدهد</p><p class="stat-value text-rose-700">{{ formatToman(summary.total_owed_toman) }}</p></article>
                <article class="stat-card"><p class="stat-label">این عضو باید بگیرد</p><p class="stat-value text-emerald-700">{{ formatToman(summary.total_receivable_toman) }}</p></article>
                <article class="stat-card"><p class="stat-label">خالص آماری</p><p class="stat-value" :class="summary.net_position_toman >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ formatToman(Math.abs(summary.net_position_toman)) }}</p><p class="mt-1 text-xs text-slate-400">{{ summary.net_position_toman > 0 ? 'طلبکار' : summary.net_position_toman < 0 ? 'بدهکار' : 'تسویه' }}</p></article>
            </div>
            <div class="mt-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between"><div><h2 class="section-title">گردش رویدادها</h2><p class="section-subtitle">مانده هر ردیف از دید {{ summary.member_name }} و در برابر همان طرف حساب است.</p></div><button class="btn-secondary !px-3 !py-2 text-xs" @click="load">تازه‌سازی</button></div>
                <div v-if="ledger.length" class="mt-4 overflow-x-auto">
                    <table class="min-w-[760px] w-full text-right text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="rounded-r-xl px-3 py-3">تاریخ</th><th class="px-3 py-3">نوع و شرح</th><th class="px-3 py-3">طرف حساب</th><th class="px-3 py-3">بدهی ایجادشده</th><th class="px-3 py-3">طلب ایجادشده</th><th class="px-3 py-3">اثر رویداد</th><th class="rounded-l-xl px-3 py-3">مانده جفت</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, index) in ledger" :key="`${row.type}-${index}`">
                                <td class="whitespace-nowrap px-3 py-3 text-xs text-slate-500">{{ formatJalaliDate(row.occurred_at, true) }}</td>
                                <td class="px-3 py-3"><span class="block font-bold text-slate-800">{{ row.type === 'shared' ? 'خرج مشترک' : row.type === 'consume' ? 'مصرف' : row.type === 'reversal' ? 'برگشت' : row.type === 'payment' ? 'تسویه' : 'برگشت تسویه' }}</span><span class="mt-1 block max-w-xs text-xs leading-5 text-slate-500">{{ row.description }}</span></td>
                                <td class="px-3 py-3 font-semibold">{{ row.other_member_name }}</td>
                                <td class="px-3 py-3">{{ row.owes_to_counterparty_toman ? formatToman(Math.abs(row.owes_to_counterparty_toman)) : '—' }}</td>
                                <td class="px-3 py-3">{{ row.receivable_from_counterparty_toman ? formatToman(Math.abs(row.receivable_from_counterparty_toman)) : '—' }}</td>
                                <td class="px-3 py-3 font-bold" :class="row.effect_on_balance_toman > 0 ? 'text-rose-700' : 'text-emerald-700'">{{ row.effect_on_balance_toman > 0 ? 'افزایش بدهی' : 'کاهش بدهی' }}<span class="mt-1 block text-xs">{{ formatToman(Math.abs(row.effect_on_balance_toman)) }}</span></td>
                                <td class="px-3 py-3 font-black" :class="row.balance_after_toman > 0 ? 'text-rose-700' : row.balance_after_toman < 0 ? 'text-emerald-700' : 'text-slate-500'">{{ formatToman(Math.abs(row.balance_after_toman)) }}<span class="mt-1 block text-[11px]">{{ row.balance_after_toman > 0 ? 'بدهکار' : row.balance_after_toman < 0 ? 'طلبکار' : 'تسویه' }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="mt-5 rounded-2xl bg-slate-50 p-8 text-center text-sm text-slate-500">برای این عضو رویداد مالی ثبت نشده است.</p>
            </div>
            <div v-if="summary.balances.length" class="mt-5 space-y-3"><h2 class="section-title">مانده‌های باز</h2><RouterLink v-for="balance in summary.balances" :key="`${balance.debtor_member_id}-${balance.creditor_member_id}`" :to="{ name: 'settlements', params: { roomId: route.params.roomId }, query: { from: balance.debtor_member_id, to: balance.creditor_member_id, amount: balance.amount_toman } }" class="block rounded-2xl border border-slate-200 bg-white p-4"><p class="font-bold text-slate-800">{{ balance.debtor_name }} باید به {{ balance.creditor_name }} بدهد</p><p class="mt-1 font-black text-slate-900">{{ formatToman(balance.amount_toman) }}</p></RouterLink></div>
        </template>
    </section>
</template>
