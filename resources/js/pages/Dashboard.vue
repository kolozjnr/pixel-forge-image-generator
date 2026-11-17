<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, ImagePlus, Images, Sparkles, Wallet } from '@lucide/vue';
import { dashboard } from '@/routes';

const props = defineProps<{ stats: { image_count: number; balance_nano_usd: number; month_spend_nano_usd: number }; recent: Array<{ id: number; status: string; prompt: string; image_url: string | null; created_at: string | null }> }>();

const metrics = computed(() => [
    { label: 'Images created', value: String(props.stats.image_count), icon: Images, detail: 'Your creative library' },
    { label: 'Credits available', value: `$${(props.stats.balance_nano_usd / 1_000_000_000).toFixed(2)}`, icon: Wallet, detail: 'Ready when you are' },
    { label: 'This month', value: `$${(props.stats.month_spend_nano_usd / 1_000_000_000).toFixed(2)}`, icon: Sparkles, detail: 'Customer generation charges' },
]);
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Overview',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Overview" />

    <main class="min-h-full bg-[#f8f9fc] p-5 text-[#202133] dark:bg-[#10111a] dark:text-white sm:p-8">
        <div class="mx-auto flex max-w-7xl flex-col gap-8">
            <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div class="space-y-2"><p class="text-sm font-semibold tracking-wide text-[#6957e8]">YOUR CREATIVE STUDIO</p><h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Make something wonderful.</h1><p class="max-w-xl text-sm leading-6 text-[#85889a]">Turn a thought into an image. Shape every detail, then watch your ideas come to life.</p></div>
                <Link href="/generations/create" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#6957e8] px-5 text-sm font-semibold text-white shadow-lg shadow-[#6957e8]/20 transition hover:bg-[#5846d6]"><ImagePlus class="size-4" /> Create an image <ArrowUpRight class="size-4" /></Link>
            </section>
            <section class="grid gap-4 sm:grid-cols-3">
                <article v-for="metric in metrics" :key="metric.label" class="rounded-2xl border border-[#e9eaf1] bg-white p-5 shadow-sm dark:border-white/10 dark:bg-[#191b27]">
                    <div class="flex items-start justify-between"><div class="grid size-10 place-items-center rounded-xl bg-[#f0edff] text-[#6957e8] dark:bg-[#6957e8]/15"><component :is="metric.icon" class="size-5" /></div><span class="text-xs text-[#85889a]">{{ metric.detail }}</span></div>
                    <p class="mt-5 text-sm text-[#85889a]">{{ metric.label }}</p><p class="mt-1 text-2xl font-semibold">{{ metric.value }}</p>
                </article>
            </section>
            <section class="overflow-hidden rounded-3xl border border-[#e9eaf1] bg-white dark:border-white/10 dark:bg-[#191b27]"><div class="grid min-h-[360px] lg:grid-cols-[1fr_0.85fr]">
                <div class="flex flex-col justify-center p-7 sm:p-10 lg:p-12"><div class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-[#f0edff] text-[#6957e8] dark:bg-[#6957e8]/15"><Sparkles class="size-6" /></div><p class="text-sm font-medium text-[#6957e8]">A blank canvas, full of possibility</p><h2 class="mt-3 max-w-lg text-3xl font-semibold leading-tight tracking-tight">Your next favorite image starts with a prompt.</h2><p class="mt-4 max-w-md text-sm leading-6 text-[#85889a]">Describe what you imagine, choose a style and size, and create your first image in moments.</p><Link :href="dashboard()" class="mt-7 inline-flex w-fit items-center gap-2 text-sm font-semibold text-[#6957e8]">Open image workspace <ArrowUpRight class="size-4" /></Link></div>
                <div class="relative min-h-64 overflow-hidden bg-gradient-to-br from-[#edeaff] via-[#f6f4ff] to-[#fceef6] dark:from-[#26233e] dark:via-[#252035] dark:to-[#342331]"><div class="absolute left-[15%] top-[17%] size-40 rounded-full bg-[#a69afb]/35 blur-3xl" /><div class="absolute bottom-[8%] right-[9%] size-52 rounded-full bg-[#f1acd1]/40 blur-3xl" /><div class="absolute inset-0 flex items-center justify-center p-8"><div class="flex aspect-[4/3] w-full max-w-sm rotate-[-3deg] items-center justify-center rounded-3xl border border-white/70 bg-white/45 shadow-2xl shadow-[#6957e8]/10 backdrop-blur-sm dark:border-white/10 dark:bg-white/5"><div class="flex flex-col items-center gap-3 text-center text-[#6957e8]/65"><Sparkles class="size-9" /><span class="text-xs font-medium tracking-wide">YOUR FIRST CREATION</span></div></div></div></div>
            </div></section>
            <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"><div><h2 class="text-lg font-semibold">Recent generations</h2><p class="mt-1 text-sm text-[#85889a]">Your latest creations will appear here.</p></div><Link href="/generations" class="inline-flex items-center gap-2 text-sm font-semibold text-[#6957e8]">View all <ArrowUpRight class="size-4" /></Link></section>
            <div v-if="recent.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><Link v-for="generation in recent" :key="generation.id" :href="`/generations/${generation.id}`" class="overflow-hidden rounded-2xl border border-[#e9eaf1] bg-white dark:border-white/10 dark:bg-[#191b27]"><div class="flex aspect-[4/3] items-center justify-center bg-[#f0edff] dark:bg-[#252239]"><img v-if="generation.image_url" :src="generation.image_url" :alt="generation.prompt" class="size-full object-cover" /><span v-else class="text-xs capitalize text-[#85889a]">{{ generation.status }}</span></div><p class="line-clamp-2 p-4 text-sm">{{ generation.prompt }}</p></Link></div>
            <div v-else class="flex min-h-40 flex-col items-center justify-center rounded-2xl border border-dashed border-[#dfe0e8] bg-white/60 text-center dark:border-white/10 dark:bg-[#191b27]/50"><div class="mb-3 grid size-10 place-items-center rounded-xl bg-[#f0edff] text-[#6957e8] dark:bg-[#6957e8]/15"><Images class="size-5" /></div><p class="text-sm font-medium">Your gallery is waiting</p><p class="mt-1 text-xs text-[#85889a]">Create an image to start building your collection.</p></div>
        </div>
    </main>
</template>
