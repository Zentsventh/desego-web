<?php

namespace App\Exceptions\Desego;

use RuntimeException;

final class WorkforceProblem extends RuntimeException
{
    public function __construct(string $message, public int $status = 422)
    {
        parent::__construct($message);
    }
}
