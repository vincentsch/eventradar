<script setup lang="ts">
import { MapPin } from '@lucide/vue';
import { computed } from 'vue';
import {
    eventCategoryLabel,
    eventDateParts,
} from '@/components/public/publicEventDisplay';
import type { PublicEventVisualFixture } from '@/types/public-events';

const props = withDefaults(
    defineProps<{
        event: PublicEventVisualFixture;
        imageLoading?: 'eager' | 'lazy';
    }>(),
    { imageLoading: 'lazy' },
);

defineEmits<{
    open: [];
}>();

const dateParts = computed(() => eventDateParts(props.event));
</script>

<template>
    <article
        class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-stone-900/10 bg-[#fffdf8] shadow-sm shadow-stone-900/5 transition duration-200 focus-within:ring-2 focus-within:ring-stone-900 hover:shadow-md hover:shadow-stone-900/10 motion-safe:hover:-translate-y-0.5"
    >
        <div class="relative aspect-[3/2] overflow-hidden bg-stone-200">
            <img
                :src="event.image.src"
                :alt="event.image.alt"
                :loading="imageLoading"
                decoding="async"
                class="h-full w-full object-cover"
            />
            <p
                class="absolute top-3 left-3 min-w-11 rounded-lg bg-[#fffdf8]/95 px-2 py-1.5 text-center shadow-md shadow-stone-900/10 backdrop-blur-sm"
            >
                <span
                    class="block text-[10px] font-black tracking-widest text-orange-700 uppercase"
                >
                    {{ dateParts.month }}
                </span>
                <span
                    class="block text-lg leading-none font-extrabold text-stone-900"
                >
                    {{ dateParts.day }}
                </span>
            </p>
        </div>

        <div class="flex flex-1 flex-col p-4">
            <p
                class="text-[11px] font-extrabold tracking-widest text-blue-700 uppercase"
            >
                {{ eventCategoryLabel(event.category) }}
            </p>
            <h2
                class="mt-1.5 min-h-14 text-xl leading-snug font-bold tracking-tight text-stone-900"
            >
                <button
                    type="button"
                    class="line-clamp-2 cursor-pointer text-left after:absolute after:inset-0 focus-visible:outline-none"
                    @click="$emit('open')"
                >
                    {{ event.title }}
                </button>
            </h2>
            <p
                class="mt-1.5 mb-4 line-clamp-2 min-h-10 text-sm leading-5 text-stone-600"
            >
                {{ event.description }}
            </p>

            <div class="mt-auto border-t border-stone-900/10 pt-3">
                <p
                    class="flex flex-wrap items-baseline gap-x-1.5 text-sm text-stone-900"
                >
                    <strong class="font-bold">{{ event.timeLabel }}</strong>
                    <span class="text-xs font-semibold text-stone-500">
                        {{ event.timezoneLabel }}
                    </span>
                    <span aria-hidden="true" class="text-stone-400">·</span>
                    <span class="font-semibold">{{ event.dateLabel }}</span>
                </p>
                <p class="mt-1.5 truncate text-sm text-stone-600">
                    {{ event.venue }}
                </p>
                <p
                    class="mt-0.5 flex items-center gap-1 text-sm text-stone-500"
                >
                    <MapPin class="size-3.5 shrink-0" aria-hidden="true" />
                    <span class="truncate">{{ event.locationLabel }}</span>
                </p>
            </div>
        </div>
    </article>
</template>
