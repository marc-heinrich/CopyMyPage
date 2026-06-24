<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.15
 */

namespace Joomla\Module\CopyMyPage\Team\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Helper class for the CopyMyPage Team module.
 */
final class TeamHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Dispatcher-provided fallback layout for the current module context.
     *
     * @var string
     */
    private string $defaultLayout = '';

    /**
     * Dispatcher-provided layout prefix for the current system slot.
     *
     * @var string
     */
    private string $layoutPrefix = '';

    /**
     * Default slot token used by the team module.
     *
     * @var string
     */
    private const DEFAULT_SLOT = 'team';

    /**
     * Default image width used by the placeholder dataset.
     *
     * @var int
     */
    private const DEFAULT_IMAGE_WIDTH = 1600;

    /**
     * Default image height used by the placeholder dataset.
     *
     * @var int
     */
    private const DEFAULT_IMAGE_HEIGHT = 900;

    /**
     * Responsive widths generated for team card images.
     *
     * @var array<int, int>
     */
    private const CARD_IMAGE_VARIANT_WIDTHS = [320, 400, 640, 800, 960, 1200];

    /**
     * Browser sizing hint matching the one-, two- and four-column team grid.
     *
     * @var string
     */
    private const CARD_IMAGE_SIZES = '(min-width: 1200px) 290px, '
        . '(min-width: 640px) calc(50vw - 30px), calc(100vw - 30px)';

    /**
     * Set the layout context resolved by the module dispatcher.
     *
     * @param   string  $defaultLayout  Validated fallback layout key.
     * @param   string  $layoutPrefix   Expected layout prefix for the slot.
     *
     * @return  void
     */
    public function setLayoutContext(string $defaultLayout, string $layoutPrefix = ''): void
    {
        $this->defaultLayout = self::normalizeLayoutKey($defaultLayout);
        $this->layoutPrefix  = self::normalizeLayoutKey($layoutPrefix);
    }

    /**
     * Build Open Graph compatible tag data for the team section.
     *
     * @param   Registry      $params  The module params.
     * @param   object|null   $module  The published module row.
     * @param   string        $slot    The active system slot.
     * @param   string        $layout  Optional validated layout key from a dispatcher caller.
     *
     * @return  array<string, string>
     */
    public function getOGTags(Registry $params, ?object $module = null, string $slot = '', string $layout = ''): array
    {
        $config         = $params->toArray();
        $layout         = $this->resolveLayoutVariant($config, $layout, $slot);
        $configuredMeta = $this->resolveConfiguredOpenGraphMeta($config);
        $primaryMeta    = [
            'image'       => '',
            'imageWidth'  => '',
            'imageHeight' => '',
            'imageAlt'    => '',
        ];

        if (
            $configuredMeta['image'] === ''
            || $configuredMeta['imageWidth'] === ''
            || $configuredMeta['imageHeight'] === ''
            || $configuredMeta['imageAlt'] === ''
        ) {
            $primaryMeta = $this->resolvePrimaryItemMeta($this->getItems($config, $layout));
        }

        $resolvedSlot = trim($slot) !== '' ? strtolower(trim($slot)) : self::DEFAULT_SLOT;
        $meta         = self::mergeOpenGraphMeta(
            [
                'title'       => self::htmlToPlainText($this->getHeadline($config, $layout)),
                'description' => self::htmlToPlainText($this->getLead($config, $layout)),
                'image'       => $primaryMeta['image'],
                'imageWidth'  => $primaryMeta['imageWidth'],
                'imageHeight' => $primaryMeta['imageHeight'],
                'imageAlt'    => $primaryMeta['imageAlt'],
                'twitterCard' => 'summary_large_image',
            ],
            $configuredMeta
        );

        if ($meta['title'] === '') {
            $moduleTitle   = self::htmlToPlainText((string) ($module->title ?? ''));
            $meta['title'] = $moduleTitle !== '' ? $moduleTitle : Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_HEADLINE');
        }

        if ($meta['twitterCard'] === '') {
            $meta['twitterCard'] = $meta['image'] !== '' ? 'summary_large_image' : 'summary';
        }

        return [
            'slot'        => $resolvedSlot,
            'label'       => Text::_('MOD_COPYMYPAGE_TEAM_OG_LABEL'),
            'title'       => $meta['title'],
            'description' => $meta['description'],
            'image'       => $meta['image'],
            'imageWidth'  => $meta['imageWidth'],
            'imageHeight' => $meta['imageHeight'],
            'imageAlt'    => $meta['imageAlt'],
            'twitterCard' => $meta['twitterCard'],
        ];
    }

    /**
     * Get the optional eyebrow text for the active layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  string
     */
    public function getEyebrow(array $cfg, string $layout): string
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);

        return trim(self::cfgString($layoutConfig, 'eyebrow', Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_EYEBROW')));
    }

    /**
     * Get the headline text for the active layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  string
     */
    public function getHeadline(array $cfg, string $layout): string
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $headline     = trim(self::cfgString($layoutConfig, 'headline', Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_HEADLINE')));

        return $headline !== '' ? $headline : Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_HEADLINE');
    }

    /**
     * Get the lead text for the active layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  string
     */
    public function getLead(array $cfg, string $layout): string
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $lead         = trim(self::cfgString($layoutConfig, 'lead', Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_LEAD')));

        return $lead !== '' ? $lead : Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_LEAD');
    }

    /**
     * Get the configured contact records for the active team layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  array<int, object>
     */
    public function getItems(array $cfg, string $layout): array
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $maxItems     = self::cfgInt($layoutConfig, 'maxItems', 3, 1, 12);
        $contacts     = [];
        $items        = [];

        foreach ($this->getPublishedContacts() as $contact) {
            if (!$this->isTeamContact($contact)) {
                continue;
            }

            $contacts[] = $contact;
        }

        usort($contacts, [$this, 'compareTeamContacts']);

        foreach ($contacts as $contact) {
            $item = $this->prepareContactItem($contact);

            if ($item === null) {
                continue;
            }

            $items[] = $item;

            if (\count($items) >= $maxItems) {
                break;
            }
        }

        return $items;
    }

    /**
     * Extract the layout-specific parameter subset from the flat module config.
     *
     * Example:
     * layout "team_cards" turns "team_cards_headline" into "headline".
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  array<string, mixed>
     */
    public static function getLayoutConfig(array $cfg, string $layout): array
    {
        $layout = strtolower(trim($layout));

        if ($layout === '') {
            return [];
        }

        return self::extractPrefixedConfig($cfg, $layout . '_');
    }

    /**
     * Typed array getter (bool) for template-side layout config usage.
     *
     * @param   array<string, mixed>  $cfg      Config bucket.
     * @param   string                $key      Array key.
     * @param   bool                  $default  Default value.
     *
     * @return  bool
     */
    public static function cfgBool(array $cfg, string $key, bool $default = false): bool
    {
        return CopyMyPageHelper::cfgBool($cfg, $key, $default);
    }

    /**
     * Typed array getter (string) for template-side layout config usage.
     *
     * @param   array<string, mixed>  $cfg      Config bucket.
     * @param   string                $key      Array key.
     * @param   string                $default  Default value.
     *
     * @return  string
     */
    public static function cfgString(array $cfg, string $key, string $default = ''): string
    {
        return CopyMyPageHelper::cfgString($cfg, $key, $default);
    }

    /**
     * Typed array getter (int) for template-side layout config usage.
     *
     * @param   array<string, mixed>  $cfg      Config bucket.
     * @param   string                $key      Array key.
     * @param   int                   $default  Default value.
     * @param   int|null              $min      Optional minimum.
     * @param   int|null              $max      Optional maximum.
     *
     * @return  int
     */
    public static function cfgInt(array $cfg, string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        return CopyMyPageHelper::cfgInt($cfg, $key, $default, $min, $max);
    }

    /**
     * Resolve the layout variant for metadata and rendering helpers.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Optional validated layout key.
     * @param   string                $slot    Optional slot name used as fallback prefix.
     *
     * @return  string
     */
    private function resolveLayoutVariant(array $cfg, string $layout = '', string $slot = ''): string
    {
        $layout       = self::normalizeLayoutKey($layout);
        $layoutPrefix = $this->layoutPrefix !== ''
            ? $this->layoutPrefix
            : self::normalizeLayoutKey($slot);

        if ($layout === '') {
            $layout = self::normalizeLayoutKey((string) ($cfg['layoutVariant'] ?? ''));
        }

        if ($layout === '' || $layout === 'default') {
            $layout = $this->resolveConfiguredLayout($cfg, $layoutPrefix);
        }

        if ($layout === '' || $layout === 'default') {
            return $this->defaultLayout;
        }

        if ($layoutPrefix !== '' && !str_starts_with($layout, $layoutPrefix . '_')) {
            return $this->defaultLayout;
        }

        return $layout;
    }

    /**
     * Infer a layout key from prefixed layout params when no explicit variant is stored.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $prefix  Slot/layout prefix.
     *
     * @return  string
     */
    private function resolveConfiguredLayout(array $cfg, string $prefix): string
    {
        $prefix = self::normalizeLayoutKey($prefix);

        if ($prefix === '') {
            return '';
        }

        foreach ($cfg as $key => $value) {
            if (!\is_string($key) || !str_starts_with($key, $prefix . '_')) {
                continue;
            }

            $parts = explode('_', $key, 3);

            if (\count($parts) >= 2 && $parts[1] !== '') {
                return $parts[0] . '_' . $parts[1];
            }
        }

        return '';
    }

    /**
     * Normalize a layout or prefix token.
     *
     * @param   string  $layout  Raw layout token.
     *
     * @return  string
     */
    private static function normalizeLayoutKey(string $layout): string
    {
        return strtolower(trim($layout));
    }

    /**
     * Build metadata from explicit Open Graph module params.
     *
     * @param   array<string, mixed>  $cfg  Flat module config array.
     *
     * @return  array<string, string>
     */
    private function resolveConfiguredOpenGraphMeta(array $cfg): array
    {
        $image       = $this->resolveOpenGraphImage($cfg['og_image'] ?? '');
        $imageWidth  = self::cfgInt($cfg, 'og_image_width', 0, 0);
        $imageHeight = self::cfgInt($cfg, 'og_image_height', 0, 0);
        $twitterCard = strtolower(trim(self::cfgString($cfg, 'og_twitter_card')));

        if (!\in_array($twitterCard, ['summary', 'summary_large_image'], true)) {
            $twitterCard = '';
        }

        if ($imageWidth === 0) {
            $imageWidth = $image['width'];
        }

        if ($imageHeight === 0) {
            $imageHeight = $image['height'];
        }

        return [
            'title'       => self::htmlToPlainText(self::cfgString($cfg, 'og_title')),
            'description' => self::htmlToPlainText(self::cfgString($cfg, 'og_description')),
            'image'       => $this->toAbsoluteUrl($image['src']),
            'imageWidth'  => $imageWidth > 0 ? (string) $imageWidth : '',
            'imageHeight' => $imageHeight > 0 ? (string) $imageHeight : '',
            'imageAlt'    => trim(self::cfgString($cfg, 'og_image_alt')),
            'twitterCard' => $twitterCard,
        ];
    }

    /**
     * Merge non-empty explicit metadata values over fallback values.
     *
     * @param   array<string, string>  $fallback   Derived fallback metadata.
     * @param   array<string, string>  $overrides  Explicit module param metadata.
     *
     * @return  array<string, string>
     */
    private static function mergeOpenGraphMeta(array $fallback, array $overrides): array
    {
        $meta = array_replace(
            [
                'title'       => '',
                'description' => '',
                'image'       => '',
                'imageWidth'  => '',
                'imageHeight' => '',
                'imageAlt'    => '',
                'twitterCard' => '',
            ],
            $fallback
        );

        foreach ($overrides as $key => $value) {
            if (!\is_string($key) || !\array_key_exists($key, $meta)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }

    /**
     * Normalize a media field value to a public image source and dimensions.
     *
     * @param   mixed  $rawImage  Stored media field value.
     *
     * @return  array{src: string, width: int, height: int}
     */
    private function resolveOpenGraphImage(mixed $rawImage): array
    {
        return $this->getImageHelper()->resolveMediaImage($rawImage);
    }

    /**
     * Convert filtered editor HTML into compact plain text for metadata.
     *
     * @param   string  $html  Filtered HTML or plain text.
     *
     * @return  string
     */
    private static function htmlToPlainText(string $html): string
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    /**
     * Build a stable metadata payload from the first available team item.
     *
     * @param   array<int, object>  $items  Prepared team items.
     *
     * @return  array<string, string>
     */
    private function resolvePrimaryItemMeta(array $items): array
    {
        $item        = isset($items[0]) && \is_object($items[0]) ? $items[0] : null;
        $placeholder = $this->getPlaceholderImageUrl();
        $image       = $this->toAbsoluteUrl(trim((string) ($item->image ?? $placeholder)));
        $imageWidth  = (int) ($item->imageWidth ?? self::DEFAULT_IMAGE_WIDTH);
        $imageHeight = (int) ($item->imageHeight ?? self::DEFAULT_IMAGE_HEIGHT);
        $imageAlt    = trim((string) ($item->imageAlt ?? Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_IMAGE_ALT')));

        return [
            'image'       => $image,
            'imageWidth'  => $imageWidth > 0 ? (string) $imageWidth : '',
            'imageHeight' => $imageHeight > 0 ? (string) $imageHeight : '',
            'imageAlt'    => $imageAlt,
        ];
    }

    /**
     * Load published contacts that may be eligible for the team section.
     *
     * @return  array<int, object>
     */
    private function getPublishedContacts(): array
    {
        $db            = $this->getDatabase();
        $language      = Factory::getApplication()->getLanguage()->getTag();
        $schemaContext = 'com_contact.contact';
        $schemaType    = 'Person';
        $query         = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('c.id'),
                    $db->quoteName('c.name'),
                    $db->quoteName('c.alias'),
                    $db->quoteName('c.con_position'),
                    $db->quoteName('c.telephone'),
                    $db->quoteName('c.mobile'),
                    $db->quoteName('c.image'),
                    $db->quoteName('c.email_to'),
                    $db->quoteName('c.webpage'),
                    $db->quoteName('c.misc'),
                    $db->quoteName('c.params'),
                    $db->quoteName('c.ordering'),
                    $db->quoteName('c.language'),
                    $db->quoteName('c.catid'),
                    $db->quoteName('s.schema', 'schemaorg_schema'),
                ]
            )
            ->from($db->quoteName('#__contact_details', 'c'))
            ->leftJoin(
                $db->quoteName('#__schemaorg', 's')
                . ' ON ' . $db->quoteName('s.itemId') . ' = ' . $db->quoteName('c.id')
                . ' AND ' . $db->quoteName('s.context') . ' = :schemaContext'
                . ' AND ' . $db->quoteName('s.schemaType') . ' = :schemaType'
            )
            ->where($db->quoteName('c.published') . ' = 1')
            ->where(
                '(' . $db->quoteName('c.language') . ' = ' . $db->quote('*')
                . ' OR ' . $db->quoteName('c.language') . ' = :language)'
            )
            ->order($db->quoteName('c.ordering') . ' ASC')
            ->order($db->quoteName('c.name') . ' ASC')
            ->bind(':schemaContext', $schemaContext, ParameterType::STRING)
            ->bind(':schemaType', $schemaType, ParameterType::STRING)
            ->bind(':language', $language, ParameterType::STRING);

        $contacts = $db->setQuery($query)->loadObjectList();

        return \is_array($contacts) ? $contacts : [];
    }

    /**
     * Check whether the contact is explicitly enabled for the CopyMyPage team section.
     *
     * @param   object  $contact  Contact row.
     *
     * @return  bool
     */
    private function isTeamContact(object $contact): bool
    {
        $params = new Registry((string) ($contact->params ?? ''));

        return CopyMyPageHelper::toBool($params->get('copymypage_team_enabled', 0), false);
    }

    /**
     * Compare contacts by CopyMyPage team order, then by Joomla ordering/name fallback.
     *
     * @param   object  $first   First contact row.
     * @param   object  $second  Second contact row.
     *
     * @return  int
     */
    private function compareTeamContacts(object $first, object $second): int
    {
        $firstTeamOrder  = $this->getTeamOrder($first);
        $secondTeamOrder = $this->getTeamOrder($second);
        $firstHasOrder   = $firstTeamOrder > 0;
        $secondHasOrder  = $secondTeamOrder > 0;

        if ($firstHasOrder !== $secondHasOrder) {
            return $firstHasOrder ? -1 : 1;
        }

        if ($firstHasOrder && $firstTeamOrder !== $secondTeamOrder) {
            return $firstTeamOrder <=> $secondTeamOrder;
        }

        $ordering = ((int) ($first->ordering ?? 0)) <=> ((int) ($second->ordering ?? 0));

        if ($ordering !== 0) {
            return $ordering;
        }

        $name = strnatcasecmp((string) ($first->name ?? ''), (string) ($second->name ?? ''));

        if ($name !== 0) {
            return $name;
        }

        return ((int) ($first->id ?? 0)) <=> ((int) ($second->id ?? 0));
    }

    /**
     * Get the optional CopyMyPage team ordering value from contact params.
     *
     * @param   object  $contact  Contact row.
     *
     * @return  int
     */
    private function getTeamOrder(object $contact): int
    {
        $params = new Registry((string) ($contact->params ?? ''));

        return CopyMyPageHelper::toInt($params->get('copymypage_team_order', 0), 0, 0);
    }

    /**
     * Convert one contact row into the object consumed by the team template.
     *
     * @param   object  $contact  Contact row with optional schema.org payload.
     *
     * @return  object|null
     */
    private function prepareContactItem(object $contact): ?object
    {
        $schema      = $this->decodeSchema((string) ($contact->schemaorg_schema ?? ''));
        $name        = trim((string) ($contact->name ?? ($schema['name'] ?? '')));
        $role        = trim((string) ($contact->con_position ?? ''));
        $description = trim(strip_tags((string) ($contact->misc ?? '')));
        $image       = $this->resolveContactImage((string) ($contact->image ?? ''), $name);
        $social      = $this->buildSocialLinks($contact, $schema);

        if (
            $name === ''
            && $role === ''
            && $description === ''
            && $image['url'] === ''
            && $social === []
        ) {
            return null;
        }

        return (object) [
            'name'            => $name,
            'role'            => $role,
            'description'     => $description,
            'image'           => $image['url'],
            'imageAlt'        => $image['alt'],
            'imageWidth'      => $image['width'],
            'imageHeight'     => $image['height'],
            'imageSrcset'     => $image['srcset'],
            'imageWebpSrcset' => $image['webpSrcset'],
            'imageAvifSrcset' => $image['avifSrcset'],
            'imageSizes'      => $image['sizes'],
            'social'          => $social,
        ];
    }

    /**
     * Decode a schema.org JSON payload.
     *
     * @param   string  $schema  Raw schema JSON.
     *
     * @return  array<string, mixed>
     */
    private function decodeSchema(string $schema): array
    {
        $schema = trim($schema);

        if ($schema === '') {
            return [];
        }

        $decoded = json_decode($schema, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Build normalized social links from contact and schema.org data.
     *
     * @param   object                $contact  Contact row.
     * @param   array<string, mixed>  $schema   Decoded schema.org data.
     *
     * @return  array<int, array{url: string, label: string, icon: string}>
     */
    private function buildSocialLinks(object $contact, array $schema): array
    {
        $links     = [];
        $email     = trim((string) ($contact->email_to ?? ($schema['email'] ?? '')));
        $telephone = trim((string) ($contact->telephone ?? ''));
        $mobile    = trim((string) ($contact->mobile ?? ''));
        $webpage   = trim((string) ($contact->webpage ?? ($schema['url'] ?? '')));

        $this->appendSocialLink(
            $links,
            $this->normalizeEmailUrl($email),
            Text::_('MOD_COPYMYPAGE_TEAM_SOCIAL_EMAIL'),
            'mail'
        );

        $this->appendSocialLink(
            $links,
            $this->normalizePhoneUrl($telephone),
            Text::_('MOD_COPYMYPAGE_TEAM_SOCIAL_PHONE'),
            'receiver'
        );

        $this->appendSocialLink(
            $links,
            $this->normalizePhoneUrl($mobile),
            Text::_('MOD_COPYMYPAGE_TEAM_SOCIAL_MOBILE'),
            'receiver'
        );

        $this->appendSocialLink(
            $links,
            $this->normalizeExternalUrl($webpage),
            Text::_('MOD_COPYMYPAGE_TEAM_SOCIAL_WEBSITE'),
            'world'
        );

        return $links;
    }

    /**
     * Append one social link when the URL is usable.
     *
     * @param   array<int, array{url: string, label: string, icon: string}>  $links  Prepared links.
     * @param   string                                                       $url    Normalized URL.
     * @param   string                                                       $label  Accessible label.
     * @param   string                                                       $icon   UIkit icon token.
     *
     * @return  void
     */
    private function appendSocialLink(array &$links, string $url, string $label, string $icon): void
    {
        if ($url === '') {
            return;
        }

        $links[] = [
            'url'   => $url,
            'label' => $label !== '' ? $label : Text::_('MOD_COPYMYPAGE_TEAM_SOCIAL_LINK'),
            'icon'  => $icon !== '' ? $icon : 'link',
        ];
    }

    /**
     * Normalize an email value into a mailto URL.
     *
     * @param   string  $email  Raw email.
     *
     * @return  string
     */
    private function normalizeEmailUrl(string $email): string
    {
        $email = trim($email);

        if ($email === '') {
            return '';
        }

        if (str_starts_with(strtolower($email), 'mailto:')) {
            $email = substr($email, 7);
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $email : '';
    }

    /**
     * Normalize a phone value into a tel URL.
     *
     * @param   string  $phone  Raw phone number.
     *
     * @return  string
     */
    private function normalizePhoneUrl(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        if (str_starts_with(strtolower($phone), 'tel:')) {
            $phone = substr($phone, 4);
        }

        $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';

        return $phone !== '' ? 'tel:' . $phone : '';
    }

    /**
     * Normalize a website/profile URL.
     *
     * @param   string  $url  Raw URL.
     *
     * @return  string
     */
    private function normalizeExternalUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /**
     * Normalize a Joomla contact image field value.
     *
     * @param   string  $rawImage  Stored contact image value.
     * @param   string  $name      Contact name used as alt fallback.
     *
     * @return  array{
     *     url: string,
     *     alt: string,
     *     width: int,
     *     height: int,
     *     srcset: string,
     *     webpSrcset: string,
     *     avifSrcset: string,
     *     sizes: string
     * }
     */
    private function resolveContactImage(string $rawImage, string $name): array
    {
        $imageHelper = $this->getImageHelper();
        $image       = $imageHelper->resolveMediaImage($rawImage);
        $picture     = $imageHelper->buildResponsiveImageData(
            $image['src'],
            self::CARD_IMAGE_VARIANT_WIDTHS,
            self::CARD_IMAGE_SIZES
        );
        $alt = trim($name) !== '' ? trim($name) : Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_IMAGE_ALT');

        return [
            'url'        => $picture['src'],
            'alt'        => $picture['src'] !== '' ? $alt : '',
            'width'      => $picture['width'] > 0 ? $picture['width'] : $image['width'],
            'height'     => $picture['height'] > 0 ? $picture['height'] : $image['height'],
            'srcset'     => $picture['srcset'],
            'webpSrcset' => $picture['webpSrcset'],
            'avifSrcset' => $picture['avifSrcset'],
            'sizes'      => $picture['sizes'],
        ];
    }

    /**
     * Get the absolute URL for the bundled placeholder image.
     *
     * @return  string
     */
    private function getPlaceholderImageUrl(): string
    {
        return rtrim(Uri::root(), '/') . '/modules/mod_copymypage_team/images/placeholder.svg';
    }

    /**
     * Convert a team asset path into an absolute URL.
     *
     * @param   string  $url  Relative, rooted or absolute URL.
     *
     * @return  string
     */
    private function toAbsoluteUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $root     = rtrim(Uri::root(), '/');
        $rootPath = rtrim((string) parse_url($root, PHP_URL_PATH), '/');
        $origin   = $root;

        if ($rootPath !== '' && $rootPath !== '/') {
            $origin = preg_replace('#' . preg_quote($rootPath, '#') . '$#', '', $root) ?? $root;
        }

        if (str_starts_with($url, '/')) {
            if ($rootPath !== '' && str_starts_with($url, $rootPath . '/')) {
                return rtrim($origin, '/') . $url;
            }

            return $root . $url;
        }

        return $root . '/' . ltrim($url, '/');
    }

    /**
     * Resolve the shared image helper via the root DI container.
     *
     * @return  \Joomla\Component\CopyMyPage\Site\Helper\Helpers\ImageHelper
     */
    private function getImageHelper(): object
    {
        return Factory::getContainer()->get('copymypage.helper.image');
    }

    /**
     * Extract a prefixed subset from a flat config array.
     *
     * @param   array<string, mixed>  $cfg          Flat config array.
     * @param   string                $prefix       Prefix to match.
     * @param   bool                  $stripPrefix  Remove the prefix from returned keys.
     *
     * @return  array<string, mixed>
     */
    private static function extractPrefixedConfig(array $cfg, string $prefix, bool $stripPrefix = true): array
    {
        $prefix = trim($prefix);

        if ($prefix === '') {
            return [];
        }

        $result = [];

        foreach ($cfg as $key => $value) {
            if (!\is_string($key) || !str_starts_with($key, $prefix)) {
                continue;
            }

            $targetKey = $stripPrefix ? substr($key, strlen($prefix)) : $key;

            if (!\is_string($targetKey) || $targetKey === '') {
                continue;
            }

            $result[$targetKey] = $value;
        }

        return $result;
    }

}
