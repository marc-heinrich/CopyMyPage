<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

/** @var \J2Commerce\Component\J2commerce\Site\View\Carts\HtmlView $this */

echo LayoutHelper::render(
    'copymypage.basket.totals',
    [
        'order'       => $this->order ?? null,
        'checkoutUrl' => $this->checkout_url ?? '',
    ]
);
