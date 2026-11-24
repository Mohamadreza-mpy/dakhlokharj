<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { apiError } from '../api/http';
import { getPurchases } from '../api/purchases.api';
import { getMembers } from '../api/rooms.api';
import PurchaseCard from '../components/purchases/PurchaseCard.vue';

const route = useRoute();
const purchases = ref([]);
const members = ref([]);
const loading = ref(true);
const error = ref('');
const kind = ref(route.query.kind ?? '');
const status = ref('posted');
const payerMemberId = ref('');
const from = ref('');
const to = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const response = await getPurchases(route.params.roomId, {
            ...(kind.value ? { kind: kind.value } : {}),
            ...(status.value ? { status: status.value } : {}),
            ...(payerMemberId.value ? { payer_member_id: payerMemberId.value } : {}),
            ...(from.value ? { from: from.value } : {}),
            ...(to.value ? { to: to.value } : {}),
            per_page: 50,
        });
        purchases.value = response.data ?? [];
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        loading.value = false;
    }
}

async function loadMembers() {
    try {
        members.value = await getMembers(route.params.roomId, null);
    } catch (requestError) {
        error.value = apiError(requestError);
    }
}

onMounted(load);
onMounted(loadMembers);
watch([kind, status, payerMemberId, from, to], load);
watch(() => route.params.roomId, () => { load(); loadMembers(); });
</script>

<template>
    <section>
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-bold text-brand-700">ثبت‌های مالی اتاق</p><h1 class="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">خریدها و خرج‌ها</h1><p class="mt-2 text-sm text-slate-500">خرید موجودی‌دار و خرجی که همان لحظه بین افراد تقسیم می‌شود.</p></div>
            <RouterLink class="btn-primary" :to="{ name: 'purchase-create', params: { roomId: route.params.roomId } }">＋ خرید جدید</RouterLink>
        </header>
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <label class="block space-y-2"><span class="text-xs font-bold text-slate-500">نوع</span><select v-model="kind" class="field-input"><option value="">همه انواع</option><option value="inventory">موجودی‌دار</option><option value="shared">خرج مشترک</option></select></label>
                <label class="block space-y-2"><span class="text-xs font-bold text-slate-500">وضعیت</span><select v-model="status" class="field-input"><option value="posted">ثبت‌شده</option><option value="voided">باطل‌شده</option><option value="">همه وضعیت‌ها</option></select></label>
                <label class="block space-y-2"><span class="text-xs font-bold text-slate-500">پرداخت‌کننده</span><select v-model="payerMemberId" class="field-input"><option value="">همه اعضا</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.display_name }}</option></select></label>
                <label class="block space-y-2"><span class="text-xs font-bold text-slate-500">از تاریخ</span><input v-model="from" type="date" class="field-input"></label>
                <label class="block space-y-2"><span class="text-xs font-bold text-slate-500">تا تاریخ</span><input v-model="to" type="date" class="field-input"></label>
            </div>
        </section>
        <p v-if="error" class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ error }}</p>
        <div v-if="loading" class="mt-5 grid gap-3 md:grid-cols-2"><div v-for="item in 6" :key="item" class="h-36 animate-pulse rounded-2xl bg-white"></div></div>
        <div v-else-if="purchases.length" class="mt-5 grid gap-3 md:grid-cols-2"><PurchaseCard v-for="purchase in purchases" :key="purchase.id" :purchase="purchase" /></div>
        <div v-else-if="!error" class="empty-card mt-5"><p class="font-bold text-slate-800">خریدی در این فهرست نیست</p><p class="mt-1 text-sm text-slate-500">با ثبت اولین خرید، موجودی و سهم اعضا در همین صفحه نمایش داده می‌شود.</p></div>
    </section>
</template>
