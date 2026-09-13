<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.20
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \DigitalPeak\Component\DPCalendar\Site\View\Booking\HtmlView $this */

$app = Factory::getApplication();
$app->getLanguage()->load(
    'com_copymypage',
    JPATH_SITE . '/components/com_copymypage',
    null,
    true
);
$app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
$app->setHeader('Pragma', 'no-cache', true);
$app->getDocument()->setMetaData('robots', 'noindex, nofollow');

echo LayoutHelper::render(
    'copymypage.tickets.completion',
    [
        'paymentAction' => '',
        'refreshUrl'    => '',
        'selectionUrl'  => Route::_('index.php?option=com_copymypage&view=ticketselection', false),
        'showBack'      => true,
        'showRefresh'   => false,
        'showResume'    => false,
        'showSteps'     => true,
        'state'         => [
            'completed'   => false,
            'icon'        => 'close',
            'integrityOk' => true,
            'introKey'    => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_CANCELLED_INTRO',
            'managed'     => true,
            'titleKey'    => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_CANCELLED_TITLE',
            'tone'        => 'warning',
        ],
    ]
);
