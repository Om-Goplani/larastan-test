<?php

namespace App\Service;

class OrderService{
    public function failBadly()
    {
        throw new \Exception('Something went wrong');
    }

    public function failNicely()
    {
        //  THIS SHOULD PASS (It's a specific exception)
        throw new \RuntimeException('Specific error');
    }

    public function persistQuickly($data)
    {
        $order = new \App\Models\GoodOrder();
        $order->save();
    }

    public function persistSafely($data)
    {
        $this->validateData($data); // <--- Rule sees "validate", so it's happy

        $order = new \App\Models\GoodOrder();
        $order->save();
    }

    private function validateData($data)
    {
        // Validation logic...
    }
}
