<?php
declare(strict_types=1);

// Deutsche UI-Texte für englische DB-Werte (SPEC §8: zentral an einer Stelle)

/** Spalte → Bezeichnung, in der Reihenfolge des Formulars */
const AGE_GROUPS = [
    'group_1' => 'Erwachsene',
    'group_2' => 'Jugendliche ab 13',
    'group_3' => 'Kinder 7–12',
    'group_4' => 'Kinder 3–6',
    'group_5' => 'Kinder 0–2',
];

/** Altersgruppen mit möglicher Kinderbetreuung (SPEC §7.1), jüngste zuerst: Spalte → [von, bis] Jahre */
const CHILDCARE_AGE_GROUPS = [
    'group_5' => [0, 2],
    'group_4' => [3, 6],
    'group_3' => [7, 12],
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
