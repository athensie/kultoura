(function () {
    function initDescriptionFields() {
        document.querySelectorAll('[data-two-paragraph-description]').forEach(field => {
            const validate = () => {
                const paragraphs = field.value.trim().split(/(?:\r\n|\r|\n)[\t ]*(?:\r\n|\r|\n)+/)
                    .map(paragraph => paragraph.trim())
                    .filter(Boolean);
                field.setCustomValidity(paragraphs.length === 2
                    ? ''
                    : 'Write exactly two paragraphs and separate them with a blank line.');
            };
            field.addEventListener('input', validate);
            validate();
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDescriptionFields);
    } else {
        initDescriptionFields();
    }
})();
