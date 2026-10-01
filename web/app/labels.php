<?php
declare(strict_types=1);

// Deutsche UI-Texte für englische DB-Werte (SPEC §8: zentral an einer Stelle)

/** Spalte → Bezeichnung, in der Reihenfolge des Formulars */
const AGE_GROUPS = [
    'adults' => 'Erwachsene',
    'youth' => 'Jugendliche ab 13',
    'kids_7_12' => 'Kinder 7–12',
    'kids_3_6' => 'Kinder 3–6',
    'kids_0_2' => 'Kinder 0–2',
];

const MAIL_TYPES = [
    'received_confirmed' => 'Eingang (bestätigt)',
    'received_waitlist' => 'Eingang (Warteliste)',
    'confirmed' => 'Bestätigung',
    'rejected' => 'Absage',
];

const WEEKDAYS = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
