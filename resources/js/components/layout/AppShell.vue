<script setup>
import { computed, onMounted, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { apiError } from '../../api/http';
import { useAuthStore } from '../../stores/auth';
import { useRoomStore } from '../../stores/room';
import { useToastStore } from '../../stores/toast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const roomStore = useRoomStore();
const toast = useToastStore();
const roomId = computed(() => route.params.roomId);
const navigation = computed(() => [
    { name: 'dashboard', label: 'خانه', icon: '⌂' },
    { name: 'purchases', label: 'خریدها', icon: '＋' },
    { name: 'balances', label: 'حساب‌وکتاب', icon: '⇄' },
    { name: 'members', label: 'اعضا', icon: '◉' },
    { name: 'settlements', label: 'تسویه‌ها', icon: '✓' },
]);

async function signOut() {
    try {
        await auth.signOut();
        roomStore.clear();
        await router.replace({ name: 'login' });
    } catch (error) {
        toast.show(apiError(error), 'error');
    }
}

onMounted(() => roomStore.loadRoom(roomId.value).catch((error) => toast.show(apiError(error), 'error')));
watch(roomId, (value) => roomStore.loadRoom(value).catch((error) => toast.show(apiError(error), 'error')));
</script>

<template>
    <div class="min-h-screen">
        <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <RouterLink :to="{ name: 'rooms' }" class="grid size-11 shrink-0 place-items-center rounded-2xl bg-brand-600 text-xl font-black text-white">د</RouterLink>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-black text-slate-900">{{ roomStore.currentRoom?.name ?? 'دخل و خرج' }}</p>
                        <p class="text-xs text-slate-500">مدیریت هزینه‌های اتاق</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden text-sm font-semibold text-slate-600 sm:inline">{{ auth.user?.name }}</span>
                    <button class="btn-secondary !px-3 !py-2 text-xs" @click="signOut">خروج</button>
                </div>
            </div>
        </header>
        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-5 sm:px-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:px-8 lg:py-8">
            <aside class="hidden lg:block">
                <div class="sticky top-24 rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="px-3 pb-3 pt-2 text-[11px] font-bold tracking-wide text-slate-400">مدیریت اتاق</p>
                    <nav class="space-y-1">
                        <RouterLink v-for="item in navigation" :key="item.name" :to="{ name: item.name, params: { roomId } }" class="nav-link" :class="route.name === item.name ? 'nav-link-active' : ''">
                            <span class="grid size-8 place-items-center rounded-xl bg-slate-100 text-lg">{{ item.icon }}</span>
                            {{ item.label }}
                        </RouterLink>
                    </nav>
                    <RouterLink :to="{ name: 'purchase-create', params: { roomId } }" class="btn-primary mt-4 w-full">＋ ثبت خرید</RouterLink>
                </div>
            </aside>
            <main class="min-w-0 pb-20 lg:pb-2">
                <RouterView />
            </main>
        </div>
        <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-slate-200 bg-white/95 px-1 pb-[max(env(safe-area-inset-bottom),.4rem)] pt-2 backdrop-blur lg:hidden">
            <RouterLink v-for="item in navigation" :key="item.name" :to="{ name: item.name, params: { roomId } }" class="flex flex-col items-center gap-1 rounded-xl py-1 text-[10px] font-bold" :class="route.name === item.name ? 'text-brand-700' : 'text-slate-500'">
                <span class="text-lg leading-none">{{ item.icon }}</span><span>{{ item.label }}</span>
            </RouterLink>
        </nav>
    </div>
</template>
