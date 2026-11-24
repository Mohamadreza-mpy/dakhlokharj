<?php

namespace App\Enums;

enum ConsumptionEntryType: string
{
    case Consume = 'consume';
    case Reversal = 'reversal';
}
