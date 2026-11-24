<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import ConfirmDialog from '../components/ui/ConfirmDialog.vue';
import StockBadge from '../components/inventory/StockBadge.vue';
import { apiError } from '../api/http';
import { reverseConsumption } from '../api/consumptions.api';
import { getPurchase, voidPurchase } from '../api/purchases.api';
import { useToastStore } from '../stores/toast';
import { formatJalaliDay } from '../utils/jalali';
import { formatJalaliDate, formatQuantity, formatToman, requestId } from '../utils/number';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const purchase = ref(null);
const loading = ref(true);
const error = ref('');
const busy = ref(false);
const dialog = ref(null);
const dialogRequestId = ref('');
const reversedIds = computed(() => new Set((purchase.value?.consumptions ?? []).filter((entry) => entry.reverses_entry_id).map((entry) => entry.reverses_entry_id)));

async function load() {
    loading.value = true;
    error.value = '';
    try {
        purchase.value = await getPurchase(route.params.roomId, route.params.purchaseId);
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        loading.value = false;
    }
}

function confirmVoid() {
    dialogRequestId.value = requestId();
    dialog.value = { type: 'purchase' };
}

function confirmReverse(entry) {
    dialogRequestId.value = requestId();
    dialog.value = { type: 'consumption', entry };
}

async function confirmAction() {
    busy.value = true;
    try {
        if (dialog.value.type === 'purchase') {
            await voidPurchase(route.params.roomId, purchase.value.id, { request_id: dialogRequestId.value, note: 'ابطال توسط مدیر اتاق' });
            toast.show('خرید با حفظ سابقه باطل شد.');
        } else {
            await reverseConsumption(route.params.roomId, dialog.value.entry.id, { request_id: dialogRequestId.value, note: 'برگشت مصرف توسط مدیر اتاق' });
            toast.show('مصرف برگشت خورد و موجودی آزاد شد.');
        }
        dialog.value = null;
        await load();
    } catch (requestError) {
        error.value = apiError(requestError);
        toast.show(error.value, 'error');
    } finally {
        busy.value = false;
    }
}

onMounted(load);
watch(() => [route.params.roomId, route.params.purchaseId], load);
</script>

