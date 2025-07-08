document.addEventListener('DOMContentLoaded', function () {
    "use strict";

    const form = document.getElementById('wecantrack_ajax_form');
    const loading = document.getElementById('wecantrack_loading');
    const feedbackTop = document.getElementById('wecantrack_form_feedback_top');
    const feedbackBottom = document.getElementById('wecantrack_form_feedback_bottom');

    let busy = 0;

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearMessages();

        if (busy) return;
        busy = 1;
        loading.style.display = 'block';

        const formData = new FormData(form);
        formData.append('ajaxrequest', 'true');
        formData.append('submit', 'Submit Form');

        fetch(wecantrackParams.ajaxurl, {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data?.error) {
                errorMessage(`${wecantrackParams.lang_invalid_request}: ${data.error}`);
            } else {
                successMessage(wecantrackParams.lang_changes_saved);
            }
        })
        .catch(() => {
            errorMessage(wecantrackParams.lang_something_went_wrong);
        })
        .finally(() => {
            busy = 0;
            loading.style.display = 'none';
        });
    });

    function clearMessages() {
        if (feedbackTop) feedbackTop.innerHTML = '';
        if (feedbackBottom) feedbackBottom.innerHTML = '';
    }

    function errorMessage(message, position = 'top') {
        const el = position === 'bottom' ? feedbackBottom : feedbackTop;
        if (el) el.innerHTML = `<h2 class='wecantrack-text-danger'>${message}</h2><br>`;
    }

    function successMessage(message, position = 'top') {
        const el = position === 'bottom' ? feedbackBottom : feedbackTop;
        if (el) el.innerHTML = `<h2 class='wecantrack-text-success'>${message}</h2><br>`;
    }
});