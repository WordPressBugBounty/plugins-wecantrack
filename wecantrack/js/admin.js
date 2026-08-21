document.addEventListener('DOMContentLoaded', function () {
    "use strict";

    let busy = 0;
    const $form = document.getElementById('wecantrack_ajax_form');
    const $api_key = document.getElementById('wecantrack_api_key');
    const $loading = document.getElementById('wecantrack_loading');
    const $submit_type = document.getElementById('wecantrack_submit_type');
    const $submit_verified = document.getElementById('submit-verified');
    const $fieldset = document.getElementById('wecantrack_settings_fieldset');
    const $summary = document.getElementById('wecantrack_connection_summary');
    const $setup = document.getElementById('wecantrack_connection_setup');
    const $change_key = document.getElementById('wecantrack_change_key');
    const $pill = document.getElementById('wecantrack_connection_pill');
    const $plugin_status = document.getElementById('wecantrack_plugin_status');
    const $session_enabler_row = document.querySelector('.wecantrack-session-enabler');
    const $website_override_row = document.querySelector('.wecantrack-website-override');
    const $website_override = document.getElementById('wecantrack_website_override');
    const $verify_feedback = document.getElementById('wecantrack_verify_feedback');
    const $save_feedback = document.getElementById('wecantrack_save_feedback');

    // ── Feedback ────────────────────────────────────────────

    function show_feedback(container, message, success, autofade = false) {
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

    function clear_feedback() {
        if ($verify_feedback) $verify_feedback.replaceChildren();
        if ($save_feedback) $save_feedback.replaceChildren();
    }

    // ── Connection state ────────────────────────────────────

    function set_pill(connected, tracking_on = true) {
        if (!$pill) return;
        const paused = connected && !tracking_on;
        $pill.classList.toggle('wecantrack-pill-connected', connected && !paused);
        $pill.classList.toggle('wecantrack-pill-paused', paused);
        $pill.classList.toggle('wecantrack-pill-disconnected', !connected);
        $pill.textContent = !connected
            ? $pill.dataset.langDisconnected
            : (paused ? $pill.dataset.langTrackingOff : $pill.dataset.langConnected);
    }

    function set_disconnected_state() {
        set_pill(false);
        if ($summary) $summary.classList.add('hidden');
        if ($setup) $setup.classList.remove('hidden');
        if ($fieldset) $fieldset.disabled = true;
        document.querySelector('.wecantrack-prerequisites')?.classList.add('hidden');
        if ($website_override_row) $website_override_row.classList.add('hidden');
        if ($website_override) {
            $website_override.innerHTML = '';
            delete $website_override.dataset.populated;
        }
    }

    function user_passed_prerequisites() {
        return document.querySelectorAll('.wecantrack-prerequisites .dashicons-yes').length >= 2;
    }

    function check_prerequisites(response) {
        if (
            typeof response.data.total_active_network_accounts === 'undefined' ||
            typeof response.data.has_website === 'undefined' ||
            typeof response.data.features === 'undefined'
        ) {
            show_feedback($verify_feedback, wecantrackParams.lang_request_wrong, false);
            return;
        }

        document.querySelector('.wecantrack-prerequisites').classList.remove('hidden');

        const netIcon = document.querySelector('.wecantrack-preq-network-account i');
        const netText = document.querySelector('.wecantrack-preq-network-account span');
        if (response.data.total_active_network_accounts > 0) {
            netIcon.classList.replace('dashicons-no', 'dashicons-yes');
            netText.innerHTML = wecantrackParams.lang_added_one_active_network;
        } else {
            netIcon.classList.replace('dashicons-yes', 'dashicons-no');
            netText.innerHTML = `<a target="_blank" href="https://app.wecantrack.com/user/data-source/networks">${wecantrackParams.lang_not_added_one_active_network}</a>`;
        }

        const featIcon = document.querySelector('.wecantrack-preq-feature i');
        const featText = document.querySelector('.wecantrack-preq-feature span');
        if (response.data.has_website) {
            featIcon.classList.replace('dashicons-no', 'dashicons-yes');
            featText.textContent = wecantrackParams.lang_website_added;
        } else {
            featIcon.classList.replace('dashicons-yes', 'dashicons-no');
            featText.innerHTML = `<a target="_blank" href="https://app.wecantrack.com/user/websites/create?website=${wecantrackParams.site_url}">${wecantrackParams.lang_website_not_added}</a>`;
        }

        maybe_show_website_override(response.data);

        const passed = user_passed_prerequisites();
        set_pill(passed, !$plugin_status || $plugin_status.checked);
        if ($fieldset) $fieldset.disabled = !passed;

        if (passed) {
            update_connection_summary(response.data);
        }
    }

    // Update the connection card in place after a successful verify — no reload,
    // so the success feedback stays visible and the page doesn't jump.
    function update_connection_summary(data) {
        const was_setup = $setup && !$setup.classList.contains('hidden');

        if (data.matched_website) {
            const $website = document.getElementById('wecantrack_connected_website');
            if ($website) $website.textContent = data.matched_website;
        }

        const $property = document.getElementById('wecantrack_property_id');
        if ($property) {
            $property.textContent = data.property_id || '';
            $property.classList.toggle('hidden', !data.property_id);
        }

        const $masked = document.getElementById('wecantrack_masked_key');
        if ($masked && $api_key.value.length > 8) {
            $masked.textContent = $api_key.value.slice(0, 4) + '••••••••' + $api_key.value.slice(-4);
        }

        if ($summary) $summary.classList.remove('hidden');
        document.getElementById('wecantrack_api_key_row')?.classList.remove('hidden');
        if ($setup) $setup.classList.add('hidden');

        if (was_setup) {
            show_feedback($verify_feedback, wecantrackParams.lang_valid_api_key, true, true);
        }
    }

    // When the site domain isn't registered (e.g. staging) but the account has websites,
    // offer a dropdown so the user can pick which website to use for this install.
    function maybe_show_website_override(data) {
        if (!$website_override_row || !$website_override) {
            return;
        }

        if (data.has_website || !Array.isArray(data.websites) || data.websites.length === 0) {
            $website_override_row.classList.add('hidden');
            return;
        }

        const urls = data.websites.map(w => w.url).filter(Boolean);
        const signature = urls.join('|');

        // Populate once per unique list; preserve any selection the user already made.
        if ($website_override.dataset.populated !== signature) {
            const preselect = $website_override.dataset.selected || '';
            $website_override.innerHTML = '';
            urls.forEach(url => {
                const opt = document.createElement('option');
                opt.value = url;
                opt.textContent = url;
                if (url === preselect) {
                    opt.selected = true;
                }
                $website_override.appendChild(opt);
            });
            $website_override.dataset.populated = signature;
        }

        $website_override_row.classList.remove('hidden');
    }

    // ── Session enabler visibility ──────────────────────────

    function toggle_session_enabler() {
        if (!$session_enabler_row || !$plugin_status) return;
        $session_enabler_row.classList.toggle('hidden', $plugin_status.checked);
    }

    if ($plugin_status) {
        $plugin_status.addEventListener('change', toggle_session_enabler);
    }

    // ── Change key ──────────────────────────────────────────

    if ($change_key) {
        $change_key.addEventListener('click', function (e) {
            e.preventDefault();
            // The input replaces the masked-key row while editing.
            document.getElementById('wecantrack_api_key_row')?.classList.add('hidden');
            $setup.classList.remove('hidden');
            $api_key.focus();
            $api_key.select();
        });
    }

    // ── Verify / save submits ───────────────────────────────

    if ($submit_verified) {
        $submit_verified.addEventListener('click', function () {
            $submit_type.value = 'save';
        });
    }

    // Re-run verification against the chosen website. Keep submit_type as 'verify' so this
    // resolves the website without prematurely persisting plugin status / session enabler.
    if ($website_override) {
        $website_override.addEventListener('change', function () {
            if (busy) return;
            $submit_type.value = 'verify';
            $form.dispatchEvent(new Event('submit'));
        });
    }

    $form.addEventListener('submit', function (event) {
        event.preventDefault();
        clear_feedback();
        if (busy) return;
        busy = 1;
        $loading.style.display = 'block';

        const isSave = $submit_type.value !== 'verify';
        const feedback_el = isSave ? $save_feedback : $verify_feedback;

        let formData = new FormData($form);
        formData.append('ajaxrequest', 'true');
        formData.append('submit', 'Submit Form');

        fetch(wecantrackParams.ajaxurl, {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(response => {
            if (response.data?.error?.includes('Unauthorised')) {
                show_feedback($verify_feedback, wecantrackParams.lang_invalid_api_key, false);
                set_disconnected_state();
            } else if (response.data?.error) {
                show_feedback(feedback_el, response.data.error, false);
                set_disconnected_state();
            } else {
                if (isSave) {
                    show_feedback($save_feedback, wecantrackParams.lang_changes_saved, true, true);
                }
                check_prerequisites(response);
            }
        }).catch(() => {
            show_feedback(feedback_el, wecantrackParams.lang_something_went_wrong, false);
        }).finally(() => {
            busy = 0;
            $submit_type.value = 'verify';
            $loading.style.display = 'none';
        });
    });

    // Auto-verify on load when a key is present, to refresh requirements and config.
    if ($api_key && $api_key.value.length > 30) {
        $form.dispatchEvent(new Event('submit'));
    }

    // Submit the form when pressing Enter in the session enabler field.
    document.getElementById('wecantrack_session_enabler')?.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            $submit_verified.click();
            e.preventDefault();
        }
    });

    // ── Script version switch ───────────────────────────────
    // The settings form auto-verifies on page load and its response rewrites the stored
    // website options, so this request must not run concurrently with it. Instead of
    // silently dropping the click, wait for the form to go idle, then take the shared lock.
    let script_version_clicked = 0;
    const $script_version_button = document.getElementById('wecantrack_script_version_button');
    if ($script_version_button) {
        $script_version_button.addEventListener('click', function () {
            if (script_version_clicked) return;

            const version = $script_version_button.dataset.version;
            if (version === '1' && !confirm(wecantrackParams.lang_script_revert_confirm)) {
                return;
            }

            script_version_clicked = 1;
            $loading.style.display = 'block';
            $script_version_button.disabled = true;

            const feedback = document.getElementById('wecantrack_script_version_feedback');

            const send_request = () => {
                busy = 1;

                const formData = new FormData();
                formData.append('action', 'wecantrack_script_version_response');
                formData.append('wecantrack_form_nonce', $script_version_button.dataset.nonce);
                formData.append('wecantrack_script_version', version);

                fetch(wecantrackParams.ajaxurl, {
                    method: 'POST',
                    body: formData
                }).then(res => res.json()).then(response => {
                    if (response.success) {
                        show_feedback(feedback, version === '2' ? wecantrackParams.lang_script_upgraded : wecantrackParams.lang_script_reverted, true);
                        // Reload so the page re-renders against the new version.
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        show_feedback(feedback, response.data?.error || wecantrackParams.lang_something_went_wrong, false);
                        $script_version_button.disabled = false;
                        script_version_clicked = 0;
                    }
                }).catch(() => {
                    show_feedback(feedback, wecantrackParams.lang_something_went_wrong, false);
                    $script_version_button.disabled = false;
                    script_version_clicked = 0;
                }).finally(() => {
                    busy = 0;
                    $loading.style.display = 'none';
                });
            };

            (function wait_for_form_idle() {
                if (!busy) return send_request();
                setTimeout(wait_for_form_idle, 150);
            })();
        });
    }

    // Tag health check: fetch the homepage server-side and show the verdict.
    const $tag_check_button = document.getElementById('wecantrack_tag_check_button');
    if ($tag_check_button) {
        $tag_check_button.addEventListener('click', function () {
            const $result = document.getElementById('wecantrack_tag_check_result');

            $tag_check_button.disabled = true;
            $result.classList.add('hidden');
            $result.className = 'wecantrack-tag-check-result hidden';

            const formData = new FormData();
            formData.append('action', 'wecantrack_tag_check');
            formData.append('wecantrack_form_nonce', $tag_check_button.dataset.nonce);

            fetch(wecantrackParams.ajaxurl, {
                method: 'POST',
                body: formData
            }).then(res => res.json()).then(response => {
                if (response.success && response.data?.verdict) {
                    $result.textContent = response.data.message;
                    $result.classList.add('wecantrack-tag-check-' + (response.data.verdict === 'ok' ? 'ok' : (response.data.verdict === 'error' ? 'error' : 'warn')));
                } else {
                    $result.textContent = response.data?.error || wecantrackParams.lang_something_went_wrong;
                    $result.classList.add('wecantrack-tag-check-error');
                }
                $result.classList.remove('hidden');
            }).catch(() => {
                $result.textContent = wecantrackParams.lang_something_went_wrong;
                $result.classList.add('wecantrack-tag-check-error');
                $result.classList.remove('hidden');
            }).finally(() => {
                $tag_check_button.disabled = false;
            });
        });
    }
});
