<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarRange,
    ChevronDown,
    LoaderCircle,
    MapPin,
    Search,
    SlidersHorizontal,
    Tag,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import EventAgendaItem from '@/components/public/EventAgendaItem.vue';
import EventDateRangePicker from '@/components/public/EventDateRangePicker.vue';
import EventDetailModal from '@/components/public/EventDetailModal.vue';
import EventMap from '@/components/public/EventMap.vue';
import FilterSelect from '@/components/public/FilterSelect.vue';
import MultiSelectFilter from '@/components/public/MultiSelectFilter.vue';
import {
    customDateValue,
    eventDateParts,
    eventWeekday,
} from '@/components/public/publicEventDisplay';
import SelectedFilterPills from '@/components/public/SelectedFilterPills.vue';
import UpcomingOnlyToggle from '@/components/public/UpcomingOnlyToggle.vue';
import { useAutoApplyingEventFilters } from '@/components/public/useAutoApplyingEventFilters';
import { usePublicEventFilters } from '@/components/public/usePublicEventFilters';
import ViewNavigation from '@/components/public/ViewNavigation.vue';
import type {
    MapBounds,
    PublicEvent,
    PublicEventFilterOptions,
    PublicEventParameters,
    PublicEventQuery,
} from '@/types/public-events';

interface NearAndSoonQuery extends PublicEventQuery {
    north: number | null;
    south: number | null;
    east: number | null;
    west: number | null;
}

interface MapDiscoveryState {
    status: 'ready' | 'unavailable';
    providerCount: number;
    totalCountIsCapped: boolean;
    hydratedCount: number;
    processingTimeMs: number;
    limit: number;
}

const props = defineProps<{
    events: PublicEvent[];
    query: NearAndSoonQuery;
    discovery: MapDiscoveryState;
    filters: PublicEventFilterOptions;
}>();

const selectedId = ref(props.events[0]?.id ?? '');
const focusEventId = ref('');
const focusZoom = ref(13.5);
const focusRevision = ref(0);
const filtersOpen = ref(false);
const loading = ref(false);
let latestRequest = 0;
const currentBounds = ref<MapBounds | null>(boundsFromQuery(props.query));
const searchedBounds = ref<MapBounds | null>(boundsFromQuery(props.query));
const initialBounds = boundsFromQuery(props.query);
const {
    searchTerm,
    locationChoices,
    categoryChoices,
    upcomingOnly,
    locationOptions,
    categoryOptions,
    hasClearableFilters,
    parameters,
    reset,
    dateChoice,
    dateRange,
    rangePanelOpen,
    customRangeLabel,
    dateOptions,
    rangeHint,
    openRangePanel,
    dismissRangePanel,
    clearDateRange,
} = usePublicEventFilters(props.query, props.filters, 'near-soon-date');
const detailEvent = ref<PublicEvent | null>(null);

function openDetail(id: string) {
    detailEvent.value = props.events.find((event) => event.id === id) ?? null;
}

function selectEvent(id: string) {
    selectedId.value = id;
    focusMapOnEvent(id, 13.5);
}

function focusMapOnEvent(id: string, zoom: number) {
    focusEventId.value = id;
    focusZoom.value = zoom;
    focusRevision.value += 1;
}

function selectFromMap(id: string) {
    selectEvent(id);

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
    events: PublicEvent[];
}

const agendaDays = computed<AgendaDay[]>(() => {
    const grouped = new Map<string, PublicEvent[]>();

    for (const event of props.events) {
        const dayEvents = grouped.get(event.localDate);

        if (dayEvents) {
            dayEvents.push(event);
        } else {
            grouped.set(event.localDate, [event]);
        }
    }

    return [...grouped.entries()]
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([key, events]) => {
            events.sort(
                (left, right) =>
                    left.timeLabel.localeCompare(right.timeLabel) ||
                    left.id.localeCompare(right.id),
            );

            return {
                key,
                weekday: eventWeekday(events[0]!),
                ...eventDateParts(events[0]!),
                events,
            };
        });
});
const formattedTotalCount = computed(() =>
    new Intl.NumberFormat('en-US').format(props.discovery.providerCount),
);

watch(
    () => props.events,
    (events) => {
        if (!events.some((event) => event.id === selectedId.value)) {
            selectedId.value = events[0]?.id ?? '';
        }

        if (
            detailEvent.value &&
            !events.some((event) => event.id === detailEvent.value?.id)
        ) {
            detailEvent.value = null;
        }
    },
);

watch(
    () => props.query,
    (query) => {
        searchedBounds.value = boundsFromQuery(query);
    },
);

function applyFilters() {
    requestEvents(searchedBounds.value, parameters(), 'focus-results');
}

const { applyNow, cancelPendingSearch, resetWithoutApplying } =
    useAutoApplyingEventFilters({
        searchTerm,
        locationChoices,
        categoryChoices,
        upcomingOnly,
        dateChoice,
        dateRange,
        parameters,
        apply: applyFilters,
    });

function clearFilters() {
    resetWithoutApplying(reset);
    requestEvents(searchedBounds.value, {}, 'focus-results');
}

type RequestCameraBehavior = 'focus-results' | 'preserve';

