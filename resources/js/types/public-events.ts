export type PublicEventCategory =
    | 'concert'
    | 'conference'
    | 'meetup'
    | 'workshop'
    | 'festival'
    | 'sports'
    | 'networking'
    | 'exhibition';

export interface PublicEventImage {
    src: string;
    alt: string;
}

export interface PublicEvent {
    id: string;
    status: 'published' | 'sold_out';
    href: string;
    title: string;
    description: string;
    category: PublicEventCategory;
    startsAt: string;
    localDate: string;
    dateLabel: string;
    timeLabel: string;
    timezoneLabel: string;
    timezone: string;
    venue: string;
    locationLabel: string;
    latitude: number | null;
    longitude: number | null;
    image: PublicEventImage;
    detailImage: PublicEventImage;
}

export interface PublicEventQuery {
    q: string | null;
    type: string[];
    location: string[];
    from: string | null;
    to: string | null;
    ongoing: boolean;
}

export type PublicEventParameters = Record<string, string | string[]>;

export interface PublicEventFilterOption {
    value: string;
    label: string;
}

export interface PublicEventFilterOptions {
    types: PublicEventFilterOption[];
    locations: PublicEventFilterOption[];
}

export interface PublicEventCollection {
    data: PublicEvent[];
}

export interface MapBounds {
    north: number;
    south: number;
    east: number;
    west: number;
}
