<script setup lang="ts">
import { parseDate } from '@internationalized/date';
import {
    CalendarRange,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
} from '@lucide/vue';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
    RangeCalendarCell,
    RangeCalendarCellTrigger,
    RangeCalendarGrid,
    RangeCalendarGridBody,
    RangeCalendarGridHead,
    RangeCalendarGridRow,
    RangeCalendarHeadCell,
    RangeCalendarHeader,
    RangeCalendarHeading,
    RangeCalendarNext,
    RangeCalendarPrev,
    RangeCalendarRoot,
} from 'reka-ui';
import type { DateRange } from 'reka-ui';
import { computed, ref, shallowRef, watch } from 'vue';
import { formatDateRangeLabel } from '@/components/public/publicEventDisplay';

const props = defineProps<{
    from: string | null;
    to: string | null;
}>();

const emit = defineEmits<{
    change: [value: { from: string | null; to: string | null }];
}>();

const open = ref(false);

function toRange(from: string | null, to: string | null): DateRange {
    return {
        start: from ? parseDate(from) : undefined,
        end: to ? parseDate(to) : undefined,
    };
}

// shallowRef keeps the calendar date class instances intact; the picker
// always replaces the whole range object.
const range = shallowRef<DateRange>(toRange(props.from, props.to));

watch(
    () => [props.from, props.to] as const,
    ([from, to]) => {
        range.value = toRange(from, to);
    },
);

// Lock in as soon as both ends are picked; no confirmation step, matching
// the public filter behavior.
watch(range, (value) => {
    if (value.start && value.end && open.value) {
        open.value = false;
        emit('change', {
            from: value.start.toString(),
            to: value.end.toString(),
        });
    }
});

const label = computed(
    () =>
        formatDateRangeLabel(range.value.start, range.value.end) ?? 'Any dates',
);
const hasValue = computed(() => Boolean(props.from || props.to));

const hint = computed(() => {
    if (!range.value.start) {
        return 'Pick a start date';
    }

    if (!range.value.end) {
        return `${formatDateRangeLabel(range.value.start)} · pick an end date`;
    }

    return formatDateRangeLabel(range.value.start, range.value.end) ?? '';
});

function clear() {
    range.value = { start: undefined, end: undefined };
    open.value = false;
    emit('change', { from: null, to: null });
}

const navButtonClasses =
    'inline-flex size-8 cursor-pointer items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-40';
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger
            class="flex h-9 w-full cursor-pointer items-center gap-2 rounded-md border border-input bg-background px-3 text-left text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :aria-label="`Local dates: ${label}`"
        >
            <CalendarRange
                class="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <span
                class="min-w-0 flex-1 truncate"
                :class="hasValue ? '' : 'text-muted-foreground'"
            >
                {{ label }}
            </span>
            <ChevronDown
                class="size-4 shrink-0 text-muted-foreground transition-transform motion-safe:duration-150"
                :class="open ? 'rotate-180' : ''"
                aria-hidden="true"
            />
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                align="start"
                :side-offset="6"
                class="z-50 rounded-xl border bg-popover p-3 text-popover-foreground shadow-lg"
            >
                <RangeCalendarRoot
                    v-slot="{ grid, weekDays }"
                    v-model="range"
                    weekday-format="short"
                    fixed-weeks
                    class="select-none"
                >
                    <RangeCalendarHeader
                        class="flex items-center justify-between gap-2"
                    >
                        <RangeCalendarPrev :class="navButtonClasses">
                            <ChevronLeft class="size-4" aria-hidden="true" />
                        </RangeCalendarPrev>
                        <RangeCalendarHeading
                            class="text-sm font-semibold text-foreground"
                        />
                        <RangeCalendarNext :class="navButtonClasses">
                            <ChevronRight class="size-4" aria-hidden="true" />
                        </RangeCalendarNext>
                    </RangeCalendarHeader>

                    <RangeCalendarGrid
                        v-for="month in grid"
                        :key="month.value.toString()"
                        class="mt-2 w-full border-collapse select-none"
                    >
                        <RangeCalendarGridHead>
                            <RangeCalendarGridRow class="grid grid-cols-7">
                                <RangeCalendarHeadCell
                                    v-for="day in weekDays"
                                    :key="day"
                                    class="flex size-9 items-center justify-center text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{ day }}
                                </RangeCalendarHeadCell>
                            </RangeCalendarGridRow>
                        </RangeCalendarGridHead>
                        <RangeCalendarGridBody class="mt-1 grid gap-y-0.5">
                            <RangeCalendarGridRow
                                v-for="(weekDates, index) in month.rows"
                                :key="`week-${index}`"
                                class="grid grid-cols-7"
                            >
                                <RangeCalendarCell
                                    v-for="weekDate in weekDates"
                                    :key="weekDate.toString()"
                                    :date="weekDate"
                                    class="relative text-center"
                                >
                                    <RangeCalendarCellTrigger
                                        :day="weekDate"
                                        :month="month.value"
                                        class="flex size-9 cursor-pointer items-center justify-center rounded-md text-sm whitespace-nowrap text-foreground transition-colors outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring data-[disabled]:pointer-events-none data-[disabled]:opacity-40 data-[highlighted]:bg-muted data-[outside-view]:text-muted-foreground/50 data-[selected]:bg-foreground/10 data-[selected]:data-[selection-end]:bg-foreground data-[selected]:data-[selection-end]:text-background data-[selected]:data-[selection-start]:bg-foreground data-[selected]:data-[selection-start]:text-background data-[today]:underline data-[today]:underline-offset-4"
                                    />
                                </RangeCalendarCell>
                            </RangeCalendarGridRow>
                        </RangeCalendarGridBody>
                    </RangeCalendarGrid>

                    <div
                        class="mt-3 flex items-center justify-between gap-4 border-t pt-3"
                    >
                        <p class="text-xs text-muted-foreground">{{ hint }}</p>
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-2 py-1 text-xs font-semibold text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            @click="clear"
                        >
                            Clear
                        </button>
                    </div>
                </RangeCalendarRoot>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
