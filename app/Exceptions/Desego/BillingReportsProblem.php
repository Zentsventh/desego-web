<?php

namespace App\Exceptions\Desego;

use RuntimeException;

final class BillingReportsProblem extends RuntimeException
{
    public int $httpStatus;

    public function __construct(string $message, int $httpStatus = 422)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }
}
