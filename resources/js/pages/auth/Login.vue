<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const labelClasses =
    'text-xs font-black tracking-widest text-stone-500 uppercase';
const fieldClasses =
    'h-12 rounded-xl border-transparent bg-[#f4f0e8] text-sm font-medium text-stone-900 shadow-none placeholder:text-stone-500 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-stone-900 dark:border-transparent dark:bg-[#f4f0e8] dark:text-stone-900 dark:placeholder:text-stone-500';
const linkClasses =
    'text-blue-700 decoration-blue-700/30 hover:text-blue-900 dark:text-blue-700 dark:decoration-blue-700/30';
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="rounded-xl bg-lime-100 px-4 py-3 text-center text-sm font-bold text-emerald-800"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email" :class="labelClasses">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                    :class="[fieldClasses, 'px-4']"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password" :class="labelClasses">
                        Password
                    </Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs font-bold"
                        :class="linkClasses"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                    :class="[fieldClasses, 'pl-4']"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="border-stone-400 bg-white data-[state=checked]:border-stone-900 data-[state=checked]:bg-stone-900 dark:border-stone-400 dark:bg-white dark:data-[state=checked]:border-stone-900 dark:data-[state=checked]:bg-stone-900"
                    />
                    <span class="text-sm font-semibold text-stone-700">
                        Remember me
                    </span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 h-12 w-full rounded-full bg-stone-900 text-sm font-bold text-white shadow-none hover:bg-stone-800 dark:bg-stone-900 dark:text-white dark:hover:bg-stone-800"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>
    </Form>

    <p class="text-center text-sm text-stone-600">
        New to EventRadar?
        <TextLink href="/register" class="font-bold" :class="linkClasses">
            Create an account
        </TextLink>
    </p>
</template>
