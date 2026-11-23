<?php

namespace Database\Factories;

use App\Models\ProductSerial;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductSerialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'Status' => ProductSerial::STATUS_IN_STOCK,
            'StatusDate' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ProductSerial $serial) {
            if ($serial->SerialNumber === null) {
                $serial->update(['SerialNumber' => ProductSerial::formatNumber($serial->ProductSerialID)]);
            }
        });
    }

    public function sold(): static
    {
        return $this->state(fn () => ['Status' => ProductSerial::STATUS_SOLD]);
    }
}
