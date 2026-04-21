<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\Component\CopyMyPage\Site\View\Onepage;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Component\CopyMyPage\Site\View\HtmlViewMetaDataTrait;
use Joomla\Registry\Registry;

/**
 * HtmlView class for the Onepage view.
 *
 * This view is a lightweight shell that prepares the
 * document metadata while all visible content is rendered
 * via modules in the template.
 */
class HtmlView extends BaseHtmlView
{
    use HtmlViewMetaDataTrait;

    /**
     * Ordered onepage slots that may contribute section metadata.
     *
     * @var  array<int, string>
     */
    private const ONEPAGE_META_SLOTS = ['hero', 'gallery', 'team', 'contact'];

    /**
     * Component and menu parameters.
     *
     * @var  Registry|null
     */
    protected ?Registry $params = null;

    /**
     * Cached absolute onepage base URL.
     *
     * @var  string|null
     */
    private ?string $onepageUrl = null;

    /**
     * Displays the Onepage view.
     *
     * @param   string|null  $tpl  The name of the layout file to parse.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        $app          = Factory::getApplication();
        $this->params = $app->getParams();

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document metadata for the Onepage view.
     *
     * @return  void
     */
    protected function prepareDocument(): void
    {
        $app      = Factory::getApplication();
        $menu     = $app->getMenu()->getActive();
        $document = $this->document;

        // Title: prefer menu title, fallback to language string.
        if ($menu && $menu->title) {
            $title = $menu->title;
        } else {
            $title = Text::_('COM_COPYMYPAGE_VIEW_ONEPAGE_TITLE');
        }

        // Prepare defaults for meta description and keywords.
        $metaDescription = '';
        $metaKeywords    = '';

        if ($this->params instanceof Registry) {
            $metaDescription = (string) $this->params->get('meta_description', '');
            $metaKeywords    = (string) $this->params->get('meta_keywords', '');
        }

        // Fallback to template-level defaults if nothing is set in menu/component params.
        if ($metaDescription === '') {
            $metaDescription = Text::_('COM_COPYMYPAGE_VIEW_ONEPAGE_META_DESCRIPTION');
        }

        if ($metaKeywords === '') {
            $metaKeywords = Text::_('COM_COPYMYPAGE_VIEW_ONEPAGE_META_KEYWORDS');
        }

        $pageMeta       = $this->buildPageMeta($title, $metaDescription);
        $sectionMeta    = $this->collectSectionMeta($pageMeta);
        $defaultSection = array_key_first($sectionMeta) ?? '';
        $sectionMeta    = $this->applyCanonicalPageUrlToDefaultSection($sectionMeta, $defaultSection, $pageMeta['url']);
        $activeSection  = $defaultSection;
        $activeMeta     = $defaultSection !== '' ? array_replace($pageMeta, $sectionMeta[$defaultSection]) : $pageMeta;

        $document->setTitle($activeMeta['title']);
        $document->setDescription($activeMeta['description']);

        if ($metaKeywords !== '') {
            $document->setMetaData('keywords', $metaKeywords);
        }

        $this->addHtmlViewOpenGraphMetaData($activeMeta);
        $this->addHtmlViewTwitterCardMetaData($activeMeta);
        $document->addScriptOptions(
            'copymypage.params',
            [
                'view' => [
                    'name'    => 'onepage',
                    'onepage' => [
                        'meta' => [
                            'page'           => $pageMeta,
                            'baseUrl'        => $pageMeta['url'],
                            'defaultSection' => $defaultSection,
                            'activeSection'  => $activeSection,
                            'sections'       => $sectionMeta,
                        ],
                    ],
                ],
            ],
            true
        );

        // Important: do NOT touch "robots" here.
    }

