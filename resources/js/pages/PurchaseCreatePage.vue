<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AmountInput from '../components/ui/AmountInput.vue';
import JalaliDatePicker from '../components/ui/JalaliDatePicker.vue';
import { apiError } from '../api/http';
import { createPurchase } from '../api/purchases.api';
import { getMembers } from '../api/rooms.api';
import { useToastStore } from '../stores/toast';
import { jalaliDateToIso, localJalaliDate } from '../utils/jalali';
import { parseInteger, requestId, toMilliUnits } from '../utils/number';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const members = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const requestKey = ref(requestId());
const units = ['عدد', 'کیلوگرم', 'گرم', 'لیتر', 'بسته'];
const purchaseDate = ref(localJalaliDate());
const form = ref({
    kind: 'inventory',
    payer_member_id: '',
    title: '',
    amount_toman: '',
    unit_label: 'عدد',
    quantity: '',
    share_mode: 'equal',
    participants: [],
    shares: {},
    note: '',
});
const amount = computed(() => parseInteger(form.value.amount_toman));
const quantityMilliUnits = computed(() => toMilliUnits(form.value.quantity));
const selectedMembers = computed(() => [...form.value.participants].map(Number).sort((a, b) => a - b));
const equalShares = computed(() => {
    if (!amount.value || !selectedMembers.value.length) return [];
    const total = BigInt(amount.value);
    const count = BigInt(selectedMembers.value.length);
    const base = Number(total / count);
    const remainder = Number(total % count);
    return selectedMembers.value.map((id, index) => ({
        member: members.value.find((member) => member.id === id),
        amount: base + (index < remainder ? 1 : 0),
    }));
});
const customTotal = computed(() => selectedMembers.value.reduce((total, id) => total + (parseInteger(form.value.shares[id]) ?? 0), 0));
const isInventoryQuantityWhole = computed(() => !['عدد', 'بسته'].includes(form.value.unit_label) || quantityMilliUnits.value === null || quantityMilliUnits.value % 1000 === 0);
const theoreticalUnitPrice = computed(() => {
    if (!amount.value || !quantityMilliUnits.value) return null;
    const amountMilli = BigInt(amount.value) * 1000n;
    const quantity = BigInt(quantityMilliUnits.value);
    return Number((amountMilli + quantity / 2n) / quantity);
});

async function load() {
    loading.value = true;
    try {
        members.value = await getMembers(route.params.roomId, true);
        const owner = members.value.find((member) => member.role === 'owner');
        if (owner) form.value.payer_member_id = owner.id;
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        loading.value = false;
    }
}

function switchKind(kind) {
    form.value.kind = kind;
    error.value = '';
    if (kind === 'shared' && form.value.payer_member_id === '') {
        const owner = members.value.find((member) => member.role === 'owner');
        if (owner) form.value.payer_member_id = owner.id;
    }
}

function updateUnitLabel() {
    if (!form.value.unit_label) form.value.quantity = '';
}

function toggleParticipant(memberId) {
    const id = Number(memberId);
    const selected = new Set(form.value.participants.map(Number));
    if (selected.has(id)) selected.delete(id);
    else selected.add(id);
    form.value.participants = [...selected];
}

