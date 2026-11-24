<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiError } from '../api/http';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';

const props = defineProps({ registerMode: { type: Boolean, default: false } });
const auth = useAuthStore();
const toast = useToastStore();
const route = useRoute();
const router = useRouter();
const isRegister = ref(props.registerMode);
const busy = ref(false);
const error = ref('');
const form = ref({ name: '', email: '', password: '', password_confirmation: '', remember: true });

async function submit() {
    busy.value = true;
    error.value = '';
    try {
        if (isRegister.value) {
            await auth.signUp({
                name: form.value.name,
                email: form.value.email,
                password: form.value.password,
                password_confirmation: form.value.password_confirmation,
            });
        } else {
            await auth.signIn({ email: form.value.email, password: form.value.password, remember: form.value.remember });
        }
        toast.show(isRegister.value ? 'حساب شما ساخته شد.' : 'خوش آمدید.');
        await router.replace(typeof route.query.redirect === 'string' ? route.query.redirect : { name: 'rooms' });
    } catch (requestError) {
        error.value = apiError(requestError, 'ورود انجام نشد. اطلاعات را بررسی کنید.');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <main class="grid min-h-screen place-items-center bg-[radial-gradient(circle_at_top_left,#dcefe9,transparent_42%),#f4f7f6] px-4 py-10">
        <div class="grid w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/80 bg-white shadow-xl shadow-slate-900/5 lg:grid-cols-[.95fr_1.05fr]">
            <section class="order-2 p-6 sm:p-10 lg:order-1 lg:p-14">
                <RouterLink :to="{ name: 'rooms' }" class="mb-8 inline-flex items-center gap-2 text-sm font-bold text-brand-700"><span class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white">د</span> دخل و خرج</RouterLink>
                <h1 class="text-2xl font-black text-slate-900 sm:text-3xl">{{ isRegister ? 'حساب مدیر را بسازید' : 'به حساب‌وکتاب اتاق وارد شوید' }}</h1>
                <p class="mt-3 leading-7 text-slate-500">خریدها، مصرف واقعی و تسویه‌ها را با هم و با حساب دقیق تومان ثبت کنید.</p>

                <form class="mt-8 space-y-4" @submit.prevent="submit">
                    <label v-if="isRegister" class="block space-y-2">
                        <span class="text-sm font-semibold text-slate-700">نام مدیر</span>
                        <input v-model.trim="form.name" class="field-input" autocomplete="name" required>
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm font-semibold text-slate-700">ایمیل</span>
                        <input v-model.trim="form.email" type="email" dir="ltr" class="field-input text-left" autocomplete="email" required>
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm font-semibold text-slate-700">گذرواژه</span>
                        <input v-model="form.password" type="password" class="field-input" :autocomplete="isRegister ? 'new-password' : 'current-password'" required minlength="8">
                    </label>
                    <label v-if="isRegister" class="block space-y-2">
                        <span class="text-sm font-semibold text-slate-700">تکرار گذرواژه</span>
                        <input v-model="form.password_confirmation" type="password" class="field-input" autocomplete="new-password" required minlength="8">
                    </label>
                    <label v-if="!isRegister" class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="form.remember" type="checkbox" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        مرا به خاطر بسپار
                    </label>
                    <p v-if="error" class="rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium leading-6 text-rose-800" role="alert">{{ error }}</p>
                    <button class="btn-primary w-full justify-center !py-3.5" :disabled="busy">
                        {{ busy ? 'در حال بررسی…' : isRegister ? 'ساخت حساب' : 'ورود امن' }}
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    {{ isRegister ? 'حساب دارید؟' : 'حساب ندارید؟' }}
                    <RouterLink :to="{ name: isRegister ? 'login' : 'register' }" class="font-bold text-brand-700 hover:text-brand-800">
                        {{ isRegister ? 'وارد شوید' : 'ثبت‌نام مدیر' }}
                    </RouterLink>
                </p>
            </section>
            <aside class="relative order-1 flex min-h-52 flex-col justify-between overflow-hidden bg-brand-700 p-7 text-white sm:p-10 lg:order-2 lg:min-h-full lg:p-12">
                <div class="absolute -left-20 -top-24 size-72 rounded-full border-[44px] border-white/5"></div>
                <div class="absolute -bottom-24 -right-10 size-64 rounded-full bg-white/5"></div>
                <div class="relative">
                    <p class="text-sm font-bold text-emerald-100">دفتر مالی مشترک</p>
                    <h2 class="mt-4 max-w-sm text-3xl font-black leading-[1.6] sm:text-4xl">هر خرج، روشن و قابل پیگیری</h2>
                    <p class="mt-4 max-w-sm leading-8 text-emerald-50/85">موجودی مصرف‌نشده بدهی کسی نیست. سهم واقعی هر نفر ثبت می‌شود و بدهی‌های دوطرفه خودکار تهاتر می‌شوند.</p>
                </div>
                <div class="relative mt-7 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-3"><span class="block text-lg font-black">۱</span><span class="mt-1 block text-xs text-emerald-100">خرید</span></div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-3"><span class="block text-lg font-black">۲</span><span class="mt-1 block text-xs text-emerald-100">مصرف</span></div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-3"><span class="block text-lg font-black">۳</span><span class="mt-1 block text-xs text-emerald-100">تسویه</span></div>
                </div>
            </aside>
        </div>
    </main>
</template>
