<?php

namespace App\Exceptions\Desego;

use RuntimeException;

final class OperationsProblem extends RuntimeException
{
    public function __construct(string $message, public int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}
