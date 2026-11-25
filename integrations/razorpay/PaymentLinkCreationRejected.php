<?php
declare(strict_types=1);

namespace App\Integrations\Razorpay;

/** The create request was rejected before the provider could create a link. */
final class PaymentLinkCreationRejected extends \RuntimeException
{
    public function __construct(int $status = 401)
    {
        parent::__construct('Razorpay rejected payment-link creation.', $status);
    }
}
