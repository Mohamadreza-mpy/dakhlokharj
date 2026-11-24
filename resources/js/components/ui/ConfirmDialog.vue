<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'تأیید عملیات' },
    description: { type: String, default: 'از انجام این عملیات مطمئن هستید؟' },
    confirmLabel: { type: String, default: 'تأیید' },
    danger: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-40 grid place-items-center bg-slate-950/45 p-4" @click.self="emit('close')">
            <section class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" :aria-label="title">
                <div class="mb-4 flex size-12 items-center justify-center rounded-2xl" :class="danger ? 'bg-rose-50 text-rose-700' : 'bg-brand-50 text-brand-700'">
                    <span class="text-xl">{{ danger ? '!' : '✓' }}</span>
                </div>
                <h2 class="text-lg font-black text-slate-900">{{ title }}</h2>
                <p class="mt-2 leading-7 text-slate-600">{{ description }}</p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row">
                    <button class="btn-secondary flex-1" :disabled="busy" @click="emit('close')">انصراف</button>
                    <button class="flex-1 rounded-xl px-4 py-3 font-bold text-white" :class="danger ? 'bg-rose-700 hover:bg-rose-800' : 'bg-brand-600 hover:bg-brand-700'" :disabled="busy" @click="emit('confirm')">
                        {{ busy ? 'در حال ثبت…' : confirmLabel }}
                    </button>
                </div>
            </section>
        </div>
    </Teleport>
</template>
