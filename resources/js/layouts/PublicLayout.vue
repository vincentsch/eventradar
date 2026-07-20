<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicBrand from '@/components/public/PublicBrand.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
import { usePublicViewLinks } from '@/components/public/usePublicViewLinks';
import { Toaster } from '@/components/ui/sonner';

const page = usePage();
const { publicViewHref } = usePublicViewLinks();

// Near & soon is a full-height map workspace; the footer only belongs on
// document-style pages such as Discover and event detail.
const showFooter = computed(() => !page.url.startsWith('/events-visual-2'));

const footerLinkClasses =
    'rounded-full px-1 py-0.5 whitespace-nowrap transition-colors hover:text-stone-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2';
</script>

<template>
    <div
        class="flex min-h-screen flex-col overflow-x-clip bg-[#f4f0e8] text-stone-900 antialiased selection:bg-lime-200 selection:text-stone-900"
    >
        <a
            href="#public-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-full focus:bg-stone-900 focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-white focus:shadow-lg"
        >
            Skip to content
        </a>

        <PublicHeader />

        <main id="public-content" class="flex flex-1 flex-col">
            <slot />
        </main>

        <footer
            v-if="showFooter"
            class="border-t border-stone-900/10 bg-[#f4f0e8]"
        >
            <div
                class="mx-auto flex max-w-screen-2xl flex-col gap-6 px-4 py-10 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8"
            >
                <PublicBrand />
                <p class="max-w-md text-xs leading-relaxed text-stone-600">
                    One global catalogue, two ways in: an editorial grid and a
                    live map, always in each event's own local time.
                </p>
                <nav
                    aria-label="Footer"
                    class="flex items-center gap-4 text-xs font-bold text-stone-600"
                >
                    <Link :href="publicViewHref('/')" :class="footerLinkClasses"
                        >Discover</Link
                    >
                    <Link
                        :href="publicViewHref('/events-visual-2')"
                        :class="footerLinkClasses"
                    >
                        Near &amp; soon
                    </Link>
                    <Link href="/account" :class="footerLinkClasses"
                        >Account</Link
                    >
                </nav>
            </div>
        </footer>

        <Toaster />
    </div>
</template>
