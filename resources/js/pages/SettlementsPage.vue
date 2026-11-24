<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AmountInput from '../components/ui/AmountInput.vue';
import ConfirmDialog from '../components/ui/ConfirmDialog.vue';
import JalaliDatePicker from '../components/ui/JalaliDatePicker.vue';
import { apiError } from '../api/http';
import { getBalances } from '../api/balances.api';
import { getMembers } from '../api/rooms.api';
import { createSettlement, getSettlements, reverseSettlement } from '../api/settlements.api';
import { useToastStore } from '../stores/toast';
import { formatJalaliDay, jalaliDateToIso, localJalaliDate } from '../utils/jalali';
import { formatToman, parseInteger, requestId } from '../utils/number';

const route = useRoute();
const toast = useToastStore();
const members = ref([]);
const balances = ref([]);
const settlements = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const requestKey = ref(requestId());
const dialog = ref(null);
const reverseRequestKey = ref('');
const paymentDate = ref(localJalaliDate());
const form = ref({
    from_member_id: route.query.from ? Number(route.query.from) : '',
    to_member_id: route.query.to ? Number(route.query.to) : '',
    amount_toman: route.query.amount ? String(route.query.amount) : '',
    note: '',
});
const selectedBalance = computed(() => balances.value.find((balance) =>
    balance.debtor_member_id === Number(form.value.from_member_id)
    && balance.creditor_member_id === Number(form.value.to_member_id),
));
const availableAmount = computed(() => selectedBalance.value?.amount_toman ?? 0);
const cleanAmount = computed(() => parseInteger(form.value.amount_toman));
const remainingAfter = computed(() => availableAmount.value - (cleanAmount.value ?? 0));

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const [memberData, balanceData, settlementPage] = await Promise.all([
            getMembers(route.params.roomId, null),
            getBalances(route.params.roomId),
            getSettlements(route.params.roomId, { per_page: 100 }),
        ]);
        members.value = memberData;
        balances.value = balanceData;
        settlements.value = settlementPage.data ?? [];
        if (!form.value.from_member_id && balances.value.length) {
            form.value.from_member_id = balances.value[0].debtor_member_id;
            form.value.to_member_id = balances.value[0].creditor_member_id;
            form.value.amount_toman = String(balances.value[0].amount_toman);
        }
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        loading.value = false;
    }
}

function selectDirection() {
    if (selectedBalance.value) form.value.amount_toman = String(selectedBalance.value.amount_toman);
}

async function submit() {
    error.value = '';
    if (!form.value.from_member_id || !form.value.to_member_id || Number(form.value.from_member_id) === Number(form.value.to_member_id)) {
        error.value = 'پرداخت‌کننده و دریافت‌کننده را انتخاب کنید؛ باید دو عضو متفاوت باشند.';
        return;
    }
    if (!cleanAmount.value || cleanAmount.value < 1 || cleanAmount.value > availableAmount.value) {
        error.value = 'مبلغ باید مثبت باشد و از مانده همین جهت بیشتر نشود.';
        return;
    }
    const paidOn = jalaliDateToIso(paymentDate.value);
    if (!paidOn) {
        error.value = 'تاریخ پرداخت معتبر نیست.';
        return;
    }

    busy.value = true;
    try {
        await createSettlement(route.params.roomId, {
            request_id: requestKey.value,
            from_member_id: Number(form.value.from_member_id),
            to_member_id: Number(form.value.to_member_id),
            amount_toman: cleanAmount.value,
            paid_at: paidOn,
            ...(form.value.note.trim() ? { note: form.value.note.trim() } : {}),
        });
        toast.show('تسویه واقعی ثبت شد و مانده‌ها به‌روز شدند.');
        requestKey.value = requestId();
        form.value.amount_toman = '';
        form.value.note = '';
        await load();
    } catch (requestError) {
        error.value = apiError(requestError, 'ثبت تسویه انجام نشد.');
        if ([409, 422].includes(requestError.response?.status)) requestKey.value = requestId();
    } finally {
        busy.value = false;
    }
}

function askReverse(settlement) {
    reverseRequestKey.value = requestId();
    dialog.value = settlement;
}

async function confirmReverse() {
    busy.value = true;
    try {
        await reverseSettlement(route.params.roomId, dialog.value.id, {
            request_id: reverseRequestKey.value,
            note: 'ابطال تسویه توسط مدیر اتاق',
        });
        dialog.value = null;
        toast.show('برگشت پرداخت ثبت شد؛ سابقه پرداخت باقی ماند.');
        await load();
    } catch (requestError) {
        error.value = apiError(requestError);
        toast.show(error.value, 'error');
    } finally {
        busy.value = false;
    }
}

onMounted(load);
watch(() => route.params.roomId, load);
</script>

