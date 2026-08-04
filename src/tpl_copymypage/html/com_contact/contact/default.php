<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\PluginHelper;

/** @var \Joomla\Component\Contact\Site\View\Contact\HtmlView $this */

$coreLayout = JPATH_SITE . '/components/com_contact/tmpl/contact/default.php';
$teamItem   = null;

try {
    $app = Factory::getApplication();

    $app->getLanguage()->load(
        'mod_copymypage_team',
        JPATH_SITE . '/modules/mod_copymypage_team'
    );

    $teamHelper = $app->bootModule('mod_copymypage_team', 'site')
        ->getHelper('TeamHelper');

    if (\is_object($teamHelper) && \method_exists($teamHelper, 'getItemById')) {
        $teamItem = $teamHelper->getItemById((int) ($this->item->id ?? 0));
    }
} catch (\Throwable) {
    $teamItem = null;
}

// Keep Joomla's complete contact view for every contact that is not a team profile.
if (!\is_object($teamItem)) {
    require $coreLayout;

    return;
}

$tparams   = $this->item->params;
$canDo     = ContentHelper::getActions('com_contact', 'category', $this->item->catid);
$canEdit   = $canDo->get('core.edit')
    || ($canDo->get('core.edit.own') && $this->item->created_by === $this->getCurrentUser()->id);
$plainText = static function (mixed $value): string {
    $value = trim(strip_tags((string) $value));

    return preg_replace('/\s+/u', ' ', $value) ?? '';
};
$details = [];
$links   = [];

if ($this->params->get('show_info', 1)) {
    $addressParts = [];

    if ((int) $this->params->get('address_check', 0) > 0) {
        $addressFields = [
            'address'  => 'show_street_address',
            'suburb'   => 'show_suburb',
            'state'    => 'show_state',
            'postcode' => 'show_postcode',
            'country'  => 'show_country',
        ];

        foreach ($addressFields as $field => $parameter) {
            $value = $plainText($this->item->{$field} ?? '');

            if ($value !== '' && $this->params->get($parameter)) {
                $addressParts[] = $value;
            }
        }
    }

    if ($addressParts !== []) {
        $details[] = [
            'label' => Text::_('COM_CONTACT_ADDRESS'),
            'value' => implode(', ', $addressParts),
        ];
    }

    $detailFields = [
        'email_to'  => ['show_email', 'COM_CONTACT_EMAIL_LABEL'],
        'telephone' => ['show_telephone', 'COM_CONTACT_TELEPHONE'],
        'fax'       => ['show_fax', 'COM_CONTACT_FAX'],
        'mobile'    => ['show_mobile', 'COM_CONTACT_MOBILE'],
    ];

    foreach ($detailFields as $field => [$parameter, $label]) {
        $value = $plainText($this->item->{$field} ?? '');

        if ($value !== '' && $this->params->get($parameter)) {
            $details[] = [
                'label' => Text::_($label),
                'value' => $value,
            ];
        }
    }
}

$normalizeLink = static function (mixed $value): string {
    $url = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'));

    if ($url === '') {
        return '';
    }

    if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    if (!\in_array($scheme, ['http', 'https'], true)) {
        return '';
    }

    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
};
$seenLinks  = [];
$appendLink = static function (mixed $rawUrl, mixed $rawLabel, string $icon = 'link') use (
    &$links,
    &$seenLinks,
    $normalizeLink,
    $plainText
): void {
    $url   = $normalizeLink($rawUrl);
    $label = $plainText($rawLabel);
    $key   = strtolower($url);

    if ($url === '' || isset($seenLinks[$key])) {
        return;
    }

    $seenLinks[$key] = true;
    $links[]         = [
        'url'      => $url,
        'label'    => $label !== '' ? $label : $url,
        'icon'     => $icon,
    ];
};

if ($this->params->get('show_info', 1) && $this->params->get('show_webpage')) {
    $appendLink(
        $this->item->webpage ?? '',
        Text::_('COM_CONTACT_WEBPAGE'),
        'world'
    );
}

if ($tparams->get('show_links')) {
    foreach (range('a', 'e') as $char) {
        $appendLink(
            $tparams->get('link' . $char),
            $tparams->get('link' . $char . '_name')
        );
    }
}

$showPageHeading = (bool) $tparams->get('show_page_heading');
$headingTag      = $showPageHeading ? 'h2' : 'h1';
$headingId       = 'cmp-team-profile-title-' . (int) $this->item->id;
?>
<div class="com-contact contact cmp-team-profile">
    <?php if ($showPageHeading) : ?>
        <header class="cmp-team-profile__header">
            <h1 class="cmp-team-profile__page-title">
                <?php echo $this->escape($tparams->get('page_heading')); ?>
            </h1>
        </header>
    <?php endif; ?>

    <?php if ($canEdit) : ?>
        <div class="cmp-team-profile__toolbar">
            <?php echo HTMLHelper::_('contacticon.edit', $this->item, $tparams); ?>
        </div>
    <?php endif; ?>

    <?php echo $this->item->event->afterDisplayTitle ?? ''; ?>
    <?php echo $this->item->event->beforeDisplayContent ?? ''; ?>

    <?php echo LayoutHelper::render(
        'copymypage.team.card',
        [
            'item'            => $teamItem,
            'cardStyle'       => 'default',
            'details'         => $details,
            'fetchPriority'   => 'high',
            'headingId'       => $headingId,
            'headingTag'      => $headingTag,
            'imageLoading'    => 'eager',
            'imageSizes'      => '(min-width: 960px) 420px, calc(100vw - 2rem)',
            'links'           => $links,
            'showDescription' => true,
            'showImage'       => (bool) $tparams->get('show_image', 1),
            'variant'         => 'profile',
        ]
    ); ?>

    <?php if ($tparams->get('show_tags', 1) && !empty($this->item->tags->itemTags)) : ?>
        <div class="cmp-team-profile__tags">
            <?php $this->item->tagLayout = new FileLayout('joomla.content.tags'); ?>
            <?php echo $this->item->tagLayout->render($this->item->tags->itemTags); ?>
        </div>
    <?php endif; ?>

    <?php if ($tparams->get('show_articles') && $this->item->user_id && $this->item->articles) : ?>
        <section class="cmp-team-profile__supplementary">
            <h2><?php echo Text::_('JGLOBAL_ARTICLES'); ?></h2>
            <?php echo $this->loadTemplate('articles'); ?>
        </section>
    <?php endif; ?>

    <?php if ($tparams->get('show_profile') && $this->item->user_id && PluginHelper::isEnabled('user', 'profile')) : ?>
        <section class="cmp-team-profile__supplementary">
            <h2><?php echo Text::_('COM_CONTACT_PROFILE'); ?></h2>
            <?php echo $this->loadTemplate('profile'); ?>
        </section>
    <?php endif; ?>

    <?php if ($tparams->get('show_user_custom_fields') && $this->contactUser) : ?>
        <div class="cmp-team-profile__supplementary">
            <?php echo $this->loadTemplate('user_custom_fields'); ?>
        </div>
    <?php endif; ?>

    <?php echo $this->item->event->afterDisplayContent ?? ''; ?>
</div>
