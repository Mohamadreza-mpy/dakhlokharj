<?php

namespace App\Enums;

enum SettlementEntryType: string
{
    case Payment = 'payment';
    case Reversal = 'reversal';
}
