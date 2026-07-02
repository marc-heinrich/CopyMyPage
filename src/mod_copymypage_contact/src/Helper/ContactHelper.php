<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.16
 */

namespace Joomla\Module\CopyMyPage\Contact\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;
use Joomla\Registry\Registry;

/**
 * Helper class for the CopyMyPage Contact module.
 */
final class ContactHelper
{
    /**
     * Dispatcher-provided fallback layout.
     *
     * @var string
     */
    private string $defaultLayout = 'contact_default';

    /**
     * Dispatcher-provided layout prefix.
     *
     * @var string
     */
    private string $layoutPrefix = 'contact';

    /**
     * Set validated layout context for render and metadata calls.
     *
     * @param   string  $defaultLayout  Validated fallback layout.
     * @param   string  $layoutPrefix   Expected system-slot prefix.
     *
     * @return  void
     */
    public function setLayoutContext(string $defaultLayout, string $layoutPrefix = ''): void
    {
        $this->defaultLayout = self::normalizeLayoutKey($defaultLayout) ?: 'contact_default';
        $this->layoutPrefix  = self::normalizeLayoutKey($layoutPrefix) ?: 'contact';
    }

    /**
     * Build the stable CopyMyPage Open Graph metadata contract.
     *
     * @param   Registry     $params  Module parameters.
     * @param   object|null  $module  Module instance.
     * @param   string       $slot    Resolved onepage slot.
     * @param   string       $layout  Optional validated layout.
     *
     * @return  array<string, string>
     */
    public function getOGTags(Registry $params, ?object $module = null, string $slot = '', string $layout = ''): array
    {
        $cfg          = $params->toArray();
        $layout       = $this->resolveLayoutVariant($cfg, $layout);
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $title        = self::plainText(self::cfgString($cfg, 'og_title'));
        $description  = self::plainText(self::cfgString($cfg, 'og_description'));

        if ($title === '') {
            $title = self::plainText(
                self::translatedValue($layoutConfig, 'headline', 'MOD_COPYMYPAGE_CONTACT_DEFAULT_HEADLINE')
            );
        }

        if ($description === '') {
            $description = self::plainText(
                self::translatedValue($layoutConfig, 'lead', 'MOD_COPYMYPAGE_CONTACT_DEFAULT_LEAD')
            );
        }

        $image       = $this->getImageHelper()->resolveMediaImage($cfg['og_image'] ?? '');
        $imageUrl    = $this->getImageHelper()->toAbsoluteUrl(trim((string) ($image['src'] ?? '')));
        $imageWidth  = self::cfgInt($cfg, 'og_image_width', 0, 0);
        $imageHeight = self::cfgInt($cfg, 'og_image_height', 0, 0);
        $twitterCard = strtolower(trim(self::cfgString($cfg, 'og_twitter_card')));

        if ($imageWidth === 0) {
            $imageWidth = max(0, (int) ($image['width'] ?? 0));
        }

        if ($imageHeight === 0) {
            $imageHeight = max(0, (int) ($image['height'] ?? 0));
        }

        if (!\in_array($twitterCard, ['summary', 'summary_large_image'], true)) {
            $twitterCard = $imageUrl !== '' ? 'summary_large_image' : 'summary';
        }

        return [
            'slot'        => self::normalizeLayoutKey($slot) ?: 'contact',
            'label'       => Text::_('MOD_COPYMYPAGE_CONTACT_OG_LABEL'),
            'title'       => $title,
            'description' => $description,
            'image'       => $imageUrl,
            'imageWidth'  => $imageWidth > 0 ? (string) $imageWidth : '',
            'imageHeight' => $imageHeight > 0 ? (string) $imageHeight : '',
            'imageAlt'    => trim(self::cfgString($cfg, 'og_image_alt')),
            'twitterCard' => $twitterCard,
        ];
    }

