<script setup>
import { RouterView } from 'vue-router';
import { useToastStore } from './stores/toast';

const toast = useToastStore();
</script>

<template>
    <RouterView v-slot="{ Component, route }">
        <Transition name="page" mode="out-in">
            <component :is="Component" :key="route.matched[0]?.path" />
        </Transition>
    </RouterView>
    <div class="fixed bottom-5 left-4 z-50 flex w-[min(92vw,24rem)] flex-col gap-2" aria-live="polite">
        <button
            v-for="item in toast.items"
            :key="item.id"
            class="rounded-2xl px-4 py-3 text-right text-sm font-semibold text-white shadow-lg"
            :class="item.kind === 'error' ? 'bg-rose-700' : 'bg-slate-900'"
            @click="toast.dismiss(item.id)"
        >{{ item.message }}</button>
    </div>
</template>

<style>
.page-enter-active, .page-leave-active { transition: opacity .13s ease, transform .13s ease; }
.page-enter-from { opacity: 0; transform: translateY(5px); }
.page-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
