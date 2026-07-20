<script setup lang="ts">
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    CircleAlert,
    ImagePlus,
    MapPin,
    Save,
    Trash2,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface ExistingImage {
    token: string;
    role: string;
    path: string;
    width: number;
    height: number;
    alt: string;
}

type GalleryItem =
    | {
          kind: 'existing';
          token: string;
          url: string;
          alt: string;
      }
    | {
          kind: 'new';
          file: File;
          url: string;
          alt: string;
      };

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
const gallery = ref<GalleryItem[]>(
    (props.event?.images ?? []).map((image) => ({
        kind: 'existing',
        token: image.token,
        url: image.path,
        alt: image.alt,
    })),
);
const initialGallerySignature = (props.event?.images ?? [])
    .map((image) => image.token)
    .join('|');

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
    gallery_order: undefined as string[] | undefined,
});
const serverErrors = form.errors as Record<string, string | undefined>;
const addressResults = ref<AddressSuggestion[]>([]);
const addressLookup = useHttp<{ q: string }, { results: AddressSuggestion[] }>({
    q: '',
});

const errorCount = computed(() => Object.keys(form.errors).length);
const imageError = computed(
    () =>
        form.errors.images ??
        Object.entries(serverErrors).find(
            ([field]) =>
                field.startsWith('images.') ||
                field.startsWith('gallery_order'),
        )?.[1],
);
const hasOffsetErrors = computed(() =>
    Boolean(form.errors.starts_at_offset || form.errors.ends_at_offset),
);
const hasAddressErrors = computed(() =>
    [
        'formatted_address',
        'address_line_1',
        'postal_code',
        'locality',
        'region',
        'country',
        'country_code',
        'latitude',
        'longitude',
    ].some((field) => Boolean(serverErrors[field])),
);
const galleryChanged = computed(() => {
    if (!isEditing.value) {
        return false;
    }

    const currentExisting = gallery.value
        .filter(
            (item): item is Extract<GalleryItem, { kind: 'existing' }> =>
                item.kind === 'existing',
        )
        .map((item) => item.token)
        .join('|');

    return (
        gallery.value.some((item) => item.kind === 'new') ||
        currentExisting !== initialGallerySignature
    );
});
const suppressNextAddressLookup = ref(false);

async function findAddress() {
    const query = form.formatted_address.trim();

    if (query.length < 3) {
        addressResults.value = [];

        return;
    }

    addressLookup.cancel();
    addressLookup.clearErrors('q');
    addressLookup.q = query;

    try {
        await addressLookup.get('/admin/address-search');
        addressResults.value = addressLookup.response?.results ?? [];
    } catch {
        addressResults.value = [];
    }
}

function applyAddress(address: AddressSuggestion) {
    suppressNextAddressLookup.value = true;
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

watchDebounced(
    () => form.formatted_address,
    () => {
        if (suppressNextAddressLookup.value) {
            suppressNextAddressLookup.value = false;

            return;
        }

        void findAddress();
    },
    { debounce: 450 },
);

function syncUploads() {
    form.images = gallery.value
        .filter(
            (item): item is Extract<GalleryItem, { kind: 'new' }> =>
                item.kind === 'new',
        )
        .map((item) => item.file);
}

function selectFiles(files: File[]) {
    const availableSlots = Math.max(0, 8 - gallery.value.length);
    const accepted = files
        .filter((file) => file.type.startsWith('image/'))
        .slice(0, availableSlots);

    gallery.value.push(
        ...accepted.map((file) => ({
            kind: 'new' as const,
            file,
            url: URL.createObjectURL(file),
            alt: file.name,
        })),
    );
    syncUploads();
    form.clearErrors('images', 'gallery_order');
}

function handleInput(event: Event) {
    const input = event.target as HTMLInputElement;
    selectFiles(Array.from(input.files ?? []));
    input.value = '';
}

function handleDrop(event: DragEvent) {
    isDragging.value = false;
    selectFiles(Array.from(event.dataTransfer?.files ?? []));
}

function moveImage(index: number, direction: -1 | 1) {
    const next = index + direction;

    if (next < 0 || next >= gallery.value.length) {
        return;
    }

    const items = [...gallery.value];
    [items[index], items[next]] = [items[next], items[index]];
    gallery.value = items;
    syncUploads();
}

function removeImage(index: number) {
    const items = [...gallery.value];
    const [removed] = items.splice(index, 1);

    if (removed?.kind === 'new') {
        URL.revokeObjectURL(removed.url);
    }

    gallery.value = items;
    syncUploads();
}

function submit() {
    if (gallery.value.length < 2) {
        form.setError('images', 'Add at least two images to the gallery.');

        return;
    }

    if (isEditing.value && galleryChanged.value) {
        let newIndex = 0;
        form.gallery_order = gallery.value.map((item) =>
            item.kind === 'existing' ? item.token : `new:${newIndex++}`,
        );
    } else {
        form.gallery_order = undefined;
    }

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
    gallery.value.forEach((item) => {
        if (item.kind === 'new') {
            URL.revokeObjectURL(item.url);
        }
    });
});

