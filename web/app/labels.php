<?php
declare(strict_types=1);

// Deutsche UI-Texte für englische DB-Werte (SPEC §8: zentral an einer Stelle)

/**
 * Personengruppen (SPEC §5.1): fünf feste Plätze mit Art und Standardnamen.
 * Welche Gruppen eine Veranstaltung nutzt und wie sie heißen, steht in
 * events.person_groups (siehe event_groups()). Reihenfolge = Formular.
 */
const PERSON_GROUPS = [
    'group_1' => ['type' => 'adults', 'default' => 'Erwachsene'],
    'group_2' => ['type' => 'youth', 'default' => 'Jugendliche'],
    'group_3' => ['type' => 'kids', 'default' => 'Kindergruppe 1'],
    'group_4' => ['type' => 'kids', 'default' => 'Kindergruppe 2'],
    'group_5' => ['type' => 'kids', 'default' => 'Kindergruppe 3'],
];

/** Art einer Personengruppe; Kinderbetreuung nur für 'kids' */
const PERSON_GROUP_TYPES = [
    'adults' => 'Erwachsene',
    'youth' => 'Jugendliche',
    'kids' => 'Kindergruppe',
];

/** Status einer Anmeldung, in der Reihenfolge der UI (SPEC §7.2) */
const STATUS_LABELS = [
    'pending' => 'offen',
    'confirmed' => 'bestätigt',
    'cancelled' => 'storniert',
    'rejected' => 'abgelehnt',
];

const MAIL_TYPES = [
    'received_confirmed' => 'Eingang (bestätigt)',
    'received_waitlist' => 'Eingang (Warteliste)',
    'confirmed' => 'Bestätigung',
    'rejected' => 'Absage',
];

const WEEKDAYS = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