async function submit() {
    error.value = '';
    const cleanAmount = amount.value;
    if (cleanAmount === null || cleanAmount < 1) {
        error.value = 'مبلغ را به‌صورت عدد صحیح تومان وارد کنید.';
        return;
    }
    if (!form.value.payer_member_id) {
        error.value = 'خریدار را انتخاب کنید.';
        return;
    }
    if (form.value.kind === 'inventory' && (!quantityMilliUnits.value || !isInventoryQuantityWhole.value)) {
        error.value = 'مقدار موجودی را با دقت هزارم وارد کنید؛ واحد عدد و بسته باید کامل باشد.';
        return;
    }
    if (form.value.kind === 'shared' && !selectedMembers.value.length) {
        error.value = 'حداقل یک شریک واقعی خرج را انتخاب کنید.';
        return;
    }
    if (form.value.kind === 'shared' && form.value.quantity && (!form.value.unit_label || !quantityMilliUnits.value)) {
        error.value = 'برای مقدار توصیفی، واحد معتبر و مقدار مثبت وارد کنید.';
        return;
    }
    if (form.value.kind === 'shared' && ['عدد', 'بسته'].includes(form.value.unit_label) && quantityMilliUnits.value !== null && quantityMilliUnits.value % 1000 !== 0) {
        error.value = 'برای واحد عدد و بسته، مقدار توصیفی باید کامل باشد.';
        return;
    }
    if (form.value.kind === 'shared' && form.value.share_mode === 'custom' && customTotal.value !== cleanAmount) {
        error.value = 'جمع سهم‌ها باید دقیقاً برابر مبلغ کل باشد.';
        return;
    }

    const purchasedAt = jalaliDateToIso(purchaseDate.value);
    if (purchasedAt === null) {
        error.value = 'تاریخ خرید معتبر نیست.';
        return;
    }

    const payload = {
        request_id: requestKey.value,
        payer_member_id: Number(form.value.payer_member_id),
        title: form.value.title.trim(),
        kind: form.value.kind,
        amount_toman: cleanAmount,
        purchased_at: purchasedAt,
        ...(form.value.note.trim() ? { note: form.value.note.trim() } : {}),
    };

    if (form.value.kind === 'inventory') {
        payload.unit_label = form.value.unit_label;
        payload.total_quantity_milliunits = quantityMilliUnits.value;
    } else {
        payload.share_mode = form.value.share_mode;
        if (form.value.unit_label || form.value.quantity) payload.unit_label = form.value.unit_label || null;
        if (form.value.quantity) payload.total_quantity_milliunits = quantityMilliUnits.value;
        if (form.value.share_mode === 'equal') {
            payload.participants = selectedMembers.value;
        } else {
            payload.allocations = selectedMembers.value.map((id) => ({
                room_member_id: id,
                amount_toman: parseInteger(form.value.shares[id]),
            }));
        }
    }

    busy.value = true;
    try {
        const purchase = await createPurchase(route.params.roomId, payload);
        toast.show(form.value.kind === 'inventory' ? 'خرید ثبت شد؛ حالا مصرف واقعی را وارد کنید.' : 'خرج مشترک و سهم‌ها ثبت شد.');
        await router.push({ name: 'purchase-details', params: { roomId: route.params.roomId, purchaseId: purchase.id } });
    } catch (requestError) {
        error.value = apiError(requestError, 'ثبت خرید انجام نشد.');
        if ([409, 422].includes(requestError.response?.status)) requestKey.value = requestId();
    } finally {
        busy.value = false;
    }
}

onMounted(load);
watch(() => route.params.roomId, () => {
    requestKey.value = requestId();
    load();
});
</script>

