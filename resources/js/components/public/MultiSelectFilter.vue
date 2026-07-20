<script setup lang="ts">
import { Check, ChevronDown, Search } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import type { PublicEventFilterOption } from '@/types/public-events';

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        emptyLabel: string;
        icon: Component;
        options: PublicEventFilterOption[];
        modelValue: string[];
        surface?: 'muted' | 'white';
    }>(),
    { surface: 'muted' },
);

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();
const open = ref(false);
const selectedOptions = computed(() =>
    props.modelValue.map(
        (value) =>
            props.options.find((option) => option.value === value) ?? {
                value,
                label: value,
            },
    ),
);
const summary = computed(() => {
    if (selectedOptions.value.length === 0) {
        return props.emptyLabel;
    }

    if (selectedOptions.value.length === 1) {
        return selectedOptions.value[0]!.label;
    }

    return `${selectedOptions.value.length} ${props.label.toLowerCase()}`;
});
const triggerSurface = computed(() =>
    props.surface === 'white'
        ? 'bg-white shadow-sm ring-1 shadow-stone-900/5 ring-stone-900/10'
        : 'bg-[#f4f0e8]',
);

function update(value: string[] | string): void {
    emit('update:modelValue', Array.isArray(value) ? value : [value]);
}
</script>

<template>
    <div class="min-w-0">
        <ComboboxRoot
            v-model:open="open"
            :model-value="modelValue"
            multiple
            :reset-search-term-on-select="true"
            @update:model-value="update"
        >
            <ComboboxAnchor as-child>
                <ComboboxTrigger
                    :id="id"
                    class="relative flex h-12 w-full min-w-0 cursor-pointer items-center rounded-xl pr-9 pl-10 text-left text-sm font-bold text-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                    :class="triggerSurface"
                    :aria-label="`${label}: ${summary}`"
                >
                    <component
                        :is="icon"
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-stone-500"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ summary }}</span>
                    <ChevronDown
                        class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-stone-500 transition-transform motion-safe:duration-150"
                        :class="open ? 'rotate-180' : ''"
                        aria-hidden="true"
                    />
                </ComboboxTrigger>
            </ComboboxAnchor>

            <ComboboxPortal>
                <ComboboxContent
                    position="popper"
                    align="start"
                    :side-offset="6"
                    class="z-50 min-w-[var(--reka-combobox-trigger-width)] overflow-hidden rounded-xl border border-stone-900/10 bg-[#fffdf8] shadow-xl shadow-stone-900/15"
                >
                    <div class="relative border-b border-stone-900/10 p-2">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-5 size-4 -translate-y-1/2 text-stone-500"
                            aria-hidden="true"
                        />
                        <ComboboxInput
                            class="h-10 w-full rounded-lg bg-[#f4f0e8] pr-3 pl-9 text-sm font-medium text-stone-900 placeholder:text-stone-500 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                            :placeholder="`Search ${label.toLowerCase()}`"
                            :aria-label="`Search ${label.toLowerCase()}`"
                        />
                    </div>
                    <ComboboxViewport class="max-h-64 overflow-y-auto p-1.5">
                        <ComboboxEmpty
                            class="px-3 py-6 text-center text-sm text-stone-500"
                        >
                            No {{ label.toLowerCase() }} found
                        </ComboboxEmpty>
                        <ComboboxItem
                            v-for="option in options"
                            :key="option.value"
                            :value="option.value"
                            :text-value="option.label"
                            class="relative flex cursor-pointer items-center rounded-lg py-2.5 pr-3 pl-9 text-sm font-semibold text-stone-700 outline-none data-[highlighted]:bg-stone-900/8 data-[highlighted]:text-stone-950"
                        >
                            <ComboboxItemIndicator
                                class="absolute left-3 inline-flex size-4 items-center justify-center text-blue-700"
                            >
                                <Check class="size-4" aria-hidden="true" />
                            </ComboboxItemIndicator>
                            {{ option.label }}
                        </ComboboxItem>
                    </ComboboxViewport>
                </ComboboxContent>
            </ComboboxPortal>
        </ComboboxRoot>
    </div>
</template>
