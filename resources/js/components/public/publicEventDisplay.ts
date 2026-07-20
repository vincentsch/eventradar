import type {
    PublicEventCategory,
    PublicEvent,
    PublicEventFilterOption,
} from '@/types/public-events';

export function eventWeekday(event: PublicEvent): string {
    return new Intl.DateTimeFormat('en', {
        weekday: 'short',
        timeZone: event.timezone,
    }).format(new Date(event.startsAt));
}

export function eventCategoryLabel(category: PublicEventCategory): string {
    return category.charAt(0).toUpperCase() + category.slice(1);
}

export function eventDetailImageSrc(event: PublicEvent): string {
    return event.detailImage.src;
}

export interface EventDateParts {
    day: string;
    month: string;
}

export function eventDateParts(event: PublicEvent): EventDateParts {
    const [day = '', month = ''] = event.dateLabel.split(' ');

    return { day, month };
}

const MONTH_SHORT_LABELS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/**
 * Structural subset of the calendar date values reka-ui emits, so the
 * label formatting stays decoupled from the calendar library types.
 */
export interface CalendarDayValue {
    day: number;
    month: number;
    year: number;
}

export function formatDateRangeLabel(
    start?: CalendarDayValue,
    end?: CalendarDayValue,
): string | null {
    if (!start) {
        return null;
    }

    const dayLabel = (value: CalendarDayValue) =>
        `${value.day} ${MONTH_SHORT_LABELS[value.month - 1]}`;
    const sameDay =
        end &&
        start.day === end.day &&
        start.month === end.month &&
        start.year === end.year;

    if (!end || sameDay) {
        return `${dayLabel(start)} ${start.year}`;
    }

    if (start.year !== end.year) {
        return `${dayLabel(start)} ${start.year} – ${dayLabel(end)} ${end.year}`;
    }

    return `${dayLabel(start)} – ${dayLabel(end)} ${end.year}`;
}

export const customDateValue = 'custom';
export const anyDateValue = 'any';

export const dateFilterOptions: PublicEventFilterOption[] = [
    { value: anyDateValue, label: 'Any date' },
    { value: 'today', label: 'Today' },
    { value: 'tomorrow', label: 'Tomorrow' },
    { value: 'weekend', label: 'This weekend' },
    { value: 'next-seven-days', label: 'Next 7 days' },
    { value: 'month', label: 'This month' },
];
