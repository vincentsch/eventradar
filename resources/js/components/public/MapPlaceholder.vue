<script setup lang="ts">
import { ArrowRight, Minus, Plus, Search } from '@lucide/vue';
import { computed } from 'vue';
import { eventWeekday } from '@/components/public/publicEventDisplay';
import type { PublicEventVisualFixture } from '@/types/public-events';

const props = defineProps<{
    events: PublicEventVisualFixture[];
    selectedId: string;
}>();

defineEmits<{
    select: [id: string];
    view: [id: string];
}>();

/**
 * Static equirectangular projection of the fixture coordinates onto the
 * placeholder canvas (x = (lng + 180) / 360, y = (90 - lat) / 180). Real
 * positioning moves to Mapbox when it mounts into [data-map-canvas].
 */
const pinPositions: Record<string, string> = {
    'fixture-concert-berlin': 'left-[54%] top-[21%]',
    'fixture-exhibition-lisbon': 'left-[47%] top-[29%]',
    'fixture-workshop-tokyo': 'left-[89%] top-[30%]',
    'fixture-festival-melbourne': 'left-[90%] top-[68%]',
    'fixture-conference-mexico-city': 'left-[22%] top-[39%]',
    'fixture-sports-new-york': 'left-[29%] top-[26%]',
};

const pinPosition = (id: string) => pinPositions[id] ?? 'left-1/2 top-1/2';

// Decorative stand-ins for the clustered markers the live map will render.
const clusterPlaceholders = [
    { count: 4, position: 'left-[64%] top-[46%]' },
    { count: 3, position: 'left-[35%] top-[58%]' },
];

const selectedEvent = computed(() =>
    props.events.find((event) => event.id === props.selectedId),
);
</script>

<template>
    <div class="relative h-full w-full overflow-hidden bg-[#c9d3cc]">
        <div
            data-map-canvas
            role="group"
            aria-label="Map preview of event locations"
            class="absolute inset-0"
        >
            <div aria-hidden="true" class="absolute inset-0">
                <div
                    class="absolute top-[12%] left-[6%] h-56 w-44 -rotate-12 rounded-full bg-stone-100/70 blur-xs"
                ></div>
                <div
                    class="absolute top-[48%] left-[20%] h-52 w-32 rotate-12 rounded-full bg-stone-100/60 blur-xs"
                ></div>
                <div
                    class="absolute top-[8%] left-[44%] h-40 w-40 rotate-6 rounded-full bg-stone-100/70 blur-xs"
                ></div>
                <div
                    class="absolute top-[36%] left-[46%] h-64 w-48 rounded-full bg-stone-100/60 blur-xs"
                ></div>
                <div
                    class="absolute top-[10%] left-[68%] h-48 w-72 rotate-3 rounded-full bg-stone-100/70 blur-xs"
                ></div>
                <div
                    class="absolute top-[60%] left-[80%] h-28 w-40 rounded-full bg-stone-100/60 blur-xs"
                ></div>
                <div
                    class="absolute top-[30%] left-[30%] size-24 rounded-full bg-lime-600/15 blur-xs"
                ></div>
                <div
                    class="absolute top-[42%] left-[74%] h-20 w-28 rounded-full bg-lime-600/15 blur-xs"
                ></div>
                <div class="absolute inset-x-0 top-1/4 h-px bg-white/50"></div>
                <div class="absolute inset-x-0 top-2/4 h-px bg-white/40"></div>
                <div class="absolute inset-x-0 top-3/4 h-px bg-white/50"></div>
                <div class="absolute inset-y-0 left-1/4 w-px bg-white/50"></div>
                <div class="absolute inset-y-0 left-2/4 w-px bg-white/40"></div>
                <div class="absolute inset-y-0 left-3/4 w-px bg-white/50"></div>
                <div
                    class="absolute inset-0 bg-gradient-to-b from-white/10 via-transparent to-stone-900/10"
                ></div>
            </div>

            <button
                v-for="event in events"
                :key="event.id"
                type="button"
                :aria-pressed="selectedId === event.id"
                :aria-label="`${event.title}, ${event.locationLabel}`"
                class="absolute grid size-9 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border-[3px] border-white shadow-md shadow-stone-900/25 transition duration-200 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none motion-safe:hover:scale-110"
                :class="[
                    pinPosition(event.id),
                    selectedId === event.id
                        ? 'z-10 scale-110 bg-orange-600'
                        : 'bg-blue-600',
                ]"
                @click="$emit('select', event.id)"
            >
                <span
                    class="size-2 rounded-full bg-white"
                    aria-hidden="true"
                ></span>
            </button>

            <span
                v-for="cluster in clusterPlaceholders"
                :key="cluster.position"
                aria-hidden="true"
                class="absolute grid size-11 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border-[3px] border-white bg-orange-600 text-sm font-black text-white shadow-md shadow-stone-900/25"
                :class="cluster.position"
            >
                {{ cluster.count }}
            </span>
        </div>

        <button
            type="button"
            class="absolute top-4 left-1/2 inline-flex h-10 -translate-x-1/2 items-center gap-2 rounded-full bg-stone-900 px-4 text-xs font-bold whitespace-nowrap text-white shadow-lg shadow-stone-900/30 transition-colors hover:bg-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
        >
            <Search class="size-3.5" aria-hidden="true" />
            Search this area
        </button>

        <div class="absolute top-4 right-4 flex flex-col gap-2">
            <button
                type="button"
                aria-label="Zoom in"
                class="grid size-10 place-items-center rounded-full bg-[#fffdf8] text-stone-900 shadow-md ring-1 shadow-stone-900/15 ring-stone-900/10 transition-colors hover:bg-white focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
            >
                <Plus class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                aria-label="Zoom out"
                class="grid size-10 place-items-center rounded-full bg-[#fffdf8] text-stone-900 shadow-md ring-1 shadow-stone-900/15 ring-stone-900/10 transition-colors hover:bg-white focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
            >
                <Minus class="size-4" aria-hidden="true" />
            </button>
        </div>

        <div
            v-if="selectedEvent"
            class="absolute inset-x-3 bottom-9 flex items-center gap-3 rounded-2xl bg-[#fffdf8]/95 p-2.5 shadow-xl ring-1 shadow-stone-900/20 ring-stone-900/10 backdrop-blur-sm lg:hidden"
        >
            <img
                :src="selectedEvent.image.src"
                alt=""
                class="size-14 shrink-0 rounded-xl object-cover"
            />
            <div class="min-w-0 flex-1">
                <p
                    class="truncate text-[10px] font-black tracking-widest text-orange-700 uppercase"
                >
                    {{ eventWeekday(selectedEvent) }}
                    {{ selectedEvent.dateLabel }} ·
                    {{ selectedEvent.timeLabel }}
                    {{ selectedEvent.timezoneLabel }}
                </p>
                <p class="truncate text-sm font-bold text-stone-900">
                    {{ selectedEvent.title }}
                </p>
                <p class="truncate text-xs text-stone-600">
                    {{ selectedEvent.venue }} ·
                    {{ selectedEvent.locationLabel }}
                </p>
            </div>
            <button
                type="button"
                :aria-label="`View ${selectedEvent.title}`"
                class="grid size-10 shrink-0 cursor-pointer place-items-center rounded-full bg-stone-900 text-white transition-colors hover:bg-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                @click="$emit('view', selectedEvent.id)"
            >
                <ArrowRight class="size-4" aria-hidden="true" />
            </button>
        </div>
    </div>
</template>
