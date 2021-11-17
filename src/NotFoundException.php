<?php

declare(strict_types=1);

namespace Vader\DI\Exception;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface {}