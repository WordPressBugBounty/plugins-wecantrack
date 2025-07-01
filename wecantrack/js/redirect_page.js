document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('wecantrack_ajax_form');
    if (!form) return;

    const loading = document.getElementById('wecantrack_loading');
    const feedbackTop = document.getElementById('wecantrack_form_feedback_top');
    const feedbackBottom = document.getElementById('wecantrack_form_feedback_bottom'); // if used
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

        fetch(params.ajaxurl, {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(response => response.json())
        .then(response => {
            if (typeof response.error !== 'undefined') {
                errorMessage(params.lang_invalid_request + ': ' + response.error);
            } else {
                successMessage(params.lang_changes_saved);
            }
        })
        .catch(() => {
            errorMessage(params.lang_something_went_wrong);
        })
        .finally(() => {
            busy = 0;
            loading.style.display = 'none';
            if (userPassedPrerequisites()) {
                document.querySelectorAll('.wecantrack-snippet.hidden, .wecantrack-plugin-status.hidden, #wecantrack_ajax_form .submit.hidden').forEach(el => {
                    el.classList.remove('hidden');
                });

                const pluginStatusChecked = document.querySelector('.wecantrack-plugin-status input[name="wecantrack_plugin_status"]:checked');
                if (pluginStatusChecked && pluginStatusChecked.value === '0') {
                    document.querySelectorAll('.wecantrack-session-enabler.hidden').forEach(el => {
                        el.classList.remove('hidden');
                    });
                }
            }
        });
    });

    function clearMessages() {
        if (feedbackTop) feedbackTop.innerHTML = '';
        if (feedbackBottom) feedbackBottom.innerHTML = '';
    }

    function errorMessage(message, position = 'top') {
        clearMessages();
        const container = position === 'top' ? feedbackTop : feedbackBottom;
        if (container) {
            container.innerHTML = `<h2 class="wecantrack-text-danger">${message}</h2><br>`;
        }
    }

    function successMessage(message, position = 'top') {
        clearMessages();
        const container = position === 'top' ? feedbackTop : feedbackBottom;
        if (container) {
            container.innerHTML = `<h2 class="wecantrack-text-success">${message}</h2><br>`;
        }
    }

    function userPassedPrerequisites() {
        return document.querySelectorAll('.wecantrack-prerequisites .dashicons-yes').length >= 2;
    }
});