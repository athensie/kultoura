// Shared helper for admin pages that archive via a plain form (no full
// modal) — asks for an optional reason, then lets the submit proceed.
// Pages with a real confirm modal (Destinations, Fiesta, Food & Dining,
// People, Announcements) have their own textarea in that modal instead.
function ktArchiveWithNote(form, itemLabel) {
  const ok = confirm('Archive "' + itemLabel + '"? It will be hidden from the public site — this is reversible from the Archive page.');
  if (!ok) return false;

  const note = prompt('Reason for archiving (optional):', '') || '';
  const hidden = form.querySelector('input[name="archive_note"]');
  if (hidden) hidden.value = note;
  return true;
}
