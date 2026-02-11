<?php

namespace App\Exceptions;

use RuntimeException;

class PhoneIncompleteException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Введите полный номер телефона (минимум 10 цифр)');
    }
}
