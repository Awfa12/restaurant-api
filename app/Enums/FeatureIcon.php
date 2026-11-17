<?php

namespace App\Enums;

/**
 * The hero feature icons the dashboard picks from (frontend `FeatureIconKey`,
 * components/ui/feature-icons).
 */
enum FeatureIcon: string
{
    case Fresh = 'fresh';
    case Fire = 'fire';
    case Quality = 'quality';
    case Delivery = 'delivery';
    case Fast = 'fast';
    case Heart = 'heart';
}
