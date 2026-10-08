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
  // eine Meldung vom Server am selben Feld wird dabei ersetzt
  const showError = (input, message) => {
    input.classList.toggle('is-invalid', message !== '');
    let feedback = input.parentElement.querySelector('.invalid-feedback');
    if (!feedback && message !== '') {
      feedback = document.createElement('div');
      feedback.className = 'invalid-feedback';
      input.after(feedback);
    }
    if (feedback) {
      feedback.textContent = message;
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

  // Betreute Altersgruppen eines Programmpunkts (data-childcare-slot="group_5 group_4")
  const childcareOf = (el) => (el.closest('[data-childcare-slot]')?.dataset.childcareSlot || '')
    .split(' ').filter(Boolean);

  // Vorbelegung der Aufteilung: Anzahl oben, betreute Kindergruppen 0 (SPEC §5.4)
  const splitDefault = (el) => (childcareOf(el).includes(el.dataset.splitGroup) ? 0 : count(el.dataset.splitGroup));

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
    form.querySelectorAll('[data-childcare-split-hint]').forEach((splitHint) => {
      const slot = splitHint.closest('[data-childcare-slot]');
      splitHint.hidden = !childcareOf(splitHint).some((group) => {
        const input = slot.querySelector(`[data-split-group="${group}"] input`);
        return input && parseInt(input.value, 10) > 0;
      });
    });
  };

  const checkSplitInput = (el) => checkNumber(el.querySelector('input'), count(el.dataset.splitGroup));

  const updateContact = () => {
    form.querySelector('[data-email-field]').hidden = noEmail.checked;
    form.querySelector('[data-phone-field]').hidden = !noEmail.checked;
  };

  // Aufteilung: nur Altersgruppen mit Personen zeigen, max. = Gruppenzahl.
  // Sinkt die Anzahl oben, werden zu hohe Werte angepasst.
  const updateSplit = () => {
    if (!customSplit) {
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

  noEmail.addEventListener('change', updateContact);
  form.querySelectorAll('[data-count]').forEach((input) => {
    input.addEventListener('input', () => {
      checkNumber(input);
      updateSplit();
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
  form.addEventListener('submit', (event) => {
    const invalid = [...form.querySelectorAll('[data-count]')].filter((input) => !checkNumber(input));
    if (customSplit && customSplit.checked) {
      invalid.push(...splitGroups().filter((el) => !el.hidden && !checkSplitInput(el)).map((el) => el.querySelector('input')));
    }
    if (invalid.length > 0) {
      event.preventDefault();
      invalid[0].focus();
    }
  });

  updateContact();
  updateSplit();
}
