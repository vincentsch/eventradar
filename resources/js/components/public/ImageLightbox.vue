<script setup lang="ts">
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, nextTick, ref, watch } from 'vue';
import { useSnapCarousel } from '@/components/public/useSnapCarousel';

interface LightboxImage {
    src: string;
    alt: string;
}

const props = defineProps<{
    images: LightboxImage[];
    /** Index of the image to show, or null while closed. */
    modelValue: number | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const { index, track, onTrackScroll, step, reset } = useSnapCarousel(
    () => props.images.length,
);
const previousButton = ref<HTMLButtonElement | null>(null);
const nextButton = ref<HTMLButtonElement | null>(null);

const isOpen = computed(() => props.modelValue !== null);
const isFirst = computed(() => index.value === 0);
const isLast = computed(() => index.value >= props.images.length - 1);
const caption = computed(() => props.images[index.value]?.alt ?? '');

watch(
    () => props.modelValue,
    (value) => {
        if (value !== null) {
            void nextTick(() => reset(value));
        }
    },
);

// Keep focus on the surviving arrow when the focused one hides at an end.
watch(index, () => {
    void nextTick(() => {
        if (document.activeElement === document.body) {
            (nextButton.value ?? previousButton.value)?.focus();
        }
    });
});

function onOpenChange(open: boolean) {
    if (!open) {
        emit('update:modelValue', null);
    }
}

const arrowClasses =
    'absolute top-1/2 grid size-11 -translate-y-1/2 cursor-pointer place-items-center rounded-full bg-[#fffdf8]/90 text-stone-900 shadow-md shadow-stone-950/40 backdrop-blur-sm transition-colors hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white';
</script>

<template>
    <DialogRoot :open="isOpen" @update:open="onOpenChange">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-stone-950/90 backdrop-blur-sm motion-safe:data-[state=open]:animate-in motion-safe:data-[state=open]:fade-in-0"
            />
            <DialogContent
                class="fixed inset-0 z-50 flex flex-col focus:outline-none motion-safe:data-[state=open]:animate-in motion-safe:data-[state=open]:duration-200 motion-safe:data-[state=open]:fade-in-0"
                @keydown.left="step(-1)"
                @keydown.right="step(1)"
            >
                <DialogTitle class="sr-only">Event photos</DialogTitle>

                <div
                    ref="track"
                    class="flex flex-1 snap-x snap-mandatory [scrollbar-width:none] overflow-x-auto overflow-y-hidden [&::-webkit-scrollbar]:hidden"
                    @scroll.passive="onTrackScroll"
                >
                    <div
                        v-for="image in images"
                        :key="image.src"
                        class="flex w-full flex-none snap-center items-center justify-center p-4 pt-16 pb-24 sm:px-20 sm:pt-16 sm:pb-24"
                        @click.self="emit('update:modelValue', null)"
                    >
                        <img
                            :src="image.src"
                            :alt="image.alt"
                            class="max-h-full max-w-full rounded-xl object-contain shadow-2xl shadow-stone-950/60"
                        />
                    </div>
                </div>

                <template v-if="images.length > 1">
                    <button
                        v-if="!isFirst"
                        ref="previousButton"
                        type="button"
                        aria-label="Previous image"
                        class="left-3 sm:left-5"
                        :class="arrowClasses"
                        @click="step(-1)"
                    >
                        <ChevronLeft class="size-5" aria-hidden="true" />
                    </button>
                    <button
                        v-if="!isLast"
                        ref="nextButton"
                        type="button"
                        aria-label="Next image"
                        class="right-3 sm:right-5"
                        :class="arrowClasses"
                        @click="step(1)"
                    >
                        <ChevronRight class="size-5" aria-hidden="true" />
                    </button>
                </template>

                <div
                    class="pointer-events-none absolute inset-x-0 bottom-6 flex flex-col items-center gap-2.5 px-4"
                >
                    <div
                        v-if="images.length > 1"
                        aria-hidden="true"
                        class="flex gap-1.5"
                    >
                        <span
                            v-for="(image, imageIndex) in images"
                            :key="image.src"
                            class="size-1.5 rounded-full transition-colors"
                            :class="
                                imageIndex === index
                                    ? 'bg-white'
                                    : 'bg-white/40'
                            "
                        ></span>
                    </div>
                    <p
                        v-if="caption"
                        class="max-w-xl text-center text-xs leading-relaxed text-stone-300"
                    >
                        {{ caption }}
                    </p>
                </div>

                <DialogClose
                    aria-label="Close photo viewer"
                    class="absolute top-4 right-4 grid size-10 cursor-pointer place-items-center rounded-full bg-[#fffdf8]/90 text-stone-900 shadow-md shadow-stone-950/40 backdrop-blur-sm transition-colors hover:bg-white focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                >
                    <X class="size-5" aria-hidden="true" />
                </DialogClose>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
