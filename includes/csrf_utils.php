<?php
function csrf_start($use_show_error = false) {
    csrf_check($use_show_error);
    csrf_rewrite();
}

function csrf_rewrite() {
    global $nocsrfrewrite;
    if (!isset($nocsrfrewrite)) {
        csrf_token();
        ob_start('csrf_ob_handler');
    }
}

function csrf_ob_handler($buffer, $flags) {
    if (preg_match('/<html/i', $buffer)) {
        $buffer = preg_replace('#(<form[^>]*method\s*=\s*["\']post["\'][^>]*>)#i', '$1' . csrf_form_input(), $buffer);
    }

    return $buffer;
}

function csrf_form_input() {
    global $csrf_protection_name, $csrf_protection_xhtml;

    $token = csrf_token();
    $endslash = $csrf_protection_xhtml ? ' /' : '';
    return "\n            <input type=\"hidden\" name=\"$csrf_protection_name\" value=\"$token\"$endslash>\n";
}

function csrf_token() {
    global $_SESSION, $csrf_protection_name;
    static $token;

    if (!$token) {
        $token = md5(uniqid(mt_rand(), true));
        $session = (isset($_SESSION[$csrf_protection_name]) ? $_SESSION[$csrf_protection_name] : '');

        if (!is_array($session)) {
            $session = array();
        }
        $session[$token] = time();
        @$_SESSION[$csrf_protection_name];
    }

    return $token;
}

function csrf_check($use_show_error = false) {
    global $HTTP_SERVER_VARS, $HTTP_POST_VARS, $_SESSION, $csrf_protection_name, $csrf_protection_expires;

    if ($HTTP_SERVER_VARS['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (isset($HTTP_POST_VARS[$csrf_protection_name])) {
        $session = $_SESSION[$csrf_protection_name];

        if (!is_array($session)) {
            return false;
        }

        $found = false;

        foreach ($session as $token => $time) {
            if (!secure_compare($token, (string) $HTTP_POST_VARS[$csrf_protection_name])) {
                continue;
            }

            if ($csrf_protection_expires) {
                if (time() <= $time + $csrf_protection_expires) {
                    $found = true;
                } else {
                    unset($session[$token]);
                }
            } else {
                $found = true;
            }

            break;
        }

        $_SESSION[$csrf_protection_name];

        if ($found) {
            return;
        }
    }

    header($HTTP_SERVER_VARS['SERVER_PROTOCOL'] . ' 403 Forbidden');

    if ($use_show_error) {
        csrf_rewrite();
        show_error_page('CSRF check failed.');
    } else {
        echo "<html><head><title>CSRF check failed</title></head><body>CSRF check failed.</body></html>";
        exit;
    }
}

?>
