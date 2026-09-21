<?php

namespace App\Observers;

class CustomerObserver extends SheetSyncObserver
{
    protected function entity(): string
    {
        return 'customers';
    }
}
