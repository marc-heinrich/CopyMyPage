<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use PHPMailer\PHPMailer\SMTP;

/** Public PHPMailer SMTP injection: local acceptance into the active CMP scope, never a socket. */
final class DeferredMailTransport extends SMTP
{
    private string $sender = '';
    private array $recipients = [];

    public function __construct(private readonly PaymentMailScope $scope, private readonly string $kind)
    {
    }

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        throw new \RuntimeException('Deferred booking mail cannot open a network connection.');
    }

    public function connected() { return true; }
    public function mail($from) { $this->sender = $from; $this->recipients = []; return true; }
    public function recipient($address, $dsn = '') { $this->recipients[] = $address; return true; }
    public function data($msg_data) { $this->scope->accept($this->kind, $this->sender, $this->recipients, $msg_data); return true; }
    public function quit($close_on_error = true) { return true; }
    public function close() {}
    public function reset() { $this->sender = ''; $this->recipients = []; return true; }
    public function getServerExt($name) { return $name === 'SMTPUTF8'; }
    public function getLastTransactionID() { return 'cmp-deferred'; }
}
