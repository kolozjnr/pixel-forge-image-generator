<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ImagePlus, Plus, Sparkles, X } from '@lucide/vue';
import { useLoadingMessages } from '@/composables/useLoadingMessages';
import { dashboard } from '@/routes';

defineOptions({ layout: { breadcrumbs: [{ title: 'Create image', href: dashboard() }] } });

type Estimate = { provider_min_nano_usd: number; provider_max_nano_usd: number; customer_min_nano_usd: number; customer_max_nano_usd: number };

const props = defineProps<{ pricing: Record<string, unknown> | null }>();
const sizes = [
    { value: '1024x1024', title: 'Square', ratio: 'aspect-square' },
    { value: '1536x1024', title: 'Landscape', ratio: 'aspect-[3/2]' },
    { value: '1024x1536', title: 'Portrait', ratio: 'aspect-[2/3]' },
];
const qualities = [
    { value: 'low', title: 'Low', help: 'Fast and affordable' },
    { value: 'medium', title: 'Medium', help: 'Balanced detail' },
    { value: 'high', title: 'High', help: 'Maximum detail' },
];
const form = useForm({ prompt: '', size: '1024x1024', quality: 'medium', reference_images: [] as string[] });
const referenceDraft = ref('');
const promptCharacters = computed(() => Array.from(form.prompt).length);
const estimate = ref<Estimate | null>(null);
const pricingAvailable = computed(() => props.pricing !== null);
const formatUsd = (nanoUsd: number) => `$${(nanoUsd / 1_000_000_000).toFixed(9)}`;

