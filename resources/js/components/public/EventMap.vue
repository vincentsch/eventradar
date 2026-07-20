<script setup lang="ts">
import {
    ArrowRight,
    LoaderCircle,
    MapPin,
    Minus,
    Plus,
    Search,
} from '@lucide/vue';
import mapboxgl from 'mapbox-gl';
import type {
    GeoJSONSource,
    GeoJSONSourceSpecification,
    Map as MapboxMap,
    MapLayerMouseEvent,
} from 'mapbox-gl';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { eventWeekday } from '@/components/public/publicEventDisplay';
import type { MapBounds, PublicEvent } from '@/types/public-events';
import 'mapbox-gl/dist/mapbox-gl.css';

const props = defineProps<{
    events: PublicEvent[];
    selectedId: string;
    initialBounds: MapBounds | null;
    loading: boolean;
    unavailable: boolean;
}>();
const emit = defineEmits<{
    select: [id: string];
    view: [id: string];
    search: [bounds: MapBounds];
    'bounds-change': [bounds: MapBounds];
}>();

const sourceId = 'public-events';
const clusterLayerId = 'event-clusters';
const clusterCountLayerId = 'event-cluster-count';
const eventLayerId = 'event-points';
const selectedLayerId = 'selected-event-point';
const accessToken = String(
    import.meta.env.VITE_MAPBOX_ACCESS_TOKEN ?? '',
).trim();
const mapStyle = String(
    import.meta.env.VITE_MAPBOX_STYLE ?? 'mapbox://styles/mapbox/standard',
);
const mapContainer = ref<HTMLElement | null>(null);
const map = shallowRef<MapboxMap | null>(null);
const mapReady = ref(false);
const setupFailed = ref(false);
const currentBounds = ref<MapBounds | null>(null);
const initialViewApplied = ref(false);
type GeoJsonData = Exclude<
    GeoJSONSourceSpecification['data'],
    string | undefined
>;

const locatedEvents = computed(() =>
    props.events.filter(
        (event) => event.latitude !== null && event.longitude !== null,
    ),
);
const selectedEvent = computed(() =>
    props.events.find((event) => event.id === props.selectedId),
);
const geoJson = computed<GeoJsonData>(() => ({
    type: 'FeatureCollection',
    features: locatedEvents.value.map((event) => ({
        type: 'Feature',
        geometry: {
            type: 'Point',
            coordinates: [event.longitude as number, event.latitude as number],
        },
        properties: { id: event.id, title: event.title },
    })),
}));

onMounted(() => {
    if (accessToken === '' || !mapContainer.value) {
        return;
    }

    mapboxgl.accessToken = accessToken;
    const instance = new mapboxgl.Map({
        container: mapContainer.value,
        style: mapStyle,
        center: [0, 20],
        zoom: 1.4,
        attributionControl: false,
    });
    map.value = instance;
    instance.addControl(
        new mapboxgl.AttributionControl({ compact: true }),
        'bottom-right',
    );
    instance.on('error', () => {
        if (!instance.loaded()) {
            setupFailed.value = true;
        }
    });
    instance.on('load', () => {
        addEventLayers(instance);
        mapReady.value = true;
        setupFailed.value = false;
        applyInitialView(instance);
        updateBounds(instance);
    });
    instance.on('moveend', () => updateBounds(instance));
    instance.on('click', clusterLayerId, (event) =>
        expandCluster(instance, event),
    );
    instance.on('click', eventLayerId, (event) => selectPoint(event));
    instance.on('click', selectedLayerId, (event) => selectPoint(event));

    for (const layer of [clusterLayerId, eventLayerId, selectedLayerId]) {
        instance.on('mouseenter', layer, () => {
            instance.getCanvas().style.cursor = 'pointer';
        });
        instance.on('mouseleave', layer, () => {
            instance.getCanvas().style.cursor = '';
        });
    }
});

onBeforeUnmount(() => {
    map.value?.remove();
    map.value = null;
});

watch(geoJson, (data) => {
    const source = map.value?.getSource(sourceId) as GeoJSONSource | undefined;
    source?.setData(data);
});

watch(
    () => props.selectedId,
    (id) => {
        const instance = map.value;

        if (
            !instance ||
            !mapReady.value ||
            !instance.getLayer(selectedLayerId)
        ) {
            return;
        }

        instance.setFilter(selectedLayerId, [
            '==',
            ['get', 'id'],
            id || '__none__',
        ]);
        instance.setFilter(eventLayerId, [
            'all',
            ['!', ['has', 'point_count']],
            ['!=', ['get', 'id'], id || '__none__'],
        ]);
        const event = props.events.find((candidate) => candidate.id === id);

        if (!event || event.longitude === null || event.latitude === null) {
            return;
        }

        instance.easeTo({
            center: [event.longitude, event.latitude],
            zoom: Math.max(instance.getZoom(), 4),
            duration: prefersReducedMotion() ? 0 : 500,
        });
    },
);

