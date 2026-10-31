function ktArchiveWithNote(form, itemLabel) {
  const ok = confirm('Archive "' + itemLabel + '"? It will be hidden from the public site — this is reversible from the Archive page.');
  if (!ok) return false;
  const note = prompt('Reason for archiving (optional):', '') || '';
  const hidden = form.querySelector('input[name="archive_note"]');
  if (hidden) hidden.value = note;
  return true;
}
