<?php
declare(strict_types=1);

/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2019, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Services\Notification;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\Core\Exceptions\ConstraintException;
use SP\Core\Exceptions\QueryException;
use SP\Core\Messages\MailMessage;
use SP\DataModel\AccountData;
use SP\DataModel\NotificationData;
use SP\Repositories\Account\AccountRepository;
use SP\Repositories\NoSuchItemException;
use SP\Services\Mail\MailService;
use SP\Services\Service;
use SP\Services\ServiceException;
use SP\Services\User\UserService;
use SP\Storage\Database\QueryResult;

/**
 * Class PasswordExpiryNotificationService
 *
 * Servicio para notificar sobre la caducidad de contraseñas
 *
 * @package SP\Services\Notification
 */
final class PasswordExpiryNotificationService extends Service
{
    const COMPONENT = 'password_expiry';
    const TYPE = 'notification';

    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * @var AccountRepository
     */
    private $accountRepository;

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * Comprobar las contraseñas próximas a caducar y crear notificaciones
     *
     * @param int $daysBefore Número de días antes de la caducidad para notificar
     *
     * @return int Número de notificaciones creadas
     * @throws ConstraintException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws QueryException
     */
    public function checkAndNotify(int $daysBefore = 15): int
    {
        $configData = $this->config->getConfigData();

        if (!$configData->isPasswordExpiryNotificationEnabled()) {
            logger('Password expiry notification is disabled');

            return 0;
        }

        $notificationsCreated = 0;

        // Obtener el límite de días desde la configuración o usar el parámetro
        $days = $configData->getPasswordExpiryNotificationDays() ?: $daysBefore;

        // Calcular la marca de tiempo límite (ahora + días)
        $timeLimit = time() + ($days * 86400);

        // Obtener todas las cuentas con passDateChange > 0 (tienen fecha de caducidad)
        $accounts = $this->getAccountsExpiringBefore($timeLimit);

        foreach ($accounts as $account) {
            try {
                $notificationsCreated += $this->createNotificationForAccount($account, $configData->isPasswordExpiryNotificationEmailEnabled());
            } catch (Exception $e) {
                processException($e);

                $this->eventDispatcher->notifyEvent('exception', new Event($e));

                logger(sprintf('Error processing account ID %d: %s', $account->getId(), $e->getMessage()));
            }
        }

        $this->eventDispatcher->notifyEvent('notification.password_expiry',
            new Event($this, EventMessage::factory()
                ->addDescription(__u('Password expiry notification check completed'))
                ->addDetail(__u('Notifications created'), (string)$notificationsCreated))
        );

        logger(sprintf('Password expiry notification check completed: %d notifications created', $notificationsCreated));

        return $notificationsCreated;
    }

    /**
     * Obtener las cuentas cuya contraseña caduca antes de la fecha límite
     *
     * @param int $timeLimit Marca de tiempo UNIX límite
     *
     * @return AccountData[]
     * @throws ConstraintException
     * @throws QueryException
     */
    private function getAccountsExpiringBefore(int $timeLimit): array
    {
        $queryResult = $this->accountRepository->getAll();
        $accounts = $queryResult->getDataAsArray();
        $expiringAccounts = [];

        foreach ($accounts as $account) {
            // Solo cuentas con fecha de caducidad establecida
            if ($account->getPassDateChange() > 0
                && $account->getPassDateChange() <= $timeLimit
                && $account->getPassDateChange() > time()
            ) {
                $expiringAccounts[] = $account;
            }
        }

        return $expiringAccounts;
    }

    /**
     * Crear una notificación para una cuenta y opcionalmente enviar email
     *
     * @param AccountData $account
     * @param bool        $sendEmail
     *
     * @return int 1 si se creó la notificación, 0 en caso contrario
     * @throws ConstraintException
     * @throws QueryException
     */
    private function createNotificationForAccount(AccountData $account, bool $sendEmail): int
    {
        $expireDate = date('Y-m-d', $account->getPassDateChange());
        $daysLeft = (int)ceil(($account->getPassDateChange() - time()) / 86400);

        // Comprobar si ya existe una notificación reciente para este usuario y cuenta
        if ($this->hasRecentNotification($account->getUserId(), $account->getId())) {
            return 0;
        }

        // Crear la notificación en base de datos
        $description = __u('Password for account') . ': ' . $account->getName()
            . ' - ' . __u('expires on') . ': ' . $expireDate
            . ' (' . $daysLeft . ' ' . __u('days') . ')';

        $notificationData = new NotificationData();
        $notificationData->setType(self::TYPE);
        $notificationData->setComponent(self::COMPONENT);
        $notificationData->setUserId($account->getUserId());
        $notificationData->setSticky(0);
        $notificationData->setOnlyAdmin(0);

        // Usar el mensaje como descripción
        $notificationData->description = $description;

        $this->notificationService->create($notificationData);

        // Enviar email si está habilitado
        if ($sendEmail) {
            $this->sendExpiryEmail($account, $expireDate, $daysLeft);
        }

        $this->eventDispatcher->notifyEvent('create.notification.password_expiry',
            new Event($this, EventMessage::factory()
                ->addDescription(__u('Password expiry notification created'))
                ->addDetail(__u('Account'), $account->getName())
                ->addDetail(__u('Expires'), $expireDate))
        );

        return 1;
    }

    /**
     * Comprobar si ya existe una notificación reciente para el mismo usuario y componente
     *
     * @param int $userId
     * @param int $accountId
     *
     * @return bool
     * @throws ConstraintException
     * @throws QueryException
     */
    private function hasRecentNotification(int $userId, int $accountId): bool
    {
        $notifications = $this->notificationService->getForUserIdByDate(self::COMPONENT, $userId);

        foreach ($notifications as $notification) {
            // Comprobar si la descripción contiene el ID de la cuenta
            if (strpos($notification->description, (string)$accountId) !== false
                || strpos($notification->description, 'accountId: ' . $accountId) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enviar email de notificación de caducidad
     *
     * @param AccountData $account
     * @param string      $expireDate
     * @param int         $daysLeft
     */
    private function sendExpiryEmail(AccountData $account, string $expireDate, int $daysLeft): void
    {
        try {
            $userData = $this->userService->getById($account->getUserId());

            if (empty($userData->getEmail())) {
                logger(sprintf('User ID %d has no email, skipping notification for account %s', $account->getUserId(), $account->getName()));

                return;
            }

            $mailMessage = MailMessage::factory();
            $mailMessage->setTitle(__u('Password Expiry Notification'));
            $mailMessage->addDescription(__u('The following account password is about to expire'));
            $mailMessage->addDescription('');
            $mailMessage->addDescription(__u('Account') . ': ' . $account->getName());
            $mailMessage->addDescription(__u('Expiration date') . ': ' . $expireDate);
            $mailMessage->addDescription(__u('Days left') . ': ' . $daysLeft);

            $this->mailService->send(
                __u('Password Expiry Notification'),
                $userData->getEmail(),
                $mailMessage
            );

            logger(sprintf('Expiry email sent to %s for account %s', $userData->getEmail(), $account->getName()));
        } catch (Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent('exception', new Event($e));

            logger(sprintf('Error sending expiry email for account %s: %s', $account->getName(), $e->getMessage()));
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function initialize()
    {
        $this->notificationService = $this->dic->get(NotificationService::class);
        $this->accountRepository = $this->dic->get(AccountRepository::class);
        $this->mailService = $this->dic->get(MailService::class);
        $this->userService = $this->dic->get(UserService::class);
    }
}
