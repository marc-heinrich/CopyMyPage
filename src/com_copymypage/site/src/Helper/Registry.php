<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

namespace Joomla\Component\CopyMyPage\Site\Helper;

\defined('_JEXEC') or die;

/**
 * Service registry for CopyMyPage helpers.
 *
 * Works similar to Joomla\CMS\HTML\Registry.
 */
final class Registry
{
    /**
     * Mapping array of the CopyMyPage helper services.
     *
     * @var  array<string, string|object>
     */
    private array $serviceMap = [
        'navbar'    => Helpers\NavbarHelper::class,
        'user'      => Helpers\UserHelper::class,
    ];

    /**
     * Get the service for a given key.
     *
     * @param   string  $key  The service key to look up.
     *
     * @return  string|object
     *
     * @throws  \InvalidArgumentException  If the key is not registered.
     */
    public function getService(string $key): string|object
    {
        if (!$this->hasService($key)) {
            throw new \InvalidArgumentException(
                \sprintf("The '%s' service key is not registered.", $key)
            );
        }

        return $this->serviceMap[$key];
    }

    /**
     * Check if the registry has a service for the given key.
     *
     * @param   string  $key  The service key to look up.
     *
     * @return  bool
     */
    public function hasService(string $key): bool
    {
        return isset($this->serviceMap[$key]);
    }

    /**
     * Register a service.
     *
     * @param   string         $key      The service key to be registered.
     * @param   string|object  $handler  The handler as PHP class name or class object.
     * @param   bool           $replace  Flag indicating the service key may replace an existing definition.
     *
     * @return  void
     *
     * @throws  \RuntimeException  If the key exists and replace is false or handler is invalid.
     */
    public function register(string $key, string|object $handler, bool $replace = false): void
    {
        // If the key exists already and we are not instructed to replace existing services, bail early
        if (isset($this->serviceMap[$key]) && !$replace) {
            throw new \RuntimeException(
                \sprintf("The '%s' service key is already registered.", $key)
            );
        }

        // If the handler is a string, it must be a class that exists
        if (\is_string($handler) && !\class_exists($handler)) {
            throw new \RuntimeException(
                \sprintf(
                    "The '%s' class for service key '%s' does not exist.",
                    $handler,
                    $key
                )
            );
        }

        // Otherwise the handler must be a class object
        if (!\is_string($handler) && !\is_object($handler)) {
            throw new \RuntimeException(
                \sprintf(
                    'The handler for service key %1$s must be a PHP class name or class object, a %2$s was given.',
                    $key,
                    \gettype($handler)
                )
            );
        }

        $this->serviceMap[$key] = $handler;
    }
}
