<?php

namespace App\Enums;

enum ShipmentTrackingStatus: string
{
    case Pending = 'pending';
    case InfoReceived = 'info_received';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case AttemptFailed = 'attempt_failed';
    case Delivered = 'delivered';
    case AvailableForPickup = 'available_for_pickup';
    case Exception = 'exception';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::InfoReceived => __('Information received'),
            self::InTransit => __('In transit'),
            self::OutForDelivery => __('Out for delivery'),
            self::AttemptFailed => __('Delivery attempt failed'),
            self::Delivered => __('Delivered'),
            self::AvailableForPickup => __('Available for pickup'),
            self::Exception => __('Delivery exception'),
            self::Expired => __('Tracking expired'),
        };
    }

    public static function fromAfterShip(?string $tag): self
    {
        return match (strtolower((string) preg_replace('/[^a-z]/i', '', $tag ?? ''))) {
            'inforeceived' => self::InfoReceived,
            'intransit' => self::InTransit,
            'outfordelivery' => self::OutForDelivery,
            'attemptfail', 'attemptfailed' => self::AttemptFailed,
            'delivered' => self::Delivered,
            'availableforpickup' => self::AvailableForPickup,
            'exception' => self::Exception,
            'expired' => self::Expired,
            default => self::Pending,
        };
    }
}
