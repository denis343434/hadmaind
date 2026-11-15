<?php
declare(strict_types=1);

namespace App;

/**
 * Уведомления администратору (Telegram + email) и письма клиентам.
 * Всё отправляется после ответа клиенту (Defer) и не бросает исключений:
 * сбой внешнего сервиса не должен ломать отправку формы.
 */
final class Notify
{
    /** @param array<string, scalar|null> $fields пустые значения пропускаются */
    public static function admin(string $title, array $fields): void
    {
        $rows = array_filter($fields, fn ($v) => $v !== null && $v !== '');
        $tg = implode("\n", [
            '<b>' . e($title) . '</b>',
            ...array_map(fn ($k, $v) => '<b>' . e((string) $k) . ':</b> ' . e((string) $v), array_keys($rows), $rows),
        ]);
        $html = '<h3>' . e($title) . '</h3>' . self::table($rows);

        Defer::add(fn () => self::telegram($tg));
        Defer::add(fn () => self::mail(env('MAIL_ADMIN_TO'), $title, $html));
    }

    /** Письмо клиенту (после ответа). */
    public static function customer(string $to, string $subject, array $fields): void
    {
        $html = '<h3>' . e($subject) . '</h3>' . self::table(array_filter($fields, fn ($v) => $v !== null && $v !== ''));
        Defer::add(fn () => self::mail([$to], $subject, $html));
    }

    private static function table(array $rows): string
    {
        $out = '';
        foreach ($rows as $k => $v) $out .= '<tr><td><b>' . e((string) $k) . '</b></td><td>' . e((string) $v) . '</td></tr>';
        return "<table>$out</table>";
    }

    private static function telegram(string $text): void
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        if ($token === '' || !env('TELEGRAM_CHAT_ID')) return;

        foreach (env('TELEGRAM_CHAT_ID') as $chatId) {
            $payload = toJson(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]);
            [$status, $body] = httpPost("https://api.telegram.org/bot$token/sendMessage", $payload);
            if ($status !== 200) error_log("Telegram $status: $body");
        }
    }

    /** SMTP, если задан SMTP_HOST, иначе встроенный mail() хостинга. */
    public static function mail(array $to, string $subject, string $html): void
    {
        if (!$to) return;
        if (env('SMTP_HOST') !== '') {
            (new Smtp())->send($to, $subject, $html);
            return;
        }
        [$fromName, $fromAddr] = parseAddress(env('MAIL_FROM'));
        $headers = implode("\r\n", [
            'From: ' . encodeHeader($fromName) . " <$fromAddr>",
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ]);
        if (!@mail(implode(', ', $to), encodeHeader($subject), chunk_split(base64_encode($html)), $headers)) {
            error_log('mail() не отправил письмо: настройте SMTP_* в backend/.env');
        }
    }
}

/** Минимальный SMTP-клиент: SSL (465) или STARTTLS (587), AUTH LOGIN. */
final class Smtp
{
    /** @var resource */
    private $socket;

    public function send(array $to, string $subject, string $html): void
    {
        $host = env('SMTP_HOST');
        $port = env('SMTP_PORT');
        $secure = env('SMTP_SECURE');
        $socket = @stream_socket_client(($secure ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15);
        if (!$socket) throw new \RuntimeException("SMTP: нет соединения с $host:$port ($errstr)");
        $this->socket = $socket;
        stream_set_timeout($socket, 15);

        try {
            $this->expect(220);
            $ehlo = $this->cmd('EHLO ' . (gethostname() ?: 'localhost'), 250);
            if (!$secure && str_contains($ehlo, 'STARTTLS')) {
                $this->cmd('STARTTLS', 220);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->cmd('EHLO ' . (gethostname() ?: 'localhost'), 250);
            }
            if (env('SMTP_USER') !== '') {
                $this->cmd('AUTH LOGIN', 334);
                $this->cmd(base64_encode(env('SMTP_USER')), 334);
                $this->cmd(base64_encode(env('SMTP_PASS')), 235);
            }
            [$fromName, $fromAddr] = parseAddress(env('MAIL_FROM'));
            $this->cmd("MAIL FROM:<$fromAddr>", 250);
            foreach ($to as $rcpt) $this->cmd('RCPT TO:<' . parseAddress($rcpt)[1] . '>', [250, 251]);
            $this->cmd('DATA', 354);

            $message = implode("\r\n", [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . encodeHeader($fromName) . " <$fromAddr>",
                'To: ' . implode(', ', $to),
                'Subject: ' . encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $fromAddr)[1] ?? 'localhost') . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                rtrim(chunk_split(base64_encode($html), 76, "\r\n")),
            ]);
            $this->cmd("$message\r\n.", 250);
            $this->cmd('QUIT', 221);
        } finally {
            fclose($socket);
        }
    }

    private function cmd(string $line, int|array $code): string
    {
        fwrite($this->socket, "$line\r\n");
        return $this->expect($code);
    }

    private function expect(int|array $code): string
    {
        $response = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break; // последняя строка многострочного ответа
        }
        if (!in_array((int) substr($response, 0, 3), (array) $code, true)) {
            throw new \RuntimeException('SMTP: ' . trim($response));
        }
        return $response;
    }
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** "Сайт <noreply@example.com>" → ['Сайт', 'noreply@example.com']. */
function parseAddress(string $value): array
{
    if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>\s*$/u', $value, $m)) return [trim($m[1]), trim($m[2])];
    return ['', trim($value)];
}

function encodeHeader(string $value): string
{
    return preg_match('/[^\x20-\x7e]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
}

/** POST JSON через curl, а без него — через потоки PHP. @return array{int, string} */
function httpPost(string $url, string $json): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($status === 0) $body = curl_error($ch);
        return [$status, $body];
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $json,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $body = (string) @file_get_contents($url, false, $ctx);
    preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m);
    return [(int) ($m[1] ?? 0), $body];
}
