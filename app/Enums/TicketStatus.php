<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case New = 'new';
    case Processing = 'processing';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
            self::New => 'New',
            self::Processing => 'Processing',
        };
    }
}