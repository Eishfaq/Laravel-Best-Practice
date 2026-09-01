<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

class CreateBookingAction
{
    public function execute()
    {
        DB::transaction(function () {
            // Your booking creation logic here
        });
    }
}
