<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.16
 */

namespace Joomla\Component\CopyMyPage\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Exception\MailDisabledException;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use PHPMailer\PHPMailer\Exception as PhpMailerException;

/**
 * Contact form model for CopyMyPage module submissions.
 */
final class ContactModel extends FormModel
{
    /**
     * Build the module-owned form through its helper.
     *
     * @param   array  $data      Optional form data.
     * @param   bool   $loadData  Whether stored form data should be loaded.
     *
     * @return  Form|false
     */
    public function getForm($data = [], $loadData = true)
    {
        $module = $this->getState('contact.module');

        if (!\is_object($module)
            || (string) ($module->module ?? '') !== 'mod_copymypage_contact'
            || (int) ($module->id ?? 0) <= 0
        ) {
            $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_INVALID_MODULE'));

            return false;
        }

        try {
            $app    = Factory::getApplication();
            $params = new Registry((string) ($module->params ?? ''));
            $helper = $app->bootModule('mod_copymypage_contact', 'site')
                ->getHelper('ContactHelper');
            $form   = $helper->getContactForm(
                $params,
                $app,
                (int) $module->id
            );

            if (!$form instanceof Form) {
                return false;
            }

            $this->setState(
                'contact.privacy_article_id',
                $this->getPrivacyArticleId($form)
            );

            return $form;
        } catch (\Throwable $exception) {
            $this->setError($exception->getMessage());

            return false;
        }
    }

