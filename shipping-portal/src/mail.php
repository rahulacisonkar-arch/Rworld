<?php
require_once __DIR__ . '/config.php';

class Mailer {
    /**
     * Send email using SMTP or fallback mail().
     *
     * @param string $to
     * @param string $subject
     * @param string $htmlContent
     * @param array $attachments Array of ['path' => string, 'name' => string]
     * @return bool
     */
    public static function send($to, $subject, $htmlContent, $attachments = []) {
        // Build beautiful Artee Fabrics & Home template
        $fullBody = self::getBrandedTemplate($subject, $htmlContent);

        // If SMTP credentials are empty, fallback to PHP mail()
        if (empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
            return self::sendMailFallback($to, $subject, $fullBody, $attachments);
        }

        try {
            return self::sendSmtp($to, $subject, $fullBody, $attachments);
        } catch (Exception $e) {
            // Log error or print
            error_log("SMTP Error: " . $e->getMessage());
            // Fallback
            return self::sendMailFallback($to, $subject, $fullBody, $attachments);
        }
    }

    /**
     * Standalone SMTP Protocol Implementation over Sockets
     */
    private static function sendSmtp($to, $subject, $body, $attachments = []) {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $username = SMTP_USER;
        $password = SMTP_PASS;
        $from = SMTP_FROM_EMAIL;
        $fromName = SMTP_FROM_NAME;

        $secure = strtolower(SMTP_SECURE);
        
        $socketHost = ($secure === 'ssl') ? 'ssl://' . $host : $host;
        $timeout = 10;
        
        $smtp = @fsockopen($socketHost, $port, $errno, $errstr, $timeout);
        if (!$smtp) {
            throw new Exception("Could not connect to SMTP host: $errstr ($errno)");
        }

        $getResponse = function($smtp) {
            $data = "";
            while ($str = fgets($smtp, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) === " ") {
                    break;
                }
            }
            return $data;
        };

        $sendCommand = function($smtp, $cmd) use ($getResponse) {
            fputs($smtp, $cmd . "\r\n");
            return $getResponse($smtp);
        };

        $getResponse($smtp); // Initial connection banner

        $sendCommand($smtp, "EHLO " . $_SERVER['SERVER_NAME']);