let estimateTimer: ReturnType<typeof setTimeout> | undefined;
watch(() => [form.prompt, form.size, form.quality, form.reference_images.length], () => {
    clearTimeout(estimateTimer);
    estimateTimer = setTimeout(async () => {
    if (!form.prompt.trim()) {
        estimate.value = null;
        return;
    }

    const parameters = new URLSearchParams({ prompt: form.prompt, size: form.size, quality: form.quality, reference_count: String(form.reference_images.length) });
    try {
        const response = await fetch(`/generations/estimate?${parameters}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        estimate.value = response.ok ? await response.json() as Estimate : null;
    } catch {
        estimate.value = null;
    }
    }, 350);
}, { immediate: true });

const { current: loadingMessage } = useLoadingMessages(() => form.processing);
const generateLabel = computed(() => (form.processing ? `${loadingMessage.value.icon} ${loadingMessage.value.text}` : 'Generate image'));

const promptDraftKey = `pixel-forge:create-prompt:${usePage().props.auth.user.id}`;

function savePromptDraft(prompt: string): void {
    if (prompt) {
        localStorage.setItem(promptDraftKey, prompt);
    } else {
        localStorage.removeItem(promptDraftKey);
    }
}

watch(() => form.prompt, savePromptDraft);

onMounted(() => {
    const draft = localStorage.getItem(promptDraftKey);
    if (draft && !form.prompt) {
        form.prompt = draft;
    }
});

function submit() {
    form.post('/generations', { onSuccess: () => localStorage.removeItem(promptDraftKey) });
}

function addReference() {
    const value = referenceDraft.value.trim();
    try {
        const url = new URL(value);
        if (!['http:', 'https:'].includes(url.protocol) || form.reference_images.length >= 4) return;
        form.reference_images.push(value);
        referenceDraft.value = '';
    } catch {
        return;
    }
}
</script>

<template>
    <Head title="Create image" />
    <main class="min-h-full bg-[#f8f9fc] p-5 text-[#202133] dark:bg-[#10111a] dark:text-white sm:p-8">
        <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(340px,0.9fr)]">
            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <header><p class="text-sm font-semibold text-[#6957e8]">IMAGE WORKSPACE</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Create something new</h1><p class="mt-2 text-sm text-[#85889a]">Describe the image you want to bring to life.</p></header>
                <section class="rounded-2xl border border-[#e9eaf1] bg-white p-5 dark:border-white/10 dark:bg-[#191b27]">
                    <div class="flex items-center justify-between"><label for="prompt" class="text-sm font-semibold">Your prompt</label><button type="button" class="text-xs font-medium text-[#6957e8]" @click="form.prompt = ''">Clear prompt</button></div>
                    <textarea id="prompt" v-model="form.prompt" maxlength="4000" rows="7" placeholder="A quiet greenhouse at sunrise, soft golden light filtering through glass, lush leaves, cinematic photography…" class="mt-3 w-full resize-y rounded-xl border border-[#e9eaf1] bg-[#fbfbfd] p-4 text-sm leading-6 outline-none transition focus:border-[#8b7cf6] focus:ring-4 focus:ring-[#6957e8]/10 dark:border-white/10 dark:bg-[#10111a]" />
                    <div class="mt-2 flex justify-between text-xs text-[#85889a]"><p v-if="form.errors.prompt" class="text-red-500">{{ form.errors.prompt }}</p><span v-else>Be specific about subject, setting, mood, and style.</span><span>{{ promptCharacters }} / 4,000</span></div>
                </section>
                <section class="rounded-2xl border border-[#e9eaf1] bg-white p-5 dark:border-white/10 dark:bg-[#191b27]"><h2 class="text-sm font-semibold">Image proportions</h2><div class="mt-4 grid grid-cols-3 gap-3"><button v-for="size in sizes" :key="size.value" type="button" class="rounded-xl border p-3 text-left transition" :class="form.size === size.value ? 'border-[#6957e8] bg-[#f7f5ff] ring-2 ring-[#6957e8]/15 dark:bg-[#6957e8]/10' : 'border-[#e9eaf1] hover:border-[#bbb2fa] dark:border-white/10'" @click="form.size = size.value"><div class="flex h-12 items-center justify-center"><div class="w-10 rounded border-2" :class="[size.ratio, form.size === size.value ? 'border-[#6957e8]' : 'border-[#aeb0be']" /></div><p class="mt-2 text-sm font-semibold">{{ size.title }}</p><p class="mt-1 text-[11px] text-[#85889a]">{{ size.value.replace('x', ' × ') }}</p></button></div></section>
                <section class="rounded-2xl border border-[#e9eaf1] bg-white p-5 dark:border-white/10 dark:bg-[#191b27]"><h2 class="text-sm font-semibold">Quality</h2><div class="mt-3 grid gap-2 sm:grid-cols-3"><button v-for="quality in qualities" :key="quality.value" type="button" class="rounded-xl border p-3 text-left transition" :class="form.quality === quality.value ? 'border-[#6957e8] bg-[#f7f5ff] dark:bg-[#6957e8]/10' : 'border-[#e9eaf1] dark:border-white/10'" @click="form.quality = quality.value"><span class="text-sm font-semibold">{{ quality.title }}</span><span class="mt-1 block text-xs text-[#85889a]">{{ quality.help }}</span></button></div></section>
                <section class="rounded-2xl border border-[#e9eaf1] bg-white p-5 dark:border-white/10 dark:bg-[#191b27]"><div class="flex items-start justify-between"><div><h2 class="text-sm font-semibold">Reference images <span class="font-normal text-[#85889a]">({{ form.reference_images.length }}/4)</span></h2><p class="mt-1 text-xs text-[#85889a]">Add public image URLs to guide your result. Each adds 765 input tokens.</p></div></div><div class="mt-4 flex gap-2"><input v-model="referenceDraft" type="url" placeholder="https://example.com/image.png" class="min-w-0 flex-1 rounded-xl border border-[#e9eaf1] bg-[#fbfbfd] px-3 py-2 text-sm outline-none focus:border-[#8b7cf6] dark:border-white/10 dark:bg-[#10111a]" @keydown.enter.prevent="addReference" /><button type="button" class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#f0edff] text-[#6957e8] disabled:opacity-40" :disabled="form.reference_images.length >= 4" aria-label="Add reference image" @click="addReference"><Plus class="size-4" /></button></div><ul class="mt-3 space-y-2"><li v-for="(url, index) in form.reference_images" :key="url" class="flex items-center gap-2 rounded-lg bg-[#f8f9fc] p-2 text-xs dark:bg-[#10111a]"><img :src="url" alt="Reference preview" class="size-9 rounded object-cover" @error="($event.target as HTMLImageElement).hidden = true" /><span class="min-w-0 flex-1 truncate">{{ url }}</span><button type="button" class="text-[#85889a]" aria-label="Remove reference" @click="form.reference_images.splice(index, 1)"><X class="size-4" /></button></li></ul></section>
                <button type="submit" :disabled="form.processing || !form.prompt.trim() || !estimate" class="flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#6957e8] px-5 text-sm font-semibold text-white shadow-lg shadow-[#6957e8]/20 transition hover:bg-[#5846d6] disabled:cursor-not-allowed disabled:opacity-50"><Sparkles v-if="!form.processing" class="size-4" />{{ generateLabel }}</button><p v-if="!estimate && form.prompt.trim()" class="text-xs text-[#85889a]">Loading a verified estimate before enabling generation…</p>
            </form>
            <aside class="flex flex-col gap-5 lg:sticky lg:top-6 lg:self-start">
                <section class="rounded-2xl border border-[#e9eaf1] bg-white p-5 dark:border-white/10 dark:bg-[#191b27]"><div class="flex items-center justify-between"><h2 class="text-sm font-semibold">Estimated cost</h2><span class="rounded-full bg-[#f0edff] px-2.5 py-1 text-[11px] font-semibold text-[#6957e8]">{{ pricingAvailable ? 'Live estimate' : 'Unavailable' }}</span></div><p class="mt-1 text-xs text-[#85889a]">{{ pricingAvailable ? 'Based on current Pixelforge rates' : 'Rates could not be loaded; refresh to try again.' }}</p><template v-if="estimate"><div class="mt-5 flex items-end justify-between"><span class="text-sm text-[#85889a]">Estimated payable range</span><span class="text-lg font-semibold">{{ formatUsd(estimate.customer_min_nano_usd) }} – {{ formatUsd(estimate.customer_max_nano_usd) }}</span></div><div class="mt-4 rounded-xl bg-[#f8f9fc] p-4 dark:bg-[#10111a]"><div class="flex justify-between text-xs"><span class="text-[#85889a]">Provider estimate</span><span class="font-semibold">{{ formatUsd(estimate.provider_min_nano_usd) }} – {{ formatUsd(estimate.provider_max_nano_usd) }}</span></div><div class="mt-3 flex justify-between text-xs"><span class="text-[#85889a]">Service markup</span><span class="font-medium">25%</span></div></div><p class="mt-4 text-xs leading-5 text-[#85889a]">This estimate uses approximate text tokens and documented reasoning ranges. The final amount uses actual token usage and rates fixed when the generation is created.</p></template></section>
                <section class="rounded-2xl border border-[#e9eaf1] bg-white p-5 dark:border-white/10 dark:bg-[#191b27]"><div class="flex items-center gap-2 text-sm font-semibold"><ImagePlus class="size-4 text-[#6957e8]" /> Preview</div><div class="mt-4 flex aspect-square items-center justify-center rounded-xl border border-dashed border-[#dfe0e8] bg-[#fbfbfd] text-center dark:border-white/10 dark:bg-[#10111a]"><div class="max-w-[220px] px-5"><Sparkles class="mx-auto size-7 text-[#a69afb]" /><p class="mt-3 text-sm font-medium">Your image will appear here</p><p class="mt-1 text-xs leading-5 text-[#85889a]">Choose your settings and generate to see the result.</p></div></div></section>
            </aside>
        </div>
    </main>
</template>
