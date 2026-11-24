<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { apiError } from '../api/http';
import { createConsumption } from '../api/consumptions.api';
import { getPurchase } from '../api/purchases.api';
import { getMembers } from '../api/rooms.api';
import { useToastStore } from '../stores/toast';
import { formatJalaliDate, formatQuantity, formatToman, localDateTimeValue, requestId, toMilliUnits } from '../utils/number';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const purchase = ref(null);
const members = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const result = ref(null);
const requestKey = ref(requestId());
const form = ref({ consumer_member_id: '', quantity: '', consumed_at: localDateTimeValue(), note: '' });
const quantityMilliUnits = computed(() => toMilliUnits(form.value.quantity));
const remaining = computed(() => purchase.value?.remaining_quantity_milliunits ?? 0);
const approximateAmount = computed(() => {
    if (!purchase.value || !quantityMilliUnits.value || !purchase.value.total_quantity_milliunits) return null;
    const amount = BigInt(purchase.value.amount_toman);
    const quantity = BigInt(quantityMilliUnits.value);
    const total = BigInt(purchase.value.total_quantity_milliunits);
    return Number((amount * quantity) / total);
});

async function load() {
    loading.value = true;
    try {
        [purchase.value, members.value] = await Promise.all([
            getPurchase(route.params.roomId, route.params.purchaseId),
            getMembers(route.params.roomId, true),
        ]);
        if (!purchase.value || purchase.value.kind !== 'inventory' || purchase.value.status !== 'posted') {
            await router.replace({ name: 'purchases', params: { roomId: route.params.roomId } });
            return;
        }
        form.value.consumer_member_id = members.value.find((member) => member.role === 'owner')?.id ?? members.value[0]?.id ?? '';
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        loading.value = false;
    }
}

async function submit() {
    error.value = '';
    if (!quantityMilliUnits.value || quantityMilliUnits.value > remaining.value) {
        error.value = 'مقدار مصرف باید بیشتر از صفر و برابر یا کمتر از موجودی باقی‌مانده باشد.';
        return;
    }
    if (['عدد', 'بسته'].includes(purchase.value.unit_label) && quantityMilliUnits.value % 1000 !== 0) {
        error.value = 'برای واحد عدد و بسته، مقدار مصرف باید کامل باشد.';
        return;
    }

    busy.value = true;
    try {
        result.value = await createConsumption(route.params.roomId, purchase.value.id, {
            request_id: requestKey.value,
            consumer_member_id: Number(form.value.consumer_member_id),
            quantity_milliunits: quantityMilliUnits.value,
            consumed_at: new Date(form.value.consumed_at).toISOString(),
            ...(form.value.note.trim() ? { note: form.value.note.trim() } : {}),
        });
        toast.show('مصرف با مبلغ قطعی سرور ثبت شد.');
        purchase.value.remaining_quantity_milliunits = result.value.remaining_quantity_milliunits;
    } catch (requestError) {
        error.value = apiError(requestError, 'ثبت مصرف انجام نشد.');
        if ([409, 422].includes(requestError.response?.status)) requestKey.value = requestId();
    } finally {
        busy.value = false;
    }
}

function resetForm() {
    result.value = null;
    error.value = '';
    form.value.quantity = '';
    form.value.note = '';
    form.value.consumed_at = localDateTimeValue();
    requestKey.value = requestId();
    load();
}

onMounted(load);
watch(() => [route.params.roomId, route.params.purchaseId], () => {
    result.value = null;
    requestKey.value = requestId();
    load();
});
</script>

