<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Component\CopyMyPage\Site\Service\CustomerDataService;

/**
 * POST-only Step-3 mutation and private region lookup.
 */
final class CustomerdataController extends BaseController
{
    public function save(): void
    {
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 405);
        }

        $this->checkToken();
        $service    = $this->getCustomerDataService();
        $data       = $this->input->post->get('jform', [], 'array');
        $fieldNames = $service->getFormFieldNames();
        $revisionField = (string) ($fieldNames['expectedCartRevision'] ?? 'expectedCartRevision');
        $rawRevision = $this->input->post->get($revisionField, null, 'raw');
        $expectedRevision = \is_scalar($rawRevision)
            && preg_match('/^\d+$/', (string) $rawRevision) === 1
            ? (int) $rawRevision
            : -1;

        try {
            $result = $service->save(\is_array($data) ? $data : [], $expectedRevision);

            if ($result['errors'] !== []) {
                $service->rememberValidationData(\is_array($data) ? $data : []);
                $this->enqueueErrors($result['errors']);
                $this->app->redirect($service->getCustomerDataUrl(false), 303);

                return;
            }

            $service->clearValidationData();
            $this->app->enqueueMessage(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_SAVE_SUCCESS'), 'success');
            $this->app->redirect($service->getReviewUrl(false), 303);

            return;
        } catch (\DomainException $exception) {
            $service->rememberValidationData(\is_array($data) ? $data : []);
            $this->app->enqueueMessage($exception->getMessage(), 'error');
        } catch (\Throwable $exception) {
            $service->rememberValidationData(\is_array($data) ? $data : []);
            Log::add(
                'CopyMyPage customer-data save failed (' . $exception::class . ').',
                Log::ERROR,
                'com_copymypage'
            );
            $this->app->enqueueMessage(
                Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_SAVE_ERROR'),
                'error'
            );
        }

        $this->app->redirect($service->getCustomerDataUrl(false), 303);
    }

    public function login(): void
    {
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 405);
        }

        $this->checkToken();
        $service = $this->getCustomerDataService();

        if (!$service->canEnter()) {
            $this->app->enqueueMessage(
                Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_BLOCKED_MESSAGE'),
                'error'
            );
            $this->app->redirect($service->getCustomerDataUrl(false), 303);

            return;
        }

        $identity = $this->app->getIdentity();

        if (!(bool) ($identity->guest ?? true) && (int) ($identity->id ?? 0) > 0) {
            $this->app->redirect($service->getCustomerDataUrl(false), 303);

            return;
        }

        $remember    = $this->input->post->getBool('remember', false);
        $credentials = [
            'username'  => $this->input->post->get('username', '', 'USERNAME'),
            'password'  => $this->input->post->get('password', '', 'RAW'),
            'secretkey' => $this->input->post->get('secretkey', '', 'RAW'),
        ];
        $options = [
            'entry_url' => $service->getCustomerDataUrl(false),
            'remember'  => $remember,
            'return'    => $service->getCustomerDataUrl(false),
        ];

        if (true !== $this->app->login($credentials, $options)) {
            $service->rememberLoginMode();
            $this->app->enqueueMessage(Text::_('JGLOBAL_AUTH_INVALID_PASS'), 'error');
            $this->app->redirect($service->getCustomerDataUrl(false), 303);

            return;
        }

        if ($remember) {
            $this->app->setUserState('rememberLogin', true);
        }

        $this->app->setUserState('users.login.form.data', []);
        $this->app->redirect($service->getCustomerDataUrl(false), 303);
    }

    public function regions(): void
    {
        if (strtoupper($this->input->getMethod()) !== 'GET') {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 405);
        }

        if (!Session::checkToken('get')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $this->app->close();

            return;
        }

        $service = $this->getCustomerDataService();

        if (!$service->canEnter()) {
            echo new JsonResponse(null, Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_BLOCKED_MESSAGE'), true);
            $this->app->close();

            return;
        }

        $regions = [];

        foreach ($service->getRegions($this->input->getCmd('country', '')) as $code => $name) {
            $regions[] = ['value' => $code, 'text' => $name];
        }

        echo new JsonResponse($regions);
        $this->app->close();
    }

    /**
     * @param   array<int, \Throwable|string>  $errors
     */
    private function enqueueErrors(array $errors): void
    {
        foreach (array_slice($errors, 0, 5) as $error) {
            $message = $error instanceof \Throwable ? $error->getMessage() : (string) $error;

            if ($message !== '') {
                $this->app->enqueueMessage($message, 'error');
            }
        }
    }

    private function getCustomerDataService(): CustomerDataService
    {
        return Factory::getContainer()->get(CustomerDataService::class);
    }
}