<template>
    <section>
        <RouterLink :to="{ name: 'purchases', params: { roomId: route.params.roomId } }" class="text-sm font-bold text-brand-700">← بازگشت به خریدها</RouterLink>
        <div v-if="loading" class="mt-5 h-64 animate-pulse rounded-3xl bg-white"></div>
        <p v-else-if="error && !purchase" class="mt-5 rounded-2xl bg-rose-50 p-5 text-rose-800">{{ error }}</p>
        <template v-else-if="purchase">
            <header class="mt-4 flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between sm:p-7">
                <div>
                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-3 py-1 text-xs font-bold" :class="purchase.kind === 'inventory' ? 'bg-sky-50 text-sky-700' : 'bg-violet-50 text-violet-700'">{{ purchase.kind === 'inventory' ? 'خرید موجودی‌دار' : 'خرج مشترک' }}</span><span v-if="purchase.status === 'voided'" class="rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700">باطل‌شده</span></div>
                    <h1 class="mt-3 text-2xl font-black text-slate-900 sm:text-3xl">{{ purchase.title }}</h1>
                    <p class="mt-2 text-sm text-slate-500">پرداخت‌کننده: {{ purchase.payer?.display_name }} · {{ formatJalaliDay(purchase.purchased_at) }}</p>
                    <p v-if="purchase.unit_label" class="mt-1 text-sm font-semibold text-slate-600">مقدار توصیفی: {{ purchase.total_quantity_milliunits ? formatQuantity(purchase.total_quantity_milliunits, purchase.unit_label) : purchase.unit_label }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <RouterLink v-if="purchase.kind === 'inventory' && purchase.status === 'posted' && purchase.remaining_quantity_milliunits > 0" class="btn-primary" :to="{ name: 'consumption-create', params: { roomId: route.params.roomId, purchaseId: purchase.id } }">ثبت مصرف</RouterLink>
                    <button v-if="purchase.status === 'posted'" class="btn-danger" @click="confirmVoid">ابطال خرید</button>
                </div>
            </header>

            <p v-if="error" class="mt-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ error }}</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="stat-card"><p class="stat-label">مبلغ کل</p><p class="stat-value">{{ formatToman(purchase.amount_toman) }}</p></article>
                <article v-if="purchase.kind === 'inventory'" class="stat-card"><p class="stat-label">موجودی اولیه</p><p class="stat-value">{{ formatQuantity(purchase.total_quantity_milliunits, purchase.unit_label) }}</p></article>
                <article v-if="purchase.kind === 'inventory'" class="stat-card"><p class="stat-label">موجودی فعلی</p><div class="mt-2"><StockBadge :quantity="purchase.remaining_quantity_milliunits" :unit-label="purchase.unit_label" /></div></article>
                <article class="stat-card"><p class="stat-label">وضعیت ثبت</p><p class="mt-2 text-base font-black" :class="purchase.status === 'posted' ? 'text-emerald-700' : 'text-rose-700'">{{ purchase.status === 'posted' ? 'فعال' : 'باطل‌شده' }}</p></article>
            </div>

            <div v-if="purchase.kind === 'shared'" class="mt-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div><h2 class="section-title">سهم شرکای خرج</h2><p class="section-subtitle">سهم خریدار هم ثبت می‌شود، اما بدهی به خودش ایجاد نمی‌کند.</p></div>
                <div v-if="purchase.allocations?.length" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="allocation in purchase.allocations" :key="allocation.id" class="flex items-center justify-between rounded-2xl bg-slate-50 p-4"><span class="font-bold text-slate-800">{{ allocation.member_name }}</span><span class="font-black text-slate-900">{{ formatToman(allocation.amount_toman) }}</span></div>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">سهمی برای این خرج ثبت نشده است.</p>
            </div>

            <div v-else class="mt-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="section-title">تاریخچه مصرف</h2><p class="section-subtitle">مبلغ هر رویداد در زمان ثبت قطعی شده؛ برگشت‌ها برای حسابرسی باقی می‌مانند.</p></div><span class="text-xs text-slate-500">{{ purchase.consumptions?.length ?? 0 }} رویداد</span></div>
                <div v-if="purchase.consumptions?.length" class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-right text-sm">
                        <thead class="text-xs text-slate-500"><tr><th class="px-3 py-3">مصرف‌کننده</th><th class="px-3 py-3">مقدار</th><th class="px-3 py-3">مبلغ تخصیص</th><th class="px-3 py-3">تاریخ</th><th class="px-3 py-3">وضعیت</th><th class="px-3 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="entry in purchase.consumptions" :key="entry.id">
                                <td class="px-3 py-3 font-semibold text-slate-800">{{ entry.consumer_name }}</td>
                                <td class="px-3 py-3">{{ formatQuantity(Math.abs(entry.quantity_milliunits), purchase.unit_label) }}</td>
                                <td class="px-3 py-3 font-bold">{{ formatToman(Math.abs(entry.allocated_amount_toman)) }}<span v-if="entry.allocated_amount_toman < 0" class="mr-1 text-xs text-rose-700">(برگشت)</span></td>
                                <td class="px-3 py-3 text-xs text-slate-500">{{ formatJalaliDate(entry.consumed_at, true) }}</td>
                                <td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs font-bold" :class="entry.entry_type === 'consume' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'">{{ entry.entry_type === 'consume' ? 'مصرف' : 'برگشت' }}</span></td>
                                <td class="px-3 py-3"><button v-if="entry.entry_type === 'consume' && !reversedIds.has(entry.id) && purchase.status === 'posted'" class="text-xs font-bold text-rose-700" @click="confirmReverse(entry)">برگشت مصرف</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="mt-4 rounded-2xl bg-slate-50 p-8 text-center"><p class="font-bold text-slate-800">هنوز مصرفی ثبت نشده</p><p class="mt-1 text-sm text-slate-500">تا وقتی مصرفی ثبت نشود، برای اعضا بدهی ایجاد نمی‌شود.</p></div>
            </div>
            <p v-if="purchase.status === 'voided'" class="mt-5 rounded-2xl bg-rose-50 p-4 text-sm leading-6 text-rose-800">این خرید باطل شده و سهم‌هایش در محاسبه مانده حساب نمی‌شوند. {{ purchase.void_note }}</p>
        </template>

        <ConfirmDialog :open="Boolean(dialog)" :title="dialog?.type === 'purchase' ? 'ابطال خرید' : 'برگشت مصرف'" :description="dialog?.type === 'purchase' ? 'خرید از مانده‌ها خارج می‌شود و سابقه آن حفظ خواهد شد. برای خرید موجودی‌دار ابتدا همه مصرف‌ها را برگشت بزنید.' : `مصرف ${dialog?.entry?.consumer_name ?? ''} با مبلغ و مقدار معکوس ثبت می‌شود.`" :confirm-label="dialog?.type === 'purchase' ? 'ابطال خرید' : 'ثبت برگشت'" :danger="true" :busy="busy" @close="dialog = null" @confirm="confirmAction" />
    </section>
</template>
