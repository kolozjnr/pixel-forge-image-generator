import type { ComputedRef, MaybeRefOrGetter } from 'vue';
import { computed, onUnmounted, ref, toValue, watch } from 'vue';

export type LoadingMessage = {
    icon: string;
    text: string;
};

export type UseLoadingMessagesReturn = {
    current: ComputedRef<LoadingMessage>;
};

const loadingMessages: LoadingMessage[] = [
    { icon: '🪄', text: 'Mixing pixels with a little magic...' },
    { icon: '🤖', text: 'Teaching pixels how to behave...' },
    { icon: '🔥', text: 'Cooking up something visually delicious...' },
    { icon: '🎨', text: 'Your imagination is getting a makeover...' },
    { icon: '👀', text: 'Something awesome is taking shape...' },
    { icon: '🎬', text: 'Your masterpiece is almost ready for its close-up!' },
];

export function useLoadingMessages(
    active: MaybeRefOrGetter<boolean>,
    intervalMs = 3000,
): UseLoadingMessagesReturn {
    const index = ref(0);
    let timer: ReturnType<typeof setInterval> | undefined;

    const stop = (): void => {
        clearInterval(timer);
        timer = undefined;
    };

    watch(
        () => toValue(active),
        (isActive) => {
            stop();

            if (!isActive) {
                return;
            }

            index.value = Math.floor(Math.random() * loadingMessages.length);
            timer = setInterval(() => {
                index.value = (index.value + 1) % loadingMessages.length;
            }, intervalMs);
        },
        { immediate: true },
    );

    onUnmounted(stop);

    return { current: computed(() => loadingMessages[index.value]) };
}
