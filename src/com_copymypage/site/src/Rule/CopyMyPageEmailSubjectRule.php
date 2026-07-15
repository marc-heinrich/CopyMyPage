<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.16
 */

namespace Joomla\Component\CopyMyPage\Site\Rule;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

/**
 * Reject configured words or phrases in the contact subject.
 */
final class CopyMyPageEmailSubjectRule extends FormRule
{
    /**
     * Test the subject against the component's banned-subject list.
     *
     * @param   \SimpleXMLElement  $element  Form field definition.
     * @param   mixed              $value    Field value.
     * @param   string|null        $group    Field group.
     * @param   Registry|null      $input    Complete form data.
     * @param   Form|null          $form     Form instance.
     *
     * @return  bool
     */
    public function test(
        \SimpleXMLElement $element,
        $value,
        $group = null,
        ?Registry $input = null,
        ?Form $form = null
    ): bool {
        $banned = trim(
            (string) ComponentHelper::getParams('com_copymypage')->get('banned_subject', '')
        );

        if ($banned === '') {
            return true;
        }

        foreach (explode(';', $banned) as $item) {
            $item = trim($item);

            if ($item !== '' && StringHelper::stristr((string) $value, $item) !== false) {
                return false;
            }
        }

        return true;
    }
}
