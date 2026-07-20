<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { CalendarHeart, LogOut, ShieldCheck } from '@lucide/vue';
import { Toaster } from '@/components/ui/sonner';
import type { Auth } from '@/types';

const page = usePage<{ auth: Auth }>();
</script>

<template>
    <div class="min-h-screen bg-[#f4f0e8] text-stone-900 antialiased">
        <header class="border-b border-stone-900/10 bg-[#f4f0e8]/95">
            <div
                class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6"
            >
                <Link href="/" class="text-lg font-extrabold tracking-tight">
                    <span class="mr-2 text-orange-600">●</span>EventRadar
                </Link>
                <nav class="ml-auto flex items-center gap-2">
                    <Link
                        href="/my-events"
                        class="inline-flex h-9 items-center gap-2 rounded-full px-3 text-sm font-bold hover:bg-white/70"
                    >
                        <CalendarHeart class="size-4" />
                        My events
                    </Link>
                    <Link
                        v-if="page.props.auth.user?.is_admin"
                        href="/admin"
                        class="inline-flex h-9 items-center gap-2 rounded-full px-3 text-sm font-bold hover:bg-white/70"
                    >
                        <ShieldCheck class="size-4" />
                        Admin
                    </Link>
                    <Form action="/logout" method="post">
                        <button
                            type="submit"
                            class="inline-flex h-9 items-center gap-2 rounded-full border border-stone-900/15 bg-white/60 px-3 text-sm font-bold hover:bg-white"
                        >
                            <LogOut class="size-4" />
                            <span class="hidden sm:inline">Log out</span>
                        </button>
                    </Form>
                </nav>
            </div>
        </header>

        <main>
            <slot />
        </main>

        <Toaster />
    </div>
</template>
