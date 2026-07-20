<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import {
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

withDefaults(
    defineProps<{
        numberOfMonths?: number;
    }>(),
    { numberOfMonths: 1 },
);

const range = defineModel<DateRange>({ required: true });

const navButtonClasses =
    'inline-flex size-9 items-center justify-center rounded-lg text-stone-600 transition-colors hover:bg-stone-900/10 hover:text-stone-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-900 disabled:pointer-events-none disabled:opacity-40';
</script>

<template>
    <RangeCalendarRoot
        v-slot="{ grid, weekDays }"
        v-model="range"
        :number-of-months="numberOfMonths"
        weekday-format="short"
        fixed-weeks
        class="select-none"
    >
        <RangeCalendarHeader class="flex items-center justify-between gap-2">
            <RangeCalendarPrev :class="navButtonClasses">
                <ChevronLeft class="size-4" aria-hidden="true" />
            </RangeCalendarPrev>
            <RangeCalendarHeading class="text-sm font-bold text-stone-900" />
            <RangeCalendarNext :class="navButtonClasses">
                <ChevronRight class="size-4" aria-hidden="true" />
            </RangeCalendarNext>
        </RangeCalendarHeader>

        <div class="mt-2 flex flex-col gap-5 sm:flex-row sm:gap-8">
            <RangeCalendarGrid
                v-for="month in grid"
                :key="month.value.toString()"
                class="w-full border-collapse select-none"
            >
                <RangeCalendarGridHead>
                    <RangeCalendarGridRow class="grid grid-cols-7">
                        <RangeCalendarHeadCell
                            v-for="day in weekDays"
                            :key="day"
                            class="flex size-9 items-center justify-center text-[10px] font-black tracking-wider text-stone-500 uppercase"
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
                                class="flex size-9 items-center justify-center rounded-lg text-sm font-semibold whitespace-nowrap text-stone-800 transition-colors outline-none hover:bg-stone-900/10 focus-visible:ring-2 focus-visible:ring-stone-900 data-[disabled]:pointer-events-none data-[disabled]:opacity-40 data-[highlighted]:bg-stone-900/10 data-[outside-view]:text-stone-400 data-[selected]:bg-stone-900/15 data-[selected]:data-[selection-end]:bg-stone-900 data-[selected]:data-[selection-end]:text-white data-[selected]:data-[selection-start]:bg-stone-900 data-[selected]:data-[selection-start]:text-white data-[today]:underline data-[today]:underline-offset-4"
                            />
                        </RangeCalendarCell>
                    </RangeCalendarGridRow>
                </RangeCalendarGridBody>
            </RangeCalendarGrid>
        </div>
    </RangeCalendarRoot>
</template>
