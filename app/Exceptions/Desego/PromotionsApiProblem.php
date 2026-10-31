<?php

namespace App\Exceptions\Desego;

use RuntimeException;

final class PromotionsApiProblem extends RuntimeException
{
    public function __construct(string $message, public int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}
