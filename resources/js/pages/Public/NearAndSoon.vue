<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarRange,
    ChevronDown,
    MapPin,
    Search,
    SlidersHorizontal,
    Tag,
} from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import EventAgendaItem from '@/components/public/EventAgendaItem.vue';
import EventDateRangePicker from '@/components/public/EventDateRangePicker.vue';
import EventDetailModal from '@/components/public/EventDetailModal.vue';
import FilterSelect from '@/components/public/FilterSelect.vue';
import MapPlaceholder from '@/components/public/MapPlaceholder.vue';
import {
    categoryFilterOptions,
    customDateValue,
    eventDateParts,
    eventWeekday,
    locationFilterOptions,
} from '@/components/public/publicEventDisplay';
import { useCustomDateRange } from '@/components/public/useCustomDateRange';
import ViewNavigation from '@/components/public/ViewNavigation.vue';
import { publicEventFixtures } from '@/fixtures/public-events';
import type { PublicEventVisualFixture } from '@/types/public-events';

const events = publicEventFixtures;

// Presentation-only state: selection stays in sync between the agenda and
// the map placeholder; filtering and viewport search arrive with the
// backend integration.
const selectedId = ref(events[0]?.id ?? '');
const filtersOpen = ref(false);
const searchTerm = ref('');
const locationChoice = ref(locationFilterOptions[0]);
const categoryChoice = ref(categoryFilterOptions[0]);

const {
    dateChoice,
    dateRange,
    rangePanelOpen,
    customRangeLabel,
    dateOptions,
    rangeHint,
    openRangePanel,
    dismissRangePanel,
    clearDateRange,
} = useCustomDateRange('near-soon-date');

const detailEvent = ref<PublicEventVisualFixture | null>(null);

function openDetail(id: string) {
    detailEvent.value = events.find((event) => event.id === id) ?? null;
}

function selectEvent(id: string) {
    selectedId.value = selectedId.value === id ? '' : id;
}

function selectFromMap(id: string) {
    selectEvent(id);

    if (selectedId.value !== id) {
        return;
    }

    // Only the desktop agenda pane scrolls independently; on mobile the
    // docked summary card already reflects the selected pin.
    if (!window.matchMedia('(min-width: 1024px)').matches) {
        return;
    }

    const behavior = window.matchMedia('(prefers-reduced-motion: reduce)')
        .matches
        ? 'auto'
        : 'smooth';

    void nextTick(() => {
        document
            .getElementById(`agenda-event-${id}`)
            ?.scrollIntoView({ block: 'center', behavior });
    });
}

interface AgendaDay {
    key: string;
    weekday: string;
    day: string;
    month: string;
    events: PublicEventVisualFixture[];
}

const agendaDays = computed<AgendaDay[]>(() => {
    const days: AgendaDay[] = [];

    for (const event of events) {
        const currentDay = days[days.length - 1];

        if (currentDay && currentDay.key === event.dateLabel) {
            currentDay.events.push(event);
        } else {
            days.push({
                key: event.dateLabel,
                weekday: eventWeekday(event),
                ...eventDateParts(event),
                events: [event],
            });
        }
    }

    return days;
});

const cityCount = computed(
    () => new Set(events.map((event) => event.locationLabel)).size,
);
</script>