    /**
     * Return the layout eyebrow.
     *
     * @param   array<string, mixed>  $cfg     Flat module parameters.
     * @param   string                $layout  Validated layout.
     *
     * @return  string
     */
    public function getEyebrow(array $cfg, string $layout): string
    {
        return self::translatedValue(
            self::getLayoutConfig($cfg, $layout),
            'eyebrow',
            'MOD_COPYMYPAGE_CONTACT_DEFAULT_EYEBROW'
        );
    }

    /**
     * Return the layout headline.
     *
     * @param   array<string, mixed>  $cfg     Flat module parameters.
     * @param   string                $layout  Validated layout.
     *
     * @return  string
     */
    public function getHeadline(array $cfg, string $layout): string
    {
        return self::translatedValue(
            self::getLayoutConfig($cfg, $layout),
            'headline',
            'MOD_COPYMYPAGE_CONTACT_DEFAULT_HEADLINE'
        );
    }

    /**
     * Return the layout lead text.
     *
     * @param   array<string, mixed>  $cfg     Flat module parameters.
     * @param   string                $layout  Validated layout.
     *
     * @return  string
     */
    public function getLead(array $cfg, string $layout): string
    {
        return self::translatedValue(
            self::getLayoutConfig($cfg, $layout),
            'lead',
            'MOD_COPYMYPAGE_CONTACT_DEFAULT_LEAD'
        );
    }