function addEventLayers(instance: MapboxMap): void {
    instance.addSource(sourceId, {
        type: 'geojson',
        data: geoJson.value,
        cluster: true,
        clusterMaxZoom: 12,
        clusterRadius: 46,
    });
    instance.addLayer({
        id: clusterLayerId,
        type: 'circle',
        source: sourceId,
        filter: ['has', 'point_count'],
        paint: {
            'circle-color': '#ea580c',
            'circle-radius': [
                'step',
                ['get', 'point_count'],
                19,
                10,
                23,
                50,
                28,
            ],
            'circle-stroke-color': '#ffffff',
            'circle-stroke-width': 3,
        },
    });
    instance.addLayer({
        id: clusterCountLayerId,
        type: 'symbol',
        source: sourceId,
        filter: ['has', 'point_count'],
        layout: {
            'text-field': ['get', 'point_count_abbreviated'],
            'text-size': 12,
            'text-font': ['DIN Pro Medium', 'Arial Unicode MS Bold'],
        },
        paint: { 'text-color': '#ffffff' },
    });
    instance.addLayer({
        id: eventLayerId,
        type: 'circle',
        source: sourceId,
        filter: [
            'all',
            ['!', ['has', 'point_count']],
            ['!=', ['get', 'id'], props.selectedId || '__none__'],
        ],
        paint: {
            'circle-color': '#2563eb',
            'circle-radius': 8,
            'circle-stroke-color': '#ffffff',
            'circle-stroke-width': 3,
        },
    });
    instance.addLayer({
        id: selectedLayerId,
        type: 'circle',
        source: sourceId,
        filter: ['==', ['get', 'id'], props.selectedId || '__none__'],
        paint: {
            'circle-color': '#ea580c',
            'circle-radius': 11,
            'circle-stroke-color': '#ffffff',
            'circle-stroke-width': 4,
        },
    });
}

function applyInitialView(instance: MapboxMap): void {
    if (initialViewApplied.value) {
        return;
    }

    initialViewApplied.value = true;

    if (props.initialBounds) {
        const east =
            props.initialBounds.west > props.initialBounds.east
                ? props.initialBounds.east + 360
                : props.initialBounds.east;
        instance.fitBounds(
            [
                [props.initialBounds.west, props.initialBounds.south],
                [east, props.initialBounds.north],
            ],
            { padding: 48, duration: 0 },
        );

        return;
    }

    if (locatedEvents.value.length === 0) {
        return;
    }

    const bounds = new mapboxgl.LngLatBounds();

    for (const event of locatedEvents.value) {
        bounds.extend([event.longitude as number, event.latitude as number]);
    }

    instance.fitBounds(bounds, {
        padding: 56,
        maxZoom: locatedEvents.value.length === 1 ? 8 : 5,
        duration: 0,
    });
}

function expandCluster(instance: MapboxMap, event: MapLayerMouseEvent): void {
    const feature = event.features?.[0];
    const clusterId = Number(feature?.properties?.cluster_id);
    const coordinates =
        feature?.geometry.type === 'Point'
            ? feature.geometry.coordinates
            : null;

    if (!Number.isFinite(clusterId) || !coordinates) {
        return;
    }

    const source = instance.getSource(sourceId) as GeoJSONSource;
    source.getClusterExpansionZoom(clusterId, (error, zoom) => {
        if (error || zoom === null || zoom === undefined) {
            return;
        }

        instance.easeTo({
            center: [Number(coordinates[0]), Number(coordinates[1])],
            zoom,
            duration: prefersReducedMotion() ? 0 : 500,
        });
    });
}

function selectPoint(event: MapLayerMouseEvent): void {
    const id = event.features?.[0]?.properties?.id;

    if (typeof id === 'string') {
        emit('select', id);
    }
}

function updateBounds(instance: MapboxMap): void {
    const raw = instance.getBounds();

    if (!raw) {
        return;
    }

    const longitudeSpan = raw.getEast() - raw.getWest();
    const bounds: MapBounds = {
        north: roundCoordinate(raw.getNorth()),
        south: roundCoordinate(raw.getSouth()),
        west:
            longitudeSpan >= 360
                ? -180
                : roundCoordinate(normalizeLongitude(raw.getWest())),
        east:
            longitudeSpan >= 360
                ? 180
                : roundCoordinate(normalizeLongitude(raw.getEast())),
    };
    currentBounds.value = bounds;
    emit('bounds-change', bounds);
}

