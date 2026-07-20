<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { MapPinned, X } from '@lucide/vue';
import type { Map } from 'mapbox-gl';
import { nextTick, onBeforeUnmount, ref } from 'vue';
import 'mapbox-gl/dist/mapbox-gl.css';

interface LocationResponse {
    venue: string;
    address: string;
    latitude: number;
    longitude: number;
    resolution: 'stored' | 'reverse' | 'coordinates';
    approximate: boolean;
}

const props = defineProps<{
    eventId: string;
}>();

const expanded = ref(false);
const mapElement = ref<HTMLElement | null>(null);
const lookup = useHttp<Record<string, never>, LocationResponse>({});
const error = ref('');
let map: Map | null = null;

async function showLocation() {
    expanded.value = true;
    error.value = '';

    if (!lookup.response) {
        try {
            await lookup.get(`/events/${props.eventId}/location`);
        } catch {
            error.value = 'The map location could not be loaded right now.';

            return;
        }
    }

    await nextTick();

    try {
        await mountMap();
    } catch {
        error.value =
            'The address is available, but the map could not be loaded.';
    }
}

async function mountMap() {
    if (map || !mapElement.value || !lookup.response) {
        return;
    }

    const token = import.meta.env.VITE_MAPBOX_ACCESS_TOKEN;

    if (!token) {
        error.value =
            'The address is available, but the map is not configured.';

        return;
    }

    const { default: mapboxgl } = await import('mapbox-gl');
    mapboxgl.accessToken = token;
    const center: [number, number] = [
        lookup.response.longitude,
        lookup.response.latitude,
    ];

    map = new mapboxgl.Map({
        container: mapElement.value,
        style: 'mapbox://styles/mapbox/streets-v12',
        center,
        zoom: 15,
        attributionControl: false,
    });
    map.addControl(
        new mapboxgl.NavigationControl({ showCompass: false }),
        'top-right',
    );
    new mapboxgl.Marker({ color: '#1c1917' }).setLngLat(center).addTo(map);
}

function hideLocation() {
    expanded.value = false;
    map?.remove();
    map = null;
}

onBeforeUnmount(() => {
    lookup.cancel();
    map?.remove();
});
</script>

<template>
    <div class="mt-3">
        <button
            v-if="!expanded"
            type="button"
            class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-stone-900/15 bg-white px-3 py-1.5 text-xs font-bold text-stone-700 transition-colors hover:bg-stone-100 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
            @click="showLocation"
        >
            <MapPinned class="size-3.5" aria-hidden="true" />
            Show on map
        </button>

        <div
            v-else
            class="overflow-hidden rounded-2xl border border-stone-900/10 bg-[#fffdf8]"
        >
            <div class="flex items-start justify-between gap-4 p-4">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-stone-900">
                        {{ lookup.response?.venue }}
                    </p>
                    <p class="mt-0.5 text-sm leading-relaxed text-stone-600">
                        {{
                            lookup.processing
                                ? 'Finding the address...'
                                : lookup.response?.address
                        }}
                    </p>
                    <p
                        v-if="lookup.response?.approximate"
                        class="mt-1 text-xs leading-relaxed text-stone-500"
                    >
                        Based on the supplied event coordinates and may be
                        approximate.
                    </p>
                    <p
                        v-if="error"
                        class="mt-1 text-xs text-red-700"
                        role="alert"
                    >
                        {{ error }}
                    </p>
                </div>
                <button
                    type="button"
                    aria-label="Hide map"
                    class="grid size-8 shrink-0 cursor-pointer place-items-center rounded-full text-stone-500 transition-colors hover:bg-stone-100 hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                    @click="hideLocation"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>
            <div
                v-if="lookup.response"
                ref="mapElement"
                class="h-52 w-full bg-stone-200"
                aria-label="Event location map"
            ></div>
        </div>
    </div>
</template>