    /**
     * Build configured contact-information items.
     *
     * @param   array<string, mixed>   $cfg     Flat module parameters.
     * @param   string                 $layout  Validated layout.
     * @param   CMSApplicationInterface $app    Application.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function getInfoItems(array $cfg, string $layout, CMSApplicationInterface $app): array
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $recipient    = $this->getRecipientEmail($cfg, $layout, $app);
        $address      = trim(self::cfgString($layoutConfig, 'address'));
        $email        = trim(self::cfgString($layoutConfig, 'displayEmail', $recipient));
        $phone        = trim(self::cfgString($layoutConfig, 'phone'));
        $items        = [];

        if ($address !== '') {
            $items[] = [
                'key'    => 'address',
                'icon'   => 'location',
                'label'  => Text::_('MOD_COPYMYPAGE_CONTACT_ADDRESS'),
                'value'  => $address,
                'href'   => '',
                'isHtml' => true,
            ];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $items[] = [
                'key'    => 'email',
                'icon'   => 'mail',
                'label'  => Text::_('MOD_COPYMYPAGE_CONTACT_EMAIL'),
                'value'  => $email,
                'href'   => 'mailto:' . $email,
                'isHtml' => false,
            ];
        }

        if ($phone !== '') {
            $tel = preg_replace('/[^0-9+]/', '', $phone) ?? '';
            $items[] = [
                'key'    => 'phone',
                'icon'   => 'receiver',
                'label'  => Text::_('MOD_COPYMYPAGE_CONTACT_PHONE'),
                'value'  => $phone,
                'href'   => $tel !== '' ? 'tel:' . $tel : '',
                'isHtml' => false,
            ];
        }

        return $items;
    }

    /**
     * Return the optional map embed URL.
     *
     * @param   array<string, mixed>  $cfg     Flat module parameters.
     * @param   string                $layout  Validated layout.
     *
     * @return  string
     */
    public function getMapUrl(array $cfg, string $layout): string
    {
        $url = trim(self::cfgString(self::getLayoutConfig($cfg, $layout), 'mapUrl'));

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return \in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    /**
     * Return the accessible map title.
     *
     * @param   array<string, mixed>  $cfg     Flat module parameters.
     * @param   string                $layout  Validated layout.
     *
     * @return  string
     */
    public function getMapTitle(array $cfg, string $layout): string
    {
        return self::translatedValue(
            self::getLayoutConfig($cfg, $layout),
            'mapTitle',
            'MOD_COPYMYPAGE_CONTACT_DEFAULT_MAP_TITLE'
        );
    }

    /**
     * Return the server-side recipient address.
     *
     * @param   array<string, mixed>   $cfg     Flat module parameters.
     * @param   string                 $layout  Validated layout.
     * @param   CMSApplicationInterface $app    Application.
     *
     * @return  string
     */
    public function getRecipientEmail(array $cfg, string $layout, CMSApplicationInterface $app): string
    {
        $recipient = trim(
            self::cfgString(self::getLayoutConfig($cfg, $layout), 'recipientEmail')
        );

        if ($recipient === '') {
            $recipient = trim((string) $app->get('mailfrom', ''));
        }

        return $recipient;
    }

    /**
     * Whether users may request an email copy.
     *
     * @param   array<string, mixed>  $cfg     Flat module parameters.
     * @param   string                $layout  Validated layout.
     *
     * @return  bool
     */
    public function getShowCopy(array $cfg, string $layout): bool
    {
        return self::cfgBool(self::getLayoutConfig($cfg, $layout), 'showCopy', true);
    }

    /**
     * Whether the custom formcheck flow should ask for confirmation.
     *
     * @param   array<string, mixed>  $cfg     Flat module parameters.
     * @param   string                $layout  Validated layout.
     *
     * @return  bool
     */
    public function getConfirmSubmission(array $cfg, string $layout): bool
    {
        return self::cfgBool(self::getLayoutConfig($cfg, $layout), 'confirmSubmission', true);
    }

    /**
     * Return the form submission confirmation prompt.
     *
     * @param   array<string, mixed>  $cfg     Flat module parameters.
     * @param   string                $layout  Validated layout.
     *
     * @return  string
     */
    public function getConfirmMessage(array $cfg, string $layout): string
    {
        return self::translatedValue(
            self::getLayoutConfig($cfg, $layout),
            'confirmMessage',
            'MOD_COPYMYPAGE_CONTACT_DEFAULT_CONFIRM_MESSAGE'
        );
    }

    /**
     * Build the frontend contact form and let Joomla content plugins extend it.
     *
     * The form deliberately uses the supported com_contact.contact context so
     * Joomla's confirm-consent plugin can inject its consent field.
     *
     * @param   Registry                 $params    Module parameters.
     * @param   CMSApplicationInterface  $app       Application.
     * @param   int                      $moduleId  Module instance ID.
     *
     * @return  Form|null
     */
    public function getContactForm(
        Registry $params,
        CMSApplicationInterface $app,
        int $moduleId
    ): ?Form {
        try {
            $app->bootComponent('com_contact');

            Form::addFormPath(JPATH_SITE . '/modules/mod_copymypage_contact/forms');

            $form = Factory::getContainer()
                ->get(FormFactoryInterface::class)
                ->createForm(
                    'com_contact.contact',
                    [
                        'control'   => 'jform',
                        'load_data' => false,
                    ]
                );

            if (!$form->loadFile('contact')) {
                return null;
            }

            $stateKey = 'com_copymypage.contact.form.' . max(0, $moduleId);
            $data     = (array) $app->getUserState($stateKey, []);
            $dispatcher = $app->getDispatcher();

            PluginHelper::importPlugin('content', null, true, $dispatcher);
            $dispatcher->dispatch(
                'onContentPrepareForm',
                new PrepareFormEvent(
                    'onContentPrepareForm',
                    [
                        'subject' => $form,
                        'data'    => $data,
                    ]
                )
            );

            $form->bind($data);

            if (!$this->getShowCopy($params->toArray(), $this->resolveLayoutVariant($params->toArray()))) {
                $form->removeField('contact_copy');
            }

            return $form;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract layout-prefixed configuration.
     *
     * @param   array<string, mixed>  $cfg     Flat module parameters.
     * @param   string                $layout  Validated layout.
     *
     * @return  array<string, mixed>
     */
    public static function getLayoutConfig(array $cfg, string $layout): array
    {
        $layout = self::normalizeLayoutKey($layout);

        if ($layout === '') {
            return [];
        }

        return self::extractPrefixedConfig($cfg, $layout . '_');
    }

    /**
     * Typed string configuration getter.
     *
     * @param   array<string, mixed>  $cfg      Configuration.
     * @param   string                $key      Key.
     * @param   string                $default  Default.
     *
     * @return  string
     */
    public static function cfgString(array $cfg, string $key, string $default = ''): string
    {
        return CopyMyPageHelper::cfgString($cfg, $key, $default);
    }

    /**
     * Typed boolean configuration getter.
     *
     * @param   array<string, mixed>  $cfg      Configuration.
     * @param   string                $key      Key.
     * @param   bool                  $default  Default.
     *
     * @return  bool
     */
    public static function cfgBool(array $cfg, string $key, bool $default = false): bool
    {
        return CopyMyPageHelper::cfgBool($cfg, $key, $default);
    }

    /**
     * Typed integer configuration getter.
     *
     * @param   array<string, mixed>  $cfg      Configuration.
     * @param   string                $key      Key.
     * @param   int                   $default  Default.
     * @param   int|null              $min      Minimum.
     * @param   int|null              $max      Maximum.
     *
     * @return  int
     */
    public static function cfgInt(
        array $cfg,
        string $key,
        int $default = 0,
        ?int $min = null,
        ?int $max = null
    ): int {
        return CopyMyPageHelper::cfgInt($cfg, $key, $default, $min, $max);
    }

    /**
     * Resolve layout context for direct metadata calls.
     *
     * @param   array<string, mixed>  $cfg     Flat module parameters.
     * @param   string                $layout  Optional layout.
     *
     * @return  string
     */
    private function resolveLayoutVariant(array $cfg, string $layout = ''): string
    {
        $layout = self::normalizeLayoutKey($layout);

        if ($layout === '') {
            $layout = self::normalizeLayoutKey((string) ($cfg['layoutVariant'] ?? ''));
        }

        if ($layout === '') {
            $layout = $this->defaultLayout;
        }

        if (!str_starts_with($layout, $this->layoutPrefix . '_')) {
            return $this->defaultLayout;
        }

        return $layout;
    }

    /**
     * Return configured text or a translated fallback.
     *
     * @param   array<string, mixed>  $cfg          Configuration.
     * @param   string                $key          Key.
     * @param   string                $defaultKey   Language-key fallback.
     *
     * @return  string
     */
    private static function translatedValue(array $cfg, string $key, string $defaultKey): string
    {
        $value = trim(self::cfgString($cfg, $key));

        return $value !== '' ? Text::_($value) : Text::_($defaultKey);
    }

    /**
     * Normalize layout and slot tokens.
     *
     * @param   string  $value  Raw token.
     *
     * @return  string
     */
    private static function normalizeLayoutKey(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9_-]/i', '', trim($value)) ?? '');
    }

    /**
     * Convert filtered HTML to compact metadata text.
     *
     * @param   string  $html  HTML or plain text.
     *
     * @return  string
     */
    private static function plainText(string $html): string
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    /**
     * Extract a prefixed subset.
     *
     * @param   array<string, mixed>  $cfg          Configuration.
     * @param   string                $prefix       Prefix.
     * @param   bool                  $stripPrefix  Strip prefix from result keys.
     *
     * @return  array<string, mixed>
     */
    private static function extractPrefixedConfig(
        array $cfg,
        string $prefix,
        bool $stripPrefix = true
    ): array {
        $result = [];

        foreach ($cfg as $key => $value) {
            if (!\is_string($key) || !str_starts_with($key, $prefix)) {
                continue;
            }

            $targetKey = $stripPrefix ? substr($key, strlen($prefix)) : $key;

            if ($targetKey !== '') {
                $result[$targetKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Resolve the shared CopyMyPage image helper.
     *
     * @return  object
     */
    private function getImageHelper(): object
    {
        return Factory::getContainer()->get('copymypage.helper.image');
    }
}
