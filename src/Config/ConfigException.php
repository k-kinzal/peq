<?php

declare(strict_types=1);

namespace App\Config;

use Exception;

/**
 * Exception thrown when configuration loading or validation fails.
 *
 * This exception is raised when configuration data cannot be loaded from
 * any source (files, environment variables, CLI input) or when validation
 * of configuration values fails. It provides a consistent error handling
 * mechanism for all configuration-related errors.
 */
final class ConfigException extends \Exception {}
