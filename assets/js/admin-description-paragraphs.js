(function () {
    // Same rules and wording as kt_description_problem() on the server (config/admin_requests.php).
    // Pasted text is cleaned first, so invisible characters (non-breaking or zero-width spaces, Unicode
    // line separators) do not stop an empty line from counting as the gap between paragraphs.
    const normalize = text => text
        .replace(/\r\n?|\u2028|\u2029|\u0085/g, '\n')
        .replace(/[\u00A0\u2000-\u200B\u202F\u205F\u3000\uFEFF]/g, ' ')
        .replace(/[ \t]+$/gm, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();

    function problemFor(descriptionText, summaryText) {
        const description = normalize(descriptionText);
        const paragraphs = description.split(/\n[ \t]*\n+/).map(paragraph => paragraph.trim()).filter(Boolean);
        const summaryLength = summaryText.trim().length;
        if (!paragraphs.length) return ['Please write a description (two paragraphs separated by a blank line).', paragraphs, description];
        if (paragraphs.length === 1) return ['Your description has 1 paragraph. Split it into two paragraphs by pressing Enter twice between them, so there is an empty line in between.', paragraphs, description];
        if (paragraphs.length > 2) return [`Your description has ${paragraphs.length} paragraphs. Combine them into exactly two, with one empty line between them.`, paragraphs, description];
        if (description.length <= summaryLength) {
            return [`The description (${description.length} characters) must be longer than the card summary (${summaryLength} characters). Add more detail to the description or shorten the summary.`, paragraphs, description];
        }
        return ['', paragraphs, description];
    }

    function initDescriptionFields() {
        document.querySelectorAll('[data-two-paragraph-description]').forEach(field => {
            const summaryField = field.form?.querySelector('[name="summary"]');
            let hint = null;
            const validate = (showHint) => {
                const [message, paragraphs, description] = problemFor(field.value, summaryField ? summaryField.value : '');
                field.setCustomValidity(message);
                if (!hint) {
                    hint = document.createElement('div');
                    hint.className = 'description-paragraph-status';
                    hint.setAttribute('aria-live', 'polite');
                    field.insertAdjacentElement('afterend', hint);
                }
                const visible = showHint || field.value.trim() !== '';
                hint.hidden = !visible;
                hint.textContent = message || `\u2713 ${paragraphs.length} paragraphs, ${description.length} characters`;
                hint.classList.toggle('is-ok', !message);
            };
            field.addEventListener('input', () => validate(true));
            field.addEventListener('focus', () => validate(false));
            summaryField?.addEventListener('input', () => validate(false));
            // Edit dialogs fill the box by code (no "input" event), so check again right before saving:
            // clicks on a submit button run before the browser's own form validation.
            field.form?.addEventListener('click', event => {
                const button = event.target.closest('button, input[type="submit"]');
                if (button && (button.type === 'submit' || button.getAttribute('type') === null)) validate(true);
            }, true);
            validate(false);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDescriptionFields);
    } else {
        initDescriptionFields();
    }
})();
