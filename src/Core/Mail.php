<?php

declare(strict_types=1);

namespace Phai\Core;

/**
 * Sends one email through the SMTP server in config.php (mail.*), with attachments — no library.
 *
 *     Mail::send(['to' => 'buyer@example.test', 'cc' => ['csemail@etax.teda.th'], 'subject' => '...',
 *                 'body' => 'text', 'attachments' => [['name' => 'IV1.pdf', 'data' => $bytes, 'type' => 'application/pdf']]]);
 *
 * mail.host 'file' writes the message to storage/mail/ instead (tests, or trying without a server).
 * Throws \RuntimeException with the server's answer when sending fails.
 */
final class Mail
{
    /** @return string the Message-ID */
    public static function send(array $m): string
    {
        $c = (array) App::config('mail', []);
        $from = trim((string) ($c['from'] ?? ''));
        if ($from === '' || trim((string) ($c['host'] ?? '')) === '') {
            throw new \RuntimeException(t('etax.mail_not_set'));
        }
        $to = (array) $m['to'];
        $cc = (array) ($m['cc'] ?? []);
        $id = '<' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'phaierp') . '>';
        $message = self::compose($m, $from, (string) ($c['from_name'] ?? ''), $to, $cc, $id);

        if ($c['host'] === 'file') {
            $dir = PHAI_ROOT . '/storage/mail';
            if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
                throw new \RuntimeException('Cannot write storage/mail');
            }
            file_put_contents($dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml', $message);
            return $id;
        }
        self::smtp($c, $from, [...$to, ...$cc], $message);
        return $id;
    }

    private static function compose(array $m, string $from, string $fromName, array $to, array $cc, string $id): string
    {
        $b = 'phai-' . bin2hex(random_bytes(8));
        $head = [
            'Date: ' . date('r'),
            'From: ' . ($fromName !== '' ? self::word($fromName) . " <$from>" : $from),
            'To: ' . implode(', ', $to),
        ];
        if ($cc) {
            $head[] = 'Cc: ' . implode(', ', $cc);
        }
        array_push($head, 'Message-ID: ' . $id, 'Subject: ' . self::word((string) $m['subject']), 'MIME-Version: 1.0',
            "Content-Type: multipart/mixed; boundary=\"$b\"");
        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode((string) ($m['body'] ?? '')));
        foreach ($m['attachments'] ?? [] as $a) {
            $name = self::word($a['name']);
            $body .= "--$b\r\nContent-Type: " . ($a['type'] ?? 'application/octet-stream') . "; name=\"$name\"\r\n"
                . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$name\"\r\n\r\n"
                . chunk_split(base64_encode($a['data']));
        }
        return implode("\r\n", $head) . "\r\n\r\n" . $body . "--$b--\r\n";
    }

    /** A header word: plain ASCII stays, anything else becomes =?UTF-8?B?...?= */
    private static function word(string $s): string
    {
        $s = str_replace(["\r", "\n"], ' ', $s);
        return preg_match('/^[\x20-\x7e]*$/', $s) ? $s : '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    private static function smtp(array $c, string $from, array $rcpt, string $message): void
    {
        $secure = (string) ($c['secure'] ?? 'tls');
        $port = (int) ($c['port'] ?? ($secure === 'ssl' ? 465 : 587));
        $host = (string) $c['host'];
        $s = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $no, $err, 20);
        if (!$s) {
            throw new \RuntimeException("SMTP $host:$port $err");
        }
        stream_set_timeout($s, 30);
        try {
            $say = function (?string $line, int $want) use ($s): string {
                if ($line !== null) {
                    fwrite($s, $line . "\r\n");
                }
                $reply = '';
                while (($l = fgets($s, 1024)) !== false) {
                    $reply .= $l;
                    if (strlen($l) < 4 || $l[3] === ' ') {
                        break;
                    }
                }
                if ((int) substr($reply, 0, 3) !== $want) {
                    throw new \RuntimeException('SMTP ' . trim($reply ?: 'no answer'));
                }
                return $reply;
            };
            $me = gethostname() ?: 'localhost';
            $say(null, 220);
            $ehlo = $say("EHLO $me", 250);
            if ($secure === 'tls') {
                $say('STARTTLS', 220);
                if (!stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new \RuntimeException('SMTP STARTTLS failed');
                }
                $ehlo = $say("EHLO $me", 250);
            }
            if (($c['user'] ?? '') !== '') {
                if (str_contains($ehlo, 'PLAIN')) {
                    $say('AUTH PLAIN ' . base64_encode("\0" . $c['user'] . "\0" . ($c['pass'] ?? '')), 235);
                } else {
                    $say('AUTH LOGIN', 334);
                    $say(base64_encode((string) $c['user']), 334);
                    $say(base64_encode((string) ($c['pass'] ?? '')), 235);
                }
            }
            $say("MAIL FROM:<$from>", 250);
            foreach ($rcpt as $r) {
                $say("RCPT TO:<$r>", 250);
            }
            $say('DATA', 354);
            // a line starting with "." is doubled (RFC 5321 4.5.2)
            $say(preg_replace('/^\./m', '..', $message) . "\r\n.", 250);
            $say('QUIT', 221);
        } finally {
            fclose($s);
        }
    }
}
