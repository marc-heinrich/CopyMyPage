<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Helper\DashboardHelper;
use Joomla\Component\CopyMyPage\Site\Service\AccountMenuProvider;
use Joomla\Registry\Registry;

/**
 * HTML view for the authenticated user dashboard.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Prepared dashboard payload.
     *
     * @var array<string, mixed>
     */
    protected array $dashboard = [];

    /**
     * Shared account-menu contract.
     *
     * @var array<string, mixed>
     */
    protected array $accountMenu = [];

    /**
     * Routed profile destination.
     */
    protected string $profileUrl = '';

    /**
     * Routed profile edit destination.
     */
    protected string $profileEditUrl = '';

    /**
     * Routed profile address destination.
     */
    protected string $profileAddressUrl = '';

    /**
     * Routed security destination.
     */
    protected string $securityUrl = '';

    /**
     * Routed password edit destination.
     */
    protected string $securityEditUrl = '';

    /**
     * Current com_users profile data.
     *
     * @var User|null
     */
    protected ?User $data = null;

    /**
     * Current com_users profile form.
     *
     * @var Form|null
     */
    protected ?Form $form = null;

    /**
     * Current user's isolated avatar form.
     */
    protected ?Form $profileAvatarForm = null;

    /**
     * Effective maximum avatar upload size for localized form copy.
     */
    protected string $profileAvatarMaximumUploadSizeLabel = '';

    /**
     * Current-user-pinned metadata endpoint consumed by Joomla's Media field.
     */
    protected string $profileAvatarApiUrl = '';

    /**
     * Current user's private profile address.
     *
     * @var array<string, bool|string>
     */
    protected array $profileAddress = [];

    /**
     * Isolated profile-address form.
     */
    protected ?Form $profileAddressForm = null;

    /**
     * com_users profile parameters.
     *
     * @var Registry|null
     */
    protected ?Registry $params = null;

    /**
     * Whether at least one currently available MFA method is active.
     */
    protected bool $mfaActive = false;

    /**
     * Multi-factor authentication configuration markup.
     *
     * @var string|null
     */
    protected ?string $mfaConfigurationUI = null;

    /**
     * Display the Dashboard.
     *
     * @param   string|null  $tpl  Optional template name.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        $dashboard = $this->get('Item');

        if (\count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        if (!\is_array($dashboard) || $dashboard === []) {
            throw new GenericDataException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->dashboard = $dashboard;

        $app = Factory::getApplication();

        if (!$app instanceof CMSWebApplicationInterface) {
            throw new GenericDataException(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
        }

        $layout              = $this->getLayout();
        $accountMenuProvider = Factory::getContainer()->get(AccountMenuProvider::class);

        $this->accountMenu       = $accountMenuProvider->getMenu($app, $layout);
        $this->profileUrl        = $accountMenuProvider->getDashboardUrl($app, 'profile');
        $this->profileEditUrl    = $accountMenuProvider->getDashboardUrl($app, 'profile.edit');
        $this->profileAddressUrl = $accountMenuProvider->getDashboardUrl($app, 'profile.address');
        $this->securityUrl       = $accountMenuProvider->getDashboardUrl($app, 'security');
        $this->securityEditUrl   = $accountMenuProvider->getDashboardUrl($app, 'security.edit');

        if ($layout !== 'default') {
            $this->data              = $dashboard['data'] ?? null;
            $this->form              = $dashboard['form'] ?? null;
            $this->profileAvatarForm = $dashboard['avatarForm'] ?? null;
            $this->profileAvatarMaximumUploadSizeLabel = (string) (
                $dashboard['avatarMaximumUploadSizeLabel'] ?? ''
            );
            $this->profileAddress     = \is_array($dashboard['address'] ?? null)
                ? $dashboard['address']
                : [];
            $this->profileAddressForm = $dashboard['addressForm'] ?? null;
            $this->params             = $dashboard['params'] ?? null;
            $this->mfaActive          = (bool) ($dashboard['mfaActive'] ?? false);
            $this->mfaConfigurationUI = isset($dashboard['mfaConfigurationUI'])
                ? (string) $dashboard['mfaConfigurationUI']
                : null;

            $requiresForm        = \in_array($layout, ['profile.edit', 'security', 'security.edit'], true);
            $requiresAvatarForm  = $layout === 'profile.edit';
            $requiresAddressForm = $layout === 'profile.address';

            if (
                !$this->data instanceof User
                || !$this->params instanceof Registry
                || ($requiresForm && !$this->form instanceof Form)
                || ($requiresAvatarForm && !$this->profileAvatarForm instanceof Form)
                || ($requiresAddressForm && !$this->profileAddressForm instanceof Form)
            ) {
                throw new GenericDataException(Text::_('JERROR_USERS_PROFILE_NOT_FOUND'), 404);
            }

            if ($requiresAvatarForm) {
                $itemId = $app->getInput()->getInt('Itemid');

                $this->profileAvatarApiUrl = Route::_(
                    'index.php?option=com_copymypage&format=json'
                        . ($itemId > 0 ? '&Itemid=' . $itemId : ''),
                    false
                );
            }
        }

        if (
            \in_array($layout, ['profile.address', 'profile.edit', 'security', 'security.edit'], true)
            && isset($app->getIdentity()->cookieLogin)
            && !empty($app->getIdentity()->cookieLogin)
        ) {
            $app->enqueueMessage(Text::_('JGLOBAL_REMEMBER_MUST_LOGIN'), 'message');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        $title = match ($layout) {
            'profile'         => Text::_('COM_COPYMYPAGE_PROFILE_TITLE'),
            'profile.address' => Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_EDIT_TITLE'),
            'profile.edit'    => Text::_('COM_COPYMYPAGE_PROFILE_EDIT_TITLE'),
            'security'        => Text::_('COM_COPYMYPAGE_SECURITY_TITLE'),
            'security.edit'   => Text::_('COM_COPYMYPAGE_SECURITY_EDIT_TITLE'),
            default           => Text::_('COM_COPYMYPAGE_VIEW_DASHBOARD_TITLE'),
        };

        DashboardHelper::preparePersonalPage(
            $this->document,
            $title
        );

        parent::display($tpl);
    }
}
