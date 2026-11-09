<?php
/**
 * Contact form handler for the NexdeskAI landing page.
 *
 * Sends one plain-text email to the admin address using PHP mail(). On Cloudways
 * the server-wide SMTP add-on (Elastic Email or custom SMTP) routes mail(), so no
 * SMTP password lives in this repository.
 *
 * Optional private overrides: a file named nexdeskai-mail.local.php one folder
 * above the webroot may return an array with 'to' and 'from'. It is never
 * committed.
 */

$config = array(
    'to' => 'admin@mugenai.com',
    'from' => 'no-reply@nexdeskai.com',
    'allowed_hosts' => array('nexdeskai.com', 'www.nexdeskai.com', 'localhost', '127.0.0.1'),
    'rate_limit' => 5,
    'rate_window' => 3600,
);

$override = dirname(__DIR__) . '/nexdeskai-mail.local.php';
if (is_readable($override)) {
    $values = include $override;
    if (is_array($values)) {
        $config = array_merge($config, $values);
    }
}

$interests = array(
    'discovery' => 'Free discovery session',
    'pilot' => 'Pilot partner',
    'question' => 'A question',
);

function nx_wants_json() {
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    return strpos($accept, 'application/json') !== false;
}

function nx_respond($status, $payload) {
    http_response_code($status);
    if (nx_wants_json()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($payload);
        exit;
    }
    if (!empty($payload['ok'])) {
        header('Location: thanks.html', true, 303);
        exit;
    }
    header('Content-Type: text/html; charset=UTF-8');
    $items = '';
    $messages = isset($payload['errors']) ? array_values($payload['errors']) : array($payload['message']);
    foreach ($messages as $message) {
        $items .= '<li>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>We could not send your request</title></head>'
        . '<body style="font-family:Segoe UI,Arial,sans-serif;max-width:34rem;margin:3rem auto;padding:0 1.25rem;color:#061a40">'
        . '<h1 style="font-size:1.5rem">We could not send your request</h1><ul>' . $items . '</ul>'
        . '<p><a href="./#contact">Go back to the form</a> or email <a href="mailto:' . htmlspecialchars($GLOBALS['config']['to'], ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($GLOBALS['config']['to'], ENT_QUOTES, 'UTF-8') . '</a>.</p></body></html>';
    exit;
}

function nx_clean($value, $max) {
    $value = is_string($value) ? $value : '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    $value = trim($value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

function nx_single_line($value) {
    return trim(preg_replace('/\s+/', ' ', $value));
}

function nx_rate_limited($ip, $limit, $window) {
    $file = rtrim(sys_get_temp_dir(), '/\\') . '/nx_contact_' . md5($ip) . '.json';
    $now = time();
    $handle = @fopen($file, 'c+');
    if (!$handle) {
        return false;
    }
    flock($handle, LOCK_EX);
    $stamps = json_decode((string) stream_get_contents($handle), true);
    $stamps = is_array($stamps) ? $stamps : array();
    $stamps = array_values(array_filter($stamps, function ($t) use ($now, $window) {
        return is_int($t) && $t > $now - $window;
    }));
    $limited = count($stamps) >= $limit;
    if (!$limited) {
        $stamps[] = $now;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($stamps));
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $limited;
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    nx_respond(405, array('ok' => false, 'message' => 'Please use the form on the page to send a request.'));
}

$source = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
if ($source !== '') {
    $host = parse_url($source, PHP_URL_HOST);
    if (!$host || !in_array(strtolower($host), $config['allowed_hosts'], true)) {
        nx_respond(403, array('ok' => false, 'message' => 'This request did not come from the NexdeskAI site.'));
    }
}

if (nx_clean(isset($_POST['company_site']) ? $_POST['company_site'] : '', 200) !== '') {
    nx_respond(200, array('ok' => true));
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
if (nx_rate_limited($ip, (int) $config['rate_limit'], (int) $config['rate_window'])) {
    nx_respond(429, array('ok' => false, 'message' => 'Too many requests from your connection. Please try again later or email us directly.'));
}

$name = nx_single_line(nx_clean(isset($_POST['name']) ? $_POST['name'] : '', 100));
$email = nx_single_line(nx_clean(isset($_POST['email']) ? $_POST['email'] : '', 150));
$company = nx_single_line(nx_clean(isset($_POST['company']) ? $_POST['company'] : '', 120));
$phone = nx_single_line(nx_clean(isset($_POST['phone']) ? $_POST['phone'] : '', 30));
$interest = nx_clean(isset($_POST['interest']) ? $_POST['interest'] : '', 20);
$message = nx_clean(isset($_POST['message']) ? $_POST['message'] : '', 2000);

$errors = array();
if (strlen($name) < 2) {
    $errors['name'] = 'Please enter your name.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($phone !== '' && !preg_match('/^[0-9+()\-.\s]{5,30}$/', $phone)) {
    $errors['phone'] = 'Please use digits, spaces, and + - ( ) only for the phone number.';
}
if (!isset($interests[$interest])) {
    $errors['interest'] = 'Please choose what you would like to do.';
}
if ($errors) {
    nx_respond(422, array('ok' => false, 'errors' => $errors));
}

$subject = '[NexdeskAI] ' . $interests[$interest] . ' request from ' . $name;
$lines = array(
    'New request from the NexdeskAI website',
    '',
    'Interest: ' . $interests[$interest],
    'Name: ' . $name,
    'Email: ' . $email,
    'Company: ' . ($company !== '' ? $company : '(not given)'),
    'Phone: ' . ($phone !== '' ? $phone : '(not given)'),
    '',
    'Message:',
    $message !== '' ? $message : '(none)',
    '',
    'Sent: ' . gmdate('Y-m-d H:i') . ' UTC',
);
$body = implode("\r\n", $lines);

$headers = array(
    'From: NexdeskAI Website <' . $config['from'] . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: NexdeskAI contact form',
);

$sent = mail($config['to'], '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
if (!$sent) {
    error_log('NexdeskAI contact form: mail() returned false');
    nx_respond(500, array('ok' => false, 'message' => 'We could not send your request right now. Please email us directly.'));
}

nx_respond(200, array('ok' => true));
