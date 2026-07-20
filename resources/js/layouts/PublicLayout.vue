<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const isDiscover = computed(
    () => page.url === '/' || page.url.startsWith('/events-visual-1'),
);
const isNearAndSoon = computed(() => page.url.startsWith('/events-visual-2'));
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <a
            href="#public-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:shadow"
        >
            Skip to content
        </a>

        <header class="border-b bg-background">
            <div
                class="mx-auto flex min-h-16 max-w-screen-2xl items-center gap-6 px-4 sm:px-6 lg:px-8"
            >
                <Link href="/" class="font-semibold">Event Visuals</Link>

                <nav
                    aria-label="Public event views"
                    class="ml-auto flex items-center gap-1"
                >
                    <Link
                        href="/"
                        class="rounded-md px-3 py-2 text-sm"
                        :class="
                            isDiscover
                                ? 'bg-muted font-medium text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-current="isDiscover ? 'page' : undefined"
                    >
                        Discover
                    </Link>
                    <Link
                        href="/events-visual-2"
                        class="rounded-md px-3 py-2 text-sm"
                        :class="
                            isNearAndSoon
                                ? 'bg-muted font-medium text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-current="isNearAndSoon ? 'page' : undefined"
                    >
                        Near &amp; soon
                    </Link>
                    <Link
                        href="/login"
                        class="ml-2 rounded-md border px-3 py-2 text-sm text-muted-foreground hover:text-foreground"
                    >
                        Admin
                    </Link>
                </nav>
            </div>
        </header>

        <main id="public-content">
            <slot />
        </main>
    </div>
</template>
