<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link
        href="/"
        aria-label="EventRadar home"
        class="group inline-flex shrink-0 items-center rounded-full focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-4 focus-visible:outline-none"
    >
        <!-- One-piece logo: radar mark plus wordmark. The square mobile width
             with an xMin slice crops the lockup down to just the mark. -->
        <svg
            viewBox="0 0 154 36"
            preserveAspectRatio="xMinYMid slice"
            aria-hidden="true"
            class="h-9 w-9 min-[400px]:w-auto"
        >
            <path
                d="M18 2 A16 16 0 0 1 34 18 A16 16 0 0 1 18 34 H6 A4 4 0 0 1 2 30 V18 A16 16 0 0 1 18 2 Z"
                class="fill-stone-900"
            />
            <circle
                cx="18"
                cy="18"
                r="10.5"
                fill="none"
                stroke-width="1.8"
                class="stroke-white/90"
            />
            <circle
                cx="18"
                cy="18"
                r="5.75"
                fill="none"
                stroke-width="1.5"
                class="stroke-white/40"
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
            <circle cx="18" cy="18" r="1.9" class="fill-white" />
            <text
                x="43"
                y="18.5"
                dominant-baseline="central"
                class="font-sans text-[19px] font-extrabold tracking-tight"
            >
                <tspan class="fill-stone-900">Event</tspan>
                <tspan class="fill-orange-600">Radar</tspan>
            </text>
        </svg>
    </Link>
</template>
