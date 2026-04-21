<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\Component\CopyMyPage\Site\View;

\defined('_JEXEC') or die;

/**
 * Shared metadata helpers for CopyMyPage site HtmlView classes.
 */
trait HtmlViewMetaDataTrait
{
    /**
     * Normalize a view-level metadata payload.
     *
     * Image-specific fields stay optional so a view can expose a stable baseline
     * without pretending to know dimensions or alt text it cannot resolve.
     *
     * @param   array<string, mixed>  $meta  Raw metadata payload.
     *
     * @return  array<string, string>
     */
    protected function normalizeHtmlViewMetaPayload(array $meta): array
    {
        $image       = trim((string) ($meta['image'] ?? ''));
        $twitterCard = trim((string) ($meta['twitterCard'] ?? ''));

        if ($twitterCard === '') {
            $twitterCard = $image !== '' ? 'summary_large_image' : 'summary';
        }

        return [
            'title'       => trim((string) ($meta['title'] ?? '')),
            'description' => trim((string) ($meta['description'] ?? '')),
            'url'         => trim((string) ($meta['url'] ?? '')),
            'image'       => $image,
            'imageWidth'  => trim((string) ($meta['imageWidth'] ?? '')),
            'imageHeight' => trim((string) ($meta['imageHeight'] ?? '')),
            'imageAlt'    => trim((string) ($meta['imageAlt'] ?? '')),
            'twitterCard' => $twitterCard,
        ];
    }

    /**
     * Add Open Graph metadata for the current view payload.
     *
     * @param   array<string, mixed>  $meta  Normalized or raw metadata payload.
     *
     * @return  void
     */
    protected function addHtmlViewOpenGraphMetaData(array $meta): void
    {
        $meta     = $this->normalizeHtmlViewMetaPayload($meta);
        $document = $this->document;

        $document->addCustomTag(
            '<meta property="og:title" content="' . htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') . '" />'
        );
        $document->addCustomTag(
            '<meta property="og:description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '" />'
        );
        $document->addCustomTag(
            '<meta property="og:url" content="' . htmlspecialchars($meta['url'], ENT_QUOTES, 'UTF-8') . '" />'
        );

        if ($meta['image'] === '') {
            return;
        }

        $document->addCustomTag(
            '<meta property="og:image" content="' . htmlspecialchars($meta['image'], ENT_QUOTES, 'UTF-8') . '" />'
        );

        if ($meta['imageWidth'] !== '' && $meta['imageHeight'] !== '') {
            $document->addCustomTag(
                '<meta property="og:image:width" content="' . htmlspecialchars($meta['imageWidth'], ENT_QUOTES, 'UTF-8') . '" />'
            );
            $document->addCustomTag(
                '<meta property="og:image:height" content="' . htmlspecialchars($meta['imageHeight'], ENT_QUOTES, 'UTF-8') . '" />'
            );
        }

        if ($meta['imageAlt'] !== '') {
            $document->addCustomTag(
                '<meta property="og:image:alt" content="' . htmlspecialchars($meta['imageAlt'], ENT_QUOTES, 'UTF-8') . '" />'
            );
        }
    }

    /**
     * Add Twitter Card metadata for the current view payload.
     *
     * @param   array<string, mixed>  $meta  Normalized or raw metadata payload.
     *
     * @return  void
     */
    protected function addHtmlViewTwitterCardMetaData(array $meta): void
    {
        $meta     = $this->normalizeHtmlViewMetaPayload($meta);
        $document = $this->document;

        $document->addCustomTag(
            '<meta name="twitter:card" content="' . htmlspecialchars($meta['twitterCard'], ENT_QUOTES, 'UTF-8') . '" />'
        );
        $document->addCustomTag(
            '<meta name="twitter:title" content="' . htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') . '" />'
        );
        $document->addCustomTag(
            '<meta name="twitter:description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES, 'UTF-8') . '" />'
        );

        if ($meta['image'] !== '') {
            $document->addCustomTag(
                '<meta name="twitter:image" content="' . htmlspecialchars($meta['image'], ENT_QUOTES, 'UTF-8') . '" />'
            );
        }
    }
}
