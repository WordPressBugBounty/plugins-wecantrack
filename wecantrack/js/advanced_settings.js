document.addEventListener('DOMContentLoaded', function () {
    "use strict";

    const form = document.getElementById('wecantrack_ajax_form');
    const loading = document.getElementById('wecantrack_loading');
    const saveFeedback = document.getElementById('wecantrack_save_feedback');

    let busy = 0;

    function showFeedback(container, message, success, autofade = false) {
        if (!container) return;
        const el = document.createElement('span');
        el.className = success ? 'wecantrack-text-success' : 'wecantrack-text-danger';
        el.textContent = message;
        container.replaceChildren(el);

        if (autofade) {
            setTimeout(() => {
                el.classList.add('wecantrack-fade-out');
                setTimeout(() => container.replaceChildren(), 600);
            }, 3500);
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
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
        .then(response => {
            if (!response.success || response.data?.error) {
                showFeedback(saveFeedback, response.data?.error || wecantrackParams.lang_something_went_wrong, false);
            } else {
                showFeedback(saveFeedback, wecantrackParams.lang_changes_saved, true, true);
            }
        })
        .catch(() => {
            showFeedback(saveFeedback, wecantrackParams.lang_something_went_wrong, false);
        })
        .finally(() => {
            busy = 0;
            loading.style.display = 'none';
        });
    });

    // ── Script version switch ───────────────────────────────
    // Must not run concurrently with a settings-form submit (both rewrite plugin state):
    // wait for the form to go idle, then take the shared lock ourselves.
    let scriptVersionClicked = 0;
    const scriptVersionButton = document.getElementById('wecantrack_script_version_button');
    if (scriptVersionButton) {
        scriptVersionButton.addEventListener('click', function () {
            if (scriptVersionClicked) return;

            const version = scriptVersionButton.dataset.version;
            if (version === '1' && !confirm(wecantrackParams.lang_script_revert_confirm)) {
                return;
            }

            scriptVersionClicked = 1;
            loading.style.display = 'block';
            scriptVersionButton.disabled = true;

            const feedback = document.getElementById('wecantrack_script_version_feedback');

            const sendRequest = () => {
                busy = 1;

                const formData = new FormData();
                formData.append('action', 'wecantrack_script_version_response');
                formData.append('wecantrack_form_nonce', scriptVersionButton.dataset.nonce);
                formData.append('wecantrack_script_version', version);

                fetch(wecantrackParams.ajaxurl, {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(response => {
                    if (response.success) {
                        showFeedback(feedback, version === '2' ? wecantrackParams.lang_script_upgraded : wecantrackParams.lang_script_reverted, true);
                        // Reload so the page re-renders against the new version.
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showFeedback(feedback, response.data?.error || wecantrackParams.lang_something_went_wrong, false);
                        scriptVersionButton.disabled = false;
                        scriptVersionClicked = 0;
                    }
                })
                .catch(() => {
                    showFeedback(feedback, wecantrackParams.lang_something_went_wrong, false);
                    scriptVersionButton.disabled = false;
                    scriptVersionClicked = 0;
                })
                .finally(() => {
                    busy = 0;
                    loading.style.display = 'none';
                });
            };

            (function waitForFormIdle() {
                if (!busy) return sendRequest();
                setTimeout(waitForFormIdle, 150);
            })();
        });
    }
});
