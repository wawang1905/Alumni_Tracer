<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Email extends BaseConfig
{
    public string $fromEmail  = '';
    public string $fromName   = 'Alumni Tracer Portal';
    public string $recipients = '';

    public function __construct()
    {
        parent::__construct();

        $this->fromEmail   = (string) ($this->readEnv('email.fromEmail') ?: $this->fromEmail);
        $this->fromName    = (string) ($this->readEnv('email.fromName') ?: $this->fromName);
        $this->protocol    = (string) ($this->readEnv('email.protocol') ?: $this->protocol);
        $this->SMTPHost    = (string) ($this->readEnv('email.SMTPHost') ?: $this->SMTPHost);
        $this->SMTPUser    = (string) ($this->readEnv('email.SMTPUser') ?: $this->SMTPUser);
        $this->SMTPPass    = (string) ($this->readEnv('email.SMTPPass') ?: $this->SMTPPass);
        $this->SMTPPort    = (int) ($this->readEnv('email.SMTPPort') ?: $this->SMTPPort);
        $this->SMTPCrypto  = (string) ($this->readEnv('email.SMTPCrypto') ?: $this->SMTPCrypto);
        $this->SMTPTimeout = (int) ($this->readEnv('email.SMTPTimeout') ?: $this->SMTPTimeout);
        $this->mailType    = (string) ($this->readEnv('email.mailType') ?: 'html');
    }

    private function readEnv(string $dotKey): mixed
    {
        $underscoreKey = str_replace('.', '_', $dotKey);

        return env($dotKey) ?? env($underscoreKey);
    }

    /**
     * The "user agent"
     */
    public string $userAgent = 'CodeIgniter';

    /**
     * The mail sending protocol: mail, sendmail, smtp
     */
    public string $protocol = 'mail';

    /**
     * The server path to Sendmail.
     */
    public string $mailPath = '/usr/sbin/sendmail';

    /**
     * SMTP Server Hostname
     */
    public string $SMTPHost = '';

    /**
     * Which SMTP authentication method to use: login, plain
     */
    public string $SMTPAuthMethod = 'login';

    /**
     * SMTP Username
     */
    public string $SMTPUser = '';

    /**
     * SMTP Password
     */
    public string $SMTPPass = '';

    /**
     * SMTP Port
     */
    public int $SMTPPort = 25;

    /**
     * SMTP Timeout (in seconds)
     */
    public int $SMTPTimeout = 5;

    /**
     * Enable persistent SMTP connections
     */
    public bool $SMTPKeepAlive = false;

    /**
     * SMTP Encryption.
     *
     * @var string '', 'tls' or 'ssl'. 'tls' will issue a STARTTLS command
     *             to the server. 'ssl' means implicit SSL. Connection on port
     *             465 should set this to ''.
     */
    public string $SMTPCrypto = 'tls';

    /**
     * Enable word-wrap
     */
    public bool $wordWrap = true;

    /**
     * Character count to wrap at
     */
    public int $wrapChars = 76;

    /**
     * Type of mail, either 'text' or 'html'
     */
    public string $mailType = 'text';

    /**
     * Character set (utf-8, iso-8859-1, etc.)
     */
    public string $charset = 'UTF-8';

    /**
     * Whether to validate the email address
     */
    public bool $validate = false;

    /**
     * Email Priority. 1 = highest. 5 = lowest. 3 = normal
     */
    public int $priority = 3;

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     */
    public string $CRLF = "\r\n";

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     */
    public string $newline = "\r\n";

    /**
     * Enable BCC Batch Mode.
     */
    public bool $BCCBatchMode = false;

    /**
     * Number of emails in each BCC batch
     */
    public int $BCCBatchSize = 200;

    /**
     * Enable notify message from server
     */
    public bool $DSN = false;
}
