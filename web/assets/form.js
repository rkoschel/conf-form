// Anmeldeformular: „keine E-Mail“-Umschaltung und individuelle Aufteilung
// auf Programmpunkte (SPEC §5.1). Ohne JS funktioniert das Formular weiter,
// die Prüfung erfolgt immer serverseitig.

const form = document.getElementById('register-form');
if (form) {
  const noEmail = form.querySelector('#f-no_email');
  const customSplit = form.querySelector('#f-custom_split');

  const count = (group) => {
    const value = parseInt(form.querySelector(`[data-count="${group}"]`).value, 10);
    return Number.isNaN(value) || value < 0 ? 0 : value;
  };

  const updateContact = () => {
    form.querySelector('[data-email-field]').hidden = noEmail.checked;
    form.querySelector('[data-phone-field]').hidden = !noEmail.checked;
  };

  // Aufteilung: nur Altersgruppen mit Personen zeigen, max. = Gruppenzahl
  const updateSplit = () => {
    if (!customSplit) {
      return;
    }
    form.querySelectorAll('[data-attend]').forEach((el) => { el.hidden = customSplit.checked; });
    form.querySelectorAll('[data-split]').forEach((el) => { el.hidden = !customSplit.checked; });
    form.querySelectorAll('[data-split-group]').forEach((el) => {
      const max = count(el.dataset.splitGroup);
      const input = el.querySelector('input');
      el.hidden = max === 0;
      input.max = String(max);
      if (parseInt(input.value, 10) > max || input.value === '') {
        input.value = String(max);
      }
    });
  };

  // Beim Einschalten der Aufteilung mit den Gruppenzahlen vorbelegen
  const prefillSplit = () => {
    if (!customSplit.checked) {
      return;
    }
    form.querySelectorAll('[data-split-group]').forEach((el) => {
      el.querySelector('input').value = String(count(el.dataset.splitGroup));
    });
    updateSplit();
  };

  noEmail.addEventListener('change', updateContact);
  form.querySelectorAll('[data-count]').forEach((el) => el.addEventListener('input', updateSplit));
  if (customSplit) {
    customSplit.addEventListener('change', prefillSplit);
  }
  updateContact();
  updateSplit();
}