<template>
    <section class="mx-auto max-w-3xl">
        <RouterLink v-if="purchase" :to="{ name: 'purchase-details', params: { roomId: route.params.roomId, purchaseId: purchase.id } }" class="text-sm font-bold text-brand-700">← بازگشت به جزئیات خرید</RouterLink>
        <div v-if="loading" class="mt-5 h-80 animate-pulse rounded-3xl bg-white"></div>
        <p v-else-if="error && !purchase" class="mt-5 rounded-2xl bg-rose-50 p-5 text-rose-800">{{ error }}</p>
        <template v-else-if="purchase">
            <header class="mt-4"><p class="text-sm font-bold text-brand-700">ثبت از موجودی واقعی</p><h1 class="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">ثبت مصرف {{ purchase.title }}</h1><p class="mt-2 text-sm text-slate-500">مبلغ تخصیص قطعی پس از ثبت از سرور دریافت می‌شود.</p></header>
            <div class="mt-6 grid gap-3 sm:grid-cols-3"><article class="stat-card"><p class="stat-label">خریدار</p><p class="stat-value">{{ purchase.payer?.display_name }}</p></article><article class="stat-card"><p class="stat-label">موجودی فعلی</p><p class="stat-value">{{ formatQuantity(purchase.remaining_quantity_milliunits, purchase.unit_label) }}</p></article><article class="stat-card"><p class="stat-label">مبلغ خرید</p><p class="stat-value">{{ formatToman(purchase.amount_toman) }}</p></article></div>

            <form v-if="!result" class="mt-5 space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" @submit.prevent="submit">
                <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">مصرف‌کننده</span><select v-model="form.consumer_member_id" class="field-input" required><option v-for="member in members" :key="member.id" :value="member.id">{{ member.display_name }}</option></select></label>
                <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">مقدار مصرف</span><div class="flex gap-2"><input v-model="form.quantity" inputmode="decimal" dir="ltr" class="field-input text-left" placeholder="مثلاً ۲ یا ۰٫۵" required><span class="grid min-w-24 place-items-center rounded-xl bg-slate-100 px-3 text-sm font-bold text-slate-600">{{ purchase.unit_label }}</span></div><span class="block text-xs text-slate-500">حداکثر موجودی: {{ formatQuantity(remaining, purchase.unit_label) }}</span></label>
                <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">تاریخ و زمان مصرف</span><input v-model="form.consumed_at" type="datetime-local" class="field-input" required></label>
                <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">توضیح <span class="font-normal text-slate-400">(اختیاری)</span></span><textarea v-model.trim="form.note" rows="2" class="field-input" maxlength="5000"></textarea></label>
                <div v-if="approximateAmount !== null" class="rounded-2xl bg-sky-50 p-4 text-sm leading-6 text-sky-900">پیش‌نمایش تقریبی سهم مصرف: حدود {{ formatToman(approximateAmount) }}. تخصیص نهایی ممکن است به‌خاطر گرد کردن تجمعی یک تومان تفاوت داشته باشد.</div>
                <p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm leading-6 text-rose-800" role="alert">{{ error }}</p>
                <button class="btn-primary w-full justify-center sm:w-auto" :disabled="busy || remaining < 1">{{ busy ? 'در حال ثبت…' : 'ثبت مصرف' }}</button>
            </form>

            <section v-else class="mt-5 rounded-3xl border border-emerald-200 bg-emerald-50 p-6 sm:p-8">
                <span class="grid size-12 place-items-center rounded-2xl bg-white text-2xl text-emerald-700">✓</span>
                <h2 class="mt-4 text-xl font-black text-emerald-950">مصرف ثبت شد</h2>
                <p class="mt-2 text-sm text-emerald-900">{{ members.find((member) => member.id === result.consumer_member_id)?.display_name }} به اندازه {{ formatQuantity(result.quantity_milliunits, purchase.unit_label) }} مصرف کرد.</p>
                <p class="mt-4 text-2xl font-black text-emerald-950">{{ formatToman(result.allocated_amount_toman) }}</p>
                <p class="mt-1 text-xs text-emerald-800">مبلغ قطعی تخصیص از پاسخ سرور</p>
                <p class="mt-4 text-sm text-emerald-900">موجودی تازه: {{ formatQuantity(result.remaining_quantity_milliunits, purchase.unit_label) }} · {{ formatJalaliDate(result.consumed_at, true) }}</p>
                <div class="mt-6 flex flex-col gap-2 sm:flex-row"><button class="btn-primary justify-center" :disabled="result.remaining_quantity_milliunits < 1" @click="resetForm">ثبت مصرف دیگری</button><RouterLink class="btn-secondary justify-center" :to="{ name: 'purchase-details', params: { roomId: route.params.roomId, purchaseId: purchase.id } }">نمایش جزئیات خرید</RouterLink></div>
            </section>
        </template>
    </section>
</template>
