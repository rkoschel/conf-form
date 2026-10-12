<?php
declare(strict_types=1);

/**
 * Versionshinweise für Admins (SPEC §7.10), neueste Version zuerst.
 * Jede nutzersichtbare Änderung bekommt hier einen Punkt: neue Version
 * (Nummer erhöhen, Datum JJJJ-MM-TT) oder Eintrag in der obersten, solange
 * sie noch nicht deployt ist. Abschnitte: new (Neu), changed (Geändert),
 * fixed (Behoben). Klartext, aus Sicht der Admins formuliert.
 */
const CHANGELOG = [
    [
        'version' => '1.4',
        'date' => '2026-10-11',
        'new' => [
            'Anfragen: Knopf „Details“ klappt unter der Anmeldung die Personen je Programmpunkt (inkl. Kinderbetreuung), den Verlauf (Anmeldung, Statuswechsel, Bearbeitungen und Absage per Link mit Datum, Status und ob eine E-Mail ging) und die ganze Nachricht auf.',
            'Veranstaltungen: Aktivieren und Deaktivieren – in der Liste und beim Speichern mit geändertem Schalter „Aktiv“ – erst nach Bestätigung im Dialog.',
            'Anmeldeformular: Sind nur automatisch angekreuzte Zielgruppen-Programmpunkte gewählt, fragt das Formular vor dem Absenden nach („Zurück und weitere auswählen“ / „Weiter und absenden“).',
            'Bestätigungs- und Wartelisten-Mails nennen die gewählten Programmpunkte mit Personenzahl und Kindern in der Kinderbetreuung.',
            'Versionshinweise im Admin-Menü mit Software-Stückliste (SBOM), auch als CycloneDX-Datei zum Herunterladen.',
            'Anmeldeformular: „An allen Programmpunkten teilnehmen“ kreuzt alle möglichen Programmpunkte auf einmal an.',
        ],
        'changed' => [
            'Anmeldeformular: Anzahl Teilnehmer als eine Zeile je Personengruppe mit Knöpfen − und +; die Zuordnung von Name und Feld bleibt auch bei langen Gruppennamen eindeutig.',
            'Anmeldeformular: Vorschläge für die Heimatversammlung erst ab zwei Zeichen, auf Handy und Computer gleich (auch Treffer mitten im Namen).',
            'Auswertung und Team-Seite: „Personen je Ort“ als senkrechtes Balkendiagramm (bestätigt/offen, größter Ort = 100 %, ohne Zahlen, kleine Orte unter „Andere“).',
            'Auswertung und Team-Seite: Überschrift „Bestätigte Anwesenheit je Programmpunkt“ statt „Voraussichtliche …“.',
            'Admin: Startseite nach der Anmeldung ist „Auswertungen“, ganz links im Menü; der Bereich heißt jetzt „Admin“.',
            'Auswertungen: „Anfragen nach Status“ steht ganz unten.',
            'Veranstaltungen: neue Spalte „Belegung“ mit der bestätigten Belegung in Prozent.',
            'Anfragen: Knöpfe Bestätigen (grün), Details (blau), Ablehnen (gelb); bereits ausgeführte Aktion grau. Bearbeiten (orange) und Löschen unter „Details“.',
            'Anfragen: Mögliche Dubletten mit 2️⃣ vor dem Namen statt ⚠ dahinter.',
        ],
    ],
    [
        'version' => '1.3',
        'date' => '2026-10-10',
        'new' => [
            'Einstellungen → Mailversand: Mail-Einstellungen ansehen (ohne Passwort) und Testmail mit ausführlichem Verbindungsprotokoll senden.',
            'Einstellungen → Mailversand: optionale Benachrichtigung an eine frei wählbare Adresse bei jeder neuen Anmeldung.',
        ],
        'fixed' => [
            'Anmeldeformular: Der Hinweis zur Kinderbetreuung erschien nicht bei Veranstaltungen ohne individuelle Aufteilung.',
        ],
    ],
    [
        'version' => '1.2',
        'date' => '2026-10-09',
        'new' => [
            'Zielgruppe je Programmpunkt: Programmpunkte nur für bestimmte Personengruppen, mit Hinweisen im Formular und „–“ in der Auswertung.',
            'Je Veranstaltung einstellbar, ob Teilnehmer ihre Anzahl individuell auf Programmpunkte aufteilen dürfen.',
            'Anmeldeformular: optionales Feld „Nachricht an uns“, in den Anfragen sichtbar.',
            'Öffentliche Seiten: feste Leiste mit „Zurück zur Hauptseite“.',
        ],
        'changed' => [
            'Anmeldeformular: Mindestens ein Programmpunkt muss gewählt sein; anfangs ist keiner ausgewählt.',
            'Anmeldeformular: Die Aufteilung wächst mit, wenn oben eine Anzahl erhöht wird.',
            'Hinweise zu Kinderbetreuung und Zielgruppe in einer gemeinsamen Infobox.',
            'Farbschema-Auswahl auf den öffentlichen Seiten im Fuß der Seite, Optionen nebeneinander.',
            'Team-Seite in drei klar getrennten Bereichen: zugesagte Anmeldungen, Belegung, Anfragen nach Status.',
        ],
        'fixed' => [
            'Steuerzeichen in Texteingaben werden entfernt.',
        ],
    ],
    [
        'version' => '1.1',
        'date' => '2026-10-08',
        'new' => [
            'Personengruppen je Veranstaltung: bis zu fünf Gruppen mit eigenen Namen (Erwachsene, Jugendliche, drei Kindergruppen); alle zählen zum Kontingent.',
        ],
        'changed' => [
            'Infoseite als Einladungskarte; Anmelde-, Ergebnis- und Absageseiten als Karten auf getöntem Hintergrund.',
        ],
    ],
    [
        'version' => '1.0',
        'date' => '2026-10-02',
        'new' => [
            'Infoseite mit Ablauf und Belegung in Prozent, Anmeldeformular mit Warteliste und automatischer Bestätigung für bevorzugte Orte, Absage per Link.',
            'Admin: Veranstaltungen, Anfragen (Bestätigen/Ablehnen mit Mail, Bearbeiten, Löschen), Auswertungen, Einstellungen mit anpassbaren Texten, Hilfe.',
            'Kinderbetreuung je Programmpunkt.',
            'Team-Zugang zur Auswertung per geheimem Link.',
            'Spamschutz ohne Captcha, Hell-/Dunkelmodus.',
        ],
    ],
];

const CHANGELOG_SECTIONS = ['new' => 'Neu', 'changed' => 'Geändert', 'fixed' => 'Behoben'];

/** Aktuelle Version der App (oberster Eintrag der Versionshinweise) */
function app_version(): string
{
    return CHANGELOG[0]['version'];
}
