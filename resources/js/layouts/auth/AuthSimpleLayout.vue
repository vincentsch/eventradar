<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PublicBrandMark from '@/components/public/PublicBrandMark.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center overflow-x-clip bg-[#f4f0e8] p-6 text-stone-900 antialiased selection:bg-lime-200 selection:text-stone-900 md:p-10"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute top-16 left-1/4 -z-10 h-72 w-72 rounded-full bg-orange-400/15 blur-3xl"
        ></div>

        <div class="w-full max-w-sm">
            <Link
                :href="home()"
                aria-label="EventRadar home"
                class="group mx-auto flex w-fit rounded-full focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-4 focus-visible:outline-none"
            >
                <PublicBrandMark
                    class="size-12 transition-transform duration-300 motion-safe:group-hover:scale-105"
                />
            </Link>

            <h1
                class="mt-5 text-center text-2xl font-extrabold tracking-tight text-stone-900"
            >
                {{ title }}
            </h1>
            <p class="mt-2 text-center text-sm leading-relaxed text-stone-600">
                {{ description }}
            </p>

            <div
                class="mt-7 flex flex-col gap-6 rounded-3xl border border-stone-900/10 bg-[#fffdf8] p-6 shadow-lg shadow-stone-900/5 sm:p-7"
            >
                <slot />
            </div>

            <p class="mt-6 text-center">
                <Link
                    href="/"
                    class="rounded-full text-xs font-bold text-stone-500 transition-colors hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    Back to event discovery
                </Link>
            </p>
        </div>
    </div>
</template>
