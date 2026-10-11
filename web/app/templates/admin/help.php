<?php
/*
 * Hilfe für Admins (SPEC §7.9): erklärt, was im Hintergrund passiert und
 * welche Auswirkungen Einstellungen haben. Feste deutsche Texte; Zahlen aus
 * Konstanten bzw. Config, damit die Hilfe zur Logik passt.
 */
$rateLimit = (array) config('rate_limit');
$sections = [
    'ablauf' => 'Ablauf auf einen Blick',
    'veranstaltungen' => 'Veranstaltungen',
    'kontingent' => 'Kontingent und automatische Bestätigung',
    'gruppen' => 'Personengruppen',
    'orte' => 'Bevorzugte Orte',
    'programm' => 'Programmpunkte, Zielgruppe und Kinderbetreuung',
    'anfragen' => 'Anfragen verwalten',
    'mails' => 'E-Mails',
    'absage' => 'Absage durch Teilnehmer',
    'oeffentlich' => 'Infoseite und Anmeldeformular',
    'auswertung' => 'Auswertung und Team-Zugang',
    'texte' => 'Einstellbare Texte',
    'spam' => 'Spamschutz',
    'daten' => 'Datum, Uhrzeit und Datenschutz',
];
?>
<div class="page-narrow">
  <h1 class="h3 mb-3">Hilfe</h1>
  <p class="lead">Was im Hintergrund passiert und welche Auswirkungen Einstellungen haben.</p>
  <p>Was sich zuletzt geändert hat, steht unter <a href="<?= e(url('admin/changes.php')) ?>">Versionshinweise</a> (aktuelle Version <?= e(app_version()) ?>), dort auch die Liste der verwendeten Fremdkomponenten (SBOM).</p>

  <nav class="card mb-5" aria-label="Inhalt">
    <div class="card-body">
      <h2 class="h6 text-body-secondary">Inhalt</h2>
      <ol class="mb-0 help-toc">
        <?php foreach ($sections as $id => $label): ?>
          <li><a href="#<?= e($id) ?>"><?= e($label) ?></a></li>
        <?php endforeach ?>
      </ol>
    </div>
  </nav>

  <section id="ablauf" class="help-section">
    <h2 class="h4">Ablauf auf einen Blick</h2>
    <ol>
      <li><strong>Veranstaltung anlegen und aktivieren</strong> (Veranstaltungen). Nur die aktive Veranstaltung ist öffentlich sichtbar.</li>
      <li>Interessierte sehen die <strong>Infoseite</strong> mit Eckdaten, Ablauf und Belegung und melden sich über das <strong>Anmeldeformular</strong> an.</li>
      <li>Das System entscheidet sofort: <strong>bestätigt</strong> oder <strong>Warteliste</strong> (siehe Kontingent) und schickt, falls eine E-Mail-Adresse angegeben ist, eine Eingangsbestätigung.</li>
      <li>Du prüfst die <strong>Anfragen</strong>, bestätigst Personen von der Warteliste oder lehnst ab – jeweils wahlweise mit E-Mail.</li>
      <li>Teilnehmer können über den Link in ihrer E-Mail <strong>selbst absagen</strong>; der Platz wird frei.</li>
      <li>Die <strong>Auswertung</strong> zeigt die Zahlen für die Vorbereitung, auf Wunsch auch für Mitarbeiter per Team-Link.</li>
    </ol>
  </section>

  <section id="veranstaltungen" class="help-section">
    <h2 class="h4">Veranstaltungen</h2>
    <dl>
      <dt>Belegung in der Liste</dt>
      <dd>Bestätigte Personen aller Gruppen in Prozent der maximalen Teilnehmerzahl; über 100 % rot. Offene Anfragen zählen hier nicht (siehe Auswertungen).</dd>
      <dt>Aktiv</dt>
      <dd>Es ist immer höchstens <strong>eine</strong> Veranstaltung aktiv. Aktivierst du eine, werden alle anderen deaktiviert. Infoseite und Anmeldeformular zeigen nur die aktive Veranstaltung. Ist keine aktiv, erscheint der Text aus Einstellungen → Allgemein. Aktivieren und Deaktivieren – in der Liste wie beim Speichern mit geändertem Schalter „Aktiv“ – passiert erst, nachdem du es im Dialog bestätigt hast.</dd>

      <dt>Anmeldefrist</dt>
      <dd>Gilt in der <strong>Zeitzone der Veranstaltung</strong>; die angegebene Minute zählt noch mit (Frist 23:59 → Anmeldung bis 23:59:59). Die Frist darf spätestens zum Beginn des ersten Programmpunkts enden, ohne Ablauf spätestens um 23:59 Uhr am Veranstaltungstag. Nach Ablauf zeigt die Infoseite „Der Anmeldezeitraum ist abgelaufen.“, der Button „Anmelden“ verschwindet und das Formular nimmt nichts mehr an. Im Admin kannst du weiterhin alles bearbeiten.</dd>

      <dt>Max. Teilnehmer</dt>
      <dd>Das <strong>Kontingent</strong>. Alle Personengruppen zählen dazu (siehe Kontingent).</dd>

      <dt>Personengruppen</dt>
      <dd>Welche der fünf Gruppen die Veranstaltung nutzt und wie sie heißen (siehe Personengruppen). Eine neue Veranstaltung übernimmt Auswahl und Namen der zuletzt angelegten.</dd>

      <dt>Veranstalter (Name, E-Mail)</dt>
      <dd>Die E-Mail-Adresse ist die <strong>Antwortadresse</strong> aller Mails zu dieser Veranstaltung. Der Name erscheint in der Grußformel (Platzhalter <code>{organizer}</code>); ohne Namen wird die Ersatz-Unterschrift aus den Einstellungen verwendet.</dd>

      <dt>Ablauf, Zielgruppe, Kinderbetreuung und Auswahl der Personengruppen</dt>
      <dd>Änderbar, solange es <strong>keine Anmeldungen</strong> gibt. Sobald die erste Anmeldung eingegangen ist, sind Programmpunkte, Zielgruppe, Kinderbetreuung und die Auswahl der Personengruppen gesperrt, weil die gespeicherten Angaben der Teilnehmer sonst nicht mehr passen würden. Die <strong>Namen</strong> der Gruppen und alle anderen Felder bleiben änderbar.</dd>

      <dt>Löschen</dt>
      <dd>Löscht die Veranstaltung mit <strong>allen</strong> Anmeldungen, Programmpunkt-Angaben und dem Mail-Protokoll – endgültig, ohne Mail an die Teilnehmer.</dd>
    </dl>
  </section>

  <section id="kontingent" class="help-section">
    <h2 class="h4">Kontingent und automatische Bestätigung</h2>
    <dl>
      <dt>Gruppengröße</dt>
      <dd>Summe <strong>aller</strong> Personen der Anmeldung, über alle Personengruppen. Jede Person braucht einen Platz – auch die jüngste Kindergruppe.</dd>

      <dt>Belegt</dt>
      <dd>Summe der Gruppengrößen aller <strong>bestätigten</strong> Anmeldungen. Offene, stornierte und abgelehnte belegen keinen Platz.</dd>

      <dt>Automatisch bestätigt …</dt>
      <dd>
        … wird eine neue Anmeldung nur, wenn <strong>alle</strong> drei Bedingungen erfüllt sind:
        <ul class="mb-0">
          <li>die Heimatversammlung ist ein <strong>bevorzugter Ort</strong>,</li>
          <li>die Gruppe passt noch ins Kontingent (belegt + Gruppengröße ≤ max. Teilnehmer),</li>
          <li>die Anmeldung umfasst höchstens <?= AUTO_CONFIRM_MAX_PERSONS ?> Personen insgesamt.</li>
        </ul>
        Sonst kommt sie auf die <strong>Warteliste</strong> (Status „offen“) – auch bei bevorzugtem Ort, wenn das Kontingent nicht reicht.
      </dd>

      <dt>Gleichzeitige Anmeldungen</dt>
      <dd>Werden nacheinander verarbeitet; dadurch kann das automatische Bestätigen das Kontingent nicht überschreiten.</dd>

      <dt>Obergrenze im Formular</dt>
      <dd>Öffentlich kann eine Anmeldung höchstens so viele Personen umfassen wie die Veranstaltung Plätze hat; die Zahl selbst wird dabei nicht genannt. Im Admin gibt es keine Obergrenze.</dd>

      <dt>Überbuchen</dt>
      <dd>Du darfst im Admin über das Kontingent hinaus bestätigen oder Anmeldungen vergrößern – das System <strong>warnt nur</strong>.</dd>

      <dt>Kein automatisches Nachrücken</dt>
      <dd>Wird ein Platz frei (Absage, Ablehnung, Änderung), rückt niemand automatisch nach. Du entscheidest, wen du von der Warteliste bestätigst.</dd>
    </dl>
  </section>

  <section id="gruppen" class="help-section">
    <h2 class="h4">Personengruppen</h2>
    <dl>
      <dt>Fünf feste Plätze, frei benannt</dt>
      <dd>Eine Veranstaltung unterscheidet bis zu fünf Gruppen: Gruppe 1 ist die Art <strong>Erwachsene</strong>, Gruppe 2 <strong>Jugendliche</strong>, die Gruppen 3–5 sind <strong>Kindergruppen</strong>. Welche Gruppen genutzt werden und wie sie heißen (z. B. „Kinder 3–6“), stellst du je Veranstaltung ein; Standardnamen sind <?= e(implode(', ', person_groups_default())) ?>.</dd>

      <dt>Wo die Gruppen wirken</dt>
      <dd>Nur die gewählten Gruppen erscheinen im Anmeldeformular (mit ihren Namen), in den Mails, in den Anfragen und in der Auswertung. Nicht gewählte Gruppen werden bei Anmeldungen als 0 gespeichert.</dd>

      <dt>Umbenennen</dt>
      <dd>Namen sind reine Beschriftungen und jederzeit änderbar; gespeicherte Zahlen bleiben gleich und erscheinen danach überall unter dem neuen Namen.</dd>

      <dt>Kinderbetreuung</dt>
      <dd>Nur Kindergruppen (3–5) können eine Kinderbetreuung bekommen, und nur, wenn sie bei der Veranstaltung gewählt sind.</dd>
    </dl>
  </section>

  <section id="orte" class="help-section">
    <h2 class="h4">Bevorzugte Orte</h2>
    <ul>
      <li>Gelten für <strong>alle Veranstaltungen</strong> (Einstellungen → Allgemein), ein Ort pro Zeile.</li>
      <li>Erscheinen im Anmeldeformular als <strong>Vorschläge</strong> für die Heimatversammlung, sobald jemand mindestens zwei Buchstaben eingegeben hat (auf Handy und Computer gleich; auch Treffer mitten im Namen, z. B. „ue“ für „Hamm-Uentrop“). Freitext ist trotzdem möglich.</li>
      <li>Verglichen wird ohne Rücksicht auf Groß-/Kleinschreibung und überflüssige Leerzeichen („hamm“ = „Hamm“).</li>
      <li>Nur Anmeldungen aus diesen Orten können <strong>automatisch bestätigt</strong> werden, alle anderen kommen auf die Warteliste.</li>
      <li>Änderungen wirken nur auf <strong>neue</strong> Anmeldungen; der Status bestehender Anmeldungen bleibt unverändert. Die Hervorhebung (fett) in Anfragen und Auswertung richtet sich nach der aktuellen Liste.</li>
    </ul>
  </section>

  <section id="programm" class="help-section">
    <h2 class="h4">Programmpunkte, Zielgruppe und Kinderbetreuung</h2>
    <dl>
      <dt>Voraussichtliche Anwesenheit</dt>
      <dd>Standard: je Programmpunkt ein Häkchen, das für die ganze Gruppe gilt. Anfangs ist <strong>kein</strong> Programmpunkt ausgewählt, damit bewusst entschieden wird; ohne mindestens einen Programmpunkt lässt sich die Anmeldung nicht absenden (auch nicht im Admin-Bearbeiten). Ob Teilnehmer zusätzlich individuell aufteilen dürfen, stellst du je Veranstaltung ein („Individuelle Aufteilung auf Programmpunkte erlauben“, jederzeit änderbar; bestehende Aufteilungen bleiben erhalten und sind im Bearbeiten weiter sichtbar). Mit „Anzahl individuell aufteilen“ geben Teilnehmer je Programmpunkt die Anzahl pro Personengruppe an (höchstens die oben angegebene Anzahl). Ändert man oben eine Anzahl, passt sich die Aufteilung an: Beim Verringern werden zu hohe Werte gekappt, beim Erhöhen wachsen Felder mit, die auf dem bisherigen Maximum standen; bewusst kleinere Werte und Kinder in der Betreuung (0) bleiben. Gespeichert wird immer die Anzahl je Programmpunkt und Personengruppe.</dd>

      <dt>Kinderbetreuung</dt>
      <dd>Je Programmpunkt einstellbar mit den betreuten Kindergruppen der Veranstaltung. Infoseite und Formular zeigen dann „Parallel Kinderbetreuung für …“ mit den Namen dieser Gruppen.</dd>

      <dt>Zielgruppe</dt>
      <dd>
        Je Programmpunkt kannst du mit „Zielgruppe festlegen“ bestimmen, für welche Personengruppen er gilt (mindestens eine der bei der Veranstaltung gewählten Gruppen).
        Wählst du alle Gruppen, gilt der Programmpunkt für alle – dann wird nichts gespeichert und es erscheint kein Hinweis.
        Infoseite und Formular zeigen „Zielgruppe: …“ mit den Namen der Gruppen.
        <strong>Zielgruppe und Kinderbetreuung schließen sich aus:</strong> Wenn einer der beiden Schalter an ist, ist der andere gesperrt.
        Wählst du bei der Veranstaltung eine Personengruppe ab, fällt sie auch aus den Zielgruppen heraus; bleibt keine übrig, meldet das Formular „Zielgruppe auswählen“.
        Die Zielgruppe hat keinen Einfluss auf Kontingent, öffentliche Belegung und Mails.
      </dd>

      <dt>Zielgruppe im Anmeldeformular</dt>
      <dd>
        Bei einem Programmpunkt mit Zielgruppe zählen nur Personen dieser Gruppen; die übrigen Personen der Anmeldung zählen dort <strong>nirgends</strong> (auch nicht in einer Betreuung).
        Das Formular erklärt das unter den Anzahlen, aber nur, wenn mindestens eine Person der Zielgruppe eingetragen ist <strong>und</strong> außerdem Personen, die dort nicht dazugehören. Der Hinweis steht zusammen mit dem Hinweis zur Kinderbetreuung in einer gemeinsamen Infobox.
        Gehört <strong>niemand</strong> aus der Anmeldung zur Zielgruppe, ist das Häkchen ausgegraut und abgewählt („Niemand aus deiner Anmeldung gehört zur Zielgruppe.“). Sobald jemand aus der Zielgruppe eingetragen wird, kreuzt das Formular den Programmpunkt <strong>automatisch an</strong>; wer ihn bewusst abwählt, behält das so. Beim Öffnen einer bestehenden Anmeldung im Admin wird nichts automatisch angekreuzt. Sind beim Absenden <strong>nur</strong> solche automatisch angekreuzten Programmpunkte ausgewählt, fragt das Formular nach („Programmpunkte prüfen“); abschicken lässt es sich danach mit „Weiter und absenden“.
        In der individuellen Aufteilung gibt es dort nur Felder der Zielgruppe.
        Serverseitig gilt dasselbe (auch im Admin-Bearbeiten): Angekreuzt, aber niemand aus der Zielgruppe → 0 beim Programmpunkt, kein Fehler; ein solcher Programmpunkt zählt nicht für „mindestens ein Programmpunkt“.
      </dd>

      <dt>Wer wird wo gezählt?</dt>
      <dd>
        <table class="table table-sm small mt-1 mb-1">
          <thead>
            <tr><th>Fall</th><th>Beim Programmpunkt</th><th>In der Kinderbetreuung</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Häkchen gesetzt</td>
              <td>alle Gruppen ohne Betreuungsangebot</td>
              <td>Kinder der betreuten Kindergruppen</td>
            </tr>
            <tr>
              <td>Individuelle Aufteilung</td>
              <td>die eingetragenen Zahlen</td>
              <td>je betreuter Gruppe: Anzahl oben − eingetragene Zahl, sofern jemand der Anmeldung den Programmpunkt besucht</td>
            </tr>
            <tr>
              <td>Programmpunkt nicht besucht</td>
              <td>niemand</td>
              <td>niemand</td>
            </tr>
          </tbody>
        </table>
        In der Aufteilung sind betreute Kindergruppen deshalb mit 0 vorbelegt. Bei einem Programmpunkt mit Zielgruppe zählen nur deren Gruppen (siehe oben); eine Betreuung gibt es dort nicht.
      </dd>
    </dl>
  </section>

  <section id="anfragen" class="help-section">
    <h2 class="h4">Anfragen verwalten</h2>
    <dl>
      <dt>Status</dt>
      <dd>
        <strong>offen</strong> = Warteliste, belegt keinen Platz ·
        <strong>bestätigt</strong> = belegt einen Platz ·
        <strong>storniert</strong> = vom Teilnehmer abgesagt ·
        <strong>abgelehnt</strong> = von dir abgelehnt.
      </dd>

      <dt>Bestätigen und Ablehnen</dt>
      <dd>Bestätigen geht aus jedem Status (das ist auch das „Nachrücken“ von der Warteliste), Ablehnen aus jedem Status außer abgelehnt. Ein Dialog fragt, ob eine E-Mail verschickt werden soll; ohne E-Mail-Adresse erscheint stattdessen die Telefonnummer. Würde eine Bestätigung das Kontingent überschreiten, warnt der Dialog vorher.</dd>

      <dt>Bearbeiten</dt>
      <dd>Alle Angaben inkl. Status und Aufteilung sind änderbar. Dabei wird <strong>keine</strong> E-Mail verschickt – für Bestätigen/Ablehnen mit Mail die Aktionen in der Liste verwenden.</dd>

      <dt>Löschen</dt>
      <dd>Endgültig, inklusive Programmpunkt-Angaben und Mail-Protokoll, ohne E-Mail. Für eine nachvollziehbare Absage besser „Ablehnen“ verwenden.</dd>

      <dt>Knöpfe in der Liste</dt>
      <dd>In jeder Zeile: Bestätigen, Details, Ablehnen. Ist eine Anmeldung schon bestätigt bzw. abgelehnt, ist der passende Knopf grau; der andere bleibt nutzbar (z. B. eine bestätigte Anmeldung doch ablehnen). Bearbeiten und Löschen findest du unter „Details“.</dd>
      <dt>Details</dt>
      <dd>Klappt unter der Anmeldung auf: links je Programmpunkt, wer dabei ist (je Gruppe) und wie viele Kinder in der Kinderbetreuung sind; rechts der <strong>Verlauf</strong> mit Datum, Aktion (Anmeldung, Statuswechsel durch dich, Bearbeitet, Absage per Link), Status danach und ob eine E-Mail ging (ja, nein oder fehlgeschlagen). Jedes Speichern im Bearbeiten erscheint dort, auch ohne Statusänderung. Darunter die ganze Nachricht sowie Bearbeiten und Löschen. Für Anmeldungen von vor diesem Update ist der Verlauf nur so weit da, wie Mails verschickt wurden.</dd>
      <dt>Mögliche Dubletten (2️⃣)</dt>
      <dd>Markiert werden Anmeldungen derselben Veranstaltung mit gleicher E-Mail-Adresse oder gleichem Vor- und Nachnamen – ohne Rücksicht auf Groß-/Kleinschreibung und Leerzeichen, über alle Status. Das ist nur ein Hinweis; es wird nichts automatisch zusammengeführt.</dd>

      <dt>Nachricht an uns</dt>
      <dd>Optionales Freitextfeld in der Anmeldung (höchstens <?= REGISTRATION_MESSAGE_MAX ?> Zeichen). Steht in der Liste gekürzt unter dem Namen (ganzer Text beim Darüberfahren) und im Bearbeiten-Formular; nicht in der Auswertung und nicht in Mails. Den grauen Platzhalter stellst du unter Einstellungen → Anmeldeformular ein.</dd>

      <dt>Ohne E-Mail-Adresse</dt>
      <dd>Wer „Ich habe keine E-Mail-Adresse“ wählt, muss eine Telefonnummer angeben und bekommt nie eine E-Mail – bitte telefonisch informieren.</dd>
    </dl>
  </section>

  <section id="mails" class="help-section">
    <h2 class="h4">E-Mails</h2>
    <table class="table table-sm">
      <thead>
        <tr><th>Anlass</th><th>Wann</th><th>Absage-Link</th></tr>
      </thead>
      <tbody>
        <tr><td>Eingang, bestätigt</td><td>automatisch nach der Anmeldung</td><td>ja</td></tr>
        <tr><td>Eingang, Warteliste</td><td>automatisch nach der Anmeldung</td><td>ja (Anfrage zurückziehen)</td></tr>
        <tr><td>Bestätigung</td><td>nur wenn du im Dialog „mit E-Mail“ wählst</td><td>ja</td></tr>
        <tr><td>Absage</td><td>nur wenn du im Dialog „mit E-Mail“ wählst</td><td>nein</td></tr>
      </tbody>
    </table>
    <ul>
      <li>Mails gehen nur an Anmeldungen <strong>mit</strong> E-Mail-Adresse. Bearbeiten, Löschen und Absagen per Link verschicken keine Mail.</li>
      <li>Absender ist das konfigurierte Postfach, <strong>Antworten</strong> gehen an die Veranstalter-E-Mail der Veranstaltung.</li>
      <li>Die Mails enthalten die Eckdaten der Veranstaltung und die angemeldeten Personen, Bestätigungen und Warteliste zusätzlich die gewählten Programmpunkte (mit Kindern in der Kinderbetreuung); die Texte stellst du unter Einstellungen → E-Mails ein.</li>
      <li>Jeder Versand wird protokolliert: Spalte „Mail“ in den Anfragen zeigt den letzten Versand mit ✓ oder ✗ (Fehlermeldung beim Darüberfahren). Fehlgeschlagene Mails werden <strong>nicht</strong> automatisch wiederholt.</li>
      <li><strong>Benachrichtigung an dich:</strong> Unter Einstellungen → Mailversand kannst du einschalten, dass bei jeder neuen Anmeldung eine E-Mail an eine Adresse deiner Wahl geht – mit Status, Kontakt, Personen, Programmpunkten, Nachricht und einem Link zur Anmeldung im Admin. „Antworten“ geht direkt an den Teilnehmer. Schlägt diese Mail fehl, wird die Anmeldung trotzdem gespeichert.</li>
      <li><strong>Mailversand testen:</strong> Unter Einstellungen → Mailversand schickst du eine Testmail an eine beliebige Adresse. Danach siehst du dort, ob der Mailserver sie angenommen hat, und ein ausführliches Protokoll der Verbindung (Server, Verschlüsselung, Anmeldung, Antworten des Servers). Zugangsdaten sind darin ausgeblendet; das Protokoll wird nicht gespeichert. Die Mail-Einstellungen selbst stehen in der Konfigurationsdatei auf dem Server.</li>
    </ul>
  </section>

  <section id="absage" class="help-section">
    <h2 class="h4">Absage durch Teilnehmer</h2>
    <ul>
      <li>Jede Anmeldung hat einen geheimen Absage-Link, der in den E-Mails steht.</li>
      <li>Die Seite zeigt Name und Veranstaltung; abgesagt wird erst mit dem Button „Teilnahme absagen“ (so sagen Link-Vorschauen in Mailprogrammen nicht versehentlich ab).</li>
      <li>Absagen geht aus „offen“ und „bestätigt“; danach ist der Status „storniert“ und ein bestätigter Platz wird frei.</li>
      <li>Es geht <strong>keine</strong> Mail raus und niemand rückt automatisch nach.</li>
    </ul>
  </section>

  <section id="oeffentlich" class="help-section">
    <h2 class="h4">Infoseite und Anmeldeformular</h2>
    <dl>
      <dt>Leiste „Zurück zur Hauptseite“</dt>
      <dd>Auf allen öffentlichen Seiten (nicht im Admin) steht oben eine feste Leiste, die beim Scrollen sichtbar bleibt. Sie enthält nur den Button „Zurück zur Hauptseite“. Das Ziel ist <code>home_url</code> in der Konfiguration (<code>config.php</code> auf dem Server, nicht hier einstellbar); ohne Eintrag führt der Button zur Wurzel der Domain.</dd>

      <dt>Infoseite</dt>
      <dd>Zeigt Titel, Datum, Ort, Frist, Beschreibung, Ablauf (mit Kinderbetreuung bzw. Zielgruppe) und den Link zur allgemeinen Info-Seite. Solange die Anmeldung offen ist, kommen die Belegung und der Button „Anmelden“ dazu.</dd>

      <dt>Belegung</dt>
      <dd>Bestätigt / Warteliste / frei – <strong>nur in ganzen Prozent</strong> der Kapazität (alle Personengruppen), nie mit absoluten Zahlen. Steht „frei“ bei 0 %, erscheint der Hinweis „ausgebucht“ aus den Einstellungen; anmelden bleibt möglich (Warteliste).</dd>

      <dt>Formular</dt>
      <dd>Prüft Eingaben schon beim Tippen (z. B. Aufteilung höchstens bis zur Anzahl oben) und immer noch einmal auf dem Server. Nach dem Absenden sieht der Teilnehmer „bestätigt“ oder „Warteliste“ mit den Texten aus den Einstellungen.</dd>
    </dl>
  </section>

  <section id="auswertung" class="help-section">
    <h2 class="h4">Auswertung und Team-Zugang</h2>
    <dl>
      <dt>Was wird wie gezählt?</dt>
      <dd>
        <ul class="mb-0">
          <li><strong>Gesamtbelegung:</strong> bestätigte Personen aller Gruppen; zusätzlich offene Anfragen (gelb), um zu sehen, wie voll es würde, wenn alle bestätigt würden.</li>
          <li><strong>Je Personengruppe:</strong> ein Balken pro gewählter Gruppe mit bestätigten und offenen Personen, im selben Maßstab wie die Gesamtbelegung (Kapazität).</li>
          <li><strong>Anfragen nach Status:</strong> alle Personen und Anmeldungen.</li>
          <li><strong>Personen je Ort:</strong> senkrechte Balken mit bestätigten (blau) und offenen Personen (gelb) je Ort, ohne Zahlen. Der Ort mit den meisten Personen ist der volle Balken, die anderen relativ dazu. Orte mit weniger als 5 % davon stehen zusammen unter „Andere“.</li>
          <li><strong>Programmpunkte und Kinderbetreuung:</strong> nur bestätigte Anmeldungen. Bei einem Programmpunkt mit Zielgruppe stehen die Gruppen außerhalb der Zielgruppe als „–“.</li>
        </ul>
      </dd>

      <dt>Team-Zugang</dt>
      <dd>Ein geheimer Link (Einstellungen → Team-Zugang) zeigt Mitarbeitern die Auswertung der <strong>aktiven</strong> Veranstaltung ohne Login, nur lesend, mit absoluten Zahlen. „Neuen Link erzeugen“ macht den alten sofort ungültig, „Deaktivieren“ schaltet den Zugang ab. Ohne gültigen Link meldet die Seite „nicht gefunden“. Die Seite hat drei abgegrenzte Bereiche: <strong>Zugesagte Anmeldungen</strong> (Anwesenheit und Kinderbetreuung je Programmpunkt, nur bestätigte), <strong>Gesamtbelegung und Anmeldungen</strong> und <strong>Anfragen nach Status</strong>. Die Auswertung im Admin ist anders angeordnet.</dd>
    </dl>
  </section>

  <section id="texte" class="help-section">
    <h2 class="h4">Einstellbare Texte</h2>
    <ul>
      <li>Ein <strong>leeres Feld</strong> bedeutet: Standardtext (grau angezeigt). So bleiben Verbesserungen am Standardtext automatisch wirksam.</li>
      <li>Änderungen gelten sofort für alle neuen Anzeigen und Mails – auch für die aktive Veranstaltung.</li>
      <li>In Mail-Texten werden Platzhalter wie <code>{first_name}</code> oder <code>{title}</code> ersetzt (Liste unter Einstellungen → E-Mails).</li>
      <li>Klartext; Zeilenumbrüche bleiben erhalten, HTML wird nicht ausgeführt.</li>
    </ul>
  </section>

  <section id="spam" class="help-section">
    <h2 class="h4">Spamschutz</h2>
    <dl>
      <dt>Verstecktes Feld</dt>
      <dd>Bots füllen ein für Menschen unsichtbares Feld aus. Solche Anmeldungen werden still verworfen: Der Absender sieht eine Erfolgsmeldung, gespeichert und verschickt wird nichts.</dd>

      <dt>Zeitstempel</dt>
      <dd>Das Formular muss zwischen <?= SPAM_MIN_SECONDS ?> Sekunden und <?= SPAM_MAX_SECONDS / 3600 ?> Stunden nach dem Aufruf abgeschickt werden. Sonst: „Bitte Formular neu laden und erneut absenden.“</dd>

      <dt>Begrenzung je Anschluss</dt>
      <dd>Höchstens <?= (int) ($rateLimit['max_attempts'] ?? 0) ?> Absendeversuche je <?= (int) ($rateLimit['window_minutes'] ?? 0) ?> Minuten von derselben IP-Adresse. Der Wert ist bewusst großzügig, weil mehrere Familien im selben WLAN dieselbe Adresse haben können.</dd>
    </dl>
  </section>

  <section id="daten" class="help-section">
    <h2 class="h4">Datum, Uhrzeit und Datenschutz</h2>
    <ul>
      <li>Datum immer <strong>TT.MM.JJJJ</strong>, Uhrzeit immer <strong>24 h</strong> (HH:MM). Kurzformen wie „1.5.2027“ oder „9:30“ werden ergänzt.</li>
      <li>Frist und Anzeigen richten sich nach der <strong>Zeitzone der Veranstaltung</strong>; Eingangszeiten werden in dieser Zeitzone angezeigt.</li>
      <li>IP-Adressen werden nie im Klartext gespeichert, sondern nur als nicht zurückrechenbare Prüfsumme und nur für die Dauer der Spam-Begrenzung.</li>
      <li>Anmeldedaten werden nicht automatisch gelöscht. Nach der Veranstaltung bitte einzelne Anmeldungen oder die ganze Veranstaltung löschen.</li>
      <li>Admin-Seiten werden vom Browser nicht zwischengespeichert.</li>
    </ul>
  </section>
</div>
