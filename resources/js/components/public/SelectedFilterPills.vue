<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed } from 'vue';
import type { PublicEventFilterOption } from '@/types/public-events';

const props = defineProps<{
    locationOptions: PublicEventFilterOption[];
    categoryOptions: PublicEventFilterOption[];
}>();
const locations = defineModel<string[]>('locations', { required: true });
const categories = defineModel<string[]>('categories', { required: true });

const selections = computed(() => [
    ...locations.value.map((value) => ({
        key: `location:${value}`,
        value,
        group: 'location' as const,
        label: labelFor(props.locationOptions, value),
    })),
    ...categories.value.map((value) => ({
        key: `category:${value}`,
        value,
        group: 'category' as const,
        label: labelFor(props.categoryOptions, value),
    })),
]);

function labelFor(options: PublicEventFilterOption[], value: string): string {
    return options.find((option) => option.value === value)?.label ?? value;
}

function remove(group: 'location' | 'category', value: string): void {
    if (group === 'location') {
        locations.value = locations.value.filter((item) => item !== value);

        return;
    }

    categories.value = categories.value.filter((item) => item !== value);
}
</script>

<template>
    <ul
        v-if="selections.length"
        data-filter-selection-list
        aria-label="Selected filters"
        class="flex min-w-0 flex-wrap items-center gap-1.5"
    >
        <li v-for="selection in selections" :key="selection.key">
            <button
                type="button"
                class="inline-flex h-7 max-w-full items-center gap-1 rounded-full bg-stone-900 px-2.5 text-[11px] font-bold text-white transition-colors hover:bg-stone-700 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-1 focus-visible:outline-none"
                :aria-label="`Remove ${selection.label}`"
                @click="remove(selection.group, selection.value)"
            >
                <span class="max-w-40 truncate">{{ selection.label }}</span>
                <X class="size-3" aria-hidden="true" />
            </button>
        </li>
    </ul>
</template>
