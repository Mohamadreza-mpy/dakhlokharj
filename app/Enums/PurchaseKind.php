<?php

namespace App\Enums;

enum PurchaseKind: string
{
    case Inventory = 'inventory';
    case Shared = 'shared';
}
