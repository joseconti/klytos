<?php
/**
 * Klytos -- Mailer
 * Central email sending service for the entire CMS.
 *
 * Every component that needs to send email (2FA magic links, password recovery,
 * user registration, e-commerce plugins, form notifications, etc.) MUST use
 * this class instead of calling mail() directly.
 *
 * Transport:
 *   - Default: PHP mail() function.
 *   - Optional: SMTP via fsockopen (built-in, zero dependencies).
 *   - Extensible: plugins can override the transport via the 'mailer.send' filter.
 *
 * Templates:
 *   All emails are rendered through a base HTML layout that plugins can
 *   customize via the 'mailer.html_template' filter.
 *
 * Usage:
 *   $mailer = $app->getMailer();
 *
 *   // Simple text email.
 *   $mailer->send('user@example.com', 'Subject', 'Plain text body.');
 *
 *   // HTML email.
 *   $mailer->send('user@example.com', 'Subject', '<h1>Hello</h1>', ['html' => true]);
 *
 *   // With custom from.
 *   $mailer->send('user@example.com', 'Subject', 'Body', [
 *       'from_name'  => 'My Store',
 *       'from_email' => 'shop@example.com',
 *   ]);
 *
 *   // With reply-to and CC.
 *   $mailer->send('user@example.com', 'Subject', 'Body', [
 *       'reply_to' => 'support@example.com',
 *       'cc'       => ['manager@example.com'],
 *       'bcc'      => ['archive@example.com'],
 *   ]);
 *
 * @package Klytos
 * @since   0.5.0
 *
 * @license    Elastic License 2.0 (ELv2) -- https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 Jose Conti -- https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class Mailer
{
    /** @var array Email configuration (from site config). */
    private array $config;

    /** @var string Site name (used as default From name). */
    private string $siteName;

    /** @var array Last error info. */
    private array $lastError = [];

    /**
     * @param array  $emailConfig Email settings from site config (smtp_host, smtp_port, etc.).
     * @param string $siteName    Site name for default From header.
     */
    public function __construct(array $emailConfig = [], string $siteName = 'Klytos')
    {
        $this->config   = $emailConfig;
        $this->siteName = $siteName;
    }

    // ================================================================
    //  Public API
    // ================================================================

    /**
     * Send an email.
     *
     * @param  string|array $to      Recipient(s). String or array of strings.
     * @param  string       $subject Email subject.
     * @param  string       $body    Email body (plain text or HTML).
     * @param  array        $options Optional settings:
     *   - 'html'       => bool   Send as HTML (default: auto-detect).
     *   - 'from_name'  => string Override From name.
     *   - 'from_email' => string Override From email.
     *   - 'reply_to'   => string Reply-To address.
     *   - 'cc'         => array  CC recipients.
     *   - 'bcc'        => array  BCC recipients.
     *   - 'headers'    => array  Additional raw headers.
     *   - 'template'   => bool   Wrap body in HTML template (default: true for HTML).
     * @return bool True if sent successfully.
     */
    public function send(string|array $to, string $subject, string $body, array $options = []): bool
    {
        $this->lastError = [];

        // Normalize recipients.
        $recipients = is_array($to) ? $to : [$to];
        $recipients = array_filter(array_map('trim', $recipients));

        if (empty($recipients)) {
            $this->lastError = ['code' => 'no_recipient', 'message' => 'No recipient specified.'];
            return false;
        }

        // Validate all recipients.
        foreach ($recipients as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->lastError = ['code' => 'invalid_email', 'message' => "Invalid email: {$email}"];
                return false;
            }
        }

        // Determine content type.
        $isHtml = $options['html'] ?? $this->looksLikeHtml($body);

        // Wrap in HTML template if requested and HTML mode.
        if ($isHtml && ($options['template'] ?? true)) {
            $body = $this->wrapInTemplate($body, $subject);
        }

        // Build From.
        $fromName  = $options['from_name']  ?? $this->config['from_name']  ?? $this->siteName;
        $fromEmail = $options['from_email'] ?? $this->config['from_email'] ?? $this->defaultFromEmail();

        // Build headers.
        $headers = $this->buildHeaders($fromName, $fromEmail, $isHtml, $options);

        // Allow plugins to completely override the send mechanism.
        // Return true from the filter to indicate the email was handled.
        $handled = Hooks::applyFilters('mailer.send', false, [
            'to'      => $recipients,
            'subject' => $subject,
            'body'    => $body,
            'headers' => $headers,
            'options' => $options,
            'config'  => $this->config,
        ]);

        if ($handled === true) {
            return true;
        }

        // Fire pre-send action (logging, analytics, etc.).
        Hooks::doAction('mailer.before_send', $recipients, $subject);

        // Choose transport.
        $transport = $this->config['transport'] ?? 'mail';

        if ($transport === 'smtp' && !empty($this->config['smtp_host'])) {
            $result = $this->sendSmtp($recipients, $subject, $body, $headers, $options);
        } else {
            $result = $this->sendPhpMail($recipients, $subject, $body, $headers);
        }

        // Fire post-send action.
        Hooks::doAction('mailer.after_send', $recipients, $subject, $result);

        return $result;
    }

    /**
     * Get the last error (if any).
     *
     * @return array ['code' => string, 'message' => string] or empty array.
     */
    public function getLastError(): array
    {
        return $this->lastError;
    }

    /**
     * Test the current email configuration by sending a test email.
     *
     * @param  string $to Test recipient.
     * @return bool
     */
    public function sendTest(string $to): bool
    {
        return $this->send(
            $to,
            "[{$this->siteName}] Email test",
            '<h2>Email test</h2><p>If you can read this, your email configuration is working correctly.</p>',
            ['html' => true]
        );
    }

    // ================================================================
    //  Transport: PHP mail()
    // ================================================================

    /**
     * Send via PHP's built-in mail() function.
     */
    private function sendPhpMail(array $to, string $subject, string $body, string $headers): bool
    {
        $toStr = implode(', ', $to);
        $result = @mail($toStr, $subject, $body, $headers);

        if (!$result) {
            $this->lastError = [
                'code'    => 'mail_failed',
                'message' => 'PHP mail() returned false. Check server mail configuration.',
            ];
        }

        return $result;
    }

    // ================================================================
    //  Transport: SMTP (built-in, zero dependencies)
    // ================================================================

    /**
     * Send via direct SMTP connection.
     * Supports STARTTLS, AUTH LOGIN, and AUTH PLAIN.
     */
    private function sendSmtp(array $to, string $subject, string $body, string $headers, array $options): bool
    {
        $host     = $this->config['smtp_host'] ?? '';
        $port     = (int) ($this->config['smtp_port'] ?? 587);
        $username = $this->config['smtp_user'] ?? '';
        $password = $this->config['smtp_pass'] ?? '';
        $security = $this->config['smtp_security'] ?? 'tls'; // 'tls', 'ssl', or ''

        $fromName  = $options['from_name']  ?? $this->config['from_name']  ?? $this->siteName;
        $fromEmail = $options['from_email'] ?? $this->config['from_email'] ?? $this->defaultFromEmail();

        // Build the full message with headers.
        $message = $headers . "\r\n\r\n" . $body;

        // Open connection.
        $prefix = ($security === 'ssl') ? 'ssl://' : '';
        $errno  = 0;
        $errstr = '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 15);

        if (!$socket) {
            $this->lastError = [
                'code'    => 'smtp_connect_failed',
                'message' => "Cannot connect to {$host}:{$port} — {$errstr}",
            ];
            return false;
        }

        stream_set_timeout($socket, 30);

        try {
            // Read greeting.
            $this->smtpRead($socket, 220);

            // EHLO.
            $ehloHost = $_SERVER['HTTP_HOST'] ?? gethostname() ?: 'localhost';
            $this->smtpWrite($socket, "EHLO {$ehloHost}");
            $ehloResponse = $this->smtpRead($socket, 250);

            // STARTTLS if needed (port 587 typically).
            if ($security === 'tls' && stripos($ehloResponse, 'STARTTLS') !== false) {
                $this->smtpWrite($socket, 'STARTTLS');
                $this->smtpRead($socket, 220);

                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);

                if (!$crypto) {
                    throw new \RuntimeException('STARTTLS negotiation failed.');
                }

                // Re-EHLO after TLS.
                $this->smtpWrite($socket, "EHLO {$ehloHost}");
                $this->smtpRead($socket, 250);
            }

            // AUTH if credentials provided.
            if (!empty($username) && !empty($password)) {
                $this->smtpWrite($socket, 'AUTH LOGIN');
                $this->smtpRead($socket, 334);

                $this->smtpWrite($socket, base64_encode($username));
                $this->smtpRead($socket, 334);

                $this->smtpWrite($socket, base64_encode($password));
                $this->smtpRead($socket, 235);
            }

            // MAIL FROM.
            $this->smtpWrite($socket, "MAIL FROM:<{$fromEmail}>");
            $this->smtpRead($socket, 250);

            // RCPT TO (all recipients + CC + BCC).
            $allRecipients = $to;
            if (!empty($options['cc'])) {
                $allRecipients = array_merge($allRecipients, (array) $options['cc']);
            }
            if (!empty($options['bcc'])) {
                $allRecipients = array_merge($allRecipients, (array) $options['bcc']);
            }

            foreach ($allRecipients as $recipient) {
                $this->smtpWrite($socket, "RCPT TO:<{$recipient}>");
                $this->smtpRead($socket, 250);
            }

            // DATA.
            $this->smtpWrite($socket, 'DATA');
            $this->smtpRead($socket, 354);

            // Build full email.
            $fullMessage  = "Subject: {$subject}\r\n";
            $fullMessage .= "To: " . implode(', ', $to) . "\r\n";
            $fullMessage .= $headers . "\r\n";
            $fullMessage .= "\r\n";
            $fullMessage .= $body . "\r\n";
            $fullMessage .= '.';

            $this->smtpWrite($socket, $fullMessage);
            $this->smtpRead($socket, 250);

            // QUIT.
            $this->smtpWrite($socket, 'QUIT');

        } catch (\RuntimeException $e) {
            $this->lastError = [
                'code'    => 'smtp_error',
                'message' => $e->getMessage(),
            ];
            @fclose($socket);
            return false;
        }

        @fclose($socket);
        return true;
    }

    /**
     * Write a command to the SMTP socket.
     *
     * @param resource $socket
     * @param string   $command
     */
    private function smtpWrite($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    /**
     * Read and validate an SMTP response.
     *
     * @param  resource $socket
     * @param  int      $expectedCode
     * @return string Full response text.
     * @throws \RuntimeException If the response code doesn't match.
     */
    private function smtpRead($socket, int $expectedCode): string
    {
        $response = '';
        while (true) {
            $line = fgets($socket, 512);
            if ($line === false) {
                throw new \RuntimeException('SMTP connection lost.');
            }
            $response .= $line;
            // If 4th char is a space, this is the last line.
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException(
                "SMTP error: expected {$expectedCode}, got {$code}. Response: " . trim($response)
            );
        }

        return $response;
    }

    // ================================================================
    //  Header Building
    // ================================================================

    /**
     * Build email headers string.
     */
    private function buildHeaders(string $fromName, string $fromEmail, bool $isHtml, array $options): string
    {
        $headers = [];

        // From.
        $headers[] = "From: {$fromName} <{$fromEmail}>";

        // Reply-To.
        $replyTo = $options['reply_to'] ?? $this->config['reply_to'] ?? '';
        if (!empty($replyTo)) {
            $headers[] = "Reply-To: {$replyTo}";
        }

        // CC.
        if (!empty($options['cc'])) {
            $cc = is_array($options['cc']) ? $options['cc'] : [$options['cc']];
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }

        // BCC.
        if (!empty($options['bcc'])) {
            $bcc = is_array($options['bcc']) ? $options['bcc'] : [$options['bcc']];
            $headers[] = 'Bcc: ' . implode(', ', $bcc);
        }

        // Content type.
        if ($isHtml) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }

        // Message-ID.
        $domain = $this->extractDomain($fromEmail);
        $headers[] = 'Message-ID: <' . Helpers::randomHex(16) . '@' . $domain . '>';

        // X-Mailer.
        $headers[] = 'X-Mailer: Klytos CMS';

        // Additional custom headers.
        if (!empty($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $header) {
                $headers[] = $header;
            }
        }

        // Allow plugins to modify headers.
        $headers = Hooks::applyFilters('mailer.headers', $headers, $options);

        return implode("\r\n", $headers);
    }

    // ================================================================
    //  HTML Email Template
    // ================================================================

    /**
     * Wrap an HTML body in a responsive email template.
     *
     * Plugins can override the entire template via the 'mailer.html_template' filter.
     *
     * @param  string $content HTML content.
     * @param  string $subject Email subject (used in the template header).
     * @return string Complete HTML email.
     */
    private function wrapInTemplate(string $content, string $subject): string
    {
        $siteName = htmlspecialchars($this->siteName, ENT_QUOTES, 'UTF-8');

        $template = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .email-wrapper { max-width: 600px; margin: 0 auto; padding: 20px; }
        .email-header { background: #2563eb; color: #ffffff; padding: 24px 32px; border-radius: 12px 12px 0 0; text-align: center; }
        .email-header h1 { margin: 0; font-size: 1.2rem; font-weight: 600; }
        .email-body { background: #ffffff; padding: 32px; border-radius: 0 0 12px 12px; color: #1e293b; font-size: 15px; line-height: 1.6; }
        .email-body a { color: #2563eb; text-decoration: none; }
        .email-body .btn { display: inline-block; padding: 12px 28px; background: #2563eb; color: #ffffff !important; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 16px 0; }
        .email-footer { text-align: center; padding: 16px; color: #94a3b8; font-size: 12px; }
        .email-footer a { color: #94a3b8; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <h1>{$siteName}</h1>
        </div>
        <div class="email-body">
            {$content}
        </div>
        <div class="email-footer">
            <p>{$siteName}</p>
        </div>
    </div>
</body>
</html>
HTML;

        // Allow plugins to completely replace the template.
        return Hooks::applyFilters('mailer.html_template', $template, $content, $subject, $this->siteName);
    }

    // ================================================================
    //  Convenience Methods
    // ================================================================

    /**
     * Send a plain-text email.
     *
     * @param  string $to      Recipient.
     * @param  string $subject Subject.
     * @param  string $body    Plain text body.
     * @return bool
     */
    public function sendText(string $to, string $subject, string $body): bool
    {
        return $this->send($to, $subject, $body, ['html' => false, 'template' => false]);
    }

    /**
     * Send an HTML email.
     *
     * @param  string $to      Recipient.
     * @param  string $subject Subject.
     * @param  string $body    HTML body content (will be wrapped in template).
     * @return bool
     */
    public function sendHtml(string $to, string $subject, string $body): bool
    {
        return $this->send($to, $subject, $body, ['html' => true]);
    }

    /**
     * Send an email with a call-to-action button.
     * Useful for magic links, password resets, confirmations, etc.
     *
     * @param  string $to         Recipient.
     * @param  string $subject    Subject.
     * @param  string $message    Message text (above the button).
     * @param  string $buttonText Button label.
     * @param  string $buttonUrl  Button URL.
     * @param  string $footer     Optional footer text (below the button).
     * @return bool
     */
    public function sendWithButton(
        string $to,
        string $subject,
        string $message,
        string $buttonText,
        string $buttonUrl,
        string $footer = ''
    ): bool {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeButton  = htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8');
        $safeUrl     = htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8');

        $html = "<p>{$safeMessage}</p>";
        $html .= "<p style=\"text-align:center;\"><a href=\"{$safeUrl}\" class=\"btn\">{$safeButton}</a></p>";

        if (!empty($footer)) {
            $safeFooter = htmlspecialchars($footer, ENT_QUOTES, 'UTF-8');
            $html .= "<p style=\"font-size:13px;color:#64748b;\">{$safeFooter}</p>";
        }

        // Also include the raw URL as fallback for email clients that strip buttons.
        $html .= "<p style=\"font-size:12px;color:#94a3b8;word-break:break-all;\">{$safeUrl}</p>";

        return $this->send($to, $subject, $html, ['html' => true]);
    }

    // ================================================================
    //  Internal Helpers
    // ================================================================

    /**
     * Check if a string looks like HTML content.
     */
    private function looksLikeHtml(string $text): bool
    {
        return (bool) preg_match('/<[a-z][\s\S]*>/i', $text);
    }

    /**
     * Generate a default From email based on the server hostname.
     */
    private function defaultFromEmail(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $host = explode(':', $host)[0]; // Remove port.
        return 'noreply@' . $host;
    }

    /**
     * Extract domain from an email address.
     */
    private function extractDomain(string $email): string
    {
        $parts = explode('@', $email);
        return $parts[1] ?? 'localhost';
    }
}
