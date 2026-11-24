<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiError } from '../api/http';
import { createRoom, getRooms } from '../api/rooms.api';
import { useToastStore } from '../stores/toast';

const router = useRouter();
const toast = useToastStore();
const rooms = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const form = ref({ name: '', description: '' });

async function load() {
    loading.value = true;
    try {
        const page = await getRooms();
        rooms.value = Array.isArray(page) ? page : page.data ?? [];
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        loading.value = false;
    }
}

async function submit() {
    busy.value = true;
    error.value = '';
    try {
        const room = await createRoom(form.value);
        toast.show('اتاق ساخته شد؛ حالا اعضا را اضافه کنید.');
        await router.push({ name: 'dashboard', params: { roomId: room.id } });
    } catch (requestError) {
        error.value = apiError(requestError, 'ساخت اتاق انجام نشد.');
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <main class="min-h-screen bg-[radial-gradient(circle_at_top_right,#dcefe9,transparent_40%),#f4f7f6] px-4 py-10 sm:px-6">
        <div class="mx-auto max-w-6xl">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-brand-700">فضای مدیریت</p>
                    <h1 class="mt-2 text-3xl font-black text-slate-900">اتاق‌های من</h1>
                    <p class="mt-2 text-slate-500">یک اتاق را انتخاب کنید یا دفتر تازه‌ای بسازید.</p>
                </div>
                <span class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm">واحد پول: تومان</span>
            </header>

            <div class="mt-8 grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-black text-slate-900">اتاق‌های موجود</h2>
                        <button class="text-sm font-bold text-brand-700" @click="load">تازه‌سازی</button>
                    </div>
                    <div v-if="loading" class="mt-5 space-y-3"><div v-for="item in 3" :key="item" class="h-20 animate-pulse rounded-2xl bg-slate-100"></div></div>
                    <p v-else-if="error" class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ error }}</p>
                    <div v-else-if="rooms.length" class="mt-5 grid gap-3 sm:grid-cols-2">
                        <button v-for="room in rooms" :key="room.id" class="rounded-2xl border border-slate-200 p-5 text-right transition hover:border-brand-300 hover:bg-brand-50/50" @click="router.push({ name: 'dashboard', params: { roomId: room.id } })">
                            <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-lg font-black text-brand-700">⌂</span>
                            <span class="mt-4 block font-extrabold text-slate-900">{{ room.name }}</span>
                            <span class="mt-1 block text-sm text-slate-500">{{ room.members_count ?? 0 }} عضو · {{ room.purchases_count ?? 0 }} خرید</span>
                        </button>
                    </div>
                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                        <span class="text-3xl">⌂</span><p class="mt-3 font-bold text-slate-800">هنوز اتاقی نساخته‌اید</p><p class="mt-1 text-sm text-slate-500">فرم کنار صفحه را برای ساخت اولین اتاق پر کنید.</p>
                    </div>
                </section>

                <section class="rounded-3xl bg-slate-900 p-6 text-white shadow-lg sm:p-8">
                    <span class="grid size-11 place-items-center rounded-2xl bg-white/10 text-xl">＋</span>
                    <h2 class="mt-5 text-xl font-black">ساخت اتاق جدید</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-300">بعد از ساخت، از بخش اعضا افراد اتاق را اضافه کنید؛ اعضا برای کار با سیستم به حساب کاربری نیاز ندارند.</p>
                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <label class="block space-y-2"><span class="text-sm font-semibold text-slate-200">نام اتاق</span><input v-model.trim="form.name" class="field-input !border-white/15 !bg-white/10 !text-white placeholder:!text-slate-400" placeholder="مثلاً اتاق ما" maxlength="150" required></label>
                        <label class="block space-y-2"><span class="text-sm font-semibold text-slate-200">توضیح <span class="font-normal text-slate-400">(اختیاری)</span></span><textarea v-model.trim="form.description" rows="3" class="field-input !border-white/15 !bg-white/10 !text-white placeholder:!text-slate-400" placeholder="یادداشتی درباره این اتاق"></textarea></label>
                        <p v-if="error && !loading" class="rounded-xl bg-rose-900/60 p-3 text-sm text-rose-100">{{ error }}</p>
                        <button class="btn-primary w-full justify-center !bg-emerald-500 hover:!bg-emerald-400" :disabled="busy">{{ busy ? 'در حال ساخت…' : 'ساخت اتاق' }}</button>
                    </form>
                </section>
            </div>
        </div>
    </main>
</template>
