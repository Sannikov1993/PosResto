<?php

namespace App\Exceptions;

use RuntimeException;

class DishesUnavailableException extends RuntimeException
{
    protected array $dishes;

    public function __construct(array $dishes)
    {
        $this->dishes = $dishes;
        parent::__construct('Блюда недоступны: ' . implode(', ', $dishes));
    }

    public function getDishes(): array
    {
        return $this->dishes;
    }
}
