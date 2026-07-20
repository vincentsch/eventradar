<script setup lang="ts">
import { Check } from '@lucide/vue';

defineProps<{
    modelValue: boolean;
}>();

defineEmits<{
    'update:modelValue': [value: boolean];
}>();
</script>

<template>
    <label
        class="inline-flex min-h-9 cursor-pointer items-center gap-2 px-2 text-xs font-bold text-stone-700"
    >
        <span class="relative grid size-4 shrink-0 place-items-center">
            <input
                :checked="modelValue"
                type="checkbox"
                class="peer absolute inset-0 size-4 cursor-pointer appearance-none rounded-[0.3rem] border border-stone-400 bg-white checked:border-stone-900 checked:bg-stone-900 focus:outline-none"
                @change="
                    $emit(
                        'update:modelValue',
                        ($event.target as HTMLInputElement).checked,
                    )
                "
            />
            <Check
                class="pointer-events-none relative z-10 size-3 text-white opacity-0 peer-checked:opacity-100"
                :stroke-width="3"
                aria-hidden="true"
            />
        </span>
        Upcoming only
    </label>
</template>
