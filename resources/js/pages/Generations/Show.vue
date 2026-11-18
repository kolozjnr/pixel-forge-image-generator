<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Sparkles } from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';
import { useLoadingMessages } from '@/composables/useLoadingMessages';

const props = defineProps<{ generation: { id: number; status: string; prompt: string; size: string; quality: string; image_url: string | null; provider_error: { message?: string } | null; customer_cost_nano_usd: number | null } }>();
const isGenerating = computed(() => !['succeeded', 'failed'].includes(props.generation.status));
const { current: loadingMessage } = useLoadingMessages(isGenerating);
let interval: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    if (!['succeeded', 'failed'].includes(props.generation.status)) {
        interval = setInterval(() => router.reload({ only: ['generation'] }), 5000);
    }
});
onUnmounted(() => clearInterval(interval));
defineOptions({ layout: { breadcrumbs: [{ title: 'Generation details', href: '/generations' }] } });
</script>

<template>
    <Head title="Generation details" />
    <main class="min-h-full bg-[#f8f9fc] p-5 text-[#202133] dark:bg-[#10111a] dark:text-white sm:p-8"><div class="mx-auto max-w-6xl space-y-6"><Link href="/generations" class="inline-flex items-center gap-2 text-sm font-medium text-[#6957e8]"><ArrowLeft class="size-4" /> All generations</Link><div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]"><section class="flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-[#e9eaf1] bg-white dark:border-white/10 dark:bg-[#191b27]"><img v-if="generation.image_url" :src="generation.image_url" :alt="generation.prompt" class="size-full object-contain" /><div v-else class="max-w-sm p-8 text-center"><div class="mx-auto grid size-12 place-items-center rounded-2xl bg-[#f0edff]"><span v-if="isGenerating" class="text-2xl">{{ loadingMessage.icon }}</span><Sparkles v-else class="size-6 text-[#6957e8]" /></div><h1 class="mt-4 text-lg font-semibold capitalize">{{ generation.status === 'queued' ? 'Waiting in queue' : generation.status }}</h1><p class="mt-2 text-sm text-[#85889a]">{{ isGenerating ? loadingMessage.text : generation.status === 'failed' ? generation.provider_error?.message || 'This generation could not be completed.' : 'We’ll refresh this view as your image is created.' }}</p></div></section><aside class="space-y-4 rounded-2xl border border-[#e9eaf1] bg-white p-5 dark:border-white/10 dark:bg-[#191b27]"><div class="flex items-start justify-between"><div><p class="text-xs font-semibold tracking-wide text-[#6957e8]">GENERATION DETAILS</p><h2 class="mt-2 text-xl font-semibold capitalize">{{ generation.quality }} quality</h2></div><span class="rounded-full bg-[#f0edff] px-3 py-1 text-xs font-semibold capitalize text-[#6957e8]">{{ generation.status }}</span></div><div><p class="text-xs font-medium text-[#85889a]">PROMPT</p><p class="mt-2 whitespace-pre-wrap text-sm leading-6">{{ generation.prompt }}</p></div><div class="border-t border-[#e9eaf1] pt-4 text-sm dark:border-white/10"><div class="flex justify-between"><span class="text-[#85889a]">Image size</span><span>{{ generation.size }}</span></div><div v-if="generation.customer_cost_nano_usd !== null" class="mt-3 flex justify-between"><span class="text-[#85889a]">Final amount</span><span>${{ (generation.customer_cost_nano_usd / 1_000_000_000).toFixed(9) }}</span></div></div><Link href="/generations/create" class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-[#6957e8] text-sm font-semibold text-white">Create another image</Link></aside></div></div></main>
</template>
