<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import BalanceCard from '../components/balances/BalanceCard.vue';
import { apiError } from '../api/http';
import { getBalances } from '../api/balances.api';
import { getMembers } from '../api/rooms.api';
import { useToastStore } from '../stores/toast';
import { formatToman } from '../utils/number';

const route = useRoute();
const toast = useToastStore();
const balances = ref([]);
const members = ref([]);
const selectedMemberId = ref('');
const loading = ref(true);
const error = ref('');
const netPosition = computed(() => {
    if (!selectedMemberId.value) return null;
    const memberId = Number(selectedMemberId.value);
    return balances.value.reduce((net, balance) => {
        if (balance.creditor_member_id === memberId) return net + balance.amount_toman;
        if (balance.debtor_member_id === memberId) return net - balance.amount_toman;
        return net;
    }, 0);
});

async function load() {
    loading.value = true;
    error.value = '';
    try {
        [balances.value, members.value] = await Promise.all([
            getBalances(route.params.roomId, selectedMemberId.value || null),
            getMembers(route.params.roomId, null),
        ]);
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(selectedMemberId, load);
watch(() => route.params.roomId, load);
</script>

<template>
    <section>
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-bold text-brand-700">تهاتر دوطرفه</p><h1 class="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">حساب‌وکتاب</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">هر ردیف می‌گوید چه کسی باید به چه کسی پرداخت کند. بدهی فرد سوم با این ردیف‌ها ادغام نمی‌شود.</p></div>
            <RouterLink class="btn-primary" :to="{ name: 'settlements', params: { roomId: route.params.roomId } }">تاریخچه و ثبت تسویه</RouterLink>
        </header>
        <div class="mt-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <label class="flex items-center gap-3"><span class="shrink-0 text-sm font-bold text-slate-600">فیلتر عضو</span><select v-model="selectedMemberId" class="field-input sm:min-w-56"><option value="">همه اعضا</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.display_name }}{{ member.is_active ? '' : ' · غیرفعال' }}</option></select></label>
            <p v-if="netPosition !== null" class="text-sm font-semibold" :class="netPosition > 0 ? 'text-emerald-700' : netPosition < 0 ? 'text-rose-700' : 'text-slate-600'">خالص آماری این عضو: {{ formatToman(Math.abs(netPosition)) }} {{ netPosition > 0 ? 'طلبکار' : netPosition < 0 ? 'بدهکار' : 'تسویه' }}</p>
            <p v-else class="text-xs text-slate-500">خالص شخصی فقط آمار است و جای حساب دوبه‌دو را نمی‌گیرد.</p>
        </div>
        <p v-if="error" class="mt-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ error }}</p>
        <div v-if="loading" class="mt-5 space-y-3"><div v-for="item in 4" :key="item" class="h-28 animate-pulse rounded-2xl bg-white"></div></div>
        <div v-else-if="balances.length" class="mt-5 space-y-3"><BalanceCard v-for="balance in balances" :key="`${balance.debtor_member_id}-${balance.creditor_member_id}`" :balance="balance" /></div>
        <div v-else-if="!error" class="empty-card mt-5"><span class="text-2xl text-emerald-700">✓</span><p class="mt-2 font-bold text-slate-800">حساب باز پیدا نشد</p><p class="mt-1 text-sm text-slate-500">برای عضو انتخاب‌شده یا کل اتاق مانده‌ای باقی نمانده است.</p></div>
        <div class="mt-5 rounded-2xl bg-slate-100 p-4 text-xs leading-6 text-slate-600">تهاتر بدهی‌های متقابل بین همان دو عضو انجام می‌شود. اگر A به B بدهکار باشد و B به C، این دو حساب مستقل باقی می‌مانند.</div>
    </section>
</template>
