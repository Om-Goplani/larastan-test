<?php

namespace App\Http\Controllers;

class BadController
{
    public function index()
    {
        // This triggers the error because it is "new Exception"
        throw new \Exception("I am generic and bad.");
    }

    public function good()
    {
        //  This passes because it is NOT "new Exception"
        abort(403, "Forbidden");
    }
}
