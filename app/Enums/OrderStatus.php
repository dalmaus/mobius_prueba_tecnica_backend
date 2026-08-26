<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isCancellable(): bool
    {
        return $this === self::Pending;
    }
}