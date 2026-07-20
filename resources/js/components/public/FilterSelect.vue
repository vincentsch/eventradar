<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';

interface FilterOption {
    value: string;
    label: string;
}

const props = defineProps<{
    id: string;
    label: string;
    icon: Component;
    options: Array<string | FilterOption>;
    modelValue: string;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();

const normalizedOptions = computed<FilterOption[]>(() =>
    props.options.map((option) =>
        typeof option === 'string' ? { value: option, label: option } : option,
    ),
);
</script>

<template>
    <div class="relative">
        <label :for="id" class="sr-only">{{ label }}</label>
        <component
            :is="icon"
            class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-stone-500"
            aria-hidden="true"
        />
        <select
            :id="id"
            :value="modelValue"
            class="h-12 w-full cursor-pointer appearance-none rounded-xl bg-[#f4f0e8] pr-9 pl-10 text-sm font-bold text-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
            @change="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLSelectElement).value,
                )
            "
        >
            <option
                v-for="option in normalizedOptions"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
        <ChevronDown
            class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-stone-500"
            aria-hidden="true"
        />
    </div>
</template>
