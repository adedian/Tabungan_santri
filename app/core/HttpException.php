<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Lempar dari controller/middleware untuk menghasilkan halaman/JSON error dengan status tertentu. */
final class HttpException extends RuntimeException
{
    public function __construct(
        private int $status,
        string $message = '',
        private array $headers = []
    ) {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }
}