    /**
     * Build the onepage-level meta defaults before section overrides are applied.
     *
     * @param   string  $title        The resolved onepage title.
     * @param   string  $description  The resolved onepage description.
     *
     * @return  array<string, string>
     */
    private function buildPageMeta(string $title, string $description): array
    {
        return $this->normalizeHtmlViewMetaPayload(
            [
                'title'       => trim($title),
                'description' => trim($description),
                'url'         => $this->buildOnepageUrl(),
                'image'       => '',
                'imageWidth'  => '',
                'imageHeight' => '',
                'imageAlt'    => '',
                'twitterCard' => 'summary',
            ]
        );
    }

    /**
     * Build the canonical onepage URL for the page or a specific section.
     *
     * @param   string  $slot  Optional onepage section token.
     *
     * @return  string
     */
    private function buildOnepageUrl(string $slot = ''): string
    {
        if ($this->onepageUrl === null) {
            $this->onepageUrl = Route::link(
                'site',
                'index.php?option=com_copymypage&view=onepage',
                false,
                Route::TLS_IGNORE,
                true
            );
        }

        $slot = strtolower(trim($slot));

        if ($slot === '') {
            return $this->onepageUrl;
        }

        return $this->onepageUrl . '#' . $slot;
    }

    /**
     * Collect normalized section metadata from published module helpers.
     *
     * @param   array<string, string>  $pageMeta  The page-level defaults.
     *
     * @return  array<string, array<string, string>>
     */
    private function collectSectionMeta(array $pageMeta): array
    {
        $sections = [];

        foreach (self::ONEPAGE_META_SLOTS as $slot) {
            $meta = $this->resolveSectionMeta($slot, $pageMeta);

            if ($meta === []) {
                continue;
            }

            $sections[$slot] = $meta;
        }

        return $sections;
    }

    /**
     * Keep the landing page URL neutral for the first available onepage section.
     *
     * This avoids coupling the canonical start URL to a specific slot such as
     * "hero". The first active section still provides title/description/image
     * overrides, but its URL stays the base onepage URL without a hash.
     *
     * @param   array<string, array<string, string>>  $sections        The collected section meta payloads.
     * @param   string                                $defaultSection  The first available section token.
     * @param   string                                $pageUrl         The canonical onepage base URL.
     *
     * @return  array<string, array<string, string>>
     */
    private function applyCanonicalPageUrlToDefaultSection(array $sections, string $defaultSection, string $pageUrl): array
    {
        if ($defaultSection === '' || !isset($sections[$defaultSection])) {
            return $sections;
        }

        $sections[$defaultSection]['url'] = $pageUrl;

        return $sections;
    }

    /**
     * Resolve metadata for one slot from the first published helper that supports getOGTags().
     *
     * @param   string                 $slot      The onepage system slot.
     * @param   array<string, string>  $pageMeta  The page-level defaults.
     *
     * @return  array<string, string>
     */
    private function resolveSectionMeta(string $slot, array $pageMeta): array
    {
        $app = Factory::getApplication();
        $language = $app->getLanguage();

        if (!method_exists($app, 'bootModule')) {
            return [];
        }

        foreach (ModuleHelper::getModules($slot) as $module) {
            if (!\is_object($module) || !isset($module->module)) {
                continue;
            }

            $language->load((string) $module->module, JPATH_SITE . '/modules/' . (string) $module->module, null, true);

            $helperName  = $this->resolveModuleHelperName((string) $module->module);
            $helperClass = $this->resolveModuleHelperClass((string) $module->module, $helperName);

            if ($helperName === '') {
                continue;
            }

            try {
                $helper = $app->bootModule((string) $module->module, 'site')->getHelper($helperName);
            } catch (\Throwable) {
                $helper = null;
            }

            if (!\is_object($helper) && $helperClass !== '' && class_exists($helperClass)) {
                try {
                    $helper = new $helperClass();
                } catch (\Throwable) {
                    $helper = null;
                }
            }

            if (!\is_object($helper) || !method_exists($helper, 'getOGTags')) {
                continue;
            }

            $params = new Registry((string) ($module->params ?? ''));
            $tags   = $helper->getOGTags($params, $module, $slot);

            if (!\is_array($tags) || $tags === []) {
                continue;
            }

            $meta = $this->normalizeSectionMeta($slot, $tags, $pageMeta);

            if ($meta !== []) {
                return $meta;
            }
        }

        return [];
    }

