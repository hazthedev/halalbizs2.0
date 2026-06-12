<?php

namespace App\Enums;

enum GatewayPaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Success => __('Success'),
            self::Failed => __('Failed'),
            self::Expired => __('Expired'),
        };
    }
}
