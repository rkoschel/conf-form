// Admin: Programmpunkte hinzufügen/entfernen, Lösch-Dialog befüllen

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

const deleteModal = document.getElementById('delete-modal');
if (deleteModal) {
  deleteModal.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    deleteModal.querySelector('[name="id"]').value = button.dataset.id;
    deleteModal.querySelector('[data-delete-title]').textContent = button.dataset.title;
    deleteModal.querySelector('[data-delete-count]').textContent = button.dataset.count;
  });
}
