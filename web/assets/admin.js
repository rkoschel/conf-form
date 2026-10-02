// Admin: Programmpunkte hinzufügen/entfernen, Dialoge (Löschen, Statuswechsel) befüllen

document.addEventListener('click', (event) => {
  const add = event.target.closest('[data-add-slot]');
  if (add) {
    const rows = document.getElementById('slot-rows');
    const template = document.getElementById('slot-row-template');
    const index = Number(rows.dataset.nextIndex);
    rows.dataset.nextIndex = String(index + 1);
    rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
    rows.lastElementChild.querySelector('input').focus();
    return;
  }

  const remove = event.target.closest('[data-remove-slot]');
  if (remove) {
    remove.closest('.slot-row').remove();
  }
});

// Ablauf: Altersgruppen nur bei eingeschalteter Kinderbetreuung zeigen
document.addEventListener('change', (event) => {
  const toggle = event.target.closest('[data-childcare-toggle]');
  if (toggle) {
    toggle.closest('.slot-row').querySelector('[data-childcare-groups]').hidden = !toggle.checked;
  }
});

const deleteModal = document.getElementById('delete-modal');
if (deleteModal) {
  deleteModal.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    deleteModal.querySelector('[name="id"]').value = button.dataset.id;
    deleteModal.querySelector('[data-delete-title]').textContent = button.dataset.title;
    deleteModal.querySelector('[data-delete-count]').textContent = button.dataset.count;
  });
}

// Anfragen: Dialog Bestätigen/Ablehnen mit Frage „E-Mail senden?“ (SPEC §7.3)
const statusModal = document.getElementById('status-modal');
if (statusModal) {
  statusModal.addEventListener('show.bs.modal', (event) => {
    const data = event.relatedTarget.dataset;
    const confirm = data.action === 'confirm';
    const hasEmail = data.email !== '';
    const find = (selector) => statusModal.querySelector(selector);

    find('[name="action"]').value = data.action;
    find('[name="id"]').value = data.id;
    find('[data-status-title]').textContent = confirm ? 'Anmeldung bestätigen' : 'Anmeldung ablehnen';
    find('[data-status-name]').textContent = data.name;
    find('[data-status-count]').textContent = data.count;
    find('[data-status-email]').textContent = data.email;
    find('[data-status-phone]').textContent = data.phone || 'keine Telefonnummer';
    find('[data-status-exceeds]').hidden = data.exceeds !== '1';
    find('[data-status-with-email]').hidden = !hasEmail;
    find('[data-status-without-email]').hidden = hasEmail;
    find('[data-status-mail]').hidden = !hasEmail;
    find('[data-status-no-mail]').textContent = hasEmail
      ? 'Nein, ohne E-Mail'
      : (confirm ? 'Bestätigen' : 'Ablehnen');
  });
}

// Anfragen: Lösch-Dialog mit Name und Personenzahl (SPEC §7.6)
const deleteRegistrationModal = document.getElementById('delete-registration-modal');
if (deleteRegistrationModal) {
  deleteRegistrationModal.addEventListener('show.bs.modal', (event) => {
    const data = event.relatedTarget.dataset;
    deleteRegistrationModal.querySelector('[name="id"]').value = data.id;
    deleteRegistrationModal.querySelector('[data-delete-name]').textContent = data.name;
    deleteRegistrationModal.querySelector('[data-delete-count]').textContent = data.count;
  });
}
