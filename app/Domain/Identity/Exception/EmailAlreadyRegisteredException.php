<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exception;

use RuntimeException;

final class EmailAlreadyRegisteredException extends RuntimeException {}
