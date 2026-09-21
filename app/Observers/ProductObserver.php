<?php

namespace App\Observers;

class ProductObserver extends SheetSyncObserver
{
    protected function entity(): string
    {
        return 'products';
    }
}
