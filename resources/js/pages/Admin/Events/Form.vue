<script setup lang="ts">
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    ImagePlus,
    MapPin,
    Save,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface ExistingImage {
    role: string;
    path: string;
    width: number;
    height: number;
    alt: string;
}

interface AdminEvent {
    id: string;
    title: string;
    description: string;
    organizer_name: string;
    venue_name: string;
    formatted_address: string;
    address_line_1: string | null;
    postal_code: string | null;
    locality: string;
    region: string | null;
    country: string;
    country_code: string;
    latitude: number | null;
    longitude: number | null;
    timezone: string;
    starts_at_local: string;
    ends_at_local: string;
    starts_at_offset: string;
    ends_at_offset: string;
    status: string;
    type: string;
    tags: string[];
    minimum_price: string | null;
    currency_code: string | null;
    capacity: number | null;
    images: ExistingImage[];
}

interface AddressSuggestion {
    formatted_address: string;
    address_line_1: string | null;
    postal_code: string | null;
    locality: string;
    region: string | null;
    country: string;
    country_code: string;
    latitude: number;
    longitude: number;
}

const props = defineProps<{
    event: AdminEvent | null;
    options: {
        statuses: string[];
        types: string[];
        timezones: string[];
    };
}>();

const isEditing = computed(() => props.event !== null);
const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const previews = ref<Array<{ file: File; url: string }>>([]);

const form = useForm({
    title: props.event?.title ?? '',
    description: props.event?.description ?? '',
    organizer_name: props.event?.organizer_name ?? '',
    venue_name: props.event?.venue_name ?? '',
    formatted_address: props.event?.formatted_address ?? '',
    address_line_1: props.event?.address_line_1 ?? '',
    postal_code: props.event?.postal_code ?? '',
    locality: props.event?.locality ?? '',
    region: props.event?.region ?? '',
    country: props.event?.country ?? '',
    country_code: props.event?.country_code ?? '',
    latitude: props.event?.latitude?.toString() ?? '',
    longitude: props.event?.longitude?.toString() ?? '',
    timezone: props.event?.timezone ?? 'Europe/Berlin',
    starts_at_local: props.event?.starts_at_local ?? '',
    ends_at_local: props.event?.ends_at_local ?? '',
    starts_at_offset: props.event?.starts_at_offset ?? '',
    ends_at_offset: props.event?.ends_at_offset ?? '',
    status: props.event?.status ?? 'draft',
    type: props.event?.type ?? 'meetup',
    tags_text: props.event?.tags.join(', ') ?? '',
    minimum_price: props.event?.minimum_price ?? '',
    currency_code: props.event?.currency_code ?? '',
    capacity: props.event?.capacity?.toString() ?? '',
    images: [] as File[],
});
const serverErrors = form.errors as Record<string, string | undefined>;
const addressResults = ref<AddressSuggestion[]>([]);
const addressLookup = useHttp<{ q: string }, { results: AddressSuggestion[] }>({
    q: '',
});

async function findAddress() {
    addressLookup.q = form.formatted_address;

    try {
        await addressLookup.get('/admin/address-search');
        addressResults.value = addressLookup.response?.results ?? [];
    } catch {
        addressResults.value = [];
    }
}

function applyAddress(address: AddressSuggestion) {
    form.formatted_address = address.formatted_address;
    form.address_line_1 = address.address_line_1 ?? '';
    form.postal_code = address.postal_code ?? '';
    form.locality = address.locality;
    form.region = address.region ?? '';
    form.country = address.country;
    form.country_code = address.country_code;
    form.latitude = address.latitude.toString();
    form.longitude = address.longitude.toString();
    addressResults.value = [];
}

function selectFiles(files: File[]) {
    const accepted = files
        .filter((file) => file.type.startsWith('image/'))
        .slice(0, 8);

    previews.value.forEach((preview) => URL.revokeObjectURL(preview.url));
    previews.value = accepted.map((file) => ({
        file,
        url: URL.createObjectURL(file),
    }));
    form.images = accepted;
}

function handleInput(event: Event) {
    selectFiles(Array.from((event.target as HTMLInputElement).files ?? []));
}

function handleDrop(event: DragEvent) {
    isDragging.value = false;
    selectFiles(Array.from(event.dataTransfer?.files ?? []));
}

function moveImage(index: number, direction: -1 | 1) {
    const next = index + direction;

    if (next < 0 || next >= previews.value.length) {
        return;
    }

    const items = [...previews.value];
    [items[index], items[next]] = [items[next], items[index]];
    previews.value = items;
    form.images = items.map((item) => item.file);
}

function removeImage(index: number) {
    const items = [...previews.value];
    const [removed] = items.splice(index, 1);

    if (removed) {
        URL.revokeObjectURL(removed.url);
    }

    previews.value = items;
    form.images = items.map((item) => item.file);
}

