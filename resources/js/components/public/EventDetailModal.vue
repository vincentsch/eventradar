<script setup lang="ts">
import { Link, router, useHttp, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BellRing,
    CalendarDays,
    Check,
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
import { useSnapCarousel } from '@/components/public/useSnapCarousel';
import type { Auth } from '@/types';
import type { PublicEvent } from '@/types/public-events';

type AttendanceIntent = 'interested' | 'going';

const props = defineProps<{
    event: PublicEvent | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const page = usePage<{ auth: Auth }>();
const isAuthenticated = computed(() => Boolean(page.props.auth.user));

const viewerIntent = ref<AttendanceIntent | null>(null);
const attendanceBusy = ref(false);
const statusLookup = useHttp<
    Record<string, never>,
    { intent: AttendanceIntent | null }
>({});
let statusRequestToken = 0;

async function loadViewerIntent(event: PublicEvent) {
    const token = ++statusRequestToken;
    statusLookup.cancel();

    try {
        await statusLookup.get(`/events/${event.id}/attendance/status`);

        if (token === statusRequestToken && statusLookup.response) {
            viewerIntent.value = statusLookup.response.intent ?? null;
        }
    } catch {
        // Attendance actions remain available if this optional status lookup fails.
    }
}

function setIntent(intent: AttendanceIntent) {
    if (!props.event || attendanceBusy.value) {
        return;
    }

    const previous = viewerIntent.value;
    viewerIntent.value = intent;
    attendanceBusy.value = true;

    router.put(
        `/events/${props.event.id}/attendance`,
        { intent },
        {
            preserveScroll: true,
            only: ['errors'],
            onError: () => {
                viewerIntent.value = previous;
            },
            onFinish: () => {
                attendanceBusy.value = false;
            },
        },
    );
}

function leaveList() {
    if (!props.event || attendanceBusy.value) {
        return;
    }

    const previous = viewerIntent.value;
    viewerIntent.value = null;
    attendanceBusy.value = true;

    router.delete(`/events/${props.event.id}/attendance`, {
        preserveScroll: true,
        only: ['errors'],
        onError: () => {
            viewerIntent.value = previous;
        },
        onFinish: () => {
            attendanceBusy.value = false;
        },
    });
}

const intentPillClasses = (
    active: boolean,
    activeClasses: string,
    idleClasses: string,
) => [
    'inline-flex h-10 cursor-pointer items-center gap-1.5 rounded-full px-4 text-sm font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 disabled:cursor-default disabled:opacity-60',
    active ? activeClasses : idleClasses,
];

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

const {
    index: galleryIndex,
    track: galleryTrack,
    onTrackScroll,
    step: stepGallery,
    reset: resetGallery,
} = useSnapCarousel(() => galleryImages.value.length);
const previousButton = ref<HTMLButtonElement | null>(null);
const nextButton = ref<HTMLButtonElement | null>(null);

watch(
    () => props.event,
    (event) => {
        resetGallery();
        viewerIntent.value = null;

        if (event && isAuthenticated.value) {
            loadViewerIntent(event);
        }
    },
);

const isFirstImage = computed(() => galleryIndex.value === 0);
const isLastImage = computed(
    () => galleryIndex.value >= galleryImages.value.length - 1,
);

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
                            <p class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-[11px] font-extrabold tracking-widest text-blue-700 uppercase"
                                >
                                    {{ eventCategoryLabel(event.category) }}
                                </span>
                                <span
                                    v-if="event.status === 'sold_out'"
                                    class="rounded-full bg-orange-600/10 px-2 py-0.5 text-[10px] font-black tracking-widest text-orange-700 uppercase"
                                >
                                    Sold out
                                </span>
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

                            <div class="mt-6">
                                <div
                                    v-if="isAuthenticated"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <button
                                        type="button"
                                        :disabled="attendanceBusy"
                                        :aria-pressed="
                                            viewerIntent === 'interested'
                                        "
                                        :class="
                                            intentPillClasses(
                                                viewerIntent === 'interested',
                                                'bg-blue-700 text-white hover:bg-blue-800',
                                                'bg-blue-700/5 text-blue-800 ring-1 ring-blue-700/25 hover:bg-blue-700/10',
                                            )
                                        "
                                        @click="setIntent('interested')"
                                    >
                                        <Check
                                            v-if="viewerIntent === 'interested'"
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Interested
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="attendanceBusy"
                                        :aria-pressed="viewerIntent === 'going'"
                                        :class="
                                            intentPillClasses(
                                                viewerIntent === 'going',
                                                'bg-lime-300 text-stone-900 hover:bg-lime-200',
                                                'bg-lime-400/10 text-stone-800 ring-1 ring-lime-600/30 hover:bg-lime-400/20',
                                            )
                                        "
                                        @click="setIntent('going')"
                                    >
                                        <Check
                                            v-if="viewerIntent === 'going'"
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Going
                                    </button>
                                    <button
                                        v-if="viewerIntent"
                                        type="button"
                                        :disabled="attendanceBusy"
                                        class="cursor-pointer rounded-full px-2 py-1 text-xs font-bold text-stone-500 transition-colors hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                                        @click="leaveList"
                                    >
                                        Leave list
                                    </button>
                                </div>
                                <Link
                                    v-else
                                    :href="`/events/${event.id}/attendance`"
                                    class="inline-flex h-10 items-center gap-2 rounded-full bg-stone-900 px-5 text-sm font-bold text-white transition-colors hover:bg-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    <BellRing
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Log in to join the list
                                </Link>

                                <p class="mt-3.5">
                                    <Link
                                        :href="event.href"
                                        class="inline-flex items-center gap-1 rounded-sm text-xs font-bold text-blue-700 transition-colors hover:text-blue-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        Full event details
                                        <ArrowRight
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
