<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Posted = 'posted';
    case Voided = 'voided';
}
