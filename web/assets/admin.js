// Admin: Programmpunkte hinzufügen/entfernen, Dialoge (Löschen, Statuswechsel) befüllen

document.addEventListener('click', (event) => {
  const add = event.target.closest('[data-add-slot]');
  if (add) {
    const rows = document.getElementById('slot-rows');
    const template = document.getElementById('slot-row-template');
    const index = Number(rows.dataset.nextIndex);
    rows.dataset.nextIndex = String(index + 1);
    rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
    syncChildcareOptions();
    syncExclusiveToggles(rows.lastElementChild);
    rows.lastElementChild.querySelector('input').focus();
    return;
  }

  const remove = event.target.closest('[data-remove-slot]');
  if (remove) {
    remove.closest('.slot-row').remove();
  }
});

// Personengruppen der Veranstaltung: In der Kinderbetreuung nur gewählte
// Kindergruppen, in der Zielgruppe alle gewählten Gruppen anbieten, mit
// ihren aktuellen Namen
function syncChildcareOptions() {
  document.querySelectorAll('[data-person-group]').forEach((checkbox) => {
    const key = checkbox.dataset.personGroup;
    const name = document.querySelector(`[data-group-name="${key}"]`)?.value.trim() || key;
    ['childcare', 'target'].forEach((kind) => {
      document.querySelectorAll(`[data-${kind}-option="${key}"]`).forEach((option) => {
        option.hidden = !checkbox.checked;
        if (!checkbox.checked) {
          option.querySelector('input').checked = false;
        }
      });
      document.querySelectorAll(`[data-${kind}-label="${key}"]`).forEach((label) => {
        label.textContent = name;
      });
    });
  });
}

// Zielgruppe und Kinderbetreuung schließen sich aus: Ist einer der beiden
// Schalter an, ist der andere gesperrt. Stehen nach einem Fehler beide an,
// bleiben beide bedienbar, damit man es korrigieren kann.
function syncExclusiveToggles(row) {
  const childcare = row.querySelector('[data-childcare-toggle]');
  const target = row.querySelector('[data-target-toggle]');
  if (!childcare || !target) {
    return;
  }
  childcare.disabled = target.checked && !childcare.checked;
  target.disabled = childcare.checked && !target.checked;
}
document.addEventListener('change', (event) => {
  if (event.target.closest('[data-person-group]')) {
    syncChildcareOptions();
  }
});
document.addEventListener('input', (event) => {
  if (event.target.closest('[data-group-name]')) {
    syncChildcareOptions();
  }
});

// Ablauf: Kindergruppen bzw. Zielgruppen nur bei eingeschaltetem Schalter zeigen
document.addEventListener('change', (event) => {
  const childcare = event.target.closest('[data-childcare-toggle]');
  const target = event.target.closest('[data-target-toggle]');
  const toggle = childcare || target;
  if (toggle) {
    const row = toggle.closest('.slot-row');
    row.querySelector(childcare ? '[data-childcare-groups]' : '[data-target-groups]').hidden = !toggle.checked;
    syncExclusiveToggles(row);
  }
});
document.querySelectorAll('.slot-row').forEach(syncExclusiveToggles);

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

// Formulare mit Rückfrage (data-confirm="…")
document.addEventListener('submit', (event) => {
  const message = event.target.dataset.confirm;
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
});

// Kopieren in die Zwischenablage (data-copy="#feld")
document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-copy]');
  if (!button) {
    return;
  }
  const field = document.querySelector(button.dataset.copy);
  try {
    await navigator.clipboard.writeText(field.value);
    button.textContent = 'Kopiert';
  } catch {
    // ohne Clipboard-API (z. B. ohne HTTPS): Text markieren, Strg+C bleibt
    field.select();
  }
});

// Einstellungen: Tab aus der Adresse (#e-mails) öffnen, beim Wechsel Adresse
// und verstecktes Feld aktualisieren (nach dem Speichern zurück in den Tab)
const settingsTabs = document.querySelector('[data-settings-tabs]');
if (settingsTabs && window.bootstrap) {
  const tabInput = document.querySelector('[data-settings-tab]');
  const fromHash = document.getElementById('tab-' + window.location.hash.slice(1));
  if (fromHash) {
    window.bootstrap.Tab.getOrCreateInstance(fromHash).show();
  }
  settingsTabs.addEventListener('shown.bs.tab', (event) => {
    const id = event.target.id.replace(/^tab-/, '');
    tabInput.value = id;
    history.replaceState(null, '', '#' + id);
  });
  if (fromHash) {
    tabInput.value = fromHash.id.replace(/^tab-/, '');
  }
}

