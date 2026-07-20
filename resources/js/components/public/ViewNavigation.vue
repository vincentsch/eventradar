<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid, Map as MapIcon } from '@lucide/vue';

defineProps<{
    active: 'grid' | 'map';
}>();

const segmentClasses = (current: boolean) => [
    'inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-xs font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2',
    current
        ? 'bg-stone-900 text-white shadow-sm'
        : 'text-stone-600 hover:text-stone-900',
];
</script>

<template>
    <nav
        aria-label="Event views"
        class="inline-flex items-center gap-1 rounded-full border border-stone-900/10 bg-[#fffdf8] p-1 shadow-sm shadow-stone-900/5"
    >
        <Link
            href="/"
            :aria-current="active === 'grid' ? 'page' : undefined"
            :class="segmentClasses(active === 'grid')"
        >
            <LayoutGrid class="size-3.5" aria-hidden="true" />
            Grid
        </Link>
        <Link
            href="/events-visual-2"
            :aria-current="active === 'map' ? 'page' : undefined"
            :class="segmentClasses(active === 'map')"
        >
            <MapIcon class="size-3.5" aria-hidden="true" />
            Map
        </Link>
    </nav>
</template>
