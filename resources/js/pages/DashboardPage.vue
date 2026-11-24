<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { getDashboard } from '../api/rooms.api';
import { apiError } from '../api/http';
import BalanceCard from '../components/balances/BalanceCard.vue';
import PurchaseCard from '../components/purchases/PurchaseCard.vue';
import { useToastStore } from '../stores/toast';
import { formatJalaliDate, formatToman } from '../utils/number';

const route = useRoute();
const toast = useToastStore();
const dashboard = ref(null);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        dashboard.value = await getDashboard(route.params.roomId);
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => route.params.roomId, load);
</script>

<template>
    <section>
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-bold text-brand-700">نمای کلی اتاق</p><h1 class="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">داشبورد دخل و خرج</h1><p class="mt-2 text-sm text-slate-500">خرید، هزینه منتسب‌شده و مانده حساب‌ها هر کدام شاخص جداگانه‌اند.</p></div>
            <div class="flex gap-2"><button class="btn-secondary" :disabled="loading" @click="load">تازه‌سازی</button><RouterLink class="btn-primary" :to="{ name: 'purchase-create', params: { roomId: route.params.roomId } }">＋ ثبت خرید</RouterLink></div>
        </header>

        <div v-if="loading" class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><div v-for="item in 4" :key="item" class="h-28 animate-pulse rounded-2xl bg-white shadow-sm"></div></div>
        <div v-else-if="error" class="mt-6 rounded-2xl bg-rose-50 p-5 text-rose-800">{{ error }}</div>
        <template v-else-if="dashboard">
            <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="stat-card"><span class="stat-icon bg-sky-50 text-sky-700">▣</span><p class="stat-label">مبلغ خریدهای ثبت‌شده</p><p class="stat-value">{{ formatToman(dashboard.stats.purchase_total_toman) }}</p></article>
                <article class="stat-card"><span class="stat-icon bg-emerald-50 text-emerald-700">◉</span><p class="stat-label">هزینه منتسب‌شده</p><p class="stat-value">{{ formatToman(dashboard.stats.attributed_expense_toman) }}</p><p class="mt-1 text-xs text-slate-400">شامل سهم خود خریدار</p></article>
                <article class="stat-card"><span class="stat-icon bg-violet-50 text-violet-700">⇄</span><p class="stat-label">پرداخت‌های واقعی ثبت‌شده</p><p class="stat-value">{{ formatToman(dashboard.stats.net_settled_toman) }}</p><p class="mt-1 text-xs text-slate-400">{{ dashboard.stats.settlements_count }} پرداخت</p></article>
                <article class="stat-card"><span class="stat-icon bg-amber-50 text-amber-700">↗</span><p class="stat-label">حساب‌های باز دوبه‌دو</p><p class="stat-value">{{ dashboard.stats.open_accounts_count }} حساب</p></article>
            </div>

            <div class="mt-6 grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
                <section class="space-y-5">
                    <div class="flex items-center justify-between"><div><h2 class="section-title">مانده‌های باز</h2><p class="section-subtitle">تهاتر فقط میان همان دو نفر انجام می‌شود.</p></div><RouterLink :to="{ name: 'balances', params: { roomId: route.params.roomId } }" class="text-sm font-bold text-brand-700">همه حساب‌ها ←</RouterLink></div>
                    <div v-if="dashboard.open_balances.length" class="space-y-3"><BalanceCard v-for="balance in dashboard.open_balances.slice(0, 4)" :key="`${balance.debtor_member_id}-${balance.creditor_member_id}`" :balance="balance" /></div>
                    <div v-else class="empty-card"><span class="text-2xl">✓</span><p class="mt-2 font-bold">حساب بازی وجود ندارد</p><p class="mt-1 text-sm text-slate-500">با ثبت مصرف یا خرج مشترک، مانده‌ها اینجا دیده می‌شوند.</p></div>

                    <div class="flex items-center justify-between"><div><h2 class="section-title">موجودی‌های باز</h2><p class="section-subtitle">ارزش خرید مصرف‌نشده به‌تنهایی بدهی نیست.</p></div><RouterLink :to="{ name: 'purchases', params: { roomId: route.params.roomId }, query: { kind: 'inventory' } }" class="text-sm font-bold text-brand-700">خریدها ←</RouterLink></div>
                    <div v-if="dashboard.inventory.length" class="grid gap-3 sm:grid-cols-2">
                        <RouterLink v-for="item in dashboard.inventory" :key="item.purchase_id" :to="{ name: 'purchase-details', params: { roomId: route.params.roomId, purchaseId: item.purchase_id } }" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-2"><div><p class="font-extrabold text-slate-900">{{ item.title }}</p><p class="mt-1 text-xs text-slate-500">خریدار: {{ item.payer_name }}</p></div><span class="text-xs text-slate-400">جزئیات ←</span></div>
                            <p class="mt-4 text-lg font-black text-slate-800">{{ formatToman(item.amount_toman) }}</p>
                            <p class="mt-1 text-sm font-semibold text-brand-700">باقی‌مانده: {{ (item.remaining_quantity_milliunits / 1000).toLocaleString('fa-IR', { maximumFractionDigits: 3 }) }} {{ item.unit_label }}</p>
                        </RouterLink>
                    </div>
                    <div v-else class="empty-card">موجودی بازی ثبت نشده است.</div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between"><div><h2 class="section-title">آخرین فعالیت‌ها</h2><p class="section-subtitle">تاریخچه خرید، مصرف، پرداخت و برگشت</p></div><span class="grid size-10 place-items-center rounded-2xl bg-slate-100">◷</span></div>
                    <div v-if="dashboard.recent_activities.length" class="mt-5 divide-y divide-slate-100">
                        <article v-for="activity in dashboard.recent_activities" :key="activity.id" class="flex items-start justify-between gap-3 py-4 first:pt-0">
                            <div class="flex gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-slate-50 text-sm text-brand-700">{{ activity.type.includes('settlement') || activity.type === 'payment' ? '⇄' : activity.type.includes('consum') ? '◉' : '▣' }}</span><div><p class="text-sm font-bold leading-6 text-slate-800">{{ activity.description }}</p><p class="mt-1 text-xs text-slate-400">{{ formatJalaliDate(activity.created_at, true) }}</p></div></div>
                            <span class="shrink-0 text-sm font-bold" :class="activity.amount_toman < 0 ? 'text-rose-700' : 'text-slate-700'">{{ formatToman(Math.abs(activity.amount_toman)) }}</span>
                        </article>
                    </div>
                    <div v-else class="mt-5 rounded-2xl bg-slate-50 p-8 text-center text-sm text-slate-500">هنوز فعالیتی ثبت نشده است.</div>
                </section>
            </div>
        </template>
    </section>
</template>