<template>
    <section>
        <header><p class="text-sm font-bold text-brand-700">پرداخت واقعی وجه</p><h1 class="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">تسویه‌ها</h1><p class="mt-2 text-sm leading-6 text-slate-500">تهاتر فقط نمایش خالص بدهی است؛ پس از پرداخت واقعی، مبلغ تسویه در دفتر ثبت می‌شود.</p></header>
        <p v-if="error" class="mt-5 rounded-xl bg-rose-50 p-4 text-sm leading-6 text-rose-800" role="alert">{{ error }}</p>

        <div class="mt-5 grid gap-5 xl:grid-cols-[.85fr_1.15fr]">
            <form class="h-fit rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" @submit.prevent="submit">
                <div class="flex items-start justify-between gap-3"><div><h2 class="section-title">ثبت پرداخت</h2><p class="section-subtitle">جهت و مبلغ باید با مانده باز برابر باشد.</p></div><span class="grid size-10 place-items-center rounded-2xl bg-brand-50 text-brand-700">⇄</span></div>
                <div v-if="loading" class="mt-5 space-y-3"><div class="h-12 animate-pulse rounded-xl bg-slate-100"></div><div class="h-12 animate-pulse rounded-xl bg-slate-100"></div></div>
                <div v-else class="mt-5 space-y-4">
                    <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">پرداخت‌کننده</span><select v-model="form.from_member_id" class="field-input" @change="selectDirection"><option value="" disabled>انتخاب بدهکار</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.display_name }}{{ member.is_active ? '' : ' · غیرفعال' }}</option></select></label>
                    <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">دریافت‌کننده</span><select v-model="form.to_member_id" class="field-input" @change="selectDirection"><option value="" disabled>انتخاب طلبکار</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.display_name }}{{ member.is_active ? '' : ' · غیرفعال' }}</option></select></label>
                    <div class="rounded-xl bg-slate-50 p-3 text-sm"><span class="text-slate-500">مانده جهت انتخاب‌شده:</span> <strong>{{ formatToman(availableAmount) }}</strong></div>
                    <AmountInput v-model="form.amount_toman" label="مبلغ پرداخت" placeholder="125,000" group-thousands required />
                    <p v-if="cleanAmount && cleanAmount <= availableAmount" class="rounded-xl bg-emerald-50 p-3 text-sm leading-6 text-emerald-900">پس از ثبت، مانده این جهت {{ formatToman(remainingAfter) }} خواهد بود.</p>
                    <JalaliDatePicker v-model="paymentDate" label="تاریخ پرداخت (شمسی)" />
                    <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">یادداشت <span class="font-normal text-slate-400">(اختیاری)</span></span><textarea v-model.trim="form.note" rows="2" class="field-input" maxlength="5000"></textarea></label>
                    <button class="btn-primary w-full justify-center" :disabled="busy || availableAmount < 1">{{ busy ? 'در حال ثبت…' : 'ثبت تسویه واقعی' }}</button>
                </div>
            </form>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between"><div><h2 class="section-title">تاریخچه پرداخت‌ها</h2><p class="section-subtitle">ابطال به‌صورت رکورد معکوس ثبت می‌شود.</p></div><button class="btn-secondary !px-3 !py-2 text-xs" :disabled="loading" @click="load">تازه‌سازی</button></div>
                <div v-if="loading" class="mt-5 space-y-3"><div v-for="item in 4" :key="item" class="h-16 animate-pulse rounded-xl bg-slate-100"></div></div>
                <div v-else-if="settlements.length" class="mt-4 divide-y divide-slate-100">
                    <article v-for="settlement in settlements" :key="settlement.id" class="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0">
                        <div><p class="font-bold text-slate-800">{{ settlement.from_member?.display_name }} به {{ settlement.to_member?.display_name }}</p><p class="mt-1 text-xs text-slate-500">{{ formatJalaliDay(settlement.paid_at) }}<span v-if="settlement.note"> · {{ settlement.note }}</span></p><span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold" :class="settlement.entry_type === 'payment' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'">{{ settlement.entry_type === 'payment' ? 'پرداخت' : 'برگشت پرداخت' }}</span></div>
                        <div class="text-left"><p class="font-black text-slate-900">{{ formatToman(Math.abs(settlement.amount_toman)) }}</p><button v-if="settlement.entry_type === 'payment' && !settlements.some((entry) => entry.reverses_settlement_id === settlement.id)" class="mt-2 text-xs font-bold text-rose-700" @click="askReverse(settlement)">ابطال پرداخت</button></div>
                    </article>
                </div>
                <div v-else class="mt-5 rounded-2xl bg-slate-50 p-8 text-center"><p class="font-bold text-slate-800">پرداختی ثبت نشده است</p><p class="mt-1 text-sm text-slate-500">بعد از پرداخت واقعی وجه، تسویه را از فرم کنار صفحه ثبت کنید.</p></div>
            </section>
        </div>

        <section v-if="!loading && balances.length" class="mt-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="section-title">جهت‌های دارای مانده</h2><p class="section-subtitle">برای پر کردن فرم، یک بدهی باز را انتخاب کنید.</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2"><button v-for="balance in balances" :key="`${balance.debtor_member_id}-${balance.creditor_member_id}`" class="flex items-center justify-between rounded-2xl border border-slate-200 p-4 text-right hover:border-brand-300 hover:bg-brand-50/50" @click="form.from_member_id = balance.debtor_member_id; form.to_member_id = balance.creditor_member_id; form.amount_toman = String(balance.amount_toman)"><span><strong>{{ balance.debtor_name }}</strong> به <strong>{{ balance.creditor_name }}</strong></span><b>{{ formatToman(balance.amount_toman) }}</b></button></div>
        </section>

        <ConfirmDialog :open="Boolean(dialog)" title="ابطال پرداخت" :description="dialog ? `${dialog.from_member?.display_name} به ${dialog.to_member?.display_name} پرداخت ${formatToman(Math.abs(dialog.amount_toman))} را برگشت می‌زند. سابقه پرداخت اصلی حفظ می‌شود.` : ''" confirm-label="ثبت برگشت پرداخت" :danger="true" :busy="busy" @close="dialog = null" @confirm="confirmReverse" />
    </section>
</template>
