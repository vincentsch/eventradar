export type PublicEventCategory =
    | 'concert'
    | 'conference'
    | 'meetup'
    | 'workshop'
    | 'festival'
    | 'sports'
    | 'networking'
    | 'exhibition';

export interface PublicEventVisualFixture {
    id: string;
    title: string;
    description: string;
    category: PublicEventCategory;
    startsAt: string;
    dateLabel: string;
    timeLabel: string;
    timezoneLabel: string;
    venue: string;
    locationLabel: string;
    latitude: number;
    longitude: number;
    image: {
        src: string;
        alt: string;
    };
}
