<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\CopyMyPage\Site\View\Basket\HtmlView $this */

$escape  = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$titleId = 'cmp-basket-title';
?>
<section
    class="cmp-basket"
    aria-labelledby="<?php echo $escape($titleId); ?>"
    data-cmp-drawer-document-content
>
    <h1 id="<?php echo $escape($titleId); ?>" class="cmp-basket__title">
        <?php echo $escape(Text::_('COM_COPYMYPAGE_BASKET_TITLE')); ?>
    </h1>

    <?php echo LayoutHelper::render(
        'copymypage.tickets.cart',
        [
            'cart'             => $this->cart,
            'formFieldNames'   => $this->formFieldNames,
            'idPrefix'         => 'cmp-basket-ticket-cart',
            'markupAttributes' => $this->markupAttributes,
            'showTitle'        => false,
            'showTicketSelectionBack' => !empty($this->cart['showTicketSelectionBack']),
        ]
    ); ?>
</section>
