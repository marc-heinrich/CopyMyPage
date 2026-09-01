<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.20
 */

namespace Joomla\Component\CopyMyPage\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Component\CopyMyPage\Site\Service\OrderCheckoutService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentHandoffService;

/**
 * POST-only Step-4 order confirmation.
 */
final class OrderreviewController extends BaseController
{
    public function checkout(): void
    {
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 405);
        }

        $this->checkToken();
        $container           = Factory::getContainer();
        $service             = $container->get(OrderCheckoutService::class);
        $rawRevision         = $this->input->post->get('expectedCartRevision', null, 'raw');
        $expectedRevision    = \is_scalar($rawRevision)
            && preg_match('/^\d+$/', (string) $rawRevision) === 1
            ? (int) $rawRevision
            : -1;
        $termsAccepted       = $this->input->post->getBool('terms_accepted', false);
        $paymentProvider     = trim($this->input->post->getString('payment_provider', ''));
        $checkoutSignature   = strtolower(trim(
            $this->input->post->getString('checkout_signature', '')
        ));
        $reviewUrl           = Route::_('index.php?option=com_copymypage&view=orderreview', false);

        try {
            $result = $service->checkout(
                $expectedRevision,
                $termsAccepted,
                $paymentProvider,
                $checkoutSignature
            );
            $redirectUrl = (string) $result['url'];

            if (!empty($result['paymentRequired'])) {
                try {
                    $handoff = $container
                        ->get(PaymentHandoffService::class)
                        ->issue((int) $result['bookingId']);
                    $redirectUrl .= '&cmp_payment_handoff=' . rawurlencode($handoff);
                } catch (\Throwable $exception) {
                    Log::add(
                        'CopyMyPage payment hand-off could not be issued for booking ID '
                            . (int) $result['bookingId'] . ' (' . $exception::class . ').',
                        Log::WARNING,
                        'com_copymypage'
                    );
                    $redirectUrl = (string) $result['statusUrl'];
                }
            }

            $this->app->redirect($redirectUrl, 303);

            return;
        } catch (\DomainException $exception) {
            $this->app->enqueueMessage($exception->getMessage(), 'error');
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage order checkout failed (' . $exception::class . '): '
                    . $exception->getMessage(),
                Log::ERROR,
                'com_copymypage'
            );
            $this->app->enqueueMessage(
                Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_SAVE'),
                'error'
            );
        }

        $this->app->redirect($reviewUrl, 303);
    }
}
