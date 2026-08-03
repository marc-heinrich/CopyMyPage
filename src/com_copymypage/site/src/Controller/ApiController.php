<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Service\AvatarService;

/**
 * Restricted metadata endpoint consumed by Joomla's Media field.
 */
final class ApiController extends BaseController
{
    /**
     * Return one current-user-owned image using com_media's response shape.
     */
    public function files(): void
    {
        $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
        $this->app->setHeader('Pragma', 'no-cache', true);

        if (!Session::checkToken('get')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $this->app->close();

            return;
        }

        $user = $this->app->getIdentity();

        if (!$user instanceof User || (int) $user->id === 0 || (bool) $user->guest) {
            echo new JsonResponse(null, Text::_('JERROR_ALERTNOAUTHOR'), true);
            $this->app->close();

            return;
        }

        if (isset($user->cookieLogin) && !empty($user->cookieLogin)) {
            echo new JsonResponse(null, Text::_('JGLOBAL_REMEMBER_MUST_LOGIN'), true);
            $this->app->close();

            return;
        }

        $path = $this->app->getInput()->getString('path', '');

        try {
            $metadata = Factory::getContainer()
                ->get(AvatarService::class)
                ->getMediaMetadata($user, $path);

            echo new JsonResponse([$metadata]);
        } catch (\Throwable $exception) {
            echo new JsonResponse(
                null,
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_SELECTION'),
                true
            );
        }

        $this->app->close();
    }
}
