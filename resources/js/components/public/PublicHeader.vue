<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LogIn } from '@lucide/vue';
import { computed } from 'vue';
import PublicBrand from '@/components/public/PublicBrand.vue';

const page = usePage();

const isDiscover = computed(
    () => page.url === '/' || page.url.startsWith('/events-visual-1'),
);
const isNearAndSoon = computed(() => page.url.startsWith('/events-visual-2'));

const linkClasses = (active: boolean) => [
    'relative rounded-full px-3 py-2 text-sm font-semibold whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-900',
    "after:absolute after:inset-x-3 after:bottom-0.5 after:h-0.5 after:rounded-full after:bg-orange-500 after:transition-opacity after:content-['']",
    active
        ? 'text-stone-900 after:opacity-100'
        : 'text-stone-500 after:opacity-0 hover:text-stone-900',
];
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-stone-900/10 bg-[#f4f0e8]/90 backdrop-blur-md"
    >
        <div
            class="relative mx-auto flex h-16 max-w-screen-2xl items-center px-4 sm:px-6 lg:px-8"
        >
            <PublicBrand />

            <nav
                aria-label="Primary"
                class="ml-auto flex items-center gap-0.5 sm:gap-2 md:absolute md:left-1/2 md:ml-0 md:-translate-x-1/2"
            >
                <Link
                    href="/"
                    :aria-current="isDiscover ? 'page' : undefined"
                    :class="linkClasses(isDiscover)"
                >
                    Discover
                </Link>
                <Link
                    href="/events-visual-2"
                    :aria-current="isNearAndSoon ? 'page' : undefined"
                    :class="linkClasses(isNearAndSoon)"
                >
                    Near &amp; soon
                </Link>
            </nav>

            <Link
                href="/login"
                aria-label="Admin sign in"
                class="ml-1.5 inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border border-stone-900/15 bg-white/60 px-3 text-xs font-bold text-stone-600 transition-colors hover:border-stone-900/30 hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none md:ml-auto"
            >
                <LogIn class="size-3.5" aria-hidden="true" />
                <span class="hidden md:inline">Admin</span>
            </Link>
        </div>
    </header>
</template>
