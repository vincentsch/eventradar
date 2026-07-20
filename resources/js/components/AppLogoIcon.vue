<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- EventRadar mark; inverts with the admin dark mode. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 36 36"
        :class="className"
        v-bind="$attrs"
    >
        <path
            d="M18 2 A16 16 0 0 1 34 18 A16 16 0 0 1 18 34 H6 A4 4 0 0 1 2 30 V18 A16 16 0 0 1 18 2 Z"
            class="fill-stone-900 dark:fill-stone-100"
        />
        <circle
            cx="18"
            cy="18"
            r="10.5"
            fill="none"
            stroke-width="1.8"
            class="stroke-white/90 dark:stroke-stone-900/90"
        />
        <circle
            cx="18"
            cy="18"
            r="5.75"
            fill="none"
            stroke-width="1.5"
            class="stroke-white/40 dark:stroke-stone-900/40"
        />
        <path
            d="M18 18 L18 7.5 A10.5 10.5 0 0 1 25.42 10.58 Z"
            class="fill-orange-500/40"
        />
        <path
            d="M18 18 L25.42 10.58"
            stroke-width="2"
            stroke-linecap="round"
            class="stroke-orange-500"
        />
        <circle
            cx="18"
            cy="18"
            r="1.9"
            class="fill-white dark:fill-stone-900"
        />
    </svg>
</template>
