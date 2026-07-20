import type { PublicEventVisualFixture } from '@/types/public-events';

/**
 * Stable visual-design data used until the MySQL/search response contract lands.
 * Keep public pages shaped around this interface; the integration layer will
 * replace the values without requiring the presentation to be rebuilt.
 */
export const publicEventFixtures: PublicEventVisualFixture[] = [
    {
        id: 'fixture-concert-berlin',
        title: 'Signal / Noise: Live after dark',
        description:
            'A late-night electronic set fills a converted power station with light, movement and deep bass.',
        category: 'concert',
        startsAt: '2026-08-21T19:30:00Z',
        dateLabel: '21 Aug',
        timeLabel: '21:30',
        timezoneLabel: 'CEST',
        venue: 'Kraftwerk Berlin',
        locationLabel: 'Berlin, Germany',
        latitude: 52.5107,
        longitude: 13.4197,
        image: {
            src: '/images/events/concert-industrial-after-dark/cover.webp',
            alt: 'Electronic performer playing to a crowd inside a converted brick hall',
        },
    },
    {
        id: 'fixture-exhibition-lisbon',
        title: 'Design in motion',
        description:
            'An exhibition of kinetic installations, experimental typography and moving light.',
        category: 'exhibition',
        startsAt: '2026-08-22T17:00:00Z',
        dateLabel: '22 Aug',
        timeLabel: '18:00',
        timezoneLabel: 'WEST',
        venue: 'Armazem 18',
        locationLabel: 'Lisbon, Portugal',
        latitude: 38.7223,
        longitude: -9.1393,
        image: {
            src: '/images/events/exhibition-adaptive-reuse-opening/cover.webp',
            alt: 'Visitors exploring a design exhibition inside a renovated industrial hall',
        },
    },
    {
        id: 'fixture-workshop-tokyo',
        title: 'Clay, colour, conversation',
        description:
            'A small-group ceramics workshop pairing hand-building techniques with a shared seasonal supper.',
        category: 'workshop',
        startsAt: '2026-08-23T07:00:00Z',
        dateLabel: '23 Aug',
        timeLabel: '16:00',
        timezoneLabel: 'JST',
        venue: 'Studio Ufer',
        locationLabel: 'Tokyo, Japan',
        latitude: 35.6762,
        longitude: 139.6503,
        image: {
            src: '/images/events/workshop-ceramic-studio/cover.webp',
            alt: 'Hands shaping clay around a communal ceramics studio table',
        },
    },
    {
        id: 'fixture-festival-melbourne',
        title: 'Long-table supper in the garden',
        description:
            'Local cooks and growers host an open-air dinner with music as evening settles over the garden.',
        category: 'festival',
        startsAt: '2026-08-24T09:00:00Z',
        dateLabel: '24 Aug',
        timeLabel: '19:00',
        timezoneLabel: 'AEST',
        venue: 'Princes Hill Community Garden',
        locationLabel: 'Melbourne, Australia',
        latitude: -37.8136,
        longitude: 144.9631,
        image: {
            src: '/images/events/festival-garden-long-table/cover.webp',
            alt: 'Guests gathering around a long outdoor table in a garden at dusk',
        },
    },
    {
        id: 'fixture-conference-mexico-city',
        title: 'Cities made for people',
        description:
            'Designers, organizers and planners trade practical ideas for more generous public spaces.',
        category: 'conference',
        startsAt: '2026-08-25T16:30:00Z',
        dateLabel: '25 Aug',
        timeLabel: '10:30',
        timezoneLabel: 'CST',
        venue: 'Foro Reforma',
        locationLabel: 'Mexico City, Mexico',
        latitude: 19.4326,
        longitude: -99.1332,
        image: {
            src: '/images/events/conference-timber-ideas-forum/cover.webp',
            alt: 'Speaker addressing an audience in a warm timber auditorium',
        },
    },
    {
        id: 'fixture-sports-new-york',
        title: 'Community track at sunset',
        description:
            'An all-level evening track session followed by food and conversation beside the field.',
        category: 'sports',
        startsAt: '2026-08-26T22:00:00Z',
        dateLabel: '26 Aug',
        timeLabel: '18:00',
        timezoneLabel: 'EDT',
        venue: 'Riverbank Track',
        locationLabel: 'New York, United States',
        latitude: 40.7128,
        longitude: -74.006,
        image: {
            src: '/images/events/sports-community-track-evening/cover.webp',
            alt: 'Runners meeting on a community athletics track in the evening',
        },
    },
];