function requestEvents(
    bounds: MapBounds | null,
    filterParameters: PublicEventParameters = parameters(),
    cameraBehavior: RequestCameraBehavior = 'focus-results',
) {
    cancelPendingSearch();
    const request = ++latestRequest;
    router.get(
        '/events-visual-2',
        { ...filterParameters, ...(bounds ?? {}) },
        {
            only: ['events', 'query', 'discovery'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onStart: () => {
                if (request === latestRequest) {
                    loading.value = true;
                }
            },
            onSuccess: async () => {
                await nextTick();

                if (
                    request !== latestRequest ||
                    cameraBehavior !== 'focus-results'
                ) {
                    return;
                }

                const firstLocatedEvent = props.events.find(
                    (event) =>
                        event.latitude !== null && event.longitude !== null,
                );

                if (firstLocatedEvent) {
                    selectedId.value = firstLocatedEvent.id;
                    focusMapOnEvent(firstLocatedEvent.id, 10.5);
                }
            },
            onFinish: () => {
                if (request === latestRequest) {
                    loading.value = false;
                }
            },
        },
    );
}

function boundsFromQuery(query: NearAndSoonQuery): MapBounds | null {
    if (
        query.north === null ||
        query.south === null ||
        query.east === null ||
        query.west === null
    ) {
        return null;
    }

    return {
        north: query.north,
        south: query.south,
        east: query.east,
        west: query.west,
    };
}
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
                    {{ events.length }} of {{ formattedTotalCount
                    }}{{ discovery.totalCountIsCapped ? '+' : '' }} events shown
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

                <form
                    role="search"
                    aria-label="Search map events"
                    :aria-busy="loading"
                    @submit.prevent="applyNow"
                >
                    <p role="status" aria-live="polite" class="sr-only">
                        {{ loading ? 'Updating map events' : '' }}
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
                                placeholder="Search this area"
                                class="h-12 w-full rounded-xl bg-white pr-10 pl-10 text-sm font-medium text-stone-900 shadow-sm ring-1 shadow-stone-900/5 ring-stone-900/10 placeholder:text-stone-500 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                            />
                            <LoaderCircle
                                v-if="loading"
                                class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 animate-spin text-stone-500 motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        </div>
                        <button
                            type="button"
                            aria-label="Filters"
                            :aria-expanded="filtersOpen"
                            class="inline-flex h-12 shrink-0 items-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-stone-800 shadow-sm ring-1 shadow-stone-900/5 ring-stone-900/10 transition-colors hover:text-stone-950 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                            @click="filtersOpen = !filtersOpen"
                        >
                            <SlidersHorizontal
                                class="size-4"
                                aria-hidden="true"
                            />
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
                        <MultiSelectFilter
                            id="near-soon-location"
                            v-model="locationChoices"
                            label="Locations"
                            empty-label="Anywhere"
                            :icon="MapPin"
                            :options="locationOptions"
                            surface="white"
                        />
                        <MultiSelectFilter
                            id="near-soon-category"
                            v-model="categoryChoices"
                            label="Categories"
                            empty-label="All categories"
                            :icon="Tag"
                            :options="categoryOptions"
                            surface="white"
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
                                    class="inline-flex h-9 cursor-pointer items-center rounded-full px-3.5 text-xs font-bold text-stone-600 transition-colors hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
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
                                class="cursor-pointer rounded-full font-bold text-blue-700 underline-offset-2 transition-colors hover:text-blue-900 hover:underline focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                                @click="openRangePanel"
                            >
                                Edit dates
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded-full font-bold text-stone-500 underline-offset-2 transition-colors hover:text-stone-900 hover:underline focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                                @click="clearDateRange"
                            >
                                Clear
                            </button>
                        </div>
                        <div
                            data-filter-selection-bar
                            class="flex flex-wrap items-center justify-between gap-2 border-t border-stone-900/10 pt-2 sm:col-span-2"
                        >
                            <SelectedFilterPills
                                v-model:locations="locationChoices"
                                v-model:categories="categoryChoices"
                                :location-options="locationOptions"
                                :category-options="categoryOptions"
                            />
                            <div
                                class="ml-auto flex flex-wrap items-center justify-end gap-1"
                            >
                                <UpcomingOnlyToggle v-model="upcomingOnly" />
                                <button
                                    v-if="hasClearableFilters"
                                    type="button"
                                    class="h-9 rounded-full px-4 text-xs font-bold text-stone-600 transition-colors hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                                    @click="clearFilters"
                                >
                                    Clear filters
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="mt-6">
                    <div
                        v-if="events.length === 0"
                        class="rounded-2xl border border-stone-900/10 bg-white/60 p-6 text-center"
                    >
                        <MapPin
                            class="mx-auto size-6 text-stone-500"
                            aria-hidden="true"
                        />
                        <h2 class="mt-3 text-base font-bold text-stone-900">
                            No events in this area
                        </h2>
                        <p class="mt-1 text-sm text-stone-600">
                            Zoom out, move the map, or broaden your filters.
                        </p>
                    </div>
                    <section
                        v-for="day in agendaDays"
                        :key="day.key"
                        :data-agenda-day="day.key"
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
                                :data-agenda-time="event.timeLabel"
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
            <EventMap
                :events="events"
                :selected-id="selectedId"
                :focus-event-id="focusEventId"
                :focus-zoom="focusZoom"
                :focus-revision="focusRevision"
                :initial-bounds="initialBounds"
                :loading="loading"
                :unavailable="discovery.status === 'unavailable'"
                @select="selectFromMap"
                @view="openDetail"
                @bounds-change="currentBounds = $event"
                @search="requestEvents($event, parameters(), 'preserve')"
            />
        </div>

        <EventDetailModal :event="detailEvent" @close="detailEvent = null" />
    </section>
</template>