const sectionClasses = 'rounded-xl border bg-card p-5 sm:p-6';
const sectionBadgeClasses =
    'grid size-7 shrink-0 place-items-center rounded-lg bg-muted text-xs font-bold text-foreground';
const selectClasses =
    'h-9 w-full cursor-pointer rounded-md border border-input bg-background px-3 text-sm capitalize focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head :title="isEditing ? `Edit ${event!.title}` : 'Create event'" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="space-y-3">
            <Link
                :href="
                    isEditing ? `/admin/events/${event!.id}` : '/admin/events'
                "
                class="inline-flex items-center gap-1.5 rounded-sm text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <ArrowLeft class="size-4" aria-hidden="true" /> Back
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

        <form
            class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]"
            @submit.prevent="submit"
        >
            <div class="min-w-0 space-y-6">
                <section :class="sectionClasses">
                    <div class="flex items-center gap-3">
                        <span :class="sectionBadgeClasses" aria-hidden="true">
                            1
                        </span>
                        <h2 class="font-semibold">Details</h2>
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
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
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            ></textarea>
                            <InputError :message="form.errors.description" />
                        </label>
                        <label class="space-y-1.5">
                            <span class="text-sm font-medium">Type</span>
                            <select v-model="form.type" :class="selectClasses">
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

                <section :class="sectionClasses">
                    <div class="flex items-center gap-3">
                        <span :class="sectionBadgeClasses" aria-hidden="true">
                            2
                        </span>
                        <h2 class="font-semibold">Schedule</h2>
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="space-y-1.5 md:col-span-2">
                            <span class="text-sm font-medium">Timezone</span>
                            <select
                                v-model="form.timezone"
                                :class="selectClasses"
                            >
                                <option
                                    v-for="timezone in options.timezones"
                                    :key="timezone"
                                    :value="timezone"
                                >
                                    {{ timezone }}
                                </option>
                            </select>
                            <InputError :message="form.errors.timezone" />
                        </label>
                        <label class="space-y-1.5">
                            <span class="text-sm font-medium">
                                Starts locally
                            </span>
                            <Input
                                v-model="form.starts_at_local"
                                type="datetime-local"
                            />
                            <InputError
                                :message="form.errors.starts_at_local"
                            />
                        </label>
                        <label class="space-y-1.5">
                            <span class="text-sm font-medium">
                                Ends locally
                            </span>
                            <Input
                                v-model="form.ends_at_local"
                                type="datetime-local"
                            />
                            <InputError :message="form.errors.ends_at_local" />
                        </label>
                        <div
                            v-if="hasOffsetErrors"
                            class="rounded-lg border border-amber-500/40 bg-amber-500/5 p-4 md:col-span-2"
                        >
                            <p class="text-sm font-medium">
                                Choose which occurrence of this local time you
                                mean.
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                The clock shows this time twice because daylight
                                saving time ends on this date. Use one of the
                                offsets listed in the validation message.
                            </p>
                            <div class="mt-3 grid gap-4 md:grid-cols-2">
                                <label class="space-y-1.5">
                                    <span class="text-sm font-medium">
                                        Start UTC offset
                                    </span>
                                    <Input
                                        v-model="form.starts_at_offset"
                                        placeholder="+02:00"
                                    />
                                    <InputError
                                        :message="form.errors.starts_at_offset"
                                    />
                                </label>
                                <label class="space-y-1.5">
                                    <span class="text-sm font-medium">
                                        End UTC offset
                                    </span>
                                    <Input
                                        v-model="form.ends_at_offset"
                                        placeholder="+02:00"
                                    />
                                    <InputError
                                        :message="form.errors.ends_at_offset"
                                    />
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <section :class="sectionClasses">
                    <div class="flex items-center gap-3">
                        <span :class="sectionBadgeClasses" aria-hidden="true">
                            3
                        </span>
                        <div>
                            <h2 class="font-semibold">Address</h2>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Start typing and choose a suggestion. The address,
                        coordinates, and map location are filled automatically.
                    </p>
                    <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div
                            class="relative space-y-1.5 md:col-span-2 lg:col-span-4"
                        >
                            <span class="text-sm font-medium">Address</span>
                            <Input
                                v-model="form.formatted_address"
                                role="combobox"
                                aria-label="Address"
                                autocomplete="off"
                                aria-autocomplete="list"
                                :aria-expanded="addressResults.length > 0"
                                placeholder="Start typing a street address"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    addressLookup.processing
                                        ? 'Finding addresses...'
                                        : 'Suggestions appear after three characters.'
                                }}
                            </p>
                            <InputError
                                :message="form.errors.formatted_address"
                            />
                            <InputError :message="addressLookup.errors.q" />
                            <div
                                v-if="addressResults.length"
                                class="absolute z-20 mt-1 w-full divide-y overflow-hidden rounded-lg border bg-popover shadow-lg"
                                role="listbox"
                            >
                                <button
                                    v-for="address in addressResults"
                                    :key="`${address.latitude}:${address.longitude}`"
                                    type="button"
                                    role="option"
                                    class="flex w-full cursor-pointer items-start gap-3 px-3 py-3 text-left text-sm transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
                                    @click="applyAddress(address)"
                                >
                                    <MapPin
                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span>{{ address.formatted_address }}</span>
                                </button>
                            </div>
                        </div>
                        <details
                            class="md:col-span-2 lg:col-span-4"
                            :open="hasAddressErrors"
                        >
                            <summary
                                class="cursor-pointer rounded-sm text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                Edit address details manually
                            </summary>
                            <div
                                class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4"
                            >
                                <label class="space-y-1.5 md:col-span-2">
                                    <span class="text-sm font-medium">
                                        Street address
                                    </span>
                                    <Input v-model="form.address_line_1" />
                                    <InputError
                                        :message="form.errors.address_line_1"
                                    />
                                </label>
                                <label class="space-y-1.5">
                                    <span class="text-sm font-medium">
                                        Postal code
                                    </span>
                                    <Input v-model="form.postal_code" />
                                    <InputError
                                        :message="form.errors.postal_code"
                                    />
                                </label>
                                <label class="space-y-1.5">
                                    <span class="text-sm font-medium">
                                        City / locality
                                    </span>
                                    <Input v-model="form.locality" />
                                    <InputError
                                        :message="form.errors.locality"
                                    />
                                </label>
                                <label class="space-y-1.5">
                                    <span class="text-sm font-medium"
                                        >Region</span
                                    >
                                    <Input v-model="form.region" />
                                    <InputError :message="form.errors.region" />
                                </label>
                                <label class="space-y-1.5">
                                    <span class="text-sm font-medium"
                                        >Country</span
                                    >
                                    <Input v-model="form.country" />
                                    <InputError
                                        :message="form.errors.country"
                                    />
                                </label>
                                <label class="space-y-1.5">
                                    <span class="text-sm font-medium">
                                        Country code
                                    </span>
                                    <Input
                                        v-model="form.country_code"
                                        maxlength="2"
                                        placeholder="DE"
                                    />
                                    <InputError
                                        :message="form.errors.country_code"
                                    />
                                </label>
                                <label class="space-y-1.5">
                                    <span class="text-sm font-medium"
                                        >Latitude</span
                                    >
                                    <Input
                                        v-model="form.latitude"
                                        inputmode="decimal"
                                    />
                                    <InputError
                                        :message="form.errors.latitude"
                                    />
                                </label>
                                <label class="space-y-1.5">
                                    <span class="text-sm font-medium">
                                        Longitude
                                    </span>
                                    <Input
                                        v-model="form.longitude"
                                        inputmode="decimal"
                                    />
                                    <InputError
                                        :message="form.errors.longitude"
                                    />
                                </label>
                            </div>
                        </details>
                    </div>
                </section>

                <section :class="sectionClasses">
                    <div class="flex items-center gap-3">
                        <span :class="sectionBadgeClasses" aria-hidden="true">
                            4
                        </span>
                        <h2 class="font-semibold">Gallery</h2>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Add 2 to 8 JPEG, PNG, WebP, GIF, or AVIF images. They
                        are stripped, resized, and served locally as optimized
                        WebP.
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
                    <InputError :message="imageError" class="mt-2" />

                    <div
                        v-if="gallery.length"
                        class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <figure
                            v-for="(image, index) in gallery"
                            :key="
                                image.kind === 'existing'
                                    ? image.token
                                    : image.url
                            "
                            class="overflow-hidden rounded-lg border bg-background"
                        >
                            <img
                                :src="image.url"
                                :alt="image.alt"
                                class="aspect-[4/3] w-full object-cover"
                            />
                            <figcaption class="flex items-center gap-1 p-2">
                                <span
                                    class="mr-auto truncate text-xs font-medium"
                                >
                                    {{
                                        index === 0
                                            ? 'Cover'
                                            : `Image ${index + 1}`
                                    }}
                                </span>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    :disabled="index === 0"
                                    :aria-label="`Move image ${index + 1} earlier`"
                                    @click="moveImage(index, -1)"
                                >
                                    <ArrowUp class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    :disabled="index === gallery.length - 1"
                                    :aria-label="`Move image ${index + 1} later`"
                                    @click="moveImage(index, 1)"
                                >
                                    <ArrowDown class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    :aria-label="`Remove image ${index + 1}`"
                                    @click="removeImage(index)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </figcaption>
                        </figure>
                    </div>
                </section>

                <section :class="sectionClasses">
                    <div class="flex items-center gap-3">
                        <span :class="sectionBadgeClasses" aria-hidden="true">
                            5
                        </span>
                        <h2 class="font-semibold">Capacity and pricing</h2>
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <label class="space-y-1.5">
                            <span class="text-sm font-medium">Capacity</span>
                            <Input
                                v-model="form.capacity"
                                type="number"
                                min="1"
                            />
                            <InputError :message="form.errors.capacity" />
                        </label>
                        <label class="space-y-1.5">
                            <span class="text-sm font-medium">
                                Minimum price
                            </span>
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
            </div>

            <aside
                class="space-y-4 lg:sticky lg:top-6"
                aria-label="Publish controls"
            >
                <section :class="sectionClasses">
                    <h2 class="font-semibold">Publish</h2>
                    <label class="mt-4 block space-y-1.5">
                        <span class="text-sm font-medium">Status</span>
                        <select v-model="form.status" :class="selectClasses">
                            <option
                                v-for="status in options.statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ status.replace('_', ' ') }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </label>

                    <div
                        v-if="errorCount > 0"
                        class="mt-4 flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive"
                        role="alert"
                    >
                        <CircleAlert
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <p>
                            {{ errorCount }}
                            {{
                                errorCount === 1 ? 'field needs' : 'fields need'
                            }}
                            attention. Each problem is highlighted next to its
                            input.
                        </p>
                    </div>

                    <Button
                        type="submit"
                        class="mt-4 w-full"
                        :disabled="form.processing"
                    >
                        <Save class="size-4" />
                        {{ form.processing ? 'Saving...' : 'Save event' }}
                    </Button>
                    <Button
                        as-child
                        variant="ghost"
                        class="mt-2 w-full text-muted-foreground"
                    >
                        <Link
                            :href="
                                isEditing
                                    ? `/admin/events/${event!.id}`
                                    : '/admin/events'
                            "
                        >
                            Cancel
                        </Link>
                    </Button>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Drafts stay private; published events appear in public
                        discovery within moments.
                    </p>
                </section>
            </aside>
        </form>
    </div>
</template>