<template>
    <section class="mx-auto max-w-4xl">
        <RouterLink :to="{ name: 'purchases', params: { roomId: route.params.roomId } }" class="text-sm font-bold text-brand-700">← بازگشت به خریدها</RouterLink>
        <header class="mt-4"><p class="text-sm font-bold text-brand-700">ثبت رویداد واقعی</p><h1 class="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">خرید جدید</h1><p class="mt-2 text-sm text-slate-500">مبلغ و تخصیص نهایی در سرور ثبت می‌شود؛ پیش‌نمایش‌ها فقط برای بررسی فرم هستند.</p></header>

        <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            <div class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-100 p-1.5">
                <button class="rounded-xl px-3 py-3 text-sm font-bold transition" :class="form.kind === 'inventory' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500'" @click="switchKind('inventory')">خرید موجودی‌دار</button>
                <button class="rounded-xl px-3 py-3 text-sm font-bold transition" :class="form.kind === 'shared' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500'" @click="switchKind('shared')">خرج مشترک</button>
            </div>

            <div v-if="loading" class="mt-6 space-y-3"><div class="h-12 animate-pulse rounded-xl bg-slate-100"></div><div class="h-12 animate-pulse rounded-xl bg-slate-100"></div></div>
            <form v-else class="mt-6 space-y-6" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">پرداخت‌کننده <span class="text-rose-600">*</span></span><select v-model="form.payer_member_id" class="field-input" required><option value="" disabled>انتخاب عضو</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.display_name }}{{ member.role === 'owner' ? ' · مدیر' : '' }}</option></select></label>
                    <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">عنوان {{ form.kind === 'inventory' ? 'کالا' : 'خرج' }} <span class="text-rose-600">*</span></span><input v-model.trim="form.title" class="field-input" maxlength="200" placeholder="مثلاً تخم‌مرغ یا ناهار" required></label>
                    <AmountInput v-model="form.amount_toman" label="مبلغ کل" placeholder="235,000" group-thousands required hint="مبلغ را به تومان وارد کنید." />
                    <JalaliDatePicker v-model="purchaseDate" label="تاریخ خرید (شمسی)" />
                </div>

                <template v-if="form.kind === 'inventory'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">واحد</span><select v-model="form.unit_label" class="field-input"><option v-for="unit in units" :key="unit">{{ unit }}</option></select></label>
                        <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">مقدار کل <span class="text-rose-600">*</span></span><div class="flex gap-2"><input v-model="form.quantity" inputmode="decimal" dir="ltr" class="field-input text-left" placeholder="مثلاً ۳۰ یا ۰٫۵" required><span class="grid min-w-20 place-items-center rounded-xl bg-slate-100 px-3 text-sm font-bold text-slate-600">{{ form.unit_label }}</span></div><span class="block text-xs text-slate-500">برای واحدهای وزن و حجم تا سه رقم اعشار پذیرفته می‌شود.</span></label>
                    </div>
                    <div v-if="theoreticalUnitPrice" class="rounded-2xl bg-sky-50 p-4 text-sm leading-6 text-sky-900">قیمت نظری هر {{ form.unit_label }} حدوداً {{ theoreticalUnitPrice.toLocaleString('fa-IR') }} تومان است. مبلغ مصرف واقعی با تخصیص تجمعی و دقیق محاسبه می‌شود.</div>
                </template>

                <template v-else>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">روش تقسیم</span><select v-model="form.share_mode" class="field-input"><option value="equal">تقسیم مساوی</option><option value="custom">سهم دستی</option></select></label>
                        <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">واحد توصیفی <span class="font-normal text-slate-400">(اختیاری)</span></span><select v-model="form.unit_label" class="field-input" @change="updateUnitLabel"><option value="">بدون واحد</option><option v-for="unit in units" :key="unit">{{ unit }}</option></select></label>
                    </div>
                    <label v-if="form.unit_label" class="block max-w-sm space-y-2"><span class="text-sm font-semibold text-slate-700">مقدار توصیفی</span><input v-model="form.quantity" inputmode="decimal" dir="ltr" class="field-input text-left" placeholder="مثلاً ۱ یا ۰٫۵"></label>
                    <div class="rounded-2xl border border-slate-200 p-4 sm:p-5">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-extrabold text-slate-900">چه کسانی در این خرج شریک هستند؟</h2><p class="mt-1 text-xs leading-5 text-slate-500">هیچ عضوی پیش‌فرض انتخاب نشده. اگر خریدار هم مصرف کرده، نام او را هم انتخاب کنید.</p></div><span class="text-xs font-bold text-brand-700">{{ selectedMembers.length }} نفر</span></div>
                        <div v-if="members.length" class="mt-4 grid gap-2 sm:grid-cols-2">
                            <label v-for="member in members" :key="member.id" class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-brand-200" :class="selectedMembers.includes(member.id) ? 'bg-brand-50/70' : 'bg-white'">
                                <input :checked="selectedMembers.includes(member.id)" type="checkbox" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @change="toggleParticipant(member.id)">
                                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800">{{ member.display_name }}<span v-if="Number(form.payer_member_id) === member.id" class="mr-1 text-xs text-brand-700">(خریدار)</span></span>
                                <input v-if="form.share_mode === 'custom' && selectedMembers.includes(member.id)" v-model="form.shares[member.id]" type="text" inputmode="numeric" dir="ltr" class="w-28 rounded-lg border border-slate-200 px-2 py-2 text-left text-sm" aria-label="مبلغ سهم به تومان" placeholder="سهم تومان" @click.stop>
                            </label>
                        </div>
                        <p v-else class="mt-4 text-sm text-slate-500">ابتدا از بخش اعضا، افراد اتاق را اضافه کنید.</p>
                    </div>
                    <div v-if="form.share_mode === 'equal' && equalShares.length" class="rounded-2xl bg-brand-50 p-4">
                        <p class="text-sm font-extrabold text-brand-900">پیش‌نمایش سهم‌ها</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2"><div v-for="share in equalShares" :key="share.member?.id" class="flex justify-between rounded-xl bg-white/80 px-3 py-2 text-sm"><span>{{ share.member?.display_name }}</span><strong>{{ share.amount.toLocaleString('fa-IR') }} تومان</strong></div></div>
                    </div>
                    <div v-if="form.share_mode === 'custom' && selectedMembers.length" class="rounded-2xl p-4 text-sm" :class="customTotal === amount ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900'">
                        جمع سهم‌های واردشده: <strong>{{ customTotal.toLocaleString('fa-IR') }} تومان</strong> از {{ (amount ?? 0).toLocaleString('fa-IR') }} تومان
                        <span class="mt-1 block">{{ customTotal === amount ? 'جمع سهم‌ها برابر مبلغ کل است.' : 'جمع سهم‌ها باید دقیقاً برابر مبلغ کل باشد.' }}</span>
                    </div>
                </template>

                <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">توضیحات <span class="font-normal text-slate-400">(اختیاری)</span></span><textarea v-model.trim="form.note" rows="3" class="field-input" maxlength="5000" placeholder="توضیح کوتاه درباره خرید"></textarea></label>
                <p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm leading-6 text-rose-800" role="alert">{{ error }}</p>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end"><RouterLink class="btn-secondary justify-center" :to="{ name: 'purchases', params: { roomId: route.params.roomId } }">انصراف</RouterLink><button class="btn-primary justify-center sm:min-w-52" :disabled="busy">{{ busy ? 'در حال ثبت…' : 'ثبت خرید' }}</button></div>
            </form>
        </div>
    </section>
</template>
