<?php

class WecantrackAppRedirectPage
{
    private $drop_referrer_cookie;
    private ?string $snippet;
    protected $options;
    const REDIRECT_PAGE_ENDPOINT = '/_wct/redirect';

    /**
     * WecantrackAppRedirectPage constructor.
     * @param bool $drop_referrer_cookie
     */
    public function __construct($drop_referrer_cookie = true, ?string $snippet = null)
    {
        $this->options = unserialize(get_option('wecantrack_redirect_options'));
        $this->snippet = $snippet;
        $this->drop_referrer_cookie = $drop_referrer_cookie;
    }

    public static function current_url_is_redirect_page_endpoint() {
        return strpos($_SERVER['REQUEST_URI'], self::REDIRECT_PAGE_ENDPOINT) !== false;
    }

    public function redirect_option_status_is_enabled() {
        return isset($this->options['status']) && $this->options['status'] === 1;
    }

    public function redirect_page_is_enabled() {
        if (isset($this->options['url_contains']) && !empty($this->options['url_contains'])) {
            if (strpos($_SERVER['REQUEST_URI'], $this->options['url_contains']) === false) {
                return false;
            }
        }

        return $this->redirect_option_status_is_enabled();
    }

    public function redirect_through_page($link) {
        WecantrackApp::revertHttpReferrer($this->drop_referrer_cookie);

        if (isset($this->options['delay']) && (int) $this->options['delay'] >= 0) {
            $delay = (int) $this->options['delay'];
        } else {
            $delay = 3;
        }

        if (!empty($this->options['delay'])) {
            $redirect_text = $this->options['redirect_text'];
        } else {
            $redirect_text = 'You are being directed to the merchant, one moment please';
        }

        // We internally meta redirect to $link. The WecantrackApp constructor will use header redirect to the affiliate link without doing a clickout request.
        $link = rtrim(WecantrackApp::current_url(true), '/').self::REDIRECT_PAGE_ENDPOINT.'?link='.rawurlencode($link);
        $this->render_redirect_page($link, $redirect_text, $delay);
    }
    
    public function render_redirect_page($link, $redirect_text, $delay = 2) {
        WecantrackApp::set_no_cache_headers();
        http_response_code(301);
        header('X-Robots-Tag: noindex', true);

        echo '<!DOCTYPE html><html><head>';
        echo '<title>Redirecting..</title>';

        echo "<meta http-equiv=\"refresh\" content=\"{$delay};URL={$link}\" />";

        $this->insert_snippet();

        if ($customHeader = get_option('wecantrack_custom_redirect_html')) {
            echo $customHeader;
        }

        echo '</head><body>';
        //https://loading.io/css/
        echo '<style>.lds-spinner div{transform-origin:40px 40px;animation:lds-spinner 1.2s linear infinite}.lds-spinner div:after{content:" ";display:block;position:absolute;top:3px;left:37px;width:6px;height:18px;border-radius:20%;background:#000}.lds-spinner div:nth-child(1){transform:rotate(0);animation-delay:-1.1s}.lds-spinner div:nth-child(2){transform:rotate(30deg);animation-delay:-1s}.lds-spinner div:nth-child(3){transform:rotate(60deg);animation-delay:-.9s}.lds-spinner div:nth-child(4){transform:rotate(90deg);animation-delay:-.8s}.lds-spinner div:nth-child(5){transform:rotate(120deg);animation-delay:-.7s}.lds-spinner div:nth-child(6){transform:rotate(150deg);animation-delay:-.6s}.lds-spinner div:nth-child(7){transform:rotate(180deg);animation-delay:-.5s}.lds-spinner div:nth-child(8){transform:rotate(210deg);animation-delay:-.4s}.lds-spinner div:nth-child(9){transform:rotate(240deg);animation-delay:-.3s}.lds-spinner div:nth-child(10){transform:rotate(270deg);animation-delay:-.2s}.lds-spinner div:nth-child(11){transform:rotate(300deg);animation-delay:-.1s}.lds-spinner div:nth-child(12){transform:rotate(330deg);animation-delay:0s}@keyframes lds-spinner{0%{opacity:1}100%{opacity:0}}</style>';
        echo '<style>.lds-spinner{width:80px; height:80px;margin: 0 auto;} #text{text-align:center;}</style>';
        echo '<div style="width:100%;">';
        echo '<p id="text">'.$redirect_text.'</p>';
        echo '<div class="lds-spinner"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>';
        echo '</div>';
        echo '</body></html>';

        exit;
    }

    /**
     * Inserts the WCT Snippet with preload tag.
     *
     * @param bool $force_session_snippet
     */
    public function insert_snippet() {
        if (! $this->snippet) {
            return;
        }

        preg_match('/s\.src ?= ?\'([^\']+)/', $this->snippet, $scriptSrcStringMatch);

        // if $scriptSrcStringMatch contains ?
        if (!empty($scriptSrcStringMatch[1])) {
            if (strpos($scriptSrcStringMatch[1], 'type=session') === false) {
                if (strpos($scriptSrcStringMatch[1], '?') === false) {
                    $scriptSrcStringMatch[1] .= '&type=session';
                } else {
                    $scriptSrcStringMatch[1] .= '?type=session';
                }
            }

            echo '<link rel="preload" href="'.$scriptSrcStringMatch[1].'" as="script">';
            echo '<script type="text/javascript" data-ezscrex="false" async>'.$this->snippet.'</script>';
        }
    }
}