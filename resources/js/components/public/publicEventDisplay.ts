import type {
    PublicEventCategory,
    PublicEventVisualFixture,
} from '@/types/public-events';

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/**
 * Fixture instants are authored so their UTC calendar date matches the
 * event-local dateLabel, so the placeholder UI can derive a stable weekday
 * without a timezone library. Backend integration should keep providing
 * display-ready date/time labels alongside the raw instant.
 */
export function eventWeekday(event: PublicEventVisualFixture): string {
    return WEEKDAY_LABELS[new Date(event.startsAt).getUTCDay()];
}

export function eventCategoryLabel(category: PublicEventCategory): string {
    return category.charAt(0).toUpperCase() + category.slice(1);
}

/**
 * Every set in the local image catalogue pairs its cover with a detail
 * shot. Deriving the sibling keeps the two-image presentation visible
 * until the backend supplies a real gallery payload.
 */
export function eventDetailImageSrc(event: PublicEventVisualFixture): string {
    return event.image.src.replace(/cover\.webp$/, 'detail.webp');
}

export interface EventDateParts {
    day: string;
    month: string;
}

export function eventDateParts(
    event: PublicEventVisualFixture,
): EventDateParts {
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

/** Select value that switches the date filter to the custom range picker. */
export const customDateValue = 'custom';

/**
 * Placeholder choices for the not-yet-wired filter controls. Real options
 * arrive with the search integration; only the control shapes matter here.
 */
export const dateFilterOptions = [
    'Any date',
    'Today',
    'Tomorrow',
    'This weekend',
    'Next 7 days',
    'This month',
];

export const locationFilterOptions = [
    'Anywhere',
    'Berlin',
    'Lisbon',
    'Melbourne',
    'Mexico City',
    'New York',
    'Tokyo',
];

export const categoryFilterOptions = [
    'All categories',
    'Concert',
    'Conference',
    'Exhibition',
    'Festival',
    'Meetup',
    'Networking',
    'Sports',
    'Workshop',
];
