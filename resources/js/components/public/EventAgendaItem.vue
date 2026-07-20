<script setup lang="ts">
import { ArrowRight, CalendarDays, MapPin } from '@lucide/vue';
import {
    eventCategoryLabel,
    eventWeekday,
} from '@/components/public/publicEventDisplay';
import type { PublicEvent } from '@/types/public-events';

defineProps<{
    event: PublicEvent;
    selected: boolean;
}>();

defineEmits<{
    select: [];
    view: [];
}>();
</script>

<template>
    <article
        class="overflow-hidden rounded-2xl border transition-[border-color,background-color,box-shadow] duration-200"
        :class="
            selected
                ? 'border-stone-900/80 bg-[#fffdf8] shadow-lg shadow-stone-900/10'
                : 'border-transparent bg-white/55 hover:border-stone-900/10 hover:bg-white'
        "
    >
        <button
            type="button"
            :aria-expanded="selected"
            :aria-controls="`agenda-details-${event.id}`"
            class="grid w-full grid-cols-[4rem_minmax(0,1fr)_auto] items-center gap-3 rounded-2xl p-2.5 text-left focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
            @click="$emit('select')"
        >
            <img
                :src="event.image.src"
                alt=""
                loading="lazy"
                decoding="async"
                class="size-16 rounded-xl object-cover"
            />
            <span class="min-w-0">
                <span
                    class="block truncate text-[15px] font-bold tracking-tight text-stone-900"
                >
                    {{ event.title }}
                </span>
                <span
                    class="mt-0.5 block truncate text-xs font-medium text-stone-600"
                >
                    {{ event.venue }} · {{ eventCategoryLabel(event.category) }}
                </span>
                <span class="mt-0.5 block truncate text-xs text-stone-500">
                    {{ event.locationLabel }}
                </span>
            </span>
            <span class="self-start pt-0.5 text-right">
                <span
                    class="block text-sm font-extrabold text-stone-900 tabular-nums"
                >
                    {{ event.timeLabel }}
                </span>
                <span
                    class="block text-[10px] font-bold tracking-wider text-stone-500 uppercase"
                >
                    {{ event.timezoneLabel }}
                </span>
            </span>
        </button>

        <div
            :id="`agenda-details-${event.id}`"
            class="grid transition-[grid-template-rows,opacity,visibility] duration-300 motion-reduce:transition-none"
            :class="
                selected
                    ? 'visible grid-rows-[1fr] opacity-100'
                    : 'invisible grid-rows-[0fr] opacity-0'
            "
        >
            <div class="overflow-hidden">
                <div class="border-t border-stone-900/10 px-3 pt-3 pb-3">
                    <p class="text-sm leading-relaxed text-stone-600">
                        {{ event.description }}
                    </p>
                    <p
                        class="mt-3 flex items-center gap-2 text-sm font-semibold text-stone-900"
                    >
                        <CalendarDays
                            class="size-4 shrink-0 text-stone-500"
                            aria-hidden="true"
                        />
                        <span>
                            {{ eventWeekday(event) }} {{ event.dateLabel }} ·
                            {{ event.timeLabel }} {{ event.timezoneLabel }}
                        </span>
                    </p>
                    <p
                        class="mt-1.5 flex items-center gap-2 text-sm text-stone-600"
                    >
                        <MapPin
                            class="size-4 shrink-0 text-stone-500"
                            aria-hidden="true"
                        />
                        <span class="truncate">
                            {{ event.venue }}, {{ event.locationLabel }}
                        </span>
                    </p>
                    <button
                        type="button"
                        class="mt-3.5 inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-full bg-stone-900 px-4 text-xs font-bold text-white transition-colors hover:bg-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                        @click="$emit('view')"
                    >
                        View event
                        <ArrowRight class="size-3.5" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </div>
    </article>
</template>