        if ($secure === 'tls') {
            $response = $sendCommand($smtp, "STARTTLS");
            if (strpos($response, '220') === false) {
                throw new Exception("STARTTLS failed");
            }
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS negotiation failed");
            }
            $sendCommand($smtp, "EHLO " . $_SERVER['SERVER_NAME']);
        }

        // Authentication
        $response = $sendCommand($smtp, "AUTH LOGIN");
        if (strpos($response, '334') === false) {
            throw new Exception("AUTH LOGIN not supported or failed: $response");
        }

        $response = $sendCommand($smtp, base64_encode($username));
        if (strpos($response, '334') === false) {
            throw new Exception("Username authentication failed: $response");
        }

        $response = $sendCommand($smtp, base64_encode($password));
        if (strpos($response, '235') === false) {
            throw new Exception("Password authentication failed: $response");
        }

        // Mail transaction
        $sendCommand($smtp, "MAIL FROM:<$from>");
        $sendCommand($smtp, "RCPT TO:<$to>");
        
        $response = $sendCommand($smtp, "DATA");
        if (strpos($response, '354') === false) {
            throw new Exception("DATA command rejected: $response");
        }

        $boundary = md5(uniqid(time(), true));

        // Headers
        $headers = [
            "MIME-Version: 1.0",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>",
            "To: <$to>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date('r'),
            "Message-ID: <" . md5(uniqid(microtime(), true)) . "@" . $_SERVER['SERVER_NAME'] . ">"
        ];

        if (empty($attachments)) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $messageBody = $body;
        } else {
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
            
            $messageBody = "--{$boundary}\r\n";
            $messageBody .= "Content-Type: text/html; charset=UTF-8\r\n";
            $messageBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $messageBody .= $body . "\r\n";
            
            foreach ($attachments as $att) {
                if (file_exists($att['path'])) {
                    $fileName = basename($att['name']);
                    $fileContent = file_get_contents($att['path']);
                    $encodedFile = chunk_split(base64_encode($fileContent));
                    
                    $messageBody .= "--{$boundary}\r\n";
                    $messageBody .= "Content-Type: application/pdf; name=\"{$fileName}\"\r\n";
                    $messageBody .= "Content-Transfer-Encoding: base64\r\n";
                    $messageBody .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
                    $messageBody .= $encodedFile . "\r\n";
                }
            }
            $messageBody .= "--{$boundary}--";
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $messageBody . "\r\n.";
        $response = $sendCommand($smtp, $message);
        
        $sendCommand($smtp, "QUIT");
        fclose($smtp);

        return strpos($response, '250') !== false;
    }

    /**
     * Fallback using standard PHP mail()
     */
    private static function sendMailFallback($to, $subject, $body, $attachments = []) {
        $from = SMTP_FROM_EMAIL;
        $fromName = SMTP_FROM_NAME;
        
        $boundary = md5(uniqid(time(), true));

        $headers = [
            "MIME-Version: 1.0",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>",
            "Reply-To: $from",
            "X-Mailer: PHP/" . phpversion()
        ];
        
        if (empty($attachments)) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $messageBody = $body;
        } else {
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
            
            $messageBody = "--{$boundary}\r\n";
            $messageBody .= "Content-Type: text/html; charset=UTF-8\r\n";
            $messageBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $messageBody .= $body . "\r\n";
            
            foreach ($attachments as $att) {
                if (file_exists($att['path'])) {
                    $fileName = basename($att['name']);
                    $fileContent = file_get_contents($att['path']);
                    $encodedFile = chunk_split(base64_encode($fileContent));
                    
                    $messageBody .= "--{$boundary}\r\n";
                    $messageBody .= "Content-Type: application/pdf; name=\"{$fileName}\"\r\n";
                    $messageBody .= "Content-Transfer-Encoding: base64\r\n";
                    $messageBody .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
                    $messageBody .= $encodedFile . "\r\n";
                }
            }
            $messageBody .= "--{$boundary}--";
        }
        
        return @mail($to, $subject, $messageBody, implode("\r\n", $headers));
    }

    /**
     * Branded HTML layout template
     */
    private static function getBrandedTemplate($title, $content) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body {
                    font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif;
                    background-color: #f4f6f9;
                    margin: 0;
                    padding: 0;
                    -webkit-font-smoothing: antialiased;
                }
                .container {
                    max-width: 600px;
                    margin: 40px auto;
                    background: #ffffff;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
                    border-top: 5px solid #D4AF37; /* Gold Accent */
                }
                .header {
                    background-color: #0B2545; /* Dark Blue */
                    color: #ffffff;
                    text-align: center;
                    padding: 30px;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 600;
                    letter-spacing: 1px;
                }
                .content {
                    padding: 30px;
                    line-height: 1.6;
                    color: #333333;
                    font-size: 16px;
                }
                .footer {
                    background-color: #f4f6f9;
                    text-align: center;
                    padding: 20px;
                    font-size: 12px;
                    color: #777777;
                    border-top: 1px solid #e9ecef;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 24px;
                    margin: 20px 0;
                    background-color: #0B2545;
                    color: #ffffff !important;
                    text-decoration: none;
                    border-radius: 4px;
                    font-weight: bold;
                    border: 1px solid #D4AF37;
                }
                .btn:hover {
                    background-color: #133a68;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>ARTEE FABRICS & HOME</h1>
                    <div style="font-size: 12px; color: #D4AF37; margin-top: 5px; letter-spacing: 2px;">SHIPPING PORTAL</div>
                </div>
                <div class="content">
                    ' . $content . '
                </div>
                <div class="footer">
                    This is an automated message from the Artee Fabrics & Home Logistics Department.<br>
                    &copy; ' . date('Y') . ' Artee Fabrics & Home. All rights reserved.
                </div>
            </div>
        </body>
        </html>';
    }
}
