<?php

namespace App\Enums;

enum HalalStatus: string
{
    case Certified = 'certified';
    case SelfDeclared = 'self_declared';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Certified => __('Halal certified'),
            self::SelfDeclared => __('Self-declared halal'),
            self::NotApplicable => __('Not applicable'),
        };
    }
}