function submit() {
    form.transform((data) => ({
        ...data,
        tags: data.tags_text
            .split(',')
            .map((tag) => tag.trim())
            .filter(Boolean),
        tags_text: undefined,
        _method: isEditing.value ? 'put' : undefined,
    })).post(
        isEditing.value ? `/admin/events/${props.event!.id}` : '/admin/events',
        { forceFormData: true },
    );
}

onBeforeUnmount(() => {
    previews.value.forEach((preview) => URL.revokeObjectURL(preview.url));
});
</script>

<template>
    <Head :title="isEditing ? `Edit ${event!.title}` : 'Create event'" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="space-y-3">
            <Link
                :href="
                    isEditing ? `/admin/events/${event!.id}` : '/admin/events'
                "
                class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
            >
                <ArrowLeft class="size-4" /> Back
            </Link>
            <div>
                <p class="text-sm font-medium text-muted-foreground">Admin</p>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ isEditing ? 'Edit event' : 'Create event' }}
                </h1>
                <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                    Dates are entered in the event's own timezone and stored as
                    UTC instants. The first gallery image becomes the cover.
                </p>
            </div>
        </header>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-xl border bg-card p-5">
                <h2 class="font-semibold">Event details</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="space-y-1.5 md:col-span-2">
                        <span class="text-sm font-medium">Title</span>
                        <Input v-model="form.title" maxlength="160" />
                        <InputError :message="form.errors.title" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Organizer</span>
                        <Input v-model="form.organizer_name" />
                        <InputError :message="form.errors.organizer_name" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Venue</span>
                        <Input v-model="form.venue_name" />
                        <InputError :message="form.errors.venue_name" />
                    </label>
                    <label class="space-y-1.5 md:col-span-2">
                        <span class="text-sm font-medium">Description</span>
                        <textarea
                            v-model="form.description"
                            rows="7"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Type</span>
                        <select
                            v-model="form.type"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option
                                v-for="type in options.types"
                                :key="type"
                                :value="type"
                            >
                                {{ type }}
                            </option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Status</span>
                        <select
                            v-model="form.status"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option
                                v-for="status in options.statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ status }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </label>
                    <label class="space-y-1.5 md:col-span-2">
                        <span class="text-sm font-medium">Tags</span>
                        <Input
                            v-model="form.tags_text"
                            placeholder="design, community, evening"
                        />
                        <p class="text-xs text-muted-foreground">
                            Separate up to 12 tags with commas.
                        </p>
                        <InputError :message="serverErrors.tags" />
                    </label>
                </div>
            </section>

            <section class="rounded-xl border bg-card p-5">
                <h2 class="font-semibold">Date and timezone</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Daylight-saving gaps are rejected. If a local time occurs
                    twice, the form will tell you which UTC offsets are valid.
                </p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="space-y-1.5 md:col-span-2">
                        <span class="text-sm font-medium">IANA timezone</span>
                        <Input v-model="form.timezone" list="event-timezones" />
                        <datalist id="event-timezones">
                            <option
                                v-for="timezone in options.timezones"
                                :key="timezone"
                                :value="timezone"
                            />
                        </datalist>
                        <InputError :message="form.errors.timezone" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Starts locally</span>
                        <Input
                            v-model="form.starts_at_local"
                            type="datetime-local"
                        />
                        <InputError :message="form.errors.starts_at_local" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Ends locally</span>
                        <Input
                            v-model="form.ends_at_local"
                            type="datetime-local"
                        />
                        <InputError :message="form.errors.ends_at_local" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium"
                            >Start UTC offset, only if ambiguous</span
                        >
                        <Input
                            v-model="form.starts_at_offset"
                            placeholder="+02:00"
                        />
                        <InputError :message="form.errors.starts_at_offset" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium"
                            >End UTC offset, only if ambiguous</span
                        >
                        <Input
                            v-model="form.ends_at_offset"
                            placeholder="+02:00"
                        />
                        <InputError :message="form.errors.ends_at_offset" />
                    </label>
                </div>
            </section>

            <section class="rounded-xl border bg-card p-5">
                <h2 class="font-semibold">Address</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    The formatted address is shown publicly. Coordinates power
                    the map and must be supplied together.
                </p>
                <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div class="space-y-1.5 md:col-span-2 lg:col-span-4">
                        <span class="text-sm font-medium"
                            >Formatted address</span
                        >
                        <div class="flex gap-2">
                            <Input
                                v-model="form.formatted_address"
                                placeholder="Alexanderplatz 1, 10178 Berlin, Germany"
                                @keydown.enter.prevent="findAddress"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="
                                    addressLookup.processing ||
                                    form.formatted_address.length < 3
                                "
                                @click="findAddress"
                            >
                                <Search class="size-4" />
                                {{
                                    addressLookup.processing
                                        ? 'Finding...'
                                        : 'Find'
                                }}
                            </Button>
                        </div>
                        <InputError :message="form.errors.formatted_address" />
                        <InputError :message="addressLookup.errors.q" />
                        <div
                            v-if="addressResults.length"
                            class="divide-y overflow-hidden rounded-lg border"
                        >
                            <button
                                v-for="address in addressResults"
                                :key="`${address.latitude}:${address.longitude}`"
                                type="button"
                                class="flex w-full items-start gap-3 px-3 py-3 text-left text-sm hover:bg-muted"
                                @click="applyAddress(address)"
                            >
                                <MapPin
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <span>{{ address.formatted_address }}</span>
                            </button>
                        </div>
                    </div>
                    <label class="space-y-1.5 md:col-span-2">
                        <span class="text-sm font-medium">Street address</span>
                        <Input v-model="form.address_line_1" />
                        <InputError :message="form.errors.address_line_1" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Postal code</span>
                        <Input v-model="form.postal_code" />
                        <InputError :message="form.errors.postal_code" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">City / locality</span>
                        <Input v-model="form.locality" />
                        <InputError :message="form.errors.locality" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Region</span>
                        <Input v-model="form.region" />
                        <InputError :message="form.errors.region" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Country</span>
                        <Input v-model="form.country" />
                        <InputError :message="form.errors.country" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Country code</span>
                        <Input
                            v-model="form.country_code"
                            maxlength="2"
                            placeholder="DE"
                        />
                        <InputError :message="form.errors.country_code" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Latitude</span>
                        <Input v-model="form.latitude" inputmode="decimal" />
                        <InputError :message="form.errors.latitude" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Longitude</span>
                        <Input v-model="form.longitude" inputmode="decimal" />
                        <InputError :message="form.errors.longitude" />
                    </label>
                </div>
            </section>

            <section class="rounded-xl border bg-card p-5">
                <h2 class="font-semibold">Gallery</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Add 2 to 8 JPEG, PNG, WebP, GIF, or AVIF images. They are
                    stripped, resized, and served locally as optimized WebP.
                </p>

                <div
                    class="mt-4 flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors"
                    :class="
                        isDragging
                            ? 'border-primary bg-primary/5'
                            : 'border-border hover:border-primary/60'
                    "
                    role="button"
                    tabindex="0"
                    @click="fileInput?.click()"
                    @keydown.enter="fileInput?.click()"
                    @dragenter.prevent="isDragging = true"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="handleDrop"
                >
                    <ImagePlus class="size-8 text-muted-foreground" />
                    <p class="mt-3 font-medium">
                        Drop images here or choose files
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        12 MB maximum per source image
                    </p>
                    <input
                        ref="fileInput"
                        type="file"
                        class="sr-only"
                        multiple
                        accept="image/jpeg,image/png,image/webp,image/gif,image/avif"
                        @change="handleInput"
                    />
                </div>
                <InputError :message="form.errors.images" class="mt-2" />

                <div
                    v-if="previews.length"
                    class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <figure
                        v-for="(preview, index) in previews"
                        :key="preview.url"
                        class="overflow-hidden rounded-lg border bg-background"
                    >
                        <img
                            :src="preview.url"
                            alt="Selected event image preview"
                            class="aspect-[4/3] w-full object-cover"
                        />
                        <figcaption class="flex items-center gap-1 p-2">
                            <span
                                class="mr-auto truncate text-xs font-medium"
                                >{{
                                    index === 0 ? 'Cover' : `Image ${index + 1}`
                                }}</span
                            >
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="index === 0"
                                @click="moveImage(index, -1)"
                                ><ArrowUp class="size-4"
                            /></Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="index === previews.length - 1"
                                @click="moveImage(index, 1)"
                                ><ArrowDown class="size-4"
                            /></Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                @click="removeImage(index)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </figcaption>
                    </figure>
                </div>

                <div v-else-if="event?.images.length" class="mt-4">
                    <p class="mb-3 text-sm font-medium">Current gallery</p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <figure
                            v-for="image in event.images"
                            :key="image.path"
                            class="overflow-hidden rounded-lg border"
                        >
                            <img
                                :src="image.path"
                                :alt="image.alt"
                                class="aspect-[4/3] w-full object-cover"
                            />
                        </figure>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Selecting new files replaces the complete gallery.
                    </p>
                </div>
            </section>

            <section class="rounded-xl border bg-card p-5">
                <h2 class="font-semibold">Capacity and pricing</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Capacity</span>
                        <Input v-model="form.capacity" type="number" min="1" />
                        <InputError :message="form.errors.capacity" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Minimum price</span>
                        <Input
                            v-model="form.minimum_price"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="form.errors.minimum_price" />
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Currency</span>
                        <Input
                            v-model="form.currency_code"
                            maxlength="3"
                            placeholder="EUR"
                        />
                        <InputError :message="form.errors.currency_code" />
                    </label>
                </div>
            </section>

            <div class="flex items-center justify-end gap-3">
                <Link
                    :href="
                        isEditing
                            ? `/admin/events/${event!.id}`
                            : '/admin/events'
                    "
                    class="text-sm font-medium text-muted-foreground hover:text-foreground"
                >
                    Cancel
                </Link>
                <Button type="submit" :disabled="form.processing">
                    <Save class="size-4" />
                    {{ form.processing ? 'Saving...' : 'Save event' }}
                </Button>
            </div>
        </form>
    </div>
</template>
