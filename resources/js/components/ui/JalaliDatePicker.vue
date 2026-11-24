<script setup>
import { computed, nextTick, ref, useId } from 'vue';
import { formatCalendarDate, jalaliMonthLength, jalaliMonths, jalaliMonthStartDay, localJalaliDate } from '../../utils/jalali';
import { formatNumber } from '../../utils/number';

const props = defineProps({
    modelValue: { type: Object, required: true },
    label: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);
const pickerId = useId();
const calendar = ref(null);
const open = ref(false);
const today = ref(localJalaliDate());
const month = ref({ year: props.modelValue.year, month: props.modelValue.month });
const weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
const calendarTitle = computed(() => `${jalaliMonths[month.value.month - 1]} ${month.value.year.toLocaleString('fa-IR', { useGrouping: false })}`);
const leadingDays = computed(() => jalaliMonthStartDay(month.value.year, month.value.month));
const daysInMonth = computed(() => jalaliMonthLength(month.value.year, month.value.month));

async function openCalendar() {
    today.value = localJalaliDate();
    month.value = { year: props.modelValue.year, month: props.modelValue.month };
    await nextTick();
    calendar.value.showModal();
    open.value = true;
}

function closeCalendar() {
    calendar.value.close();
}

function changeMonth(offset) {
    const monthIndex = (month.value.year - 1) * 12 + month.value.month - 1 + offset;
    if (monthIndex < 0 || monthIndex >= 9999 * 12) return;

    month.value = { year: Math.floor(monthIndex / 12) + 1, month: (monthIndex % 12) + 1 };
}

function matchesDate(date, day) {
    return date.year === month.value.year && date.month === month.value.month && date.day === day;
}

function selectDate(date) {
    emit('update:modelValue', date);
    closeCalendar();
}
</script>

<template>
    <div class="min-w-0 space-y-2">
        <span :id="`${pickerId}-label`" class="block text-sm font-semibold text-slate-700">{{ label }} <span class="text-rose-600" aria-hidden="true">*</span></span>
        <button
            type="button"
            class="field-input flex items-center justify-between gap-3 text-start"
            aria-haspopup="dialog"
            :aria-expanded="open"
            :aria-controls="pickerId"
            :aria-labelledby="`${pickerId}-label ${pickerId}-value`"
            @click="openCalendar"
        >
            <span :id="`${pickerId}-value`">{{ formatCalendarDate(modelValue) }}</span>
            <span class="text-lg leading-none text-brand-700" aria-hidden="true">▦</span>
        </button>

        <dialog
            :id="pickerId"
            ref="calendar"
            dir="rtl"
            class="m-auto max-h-[calc(100dvh_-_2rem)] w-[calc(100%_-_2rem)] max-w-sm overflow-y-auto rounded-3xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/45"
            :aria-labelledby="`${pickerId}-heading`"
            @click.self="closeCalendar"
            @close="open = false"
        >
            <div class="p-4 sm:p-5">
                <header class="flex items-center justify-between gap-3">
                    <h2 :id="`${pickerId}-heading`" class="font-bold text-slate-900">{{ label }}</h2>
                    <button type="button" class="grid size-9 place-items-center rounded-xl text-xl text-slate-600 hover:bg-slate-100" aria-label="بستن تقویم" @click="closeCalendar">×</button>
                </header>

                <div class="mt-4 grid grid-cols-[2.5rem_1fr_2.5rem] items-center gap-2">
                    <button type="button" dir="ltr" class="grid size-10 place-items-center rounded-xl border border-slate-200 text-xl text-brand-700 hover:bg-brand-50" aria-label="ماه قبل" @click="changeMonth(-1)">›</button>
                    <p class="text-center text-sm font-extrabold text-slate-900" aria-live="polite">{{ calendarTitle }}</p>
                    <button type="button" dir="ltr" class="grid size-10 place-items-center rounded-xl border border-slate-200 text-xl text-brand-700 hover:bg-brand-50" aria-label="ماه بعد" @click="changeMonth(1)">‹</button>
                </div>

                <div class="mt-4 grid grid-cols-7 gap-1 text-center">
                    <abbr v-for="weekday in weekdays" :key="weekday" :title="weekday" class="py-2 text-xs font-semibold text-slate-500 no-underline" aria-hidden="true">{{ weekday[0] }}</abbr>
                    <span v-for="blank in leadingDays" :key="`blank-${blank}`" aria-hidden="true"></span>
                    <button
                        v-for="day in daysInMonth"
                        :key="day"
                        type="button"
                        class="min-h-9 min-w-0 rounded-xl px-1 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand-600 sm:min-h-10"
                        :class="matchesDate(modelValue, day) ? 'bg-brand-600 text-white' : 'text-slate-700 hover:bg-brand-50'"
                        :autofocus="matchesDate(modelValue, day)"
                        :aria-label="formatCalendarDate({ ...month, day })"
                        :aria-pressed="matchesDate(modelValue, day)"
                        :aria-current="matchesDate(today, day) ? 'date' : undefined"
                        @click="selectDate({ ...month, day })"
                    >
                        {{ formatNumber(day) }}
                    </button>
                </div>

                <div class="mt-4 flex justify-end border-t border-slate-100 pt-3">
                    <button type="button" class="rounded-lg px-3 py-2 text-sm font-bold text-brand-700 hover:bg-brand-50" @click="selectDate(today)">امروز</button>
                </div>
            </div>
        </dialog>
    </div>
</template>
