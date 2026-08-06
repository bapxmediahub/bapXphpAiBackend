<?php
namespace App\Services;

/**
 * The couriers this shop ships with, and where a customer follows a parcel.
 *
 * The admin used to type the courier name and paste a tracking URL by hand for every
 * shipment. That is the same seven links retyped forever, and one slip puts a dead
 * link in a customer's shipment email — which the customer cannot fix and support
 * cannot see. The admin now picks a courier and enters the tracking ID; the link comes
 * from here.
 *
 * India Post is deliberately the site root. Its tracking deep links do not resolve:
 * http://www.indiapost.gov.in/tracking times out and the https form answers 404, so
 * sending a customer there would be worse than sending them to the front page with
 * their tracking number in hand.
 */
final class CourierService
{
    /** name => public tracking page. Order is the order shown in the admin dropdown. */
    private const COURIERS = [
        'DTDC'           => 'https://www.dtdc.com/track-your-shipment/',
        'Blue Dart'      => 'https://www.bluedart.com/tracking',
        'India Post'     => 'https://www.indiapost.gov.in/',
        'FedEx'          => 'https://www.fedex.com/en-in/tracking.html',
        'ST Courier'     => 'https://stcourier.com/track/shipment',
        'TPC Globe'      => 'https://tpcglobe.com/',
        'Franch Express' => 'https://franchexpress.com/courier-tracking/',
    ];

    /** @return string[] Courier names, for the admin dropdown. */
    public static function names(): array
    {
        return array_keys(self::COURIERS);
    }

    /** @return array<string,string> name => tracking page, for building the UI. */
    public static function all(): array
    {
        return self::COURIERS;
    }

    public static function isKnown(string $name): bool
    {
        return isset(self::COURIERS[trim($name)]);
    }

    /** The courier's tracking page, or '' when the name is not one of ours. */
    public static function trackingUrl(string $name): string
    {
        return self::COURIERS[trim($name)] ?? '';
    }
}
