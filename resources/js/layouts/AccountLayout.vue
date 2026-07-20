<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { LogOut, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import PublicBrand from '@/components/public/PublicBrand.vue';
import { Toaster } from '@/components/ui/sonner';
import type { Auth } from '@/types';

const page = usePage<{ auth: Auth }>();

const isMyEvents = computed(() => page.url.startsWith('/my-events'));

const navLinkClasses = (active: boolean) => [
    'relative rounded-full px-3 py-2 text-sm font-semibold whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-900',
    "after:absolute after:inset-x-3 after:bottom-0.5 after:h-0.5 after:rounded-full after:bg-orange-500 after:transition-opacity after:content-['']",
    active
        ? 'text-stone-900 after:opacity-100'
        : 'text-stone-500 after:opacity-0 hover:text-stone-900',
];

const pillClasses =
    'inline-flex h-9 shrink-0 cursor-pointer items-center gap-1.5 rounded-full border border-stone-900/15 bg-white/60 px-3 text-xs font-bold text-stone-600 transition-colors hover:border-stone-900/30 hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none';
</script>

<template>
    <div
        class="flex min-h-screen flex-col overflow-x-clip bg-[#f4f0e8] text-stone-900 antialiased selection:bg-lime-200 selection:text-stone-900"
    >
        <a
            href="#account-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-full focus:bg-stone-900 focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-white focus:shadow-lg"
        >
            Skip to content
        </a>

        <header
            class="sticky top-0 z-40 border-b border-stone-900/10 bg-[#f4f0e8]/90 backdrop-blur-md"
        >
            <div
                class="mx-auto flex h-16 max-w-screen-2xl items-center gap-1.5 px-4 sm:px-6 lg:px-8"
            >
                <PublicBrand />

                <nav
                    aria-label="Primary"
                    class="ml-auto flex items-center gap-0.5 sm:gap-2"
                >
                    <Link href="/" :class="navLinkClasses(false)">
                        Discover
                    </Link>
                    <Link
                        href="/my-events"
                        :aria-current="isMyEvents ? 'page' : undefined"
                        :class="navLinkClasses(isMyEvents)"
                    >
                        My events
                    </Link>
                </nav>

                <Link
                    v-if="page.props.auth.user?.is_admin"
                    href="/admin"
                    :class="pillClasses"
                    class="ml-1.5"
                >
                    <ShieldCheck class="size-3.5" aria-hidden="true" />
                    <span class="hidden sm:inline">Admin</span>
                </Link>
                <Form action="/logout" method="post" class="ml-1.5">
                    <button
                        type="submit"
                        aria-label="Log out"
                        :class="pillClasses"
                    >
                        <LogOut class="size-3.5" aria-hidden="true" />
                        <span class="hidden sm:inline">Log out</span>
                    </button>
                </Form>
            </div>
        </header>

        <main id="account-content" class="flex flex-1 flex-col">
            <slot />
        </main>

        <Toaster />
    </div>
</template>
