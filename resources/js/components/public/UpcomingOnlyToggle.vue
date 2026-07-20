<script setup lang="ts">
defineProps<{
    modelValue: boolean;
}>();

defineEmits<{
    'update:modelValue': [value: boolean];
}>();
</script>

<template>
    <label
        class="inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-full px-2 text-xs font-bold text-stone-700 focus-within:ring-2 focus-within:ring-stone-900 focus-within:outline-none"
    >
        <input
            :checked="modelValue"
            type="checkbox"
            class="size-4 rounded border-stone-400 accent-stone-900"
            @change="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).checked,
                )
            "
        />
        Upcoming only
    </label>
</template>
