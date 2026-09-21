<?php

namespace App\Observers;

class OrderObserver extends SheetSyncObserver
{
    protected function entity(): string
    {
        return 'orders';
    }
}
