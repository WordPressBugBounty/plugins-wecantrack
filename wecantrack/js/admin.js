document.addEventListener('DOMContentLoaded', function () {
    "use strict";

    let busy = 0;
    let current_key = '';
    const $form = document.getElementById('wecantrack_ajax_form');
    const $api_key = document.getElementById('wecantrack_api_key');
    const $loading = document.getElementById('wecantrack_loading');
    const $submit_type = document.getElementById('wecantrack_submit_type');
    const $submit_verified = document.getElementById('submit-verified');
    const $plugin_status = document.querySelectorAll('.wecantrack-plugin-status input[name="wecantrack_plugin_status"]');
    const $session_enabler = document.querySelector('.wecantrack-session-enabler');

    function toggle_session_enabler() {
        const checked = document.querySelector('.wecantrack-plugin-status input[name="wecantrack_plugin_status"]:checked');
        if (checked && checked.value === '1') {
            $session_enabler.classList.add('hidden');
        } else {
            $session_enabler.classList.remove('hidden');
        }
    }

    $plugin_status.forEach(el => {
        el.addEventListener('click', toggle_session_enabler);
    });

    function check_prerequisites(response) {
        if (
            typeof response.data.total_active_network_accounts === 'undefined' ||
            typeof response.data.has_website === 'undefined' ||
            typeof response.data.features === 'undefined'
        ) {
            error_message(params.lang_request_wrong);
            return;
        }

        document.querySelector('.wecantrack-prerequisites').classList.remove('hidden');

        const netIcon = document.querySelector('.wecantrack-preq-network-account i');
        const netText = document.querySelector('.wecantrack-preq-network-account span');
        if (response.data.total_active_network_accounts > 0) {
            netIcon.classList.replace('dashicons-no', 'dashicons-yes');
            netText.innerHTML = params.lang_added_one_active_network;
        } else {
            netIcon.classList.replace('dashicons-yes', 'dashicons-no');
            netText.innerHTML = `<a target="_blank" href="https://app.wecantrack.com/user/data-source/networks">${params.lang_not_added_one_active_network}</a>`;
        }

        const featIcon = document.querySelector('.wecantrack-preq-feature i');
        const featText = document.querySelector('.wecantrack-preq-feature span');
        if (response.data.has_website) {
            featIcon.classList.replace('dashicons-no', 'dashicons-yes');
            featText.textContent = params.lang_website_added;
        } else {
            featIcon.classList.replace('dashicons-yes', 'dashicons-no');
            featText.innerHTML = `<a target="_blank" href="https://app.wecantrack.com/user/websites/create?website=${params.site_url}">${params.lang_website_not_added}</a>`;
        }
    }

    function user_passed_prerequisites() {
        return document.querySelectorAll('.wecantrack-prerequisites .dashicons-yes').length >= 2;
    }

    function reset_form() {
        document.querySelector('.wecantrack-prerequisites').classList.add('hidden');
        document.querySelectorAll('.dashicons-yes, .dashicons-no').forEach(icon => {
            icon.classList.remove('dashicons-no');
        });
        document.querySelectorAll('.wecantrack-snippet, .wecantrack-session-enabler, .wecantrack-plugin-status, #wecantrack_ajax_form .submit').forEach(el => {
            el.classList.add('hidden');
        });
    }

    $submit_verified.addEventListener('click', function () {
        $submit_type.value = params.lang_verified;
    });

    $form.addEventListener('submit', function (event) {
        clear_messages();
        event.preventDefault();
        if (busy) return;
        busy = 1;
        current_key = '';
        $loading.style.display = 'block';

        const key = $api_key.value;
        let formData = new FormData($form);
        formData.append('ajaxrequest', 'true');
        formData.append('submit', 'Submit Form');

        fetch(params.ajaxurl, {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(response => {
            if (response.data?.error?.includes('Unauthorised')) {
                error_message(params.lang_invalid_api_key);
                reset_form();
            } else if (response.data?.error) {
                error_message(response.data.error);
                reset_form();
            } else {
                success_message(params.lang_valid_api_key + '<br>' + params.lang_changes_saved);
                current_key = key;
                check_prerequisites(response);
            }
        }).catch(() => {
            error_message(params.lang_something_went_wrong);
        }).finally(() => {
            busy = 0;
            $loading.style.display = 'none';
            if (user_passed_prerequisites()) {
                document.querySelectorAll('.wecantrack-snippet.hidden, .wecantrack-plugin-status.hidden, #wecantrack_ajax_form .submit.hidden').forEach(el => {
                    el.classList.remove('hidden');
                });

                const pluginStatus = document.querySelector('.wecantrack-plugin-status input[name="wecantrack_plugin_status"]:checked');
                if (pluginStatus && pluginStatus.value === '0') {
                    document.querySelector('.wecantrack-session-enabler.hidden')?.classList.remove('hidden');
                }
            }
        });
    });

    if ($api_key.value.length > 30) {
        $form.dispatchEvent(new Event('submit'));
    }

    function clear_messages() {
        const top = document.getElementById("wecantrack_form_feedback_top");
        const bottom = document.getElementById("wecantrack_form_feedback_bottom");
        if (top) top.innerHTML = "";
        if (bottom) bottom.innerHTML = "";
    }
    
    function error_message(message, position = 'top') {
        clear_messages();
        const el = position === 'top' 
            ? document.getElementById("wecantrack_form_feedback_top")
            : document.getElementById("wecantrack_form_feedback_bottom");
        if (el) el.innerHTML = `<h2 class='wecantrack-text-danger'>${message}</h2><br>`;
    }
    
    function success_message(message, position = 'top') {
        clear_messages();
        const el = position === 'top' 
            ? document.getElementById("wecantrack_form_feedback_top")
            : document.getElementById("wecantrack_form_feedback_bottom");
        if (el) el.innerHTML = `<h2 class='wecantrack-text-success'>${message}</h2><br>`;
    }

    const sessionInputs = $session_enabler.querySelectorAll('input');
    sessionInputs.forEach(input => {
        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                $submit_verified.click();
                e.preventDefault();
            }
        });
    });
});