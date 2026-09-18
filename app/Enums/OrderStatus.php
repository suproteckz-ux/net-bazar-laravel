<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New        = 'new';
    case Confirmed  = 'confirmed';
    case Processing = 'processing';
    case Ready      = 'ready';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::New        => 'Новый',
            self::Confirmed  => 'Подтверждён',
            self::Processing => 'В работе',
            self::Ready      => 'Готов',
            self::Completed  => 'Выполнен',
            self::Cancelled  => 'Отменён',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::New        => 'warning',
            self::Confirmed  => 'info',
            self::Processing => 'primary',
            self::Ready      => 'success',
            self::Completed  => 'gray',
            self::Cancelled  => 'danger',
        };
    }
}
