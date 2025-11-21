<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.2
 */

namespace Joomla\Component\CopyMyPage\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Registry for model data providers.
 *
 * This class maintains a map of keys to handlers which can
 * enrich a primary model with additional data (for example
 * from foreign models or external APIs).
 *
 * @since  0.0.2
 */
class ModelRegistry
{
    /**
     * Mapping of keys to handlers.
     *
     * The handler is a callable which will later receive
     * the primary model and the current user (or any other
     * runtime context you decide to pass in from the controller).
     *
     * @var  array<string, callable>
     *
     * @since  0.0.2
     */
    protected array $handlers = [];

    /**
     * Check if a handler for a given key is registered.
     *
     * @param   string  $key  The handler key to look up.
     *
     * @return  boolean  True if a handler exists, false otherwise.
     *
     * @since   0.0.2
     */
    public function hasHandler(string $key): bool
    {
        return isset($this->handlers[$key]);
    }

    /**
     * Get the handler for a given key.
     *
     * @param   string  $key  The handler key to look up.
     *
     * @return  callable  The registered handler.
     *
     * @since   0.0.2
     *
     * @throws  \InvalidArgumentException  If the key is not registered.
     */
    public function getHandler(string $key): callable
    {
        if (!$this->hasHandler($key)) {
            throw new \InvalidArgumentException(
                "The '{$key}' model handler key is not registered."
            );
        }

        return $this->handlers[$key];
    }

    /**
     * Register a handler for a given key.
     *
     * @param   string    $key      The handler key to be registered.
     * @param   callable  $handler  The handler callable.
     * @param   boolean   $replace  Allow replacing an existing handler.
     *
     * @return  void
     *
     * @since   0.0.2
     *
     * @throws  \RuntimeException  If the key exists and replace is false.
     */
    public function registerHandler(string $key, callable $handler, bool $replace = false): void
    {
        if (isset($this->handlers[$key]) && !$replace) {
            throw new \RuntimeException(
                "The '{$key}' model handler key is already registered."
            );
        }

        $this->handlers[$key] = $handler;
    }
}