<template>
    <Head title="Near & soon" />

    <section
        class="flex flex-col-reverse lg:grid lg:h-[calc(100vh-4rem)] lg:grid-cols-[minmax(24rem,28rem)_minmax(0,1fr)] xl:grid-cols-[minmax(26rem,31rem)_minmax(0,1fr)]"
    >
        <aside
            class="relative z-10 -mt-5 rounded-t-3xl bg-[#f4f0e8] ring-1 ring-stone-900/5 lg:mt-0 lg:overflow-y-auto lg:rounded-none lg:border-r lg:border-stone-900/10 lg:ring-0"
        >
            <div
                class="mx-auto w-full max-w-2xl px-4 pt-3 pb-16 sm:px-6 lg:max-w-none lg:px-8 lg:pt-8"
            >
                <div
                    aria-hidden="true"
                    class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-stone-900/15 lg:hidden"
                ></div>

                <p
                    class="text-[11px] font-black tracking-widest text-orange-700 uppercase"
                >
                    {{ events.length }} events · {{ cityCount }} cities
                </p>
                <div
                    class="mt-1 flex flex-wrap items-end justify-between gap-3"
                >
                    <h1
                        class="text-3xl font-extrabold tracking-tight text-stone-900 sm:text-4xl"
                    >
                        Near &amp; soon
                    </h1>
                    <ViewNavigation active="map" class="shrink-0" />
                </div>
                <p class="mt-2 max-w-md text-sm leading-relaxed text-stone-600">
                    The week ahead in agenda order, mirrored on the map. Every
                    time shown is the event's own local time.
                </p>

                <div class="mt-5 flex gap-2">
                    <div class="relative min-w-0 flex-1">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-stone-500"
                            aria-hidden="true"
                        />
                        <label for="near-soon-search" class="sr-only">
                            Search events
                        </label>
                        <input
                            id="near-soon-search"
                            v-model="searchTerm"
                            type="search"
                            placeholder="Search this week"
                            class="h-12 w-full rounded-xl bg-white pr-4 pl-10 text-sm font-medium text-stone-900 shadow-sm ring-1 shadow-stone-900/5 ring-stone-900/10 placeholder:text-stone-500 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                        />
                    </div>
                    <button
                        type="button"
                        :aria-expanded="filtersOpen"
                        class="inline-flex h-12 shrink-0 items-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-stone-800 shadow-sm ring-1 shadow-stone-900/5 ring-stone-900/10 transition-colors hover:text-stone-950 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                        @click="filtersOpen = !filtersOpen"
                    >
                        <SlidersHorizontal class="size-4" aria-hidden="true" />
                        <span class="hidden sm:inline">Filters</span>
                        <ChevronDown
                            class="size-4 text-stone-500 transition-transform motion-safe:duration-200"
                            :class="filtersOpen ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                    </button>
                </div>

                <div
                    v-if="filtersOpen"
                    class="mt-2 grid gap-2 rounded-2xl border border-stone-900/10 bg-[#fffdf8] p-2 sm:grid-cols-2"
                >
                    <FilterSelect
                        id="near-soon-date"
                        v-model="dateChoice"
                        label="Date"
                        :icon="CalendarDays"
                        :options="dateOptions"
                        class="sm:col-span-2"
                    />
                    <FilterSelect
                        id="near-soon-location"
                        v-model="locationChoice"
                        label="Location"
                        :icon="MapPin"
                        :options="locationFilterOptions"
                    />
                    <FilterSelect
                        id="near-soon-category"
                        v-model="categoryChoice"
                        label="Category"
                        :icon="Tag"
                        :options="categoryFilterOptions"
                    />
                    <div
                        v-if="rangePanelOpen"
                        role="group"
                        aria-label="Custom date range"
                        class="rounded-xl bg-[#f4f0e8]/70 p-3 sm:col-span-2"
                        @keydown.escape="dismissRangePanel"
                    >
                        <EventDateRangePicker
                            v-model="dateRange"
                            class="mx-auto w-fit"
                        />
                        <div
                            class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-stone-900/10 pt-3"
                        >
                            <p class="text-xs font-semibold text-stone-600">
                                {{ rangeHint }}
                            </p>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center rounded-full px-3.5 text-xs font-bold text-stone-600 transition-colors hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                                @click="clearDateRange"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                    <div
                        v-else-if="dateChoice === customDateValue"
                        class="flex flex-wrap items-center gap-x-3 gap-y-1 px-1.5 pb-1 text-xs text-stone-600 sm:col-span-2"
                    >
                        <CalendarRange
                            class="size-3.5 text-stone-500"
                            aria-hidden="true"
                        />
                        <span class="font-bold text-stone-900">
                            {{ customRangeLabel }}
                        </span>
                        <button
                            type="button"
                            class="rounded-full font-bold text-blue-700 underline-offset-2 transition-colors hover:text-blue-900 hover:underline focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                            @click="openRangePanel"
                        >
                            Edit dates
                        </button>
                        <button
                            type="button"
                            class="rounded-full font-bold text-stone-500 underline-offset-2 transition-colors hover:text-stone-900 hover:underline focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                            @click="clearDateRange"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <div class="mt-6">
                    <section
                        v-for="day in agendaDays"
                        :key="day.key"
                        class="grid grid-cols-[3.25rem_minmax(0,1fr)] gap-3 border-t border-stone-900/10 py-4"
                    >
                        <h2 class="pt-1">
                            <span
                                class="block text-[10px] font-black tracking-widest text-stone-500 uppercase"
                            >
                                {{ day.weekday }}
                            </span>
                            <span
                                class="block text-2xl leading-none font-extrabold tracking-tight text-stone-900"
                            >
                                {{ day.day }}
                            </span>
                            <span
                                class="block text-[10px] font-bold tracking-widest text-stone-400 uppercase"
                            >
                                {{ day.month }}
                            </span>
                        </h2>
                        <ul class="space-y-2">
                            <li
                                v-for="event in day.events"
                                :id="`agenda-event-${event.id}`"
                                :key="event.id"
                                class="scroll-my-2"
                            >
                                <EventAgendaItem
                                    :event="event"
                                    :selected="selectedId === event.id"
                                    @select="selectEvent(event.id)"
                                    @view="detailEvent = event"
                                />
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </aside>

        <div class="relative h-[55dvh] min-h-88 lg:h-auto">
            <MapPlaceholder
                :events="events"
                :selected-id="selectedId"
                @select="selectFromMap"
                @view="openDetail"
            />
        </div>

        <EventDetailModal :event="detailEvent" @close="detailEvent = null" />
    </section>
</template>
