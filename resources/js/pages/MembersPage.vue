<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { apiError } from '../api/http';
import { createMember, getMembers, updateMember } from '../api/rooms.api';
import { useToastStore } from '../stores/toast';

const route = useRoute();
const toast = useToastStore();
const members = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const search = ref('');
const newName = ref('');
const editingId = ref(null);
const editingName = ref('');
const filteredMembers = computed(() => members.value.filter((member) => member.display_name.includes(search.value)));

async function load() {
    loading.value = true;
    try {
        members.value = await getMembers(route.params.roomId, null);
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        loading.value = false;
    }
}

async function addMember() {
    if (!newName.value.trim()) return;
    busy.value = true;
    error.value = '';
    try {
        await createMember(route.params.roomId, { display_name: newName.value.trim() });
        newName.value = '';
        toast.show('عضو اضافه شد.');
        await load();
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        busy.value = false;
    }
}

function startEdit(member) {
    editingId.value = member.id;
    editingName.value = member.display_name;
}

async function saveName(member) {
    busy.value = true;
    try {
        await updateMember(route.params.roomId, member.id, { display_name: editingName.value.trim() });
        editingId.value = null;
        toast.show('نام عضو به‌روزرسانی شد.');
        await load();
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        busy.value = false;
    }
}

async function toggleActive(member) {
    busy.value = true;
    try {
        await updateMember(route.params.roomId, member.id, { is_active: !member.is_active });
        toast.show(member.is_active ? 'عضو غیرفعال شد؛ سابقه مالی باقی می‌ماند.' : 'عضو دوباره فعال شد.');
        await load();
    } catch (requestError) {
        error.value = apiError(requestError);
    } finally {
        busy.value = false;
    }
}

onMounted(load);
watch(() => route.params.roomId, load);
</script>

<template>
    <section>
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-bold text-brand-700">دفتر اتاق</p><h1 class="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">اعضای اتاق</h1><p class="mt-2 text-sm text-slate-500">اعضا لازم نیست حساب کاربری داشته باشند؛ سابقه افراد غیرفعال هم حفظ می‌شود.</p></div>
            <span class="rounded-full bg-white px-4 py-2 text-sm font-bold text-slate-600 shadow-sm">{{ members.length }} عضو</span>
        </header>

        <div class="mt-6 grid gap-5 lg:grid-cols-[.75fr_1.25fr]">
            <section class="h-fit rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="font-black text-slate-900">افزودن عضو</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">از نام قابل تشخیص استفاده کنید. برای هم‌نام‌ها سیستم شناسه داخلی جداگانه نگه می‌دارد.</p>
                <form class="mt-5 space-y-4" @submit.prevent="addMember">
                    <label class="block space-y-2"><span class="text-sm font-semibold text-slate-700">نام عضو</span><input v-model.trim="newName" class="field-input" maxlength="150" placeholder="نام و نام خانوادگی" required></label>
                    <p v-if="error" class="rounded-xl bg-rose-50 p-3 text-sm leading-6 text-rose-800">{{ error }}</p>
                    <button class="btn-primary w-full justify-center" :disabled="busy">{{ busy ? 'در حال ثبت…' : 'افزودن عضو' }}</button>
                </form>
                <div class="mt-5 rounded-2xl bg-brand-50 p-4 text-sm leading-6 text-brand-800">هر فرد را فقط وقتی در خرج مشترک شریک کنید که واقعاً از هزینه استفاده کرده باشد.</div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="font-black text-slate-900">فهرست اعضا</h2>
                    <input v-model="search" class="field-input sm:max-w-xs" placeholder="جست‌وجو در اعضا">
                </div>
                <p v-if="error && !loading" class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-800">{{ error }}</p>
                <div v-if="loading" class="mt-5 space-y-3"><div v-for="item in 5" :key="item" class="h-16 animate-pulse rounded-xl bg-slate-100"></div></div>
                <div v-else-if="filteredMembers.length" class="mt-5 divide-y divide-slate-100">
                    <article v-for="member in filteredMembers" :key="member.id" class="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-2xl font-black" :class="member.is_active ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500'">{{ member.display_name.slice(0, 1) }}</span>
                            <div v-if="editingId !== member.id" class="min-w-0"><p class="truncate font-bold text-slate-800">{{ member.display_name }} <span v-if="member.role === 'owner'" class="mr-1 text-xs font-medium text-brand-700">مدیر</span></p><p class="mt-1 text-xs" :class="member.is_active ? 'text-emerald-700' : 'text-slate-400'">{{ member.is_active ? 'فعال' : 'غیرفعال · سوابق حفظ شده' }}</p></div>
                            <div v-else class="flex gap-2"><input v-model.trim="editingName" class="field-input !py-2" maxlength="150"><button class="btn-primary !px-3 !py-2" :disabled="busy" @click="saveName(member)">ذخیره</button><button class="btn-secondary !px-3 !py-2" @click="editingId = null">انصراف</button></div>
                        </div>
                        <div v-if="editingId !== member.id" class="flex gap-2"><button class="btn-secondary !px-3 !py-2 text-xs" @click="startEdit(member)">تغییر نام</button><button class="rounded-xl px-3 py-2 text-xs font-bold" :class="member.is_active ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-brand-50 text-brand-700 hover:bg-brand-100'" :disabled="busy" @click="toggleActive(member)">{{ member.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}</button></div>
                    </article>
                </div>
                <p v-else class="mt-6 rounded-2xl bg-slate-50 p-7 text-center text-sm text-slate-500">{{ members.length ? 'عضوی با این نام پیدا نشد.' : 'هنوز عضوی اضافه نشده است.' }}</p>
            </section>
        </div>
    </section>
</template>