    /**
     * Convert raw helper output into a stable onepage meta payload.
     *
     * @param   string                $slot      The onepage system slot.
     * @param   array<string, mixed>  $tags      Raw helper output.
     * @param   array<string, string> $pageMeta  The page-level defaults.
     *
     * @return  array<string, string>
     */
    private function normalizeSectionMeta(string $slot, array $tags, array $pageMeta): array
    {
        $slot        = strtolower(trim($slot));
        $hash        = '#' . $slot;
        $title       = trim((string) ($tags['title'] ?? ''));
        $description = trim((string) ($tags['description'] ?? ''));
        $image       = trim((string) ($tags['image'] ?? ''));
        $label       = trim((string) ($tags['label'] ?? ucfirst($slot)));

        if ($slot === '' || $title === '') {
            return [];
        }

        return [
            'token'       => $slot,
            'label'       => $label !== '' ? $label : ucfirst($slot),
            'selector'    => $hash,
            'hash'        => $hash,
            ...$this->normalizeHtmlViewMetaPayload(
                [
                    'url'         => $this->buildOnepageUrl($slot),
                    'title'       => $this->composeSectionTitle($title, $pageMeta['title']),
                    'description' => $description !== '' ? $description : $pageMeta['description'],
                    'image'       => $image,
                    'imageWidth'  => $tags['imageWidth'] ?? '',
                    'imageHeight' => $tags['imageHeight'] ?? '',
                    'imageAlt'    => $tags['imageAlt'] ?? '',
                    'twitterCard' => $tags['twitterCard'] ?? ($image !== '' ? 'summary_large_image' : 'summary'),
                ]
            ),
        ];
    }

    /**
     * Derive the expected helper class short name from a CopyMyPage module name.
     *
     * @param   string  $moduleName  The module extension name.
     *
     * @return  string
     */
    private function resolveModuleHelperName(string $moduleName): string
    {
        $moduleName = strtolower(trim($moduleName));

        if ($moduleName === '' || !str_starts_with($moduleName, 'mod_copymypage_')) {
            return '';
        }

        $suffix = substr($moduleName, strlen('mod_copymypage_'));

        if ($suffix === false || $suffix === '') {
            return '';
        }

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $suffix))) . 'Helper';
    }

    /**
     * Derive the expected helper class name for direct fallback instantiation.
     *
     * @param   string  $moduleName  The module extension name.
     * @param   string  $helperName  The resolved short helper name.
     *
     * @return  string
     */
    private function resolveModuleHelperClass(string $moduleName, string $helperName): string
    {
        if ($helperName === '') {
            return '';
        }

        $moduleName = strtolower(trim($moduleName));
        $suffix = substr($moduleName, strlen('mod_copymypage_'));

        if ($suffix === false || $suffix === '') {
            return '';
        }

        $namespaceSuffix = str_replace(' ', '\\', ucwords(str_replace('_', ' ', $suffix)));

        return 'Joomla\\Module\\CopyMyPage\\' . $namespaceSuffix . '\\Site\\Helper\\' . $helperName;
    }

    /**
     * Compose a section title on top of the onepage base title.
     *
     * @param   string  $sectionTitle  The section-specific title.
     * @param   string  $pageTitle     The onepage base title.
     *
     * @return  string
     */
    private function composeSectionTitle(string $sectionTitle, string $pageTitle): string
    {
        $sectionTitle = trim($sectionTitle);
        $pageTitle    = trim($pageTitle);

        if ($sectionTitle === '') {
            return $pageTitle;
        }

        if ($pageTitle === '' || str_contains($sectionTitle, $pageTitle)) {
            return $sectionTitle;
        }

        return $sectionTitle . ' | ' . $pageTitle;
    }

}
