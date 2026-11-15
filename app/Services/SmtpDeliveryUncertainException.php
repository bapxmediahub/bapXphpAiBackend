<?php
namespace App\Services;
/** DATA may have reached SMTP, but its final acknowledgement was not observed. */
final class SmtpDeliveryUncertainException extends \RuntimeException {}
