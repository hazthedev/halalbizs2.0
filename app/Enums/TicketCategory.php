<?php

namespace App\Enums;

enum TicketCategory: string
{
    case General = 'general';
    case ReportListing = 'report_listing';
    case Privacy = 'privacy';

    public function label(): string
    {
        return match ($this) {
            self::General => __('General'),
            self::ReportListing => __('Report a listing'),
            self::Privacy => __('Privacy request'),
        };
    }
}
