<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\View\Avatar;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Helper\DashboardHelper;
use Joomla\Component\CopyMyPage\Site\Service\AccountMenuProvider;
use Joomla\Component\CopyMyPage\Site\Service\AvatarService;

/**
 * Avatar form and restricted picker view.
 */
final class HtmlView extends BaseHtmlView
{
    /** @var array<string, bool|string> */
    protected array $avatar = [];

    /** @var array<int, array<string, bool|int|string>> */
    protected array $items = [];

    protected ?User $user = null;

    protected ?Form $form = null;

    protected string $apiUrl = '';

    protected string $maximumUploadSizeLabel = '';

    protected string $uploadUrl = '';

    /**
     * Display the isolated avatar workflow.
     */
    public function display($tpl = null): void
    {
        $layout = strtolower(trim($this->getLayout()));

        if (!\in_array($layout, ['default', 'picker'], true)) {
            throw new GenericDataException(
                Text::sprintf('JLIB_APPLICATION_ERROR_LAYOUTFILE_NOT_FOUND', $layout),
                404
            );
        }

        $item = $this->get('Item');

        if (\count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        if (!\is_array($item) || !$item['user'] instanceof User) {
            throw new GenericDataException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $app = Factory::getApplication();

        if (!$app instanceof CMSWebApplicationInterface) {
            throw new GenericDataException(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
        }

        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
        $app->setHeader('Pragma', 'no-cache', true);

        if (isset($item['user']->cookieLogin) && !empty($item['user']->cookieLogin)) {
            $app->enqueueMessage(Text::_('JGLOBAL_REMEMBER_MUST_LOGIN'), 'message');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        if ($layout === 'default') {
            $profileEditUrl = Factory::getContainer()
                ->get(AccountMenuProvider::class)
                ->getDashboardUrl($app, 'profile.edit');

            $app->redirect($profileEditUrl . '#cmp-profile-avatar');

            return;
        }

        $avatars       = Factory::getContainer()->get(AvatarService::class);
        $itemId        = $app->getInput()->getInt('Itemid');
        $itemId        = $itemId > 0 ? '&Itemid=' . $itemId : '';
        $drawerContext = $app->getInput()->getCmd('cmp_context', '') === 'drawer'
            ? '&tmpl=component&cmp_context=drawer'
            : '';

        $this->avatar                 = \is_array($item['avatar'] ?? null) ? $item['avatar'] : [];
        $this->items                  = \is_array($item['items'] ?? null) ? $item['items'] : [];
        $this->maximumUploadSizeLabel = $avatars->getMaximumUploadSizeLabel();
        $this->user                   = $item['user'];
        $this->uploadUrl              = Route::_(
            'index.php?option=com_copymypage&task=avatar.upload'
                . $itemId
                . $drawerContext,
            false
        );
        $this->apiUrl                 = Route::_(
            'index.php?option=com_copymypage&format=json' . $itemId,
            false
        );

        $this->getDocument()->getWebAssetManager()
            ->useScript('copymypage.avatar.picker');

        $title = Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_PICKER_TITLE');

        DashboardHelper::preparePersonalPage($this->document, $title);
        $this->document->addScriptOptions(
            'csrf.token',
            Session::getFormToken(),
            false
        );

        parent::display($tpl);
    }
}
