// Anmeldeformular: „keine E-Mail“-Umschaltung, individuelle Aufteilung auf
// Programmpunkte und Sofortprüfung der Anzahlen beim Tippen (SPEC §5.1).
// Ohne JS funktioniert das Formular weiter, die Prüfung erfolgt immer auch
// serverseitig.

const form = document.getElementById('register-form');
if (form) {
  const noEmail = form.querySelector('#f-no_email');
  const customSplit = form.querySelector('#f-custom_split');

  const count = (group) => {
    const value = parseInt(form.querySelector(`[data-count="${group}"]`).value, 10);
    return Number.isNaN(value) || value < 0 ? 0 : value;
  };

  // Fehlermeldung direkt am Feld (Bootstrap is-invalid + invalid-feedback);
  // eine Meldung vom Server am selben Feld wird dabei ersetzt. Bei den
  // Anzahlen steht sie unter der ganzen Zeile (nicht zwischen Feld und +).
  const showError = (input, message) => {
    input.classList.toggle('is-invalid', message !== '');
    const countField = input.closest('[data-count-field]');
    let feedback = (countField || input.parentElement).querySelector('.invalid-feedback');
    if (!feedback && message !== '') {
      feedback = document.createElement('div');
      feedback.className = 'invalid-feedback';
      if (countField) {
        countField.append(feedback);
      } else {
        input.after(feedback);
      }
    }
    if (feedback) {
      feedback.textContent = message;
      if (countField) {
        feedback.classList.toggle('d-block', message !== '');
      }
    }
    return message === '';
  };

  // Ganze Zahl ab 0, optional mit Obergrenze; leer zählt als 0
  const checkNumber = (input, max = null) => {
    const raw = input.value.trim();
    // badInput: Zahlenfeld mit Text wie „abc“ (value ist dann leer)
    if ((input.validity && input.validity.badInput) || (raw !== '' && !/^\d+$/.test(raw))) {
      return showError(input, 'Bitte eine ganze Zahl ab 0 angeben.');
    }
    if (max !== null && parseInt(raw || '0', 10) > max) {
      return showError(input, `Höchstens ${max} (Anzahl oben).`);
    }
    return showError(input, '');
  };

  const splitGroups = () => [...form.querySelectorAll('[data-split-group]')];

  // Betreute Kindergruppen eines Programmpunkts (data-childcare-slot="group_4 group_5")
  const childcareOf = (el) => (el.closest('[data-childcare-slot]')?.dataset.childcareSlot || '')
    .split(' ').filter(Boolean);

  // Vorbelegung der Aufteilung: Anzahl oben, betreute Kindergruppen 0 (SPEC §5.4)
  const splitDefault = (el) => (childcareOf(el).includes(el.dataset.splitGroup) ? 0 : count(el.dataset.splitGroup));

  // Gemeinsame Infobox für Kinderbetreuung und Zielgruppe: nur sichtbar, wenn
  // mindestens einer der beiden Hinweise sichtbar ist
  const updateInfoBox = () => {
    const box = form.querySelector('[data-info-box]');
    if (box) {
      box.hidden = [...box.querySelectorAll('[data-childcare-hint], [data-target-hint]')].every((hint) => hint.hidden);
    }
  };

  // Hinweise zur Kinderbetreuung: unter den Anzahlen je Programmpunkt mit
  // passenden Kindern, in der Aufteilung bei eingetragenen betreuten Kindern
  const updateChildcareHints = () => {
    const hint = form.querySelector('[data-childcare-hint]');
    if (hint) {
      let any = false;
      hint.querySelectorAll('[data-childcare-groups]').forEach((item) => {
        const show = item.dataset.childcareGroups.split(' ').some((group) => count(group) > 0);
        item.hidden = !show;
        any = any || show;
      });
      hint.hidden = !any;
    }
    updateInfoBox();
    form.querySelectorAll('[data-childcare-split-hint]').forEach((splitHint) => {
      const slot = splitHint.closest('[data-childcare-slot]');
      splitHint.hidden = !childcareOf(splitHint).some((group) => {
        const input = slot.querySelector(`[data-split-group="${group}"] input`);
        return input && parseInt(input.value, 10) > 0;
      });
    });
  };

  // Zielgruppen (SPEC §5.1): Hinweiszeilen unter den Anzahlen nur, wenn Personen der
  // Zielgruppe eingetragen sind und außerdem Personen, die dort nicht dazugehören.
  // Häkchen eines Programmpunkts ausgrauen (und abwählen), wenn niemand aus der
  // Anmeldung zur Zielgruppe gehört; kommt jemand aus der Zielgruppe dazu, wird es
  // automatisch angekreuzt – nur beim Übergang „niemand“ → „jemand“ und nur durch eine
  // Eingabe in den Anzahlen (autoCheck), nie beim Laden der Seite oder beim Tippen in
  // anderen Feldern. Ohne JS prüft nur der Server.
  const allGroups = () => [...form.querySelectorAll('[data-count]')].map((input) => input.dataset.count);
  const updateTargets = (autoCheck = false) => {
    const hint = form.querySelector('[data-target-hint]');
    if (hint) {
      let any = false;
      hint.querySelectorAll('[data-target-groups]').forEach((item) => {
        const target = item.dataset.targetGroups.split(' ');
        const show = target.some((group) => count(group) > 0)
          && allGroups().some((group) => count(group) > 0 && !target.includes(group));
        item.hidden = !show;
        any = any || show;
      });
      hint.hidden = !any;
    }
    updateInfoBox();
    form.querySelectorAll('[data-target-slot]').forEach((slot) => {
      const target = slot.dataset.targetSlot.split(' ').filter(Boolean);
      if (target.length === 0) {
        return;
      }
      const nobody = !target.some((group) => count(group) > 0);
      const checkbox = slot.querySelector('[data-attend] input');
      if (checkbox) {
        const wasNobody = checkbox.disabled;
        checkbox.disabled = nobody;
        if (nobody) {
          checkbox.checked = false;
          delete checkbox.dataset.autoChecked;
        } else if (autoCheck && wasNobody) {
          checkbox.checked = true;
          // merken: nicht selbst gewählt (Rückfrage beim Absenden)
          checkbox.dataset.autoChecked = '1';
        }
      }
      slot.querySelector('[data-target-none]').hidden = !nobody;
    });
  };

  const checkSplitInput = (el) => checkNumber(el.querySelector('input'), count(el.dataset.splitGroup));

  const updateContact = () => {
    form.querySelector('[data-email-field]').hidden = noEmail.checked;
    form.querySelector('[data-phone-field]').hidden = !noEmail.checked;
  };

  // Aufteilung: nur Personengruppen mit Personen zeigen, max. = Gruppenzahl.
  // Sinkt die Anzahl oben, werden zu hohe Werte angepasst.
  const updateSplit = () => {
    // Ohne Aufteilung (bei der Veranstaltung ausgeschaltet) trotzdem die
    // Kinderbetreuungs-Hinweise aktualisieren
    if (!customSplit) {
      updateChildcareHints();
      return;
    }
    form.querySelectorAll('[data-attend]').forEach((el) => { el.hidden = customSplit.checked; });
    form.querySelectorAll('[data-split]').forEach((el) => { el.hidden = !customSplit.checked; });
    splitGroups().forEach((el) => {
      const max = count(el.dataset.splitGroup);
      const input = el.querySelector('input');
      el.hidden = max === 0;
      input.max = String(max);
      if (parseInt(input.value, 10) > max) {
        input.value = String(max);
      } else if (input.value === '') {
        input.value = String(splitDefault(el));
      }
      checkSplitInput(el);
    });
    updateChildcareHints();
  };

  // Beim Einschalten der Aufteilung mit den Gruppenzahlen vorbelegen
  const prefillSplit = () => {
    if (!customSplit.checked) {
      return;
    }
    splitGroups().forEach((el) => {
      el.querySelector('input').value = String(splitDefault(el));
    });
    updateSplit();
  };

  // Weicht die Aufteilung von der Vorbelegung ab?
  const splitModified = () => splitGroups().some((el) => (
    el.querySelector('input').value.trim() !== String(splitDefault(el))
  ));

  const resetSplit = () => {
    splitGroups().forEach((el) => {
      el.querySelector('input').value = String(splitDefault(el));
    });
    customSplit.checked = false;
    updateSplit();
  };

  // Ausschalten der Aufteilung: ohne eigene Werte sofort, sonst erst nach
  // Bestätigung im Dialog (Werte werden dann zurückgesetzt)
  const resetModalElement = form.querySelector('#split-reset-modal');
  const resetModal = () => (resetModalElement && window.bootstrap
    ? window.bootstrap.Modal.getOrCreateInstance(resetModalElement)
    : null);

  const toggleSplit = () => {
    if (customSplit.checked) {
      prefillSplit();
      return;
    }
    if (!splitModified()) {
      updateSplit();
      return;
    }
    customSplit.checked = true;
    const modal = resetModal();
    if (modal) {
      modal.show();
    } else if (window.confirm('Aufteilung zurücksetzen? Die individuelle Aufteilung geht dabei verloren.')) {
      resetSplit();
    }
  };

  // Erhöht man oben eine Anzahl, wachsen Aufteilungsfelder dieser Gruppe mit,
  // die auf dem bisherigen Maximum standen („alle“). Bewusst kleinere Werte
  // und betreute Kindergruppen auf 0 (= in der Kinderbetreuung) bleiben.
  // Beim Verringern kappt updateSplit() zu hohe Werte.
  const growSplit = (group, previous, current) => {
    if (current <= previous) {
      return;
    }
    splitGroups().filter((el) => el.dataset.splitGroup === group).forEach((el) => {
      const input = el.querySelector('input');
      const value = parseInt(input.value, 10);
      const inChildcare = childcareOf(el).includes(group) && value === 0;
      if (value === previous && !inChildcare) {
        input.value = String(current);
      }
    });
  };

  noEmail.addEventListener('change', updateContact);
  form.querySelectorAll('[data-count]').forEach((input) => {
    input.dataset.previous = String(count(input.dataset.count));
    input.addEventListener('input', () => {
      checkNumber(input);
      // Leeres Feld = wird gerade neu getippt: Aufteilung noch nicht anfassen
      if (input.value.trim() === '') {
        return;
      }
      const current = count(input.dataset.count);
      growSplit(input.dataset.count, parseInt(input.dataset.previous, 10), current);
      input.dataset.previous = String(current);
      updateSplit();
      updateTargets(true);
    });
  });
  splitGroups().forEach((el) => {
    el.querySelector('input').addEventListener('input', () => {
      checkSplitInput(el);
      updateChildcareHints();
    });
  });
  if (customSplit) {
    customSplit.addEventListener('change', toggleSplit);
    form.querySelector('[data-split-reset-confirm]')?.addEventListener('click', () => {
      resetSplit();
      resetModal()?.hide();
    });
  }

  // Nicht absenden, solange eine Anzahl erkennbar falsch ist
  // Mindestens ein Programmpunkt: angekreuzt bzw. in der Aufteilung > 0
  const attendError = form.querySelector('[data-attend-error]');
  const anyAttendance = () => {
    if (customSplit && customSplit.checked) {
      return splitGroups().some((el) => parseInt(el.querySelector('input').value, 10) > 0);
    }
    return [...form.querySelectorAll('[data-attend] input')].some((input) => input.checked);
  };
  const updateAttendError = () => {
    if (attendError && anyAttendance()) {
      attendError.hidden = true;
    }
  };
  form.addEventListener('change', updateAttendError);
  form.addEventListener('input', updateAttendError);

  // Rückfrage, wenn nur automatisch angekreuzte Zielgruppen-Programmpunkte
  // gewählt sind (niemand hat selbst etwas ausgewählt). Eine eigene Änderung
  // an einem Häkchen gilt als bewusste Auswahl. Nach „Trotzdem absenden“ wird
  // nicht erneut gefragt.
  const attendInputs = () => [...form.querySelectorAll('[data-attend] input')];
  attendInputs().forEach((input) => {
    input.addEventListener('change', () => { delete input.dataset.autoChecked; });
  });
  const onlyAutoChecked = () => {
    if (customSplit && customSplit.checked) {
      return [];
    }
    const checked = attendInputs().filter((input) => input.checked);
    return checked.length > 0 && checked.every((input) => input.dataset.autoChecked === '1') ? checked : [];
  };
  let autoConfirmed = false;
  const autoModalElement = form.querySelector('#auto-attend-modal');
  const autoModal = () => (autoModalElement && window.bootstrap
    ? window.bootstrap.Modal.getOrCreateInstance(autoModalElement)
    : null);
  const submitConfirmed = () => {
    autoConfirmed = true;
    if (form.requestSubmit) {
      form.requestSubmit();
    } else {
      form.submit();
    }
  };
  form.querySelector('[data-auto-attend-confirm]')?.addEventListener('click', () => {
    autoModal()?.hide();
    submitConfirmed();
  });
  autoModalElement?.addEventListener('hidden.bs.modal', () => {
    if (!autoConfirmed) {
      form.querySelector('[data-attend] input:not(:disabled)')?.focus();
    }
  });

  form.addEventListener('submit', (event) => {
    const invalid = [...form.querySelectorAll('[data-count]')].filter((input) => !checkNumber(input));
    if (customSplit && customSplit.checked) {
      invalid.push(...splitGroups().filter((el) => !el.hidden && !checkSplitInput(el)).map((el) => el.querySelector('input')));
    }
    if (attendError && !anyAttendance()) {
      attendError.hidden = false;
      const first = customSplit && customSplit.checked
        ? splitGroups().find((el) => !el.hidden)?.querySelector('input')
        : form.querySelector('[data-attend] input:not(:disabled)');
      if (first) {
        invalid.push(first);
      }
    }
    if (invalid.length > 0) {
      event.preventDefault();
      invalid[0].focus();
      return;
    }
    const auto = autoConfirmed ? [] : onlyAutoChecked();
    if (auto.length > 0) {
      event.preventDefault();
      const modal = autoModal();
      if (modal) {
        form.querySelector('[data-auto-attend-list]').replaceChildren(...auto.map((input) => {
          const item = document.createElement('li');
          item.textContent = input.dataset.slotName;
          return item;
        }));
        modal.show();
      } else if (window.confirm('Ausgewählt sind nur automatisch angekreuzte Programmpunkte: '
        + auto.map((input) => input.dataset.slotName).join(', ') + '. Trotzdem absenden?')) {
        submitConfirmed();
      }
    }
  });

  updateContact();
  updateSplit();
  updateTargets();
}