// Anfragen: Details einer Anmeldung auf- und zuklappen (Personen je Gruppe und Programmpunkt)
document.addEventListener('click', (event) => {
  const toggle = event.target.closest('[data-details-toggle]');
  if (!toggle) {
    return;
  }
  const row = document.getElementById(toggle.getAttribute('aria-controls'));
  if (!row) {
    return;
  }
  const open = row.hidden;
  row.hidden = !open;
  toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  toggle.classList.toggle('active', open);
  row.previousElementSibling?.classList.toggle('details-open', open);
});

// Veranstaltung aktivieren/deaktivieren erst nach Bestätigung (SPEC §7.1):
// in der Liste per Dialog, im Formular beim Speichern, wenn sich „Aktiv“ ändert
const fillActiveModal = (modal, activate, title, other, onSave) => {
  const paragraph = (text) => {
    const p = document.createElement('p');
    p.textContent = text;
    return p;
  };
  const when = onSave ? 'beim Speichern ' : '';
  const body = activate
    ? [paragraph(`„${title}“ wird ${when}aktiv: Die Infoseite zeigt diese Veranstaltung, und bis zur Anmeldefrist kann man sich anmelden.`)]
    : [paragraph(`„${title}“ wird ${when}inaktiv: Die Infoseite zeigt dann den Text für „keine Veranstaltung aktiv“, und Anmeldungen sind nicht mehr möglich. Bestehende Anmeldungen bleiben erhalten.`)];
  if (activate && other) {
    body.push(paragraph(`„${other}“ ist zurzeit aktiv und wird dabei deaktiviert.`));
  }
  body[body.length - 1].classList.add('mb-0');
  modal.querySelector('[data-active-heading]').textContent = activate ? 'Veranstaltung aktivieren?' : 'Veranstaltung deaktivieren?';
  modal.querySelector('[data-active-body]').replaceChildren(...body);
  modal.querySelector('[data-active-submit]').textContent = onSave
    ? (activate ? 'Speichern und aktivieren' : 'Speichern und deaktivieren')
    : (activate ? 'Aktivieren' : 'Deaktivieren');
  return body.map((p) => p.textContent).join(' ');
};

const activeModal = document.getElementById('active-modal');
const activeForm = document.querySelector('form[data-active-confirm]');
if (activeModal && !activeForm) {
  // Liste: der Dialog ist selbst das Formular (action + id)
  activeModal.addEventListener('show.bs.modal', (event) => {
    const data = event.relatedTarget.dataset;
    activeModal.querySelector('[name="action"]').value = data.action;
    activeModal.querySelector('[name="id"]').value = data.id;
    fillActiveModal(activeModal, data.action === 'activate', data.title, data.other, false);
  });
}
if (activeForm) {
  let activeConfirmed = false;
  const switchInput = activeForm.querySelector('#f-active');
  activeForm.addEventListener('submit', (event) => {
    const activate = switchInput.checked;
    if (activeConfirmed || activate === (activeForm.dataset.activeWas === '1')) {
      return;
    }
    event.preventDefault();
    const title = activeForm.querySelector('#f-title')?.value.trim() || 'Die Veranstaltung';
    const text = fillActiveModal(activeModal, activate, title, activeForm.dataset.activeOther, true);
    if (window.bootstrap && activeModal) {
      window.bootstrap.Modal.getOrCreateInstance(activeModal).show();
    } else if (window.confirm(text)) {
      activeConfirmed = true;
      activeForm.requestSubmit();
    }
  });
  activeModal?.querySelector('[data-active-submit]').addEventListener('click', () => {
    activeConfirmed = true;
    window.bootstrap?.Modal.getOrCreateInstance(activeModal).hide();
    activeForm.requestSubmit();
  });
}