    /**
     * Send the validated contact request.
     *
     * @param   array<string, mixed>  $data  Filtered and validated form data.
     *
     * @return  bool
     */
    public function sendMessage(array $data): bool
    {
        $app    = Factory::getApplication();
        $module = $this->getState('contact.module');

        if (!\is_object($module) || !$app->get('mailonline', 1)) {
            $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_MAIL_DISABLED'));

            return false;
        }

        try {
            $params = new Registry((string) ($module->params ?? ''));
            $helper = $app->bootModule('mod_copymypage_contact', 'site')
                ->getHelper('ContactHelper');
            $layout = strtolower(trim((string) $params->get('layoutVariant', 'contact_default')));
            $recipient = trim(
                $helper->getRecipientEmail(
                    $params->toArray(),
                    $layout,
                    $app
                )
            );

            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_RECIPIENT_INVALID'));

                return false;
            }

            $senderEmail = PunycodeHelper::emailToPunycode(
                trim((string) ($data['contact_email'] ?? ''))
            );
            $senderName = trim((string) ($data['contact_name'] ?? ''));

            if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL) || $senderName === '') {
                $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_VALIDATION_FAILED'));

                return false;
            }

            $privacyArticleId = max(
                0,
                (int) $this->getState('contact.privacy_article_id', 0)
            );

            if (!$this->saveContactIfMissing($senderName, $senderEmail, $privacyArticleId)) {
                return false;
            }

            $templateData = [
                'sitename'     => (string) $app->get('sitename'),
                'name'         => $senderName,
                'contactname'  => (string) $app->get('fromname', $app->get('sitename')),
                'email'        => $senderEmail,
                'subject'      => trim((string) ($data['contact_subject'] ?? '')),
                'body'         => stripslashes((string) ($data['contact_message'] ?? '')),
                'url'          => \Joomla\CMS\Uri\Uri::base(),
                'customfields' => '',
            ];

            $sent = $this->sendMail(
                'com_contact.mail',
                $recipient,
                $senderEmail,
                $senderName,
                $templateData
            );

            $showCopy = $helper->getShowCopy($params->toArray(), $layout);

            if ($sent && $showCopy && !empty($data['contact_copy'])) {
                $sent = $this->sendMail(
                    'com_copymypage.contact.copy',
                    $senderEmail,
                    $senderEmail,
                    $senderName,
                    $templateData
                );
            }

            if (!$sent) {
                $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_SEND_FAILED'));
            }

            return $sent;
        } catch (MailDisabledException | PhpMailerException $exception) {
            Log::add($exception->getMessage(), Log::WARNING, 'jerror');
            $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_SEND_FAILED'));

            return false;
        } catch (\Throwable $exception) {
            Log::add($exception->getMessage(), Log::WARNING, 'jerror');
            $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_SEND_FAILED'));

            return false;
        }
    }

    /**
     * Store a submitted sender as a Joomla contact when no matching contact exists.
     *
     * Existing contacts are matched globally by alias or email address, mirroring
     * the legacy OnePager behavior while keeping repeated submissions idempotent.
     *
     * @param   string  $senderName       Validated sender name.
     * @param   string  $senderEmail      Validated and punycoded sender email.
     * @param   int     $privacyArticleId Privacy article accepted during submission.
     *
     * @return  bool
     */
    private function saveContactIfMissing(
        string $senderName,
        string $senderEmail,
        int $privacyArticleId
    ): bool {
        $app   = Factory::getApplication();
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $alias = ApplicationHelper::stringURLSafe(
            $senderName,
            $app->getLanguage()->getTag()
        );

        if ($alias === '') {
            $alias = 'contact-' . substr(hash('sha256', strtolower($senderEmail)), 0, 12);
        }

        try {
            if ($this->findExistingContactId($db, $alias, $senderEmail) > 0) {
                return true;
            }

            $categoryId = $this->getOrCreateContactCategory($db);

            if ($categoryId <= 0) {
                return false;
            }

            $contactModel = $app->bootComponent('com_contact')
                ->getMVCFactory()
                ->createModel('Contact', 'Administrator', ['ignore_request' => true]);

            if (!$contactModel) {
                $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_CONTACT_SAVE_FAILED'));

                return false;
            }

            $createdByAlias = Text::_('COM_COPYMYPAGE_CONTACT_CREATED_BY_ALIAS');

            if ($privacyArticleId > 0) {
                $createdByAlias .= ' | privacy_article_id=' . $privacyArticleId;
            }

            $contactData = [
                'id'               => 0,
                'name'             => $senderName,
                'alias'            => $alias,
                'email_to'         => $senderEmail,
                'catid'            => $categoryId,
                'published'        => 1,
                'access'           => 1,
                'language'         => $app->getLanguage()->getTag(),
                'created'          => Factory::getDate()->toSql(),
                'created_by_alias' => $createdByAlias,
            ];

            $saved = false;
            $error = '';

            try {
                $saved = (bool) $contactModel->save($contactData);
                $error = trim((string) $contactModel->getError());
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }

            if (!$saved) {
                // Some extensions fail in an after-save event even though Joomla
                // has already persisted the contact. Recheck before reporting an
                // error or encouraging a duplicate submission.
                if ($this->findExistingContactId($db, $alias, $senderEmail) > 0) {
                    if ($error !== '') {
                        Log::add(
                            'Contact persisted despite a post-save error: ' . $error,
                            Log::WARNING,
                            'com_copymypage.contact'
                        );
                    }

                    return true;
                }

                if ($error !== '') {
                    Log::add($error, Log::WARNING, 'com_copymypage.contact');
                }

                $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_CONTACT_SAVE_FAILED'));

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::add($exception->getMessage(), Log::WARNING, 'com_copymypage.contact');
            $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_CONTACT_SAVE_FAILED'));

            return false;
        }
    }

    /**
     * Find a previously stored Joomla contact by legacy alias/email semantics.
     *
     * @param   DatabaseInterface  $db           Joomla database connection.
     * @param   string             $alias        Normalized contact alias.
     * @param   string             $senderEmail  Normalized contact email.
     *
     * @return  int
     */
    private function findExistingContactId(
        DatabaseInterface $db,
        string $alias,
        string $senderEmail
    ): int {
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__contact_details'))
            ->where(
                '('
                . $db->quoteName('alias') . ' = :alias'
                . ' OR ' . $db->quoteName('email_to') . ' = :email'
                . ')'
            )
            ->bind(':alias', $alias, ParameterType::STRING)
            ->bind(':email', $senderEmail, ParameterType::STRING)
            ->setLimit(1);

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Resolve the privacy article selected by Joomla's Confirm Consent field.
     *
     * The field definition is rebuilt server-side during submission, so the
     * stored article ID cannot be supplied or altered by the browser.
     *
     * @param   Form  $form  Contact form containing the injected consent field.
     *
     * @return  int
     */
    private function getPrivacyArticleId(Form $form): int
    {
        $privacyType = (string) $form->getFieldAttribute(
            'consentbox',
            'privacy_type',
            ''
        );

        if ($privacyType !== 'article') {
            return 0;
        }

        return max(
            0,
            (int) $form->getFieldAttribute('consentbox', 'articleid', 0)
        );
    }

    /**
     * Resolve or create the dedicated CopyMyPage category for Joomla contacts.
     *
     * @param   DatabaseInterface  $db  Joomla database connection.
     *
     * @return  int
     */
    private function getOrCreateContactCategory(DatabaseInterface $db): int
    {
        $extension = 'com_contact';
        $title     = 'CopyMyPage';
        $alias     = 'copymypage';

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = :extension')
                ->where(
                    '('
                    . $db->quoteName('alias') . ' = :alias'
                    . ' OR ' . $db->quoteName('title') . ' = :title'
                    . ')'
                )
                ->bind(':extension', $extension, ParameterType::STRING)
                ->bind(':alias', $alias, ParameterType::STRING)
                ->bind(':title', $title, ParameterType::STRING)
                ->setLimit(1);

            $categoryId = (int) $db->setQuery($query)->loadResult();

            if ($categoryId > 0) {
                return $categoryId;
            }

            $categoryModel = Factory::getApplication()
                ->bootComponent('com_categories')
                ->getMVCFactory()
                ->createModel('Category', 'Administrator', ['ignore_request' => true]);

            if (!$categoryModel) {
                $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_CATEGORY_SAVE_FAILED'));

                return 0;
            }

            $categoryData = [
                'id'          => 0,
                'parent_id'   => 1,
                'title'       => $title,
                'alias'       => $alias,
                'description' => '',
                'extension'   => $extension,
                'published'   => 1,
                'access'      => 1,
                'language'    => '*',
                'params'      => [
                    'category_layout' => '',
                    'image'           => '',
                ],
                'metadata'    => [
                    'author' => '',
                    'robots' => '',
                ],
                'note'        => 'CopyMyPage contact submissions',
            ];

            if (!$categoryModel->save($categoryData)) {
                $error = trim((string) $categoryModel->getError());

                if ($error !== '') {
                    Log::add($error, Log::WARNING, 'com_copymypage.contact');
                }

                $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_CATEGORY_SAVE_FAILED'));

                return 0;
            }

            return (int) $categoryModel->getState('category.id');
        } catch (\Throwable $exception) {
            Log::add($exception->getMessage(), Log::WARNING, 'com_copymypage.contact');
            $this->setError(Text::_('COM_COPYMYPAGE_CONTACT_ERROR_CATEGORY_SAVE_FAILED'));

            return 0;
        }
    }

    /**
     * Send one Joomla mail-template message.
     *
     * @param   string                $templateId   Mail-template identifier.
     * @param   string                $recipient    Recipient address.
     * @param   string                $replyEmail   Reply-to address.
     * @param   string                $replyName    Reply-to name.
     * @param   array<string, mixed>  $templateData Template variables.
     *
     * @return  bool
     */
    private function sendMail(
        string $templateId,
        string $recipient,
        string $replyEmail,
        string $replyName,
        array $templateData
    ): bool {
        $app    = Factory::getApplication();
        $mailer = new MailTemplate($templateId, $app->getLanguage()->getTag());

        $mailer->addRecipient($recipient);
        $mailer->setReplyTo($replyEmail, $replyName);
        $mailer->addTemplateData($templateData);
        $mailer->addUnsafeTags([
            'sitename',
            'name',
            'contactname',
            'email',
            'subject',
            'body',
            'url',
        ]);

        return (bool) $mailer->send();
    }
}
