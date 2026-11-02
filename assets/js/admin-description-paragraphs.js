(function () {
    function initDescriptionFields() {
        document.querySelectorAll('[data-two-paragraph-description]').forEach(field => {
            const summaryField = field.form?.querySelector('[name="summary"]');
            const validate = () => {
                const paragraphs = field.value.trim().split(/(?:\r\n|\r|\n)[\t ]*(?:\r\n|\r|\n)+/)
                    .map(paragraph => paragraph.trim())
                    .filter(Boolean);
                let message = '';
                if (paragraphs.length !== 2) {
                    message = 'Write exactly two paragraphs and separate them with a blank line.';
                } else if (summaryField && field.value.trim().length <= summaryField.value.trim().length) {
                    message = 'Keep the card summary and make the description longer.';
                }
                field.setCustomValidity(message);
            };
            field.addEventListener('input', validate);
            summaryField?.addEventListener('input', validate);
            validate();
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDescriptionFields);
    } else {
        initDescriptionFields();
    }
})();
