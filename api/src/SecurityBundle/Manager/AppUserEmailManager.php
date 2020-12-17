<?php

namespace App\SecurityBundle\Manager;

use AppZ\SecurityBundle\Entity\UserEmail;
use AppZ\SecurityBundle\Enum\UserEmailLifecycleEvent;
use AppZ\SecurityBundle\Event\UserEmailAddEvent;
use AppZ\SecurityBundle\Manager\UserEmailManager;

class AppUserEmailManager extends UserEmailManager
{
    /**
     * @inheritdoc
     *
     * @return UserEmail
     * @throws \ErrorException
     * @var UserEmail $instance
     */
    public function create($instance)
    {
        $existedEmail = $this->getRepository()->findBy(
            ['emailAddress' => $instance->getEmailAddress()]
        );

        if ($existedEmail) {
            throw  new \ErrorException('The email is existed.');
        }

        /** @var UserEmail $instance */
        $this->initializeWithUserEmailTokenData($instance);

        $this->eventDispatcher->dispatch(
            UserEmailLifecycleEvent::USER_EMAIL_ADD,
            new UserEmailAddEvent($instance)
        );

        return $instance;
    }
}