// Vorschläge für die Heimatversammlung (bevorzugte Orte, SPEC §5.1): eigene
// Liste statt <datalist>, damit sie auf Mobil und Desktop gleich aussieht und
// sich gleich verhält – erst ab zwei Zeichen, Treffer am Wortanfang zuerst.
// Ohne JS bleibt die datalist des Browsers.
const PLACES_MIN_CHARS = 2;
const PLACES_MAX = 8;
document.querySelectorAll('[data-places]').forEach((wrapper) => {
  let places = [];
  try {
    places = JSON.parse(wrapper.dataset.places || '[]');
  } catch (e) {
    places = [];
  }
  const input = wrapper.querySelector('input');
  if (!input || !Array.isArray(places) || places.length === 0) {
    return;
  }
  input.removeAttribute('list');

  const listId = `${input.id}-suggestions`;
  const list = document.createElement('div');
  list.id = listId;
  list.className = 'list-group shadow-sm place-suggestions';
  list.setAttribute('role', 'listbox');
  list.hidden = true;
  input.after(list);
  input.setAttribute('role', 'combobox');
  input.setAttribute('aria-autocomplete', 'list');
  input.setAttribute('aria-controls', listId);
  input.setAttribute('aria-expanded', 'false');

  let active = -1;
  let choosing = false;
  const items = () => [...list.querySelectorAll('[role="option"]')];

  const close = () => {
    list.hidden = true;
    list.replaceChildren();
    active = -1;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
  };

  const highlight = (index) => {
    active = index;
    items().forEach((item, i) => {
      item.classList.toggle('active', i === index);
      item.setAttribute('aria-selected', i === index ? 'true' : 'false');
    });
    if (index >= 0) {
      input.setAttribute('aria-activedescendant', items()[index].id);
      items()[index].scrollIntoView({ block: 'nearest' });
    } else {
      input.removeAttribute('aria-activedescendant');
    }
  };

  const choose = (place) => {
    input.value = place;
    close();
    // andere Listener (z. B. Fehlermarkierung) informieren, Liste aber nicht wieder öffnen
    choosing = true;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.focus();
    choosing = false;
  };

  // Ort als Option, der getippte Teil fett (DOM statt innerHTML)
  const option = (place, query, index) => {
    const item = document.createElement('button');
    item.type = 'button';
    item.id = `${listId}-${index}`;
    item.className = 'list-group-item list-group-item-action';
    item.setAttribute('role', 'option');
    item.setAttribute('aria-selected', 'false');
    item.tabIndex = -1;
    const start = place.toLocaleLowerCase('de').indexOf(query);
    if (start >= 0) {
      const strong = document.createElement('strong');
      strong.textContent = place.slice(start, start + query.length);
      item.append(place.slice(0, start), strong, place.slice(start + query.length));
    } else {
      item.textContent = place;
    }
    // pointerdown statt click: kommt vor dem blur des Eingabefelds
    item.addEventListener('pointerdown', (event) => {
      event.preventDefault();
      choose(place);
    });
    item.addEventListener('click', () => choose(place));
    return item;
  };

  const update = () => {
    if (choosing) {
      return;
    }
    const query = input.value.trim().toLocaleLowerCase('de');
    if (query.length < PLACES_MIN_CHARS) {
      close();
      return;
    }
    const lower = (place) => place.toLocaleLowerCase('de');
    const wordStart = (place) => lower(place).split(/[\s\-–/]+/).some((word) => word.startsWith(query));
    const matches = [...new Set([
      ...places.filter((place) => lower(place).startsWith(query)),
      ...places.filter(wordStart),
      ...places.filter((place) => lower(place).includes(query)),
    ])].slice(0, PLACES_MAX);
    // Steht genau ein passender Ort schon im Feld, nichts mehr anbieten
    if (matches.length === 0 || (matches.length === 1 && lower(matches[0]) === query)) {
      close();
      return;
    }
    list.replaceChildren(...matches.map((place, index) => option(place, query, index)));
    list.hidden = false;
    active = -1;
    input.setAttribute('aria-expanded', 'true');
  };

  input.addEventListener('input', update);
  input.addEventListener('focus', update);
  input.addEventListener('blur', close);
  input.addEventListener('keydown', (event) => {
    if (list.hidden) {
      return;
    }
    const count = items().length;
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      highlight((active + 1) % count);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      highlight(active <= 0 ? count - 1 : active - 1);
    } else if (event.key === 'Enter' && active >= 0) {
      event.preventDefault();
      choose(items()[active].textContent);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      close();
    }
  });
});

// Anzahlen: − / + neben dem Zahlenfeld (bequemer als die Zahlentastatur auf
// dem Handy). Ohne JS bleiben die Knöpfe verborgen, das Feld funktioniert
// normal. Ein Klick löst ein input-Ereignis aus, damit Prüfung, Aufteilung
// und Hinweise wie beim Tippen reagieren.
document.querySelectorAll('[data-count-field]').forEach((field) => {
  const input = field.querySelector('input');
  const buttons = [...field.querySelectorAll('[data-step]')];
  if (!input || buttons.length === 0) {
    return;
  }
  const current = () => {
    const value = parseInt(input.value, 10);
    return Number.isNaN(value) || value < 0 ? 0 : value;
  };
  const sync = () => {
    buttons.filter((button) => Number(button.dataset.step) < 0).forEach((button) => {
      button.disabled = current() <= 0;
    });
  };
  field.classList.add('has-stepper');
  buttons.forEach((button) => {
    button.hidden = false;
    button.addEventListener('click', () => {
      input.value = String(Math.max(0, current() + Number(button.dataset.step)));
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });
  input.addEventListener('input', sync);
  sync();
});
