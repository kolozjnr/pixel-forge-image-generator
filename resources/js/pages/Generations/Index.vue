<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { ArrowUpRight, ImagePlus, Images } from '@lucide/vue';
import { dashboard } from '@/routes';

const props = defineProps<{ generations: { data: Array<{ id: number; status: string; prompt: string; size: string; quality: string; image_url: string | null; created_at: string | null }>; links: Array<{ url: string | null; label: string; active: boolean }> }; filters: { search: string; status: string } }>();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
watch([search, status], ([searchValue, statusValue]) => router.get('/generations', { search: searchValue || undefined, status: statusValue || undefined }, { preserveState: true, replace: true }));
defineOptions({ layout: { breadcrumbs: [{ title: 'My generations', href: dashboard() }] } });
</script>

<template>
    <Head title="My generations" />
    <main class="min-h-full bg-[#f8f9fc] p-5 text-[#202133] dark:bg-[#10111a] dark:text-white sm:p-8"><div class="mx-auto flex max-w-7xl flex-col gap-6">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-sm font-semibold text-[#6957e8]">YOUR LIBRARY</p><h1 class="mt-2 text-3xl font-semibold">My generations</h1><p class="mt-2 text-sm text-[#85889a]">Every idea you’ve brought to life, all in one place.</p></div><Link href="/generations/create" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#6957e8] px-4 text-sm font-semibold text-white"><ImagePlus class="size-4" /> Create image</Link></header>
        <div class="flex flex-col gap-3 sm:flex-row"><input v-model="search" placeholder="Search prompts" class="h-10 rounded-xl border border-[#e9eaf1] bg-white px-3 text-sm dark:border-white/10 dark:bg-[#191b27]" /><select v-model="status" class="h-10 rounded-xl border border-[#e9eaf1] bg-white px-3 text-sm dark:border-white/10 dark:bg-[#191b27]"><option value="">All statuses</option><option value="queued">Queued</option><option value="processing">Processing</option><option value="succeeded">Succeeded</option><option value="failed">Failed</option></select></div>
        <div v-if="generations.data.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"><Link v-for="generation in generations.data" :key="generation.id" :href="`/generations/${generation.id}`" class="overflow-hidden rounded-2xl border border-[#e9eaf1] bg-white transition hover:-translate-y-0.5 hover:shadow-lg dark:border-white/10 dark:bg-[#191b27]"><div class="flex aspect-square items-center justify-center bg-[#f1efff] dark:bg-[#252239]"><img v-if="generation.image_url" :src="generation.image_url" :alt="generation.prompt" class="size-full object-cover" /><div v-else class="text-center"><Images class="mx-auto size-8 text-[#a69afb]" /><span class="mt-2 block text-xs font-medium capitalize text-[#85889a]">{{ generation.status }}</span></div></div><div class="p-4"><p class="line-clamp-2 text-sm font-medium">{{ generation.prompt }}</p><div class="mt-3 flex justify-between text-xs text-[#85889a]"><span class="capitalize">{{ generation.quality }} · {{ generation.size }}</span><span>{{ generation.created_at ? new Date(generation.created_at).toLocaleDateString() : '' }}</span></div></div></Link></div>
        <div v-else class="flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed border-[#dfe0e8] bg-white/70 text-center dark:border-white/10 dark:bg-[#191b27]"><div class="grid size-12 place-items-center rounded-2xl bg-[#f0edff] text-[#6957e8]"><Images class="size-6" /></div><h2 class="mt-4 text-lg font-semibold">Your gallery is waiting</h2><p class="mt-2 max-w-sm text-sm text-[#85889a]">Your generated images will be saved here. Start with a prompt and make your first one.</p><Link href="/generations/create" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-[#6957e8]">Create your first image <ArrowUpRight class="size-4" /></Link></div>
    </div></main>
</template>
