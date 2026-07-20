<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    MapPin,
    X,
} from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, nextTick, ref, watch } from 'vue';
import {
    eventCategoryLabel,
    eventDetailImageSrc,
    eventWeekday,
} from '@/components/public/publicEventDisplay';
import type { PublicEvent } from '@/types/public-events';

const props = defineProps<{
    event: PublicEvent | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const galleryImages = computed(() => {
    if (!props.event) {
        return [];
    }

    return [
        { src: props.event.image.src, alt: props.event.image.alt },
        {
            src: eventDetailImageSrc(props.event),
            alt: props.event.detailImage.alt,
        },
    ].filter(
        (image, index, images) =>
            images.findIndex((candidate) => candidate.src === image.src) ===
            index,
    );
});

const galleryIndex = ref(0);
const galleryTrack = ref<HTMLElement | null>(null);
const previousButton = ref<HTMLButtonElement | null>(null);
const nextButton = ref<HTMLButtonElement | null>(null);

watch(
    () => props.event,
    () => {
        galleryIndex.value = 0;

        void nextTick(() => {
            galleryTrack.value?.scrollTo({ left: 0, behavior: 'auto' });
        });
    },
);

const isFirstImage = computed(() => galleryIndex.value === 0);
const isLastImage = computed(
    () => galleryIndex.value >= galleryImages.value.length - 1,
);

// The slider is a native scroll-snap track, so touch swiping just works;
// the current index is derived from the scroll position.
function onTrackScroll() {
    const track = galleryTrack.value;

    if (!track || track.clientWidth === 0) {
        return;
    }

    galleryIndex.value = Math.min(
        Math.round(track.scrollLeft / track.clientWidth),
        Math.max(galleryImages.value.length - 1, 0),
    );
}

// An arrow hides once its end of the gallery is reached; if it was the
// focused element, move focus to the surviving arrow instead of losing
// it to the page.
watch(galleryIndex, () => {
    void nextTick(() => {
        if (document.activeElement === document.body) {
            (nextButton.value ?? previousButton.value)?.focus();
        }
    });
});

function stepGallery(delta: number) {
    const track = galleryTrack.value;

    if (!track) {
        return;
    }

    const target = Math.min(
        Math.max(galleryIndex.value + delta, 0),
        Math.max(galleryImages.value.length - 1, 0),
    );
    const behavior = window.matchMedia('(prefers-reduced-motion: reduce)')
        .matches
        ? 'auto'
        : 'smooth';

    track.scrollTo({ left: target * track.clientWidth, behavior });
}

function onOpenChange(open: boolean) {
    if (!open) {
        emit('close');
    }
}

const galleryButtonClasses =
    'absolute top-1/2 grid size-10 -translate-y-1/2 cursor-pointer place-items-center rounded-full bg-[#fffdf8]/90 text-stone-900 shadow-md shadow-stone-900/20 backdrop-blur-sm transition-colors hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-900';
</script>

<template>
    <DialogRoot :open="event !== null" @update:open="onOpenChange">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-stone-950/50 backdrop-blur-sm motion-safe:data-[state=open]:animate-in motion-safe:data-[state=open]:fade-in-0"
            />
            <DialogContent
                v-if="event"
                class="fixed top-1/2 left-1/2 z-50 flex w-[calc(100%-2rem)] max-w-2xl -translate-x-1/2 -translate-y-1/2 flex-col focus:outline-none motion-safe:data-[state=open]:animate-in motion-safe:data-[state=open]:duration-200 motion-safe:data-[state=open]:fade-in-0 motion-safe:data-[state=open]:zoom-in-95"
            >
                <DialogClose
                    aria-label="Close event details"
                    class="mb-2 grid size-10 cursor-pointer place-items-center self-end rounded-full bg-[#fffdf8]/90 text-stone-900 shadow-md shadow-stone-950/30 backdrop-blur-sm transition-colors hover:bg-white focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                >
                    <X class="size-5" aria-hidden="true" />
                </DialogClose>

                <div
                    class="overflow-hidden rounded-3xl bg-[#fffdf8] shadow-2xl shadow-stone-950/30"
                >
                    <div
                        class="max-h-[78dvh] overflow-y-auto overscroll-contain"
                    >
                        <div class="relative">
                            <div
                                ref="galleryTrack"
                                class="flex snap-x snap-mandatory [scrollbar-width:none] overflow-x-auto [&::-webkit-scrollbar]:hidden"
                                @scroll.passive="onTrackScroll"
                            >
                                <img
                                    v-for="(image, index) in galleryImages"
                                    :key="image.src"
                                    :src="image.src"
                                    :alt="image.alt"
                                    :loading="index === 0 ? 'eager' : 'lazy'"
                                    decoding="async"
                                    class="aspect-[16/9] w-full flex-none snap-center object-cover"
                                />
                            </div>
                            <template v-if="galleryImages.length > 1">
                                <button
                                    v-if="!isFirstImage"
                                    ref="previousButton"
                                    type="button"
                                    aria-label="Previous image"
                                    class="left-3"
                                    :class="galleryButtonClasses"
                                    @click="stepGallery(-1)"
                                >
                                    <ChevronLeft
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </button>
                                <button
                                    v-if="!isLastImage"
                                    ref="nextButton"
                                    type="button"
                                    aria-label="Next image"
                                    class="right-3"
                                    :class="galleryButtonClasses"
                                    @click="stepGallery(1)"
                                >
                                    <ChevronRight
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </button>
                                <div
                                    aria-hidden="true"
                                    class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5"
                                >
                                    <span
                                        v-for="(image, index) in galleryImages"
                                        :key="image.src"
                                        class="size-1.5 rounded-full shadow-sm transition-colors"
                                        :class="
                                            index === galleryIndex
                                                ? 'bg-white'
                                                : 'bg-white/50'
                                        "
                                    ></span>
                                </div>
                            </template>
                        </div>
                        <div class="p-5 sm:p-7">
                            <p
                                class="text-[11px] font-extrabold tracking-widest text-blue-700 uppercase"
                            >
                                {{ eventCategoryLabel(event.category) }}
                            </p>
                            <DialogTitle
                                class="mt-2 text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl"
                            >
                                {{ event.title }}
                            </DialogTitle>
                            <div class="mt-4 space-y-2">
                                <p
                                    class="flex items-center gap-2 text-sm font-semibold text-stone-900"
                                >
                                    <CalendarDays
                                        class="size-4 shrink-0 text-stone-500"
                                        aria-hidden="true"
                                    />
                                    <span>
                                        {{ eventWeekday(event) }}
                                        {{ event.dateLabel }} ·
                                        {{ event.timeLabel }}
                                        {{ event.timezoneLabel }}
                                    </span>
                                </p>
                                <p
                                    class="flex items-center gap-2 text-sm text-stone-600"
                                >
                                    <MapPin
                                        class="size-4 shrink-0 text-stone-500"
                                        aria-hidden="true"
                                    />
                                    <span>
                                        {{ event.venue }},
                                        {{ event.locationLabel }}
                                    </span>
                                </p>
                            </div>
                            <DialogDescription
                                class="mt-4 text-sm leading-relaxed text-stone-600 sm:text-base"
                            >
                                {{ event.description }}
                            </DialogDescription>
                            <Link
                                :href="event.href"
                                class="mt-6 inline-flex h-11 items-center rounded-full bg-stone-900 px-5 text-sm font-bold text-white transition-colors hover:bg-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                View full event details
                            </Link>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
