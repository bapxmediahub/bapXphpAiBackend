<?php
require __DIR__ . '/../app/bootstrap.php';

use App\Services\MailQueueService;
use App\Services\SecretService;
use App\Services\SmtpMailer;

$secrets = (new SecretService())->all();
$mailer = new SmtpMailer($secrets);
if (!$mailer->configured()) {
    fwrite(STDERR, "Email delivery is not configured.\n");
    exit(1);
}

$sent = (new MailQueueService())->processDue($mailer);
echo "Sent {$sent} queued emails.\n";