function normalizeLongitude(value: number): number {
    return ((((value + 180) % 360) + 360) % 360) - 180;
}

function roundCoordinate(value: number): number {
    return Number(value.toFixed(6));
}

function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function searchCurrentArea(): void {
    if (currentBounds.value) {
        emit('search', currentBounds.value);
    }
}
</script>

<template>
    <div class="relative h-full w-full overflow-hidden bg-[#c9d3cc]">
        <div
            v-if="accessToken !== ''"
            data-map-canvas
            role="region"
            aria-label="Interactive map of event locations"
            :aria-hidden="setupFailed ? 'true' : undefined"
            class="absolute inset-0"
        >
            <div ref="mapContainer" class="h-full w-full"></div>
        </div>
        <div
            v-if="accessToken === '' || setupFailed"
            role="status"
            class="absolute inset-0 z-10 grid place-items-center bg-[radial-gradient(circle_at_30%_25%,rgba(255,255,255,0.7),transparent_35%),linear-gradient(145deg,#d9e1db,#b9c8bd)] p-8 text-center"
        >
            <div
                class="max-w-sm rounded-2xl bg-[#fffdf8]/90 p-6 shadow-xl backdrop-blur-sm"
            >
                <MapPin
                    class="mx-auto size-7 text-orange-700"
                    aria-hidden="true"
                />
                <h2 class="mt-3 text-lg font-extrabold text-stone-900">
                    Map unavailable
                </h2>
                <p class="mt-1 text-sm leading-relaxed text-stone-600">
                    The chronological agenda remains fully usable. Configure the
                    public Mapbox token to restore the interactive map.
                </p>
            </div>
        </div>

        <div
            v-if="accessToken !== '' && !mapReady && !setupFailed"
            class="pointer-events-none absolute inset-0 grid place-items-center bg-[#c9d3cc]"
        >
            <p
                class="inline-flex items-center gap-2 rounded-full bg-white/90 px-4 py-2 text-sm font-bold text-stone-700 shadow-md"
            >
                <LoaderCircle
                    class="size-4 animate-spin motion-reduce:animate-none"
                    aria-hidden="true"
                />
                Loading map
            </p>
        </div>

        <button
            v-if="mapReady"
            type="button"
            :disabled="loading || currentBounds === null"
            class="absolute top-4 left-1/2 inline-flex h-10 -translate-x-1/2 items-center gap-2 rounded-full bg-stone-900 px-4 text-xs font-bold whitespace-nowrap text-white shadow-lg shadow-stone-900/30 transition-colors hover:bg-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-60"
            @click="searchCurrentArea"
        >
            <LoaderCircle
                v-if="loading"
                class="size-3.5 animate-spin motion-reduce:animate-none"
                aria-hidden="true"
            />
            <Search v-else class="size-3.5" aria-hidden="true" />
            {{ loading ? 'Searching' : 'Search this area' }}
        </button>

        <div v-if="mapReady" class="absolute top-4 right-4 flex flex-col gap-2">
            <button
                type="button"
                aria-label="Zoom in"
                class="grid size-10 place-items-center rounded-full bg-[#fffdf8] text-stone-900 shadow-md ring-1 shadow-stone-900/15 ring-stone-900/10 transition-colors hover:bg-white focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                @click="
                    map?.zoomIn({ duration: prefersReducedMotion() ? 0 : 300 })
                "
            >
                <Plus class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                aria-label="Zoom out"
                class="grid size-10 place-items-center rounded-full bg-[#fffdf8] text-stone-900 shadow-md ring-1 shadow-stone-900/15 ring-stone-900/10 transition-colors hover:bg-white focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                @click="
                    map?.zoomOut({ duration: prefersReducedMotion() ? 0 : 300 })
                "
            >
                <Minus class="size-4" aria-hidden="true" />
            </button>
        </div>

        <div
            v-if="unavailable"
            role="status"
            class="absolute top-16 left-1/2 w-[min(24rem,calc(100%-2rem))] -translate-x-1/2 rounded-xl bg-orange-50 p-3 text-center text-xs font-semibold text-orange-900 shadow-lg"
        >
            Event search is temporarily unavailable. Move the map or change a
            filter to retry.
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
                class="grid size-10 shrink-0 place-items-center rounded-full bg-stone-900 text-white transition-colors hover:bg-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                @click="$emit('view', selectedEvent.id)"
            >
                <ArrowRight class="size-4" aria-hidden="true" />
            </button>
        </div>
    </div>
</template>
