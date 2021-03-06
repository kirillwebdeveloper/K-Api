<?php

namespace App\SecurityBundle\Service;

use ErrorException;
use Exception;
use Google_Service_Oauth2_Userinfoplus;
use App\CalendarBundle\Service\Google\Calendar\GoogleCalendarSyncService;
use App\OrganisationBundle\Event\UserWithAutogeneratedPasswordCreatedEvent;
use App\OrganisationBundle\Service\UserService;
use App\SecurityBundle\Request\LoginWithGoogleRequest;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use AppZ\NotificationBundle\Enum\NotificationLifecycleEvent;
use AppZ\RestBundle\Exception\ValidationException;
use AppZ\RestExceptionBundle\Exception\NotFoundException;
use AppZ\SecurityBundle\Entity\LoginAttemptEntityInterface;
use AppZ\SecurityBundle\Entity\User;
use AppZ\SecurityBundle\Entity\UserToken;
use AppZ\SecurityBundle\Exception\AuthenticationException;
use AppZ\SecurityBundle\Exception\UserBlockedException;
use AppZ\SecurityBundle\Manager\UserManager;
use AppZ\SecurityBundle\Manager\UserTokenManager;
use AppZ\SecurityBundle\Service\AuthenticationEventService;
use AppZ\SecurityBundle\Service\EmailLoginAttemptService;
use AppZ\SecurityBundle\Service\PasswordEncoderService;
use AppZ\SecurityBundle\Service\PasswordGeneratorService;
use AppZ\SecurityBundle\Service\SaltObfuscatorService;
use AppZ\SecurityBundle\Service\UserEmailService;
use AppZ\SecurityBundle\Service\UsernameLoginAttemptService;

class AppAuthenticationService
{
    const ENCODED_PASSWORD = '******************';
    const INVALID_PASSWORD = '&&&&&&&&&&&&&&&&&&';
    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var SaltObfuscatorService
     */
    protected $saltObfuscator;

    /**
     * @var PasswordEncoderService
     */
    protected $passwordEncoder;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var UserTokenManager
     */
    protected $userTokenManager;

    /**
     * @var UsernameLoginAttemptService
     */
    protected $usernameLoginAttemptService;

    /**
     * @var EmailLoginAttemptService
     */
    protected $emailLoginAttemptService;

    /**
     * @var AuthenticationEventService
     */
    protected $authEventService;

    /** @var GoogleCalendarSyncService */
    private $googleCalendarSyncService;
    /** @var UserEmailService */
    private $userEmailService;
    /** @var UserService */
    private $AppUserService;
    /** @var string */
    private $frontendDomain;
    /** @var PasswordGeneratorService */
    private $passwordGenerator;
    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(
        string $frontendDomain,
        UserManager $userManager,
        SaltObfuscatorService $saltObfuscator,
        PasswordEncoderService $passwordEncoder,
        ValidatorInterface $validator,
        UserTokenManager $userTokenManager,
        UsernameLoginAttemptService $usernameLoginAttemptService,
        EmailLoginAttemptService $emailLoginAttemptService,
        AuthenticationEventService $authEventService,
        GoogleCalendarSyncService $googleCalendarSyncService,
        UserEmailService $userEmailService,
        UserService $AppUserService,
        PasswordGeneratorService $passwordGenerator,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $this->frontendDomain = $frontendDomain;
        $this->userManager = $userManager;
        $this->saltObfuscator = $saltObfuscator;
        $this->passwordEncoder = $passwordEncoder;
        $this->validator = $validator;
        $this->userTokenManager = $userTokenManager;
        $this->usernameLoginAttemptService = $usernameLoginAttemptService;
        $this->emailLoginAttemptService = $emailLoginAttemptService;
        $this->authEventService = $authEventService;
        $this->googleCalendarSyncService = $googleCalendarSyncService;
        $this->userEmailService = $userEmailService;
        $this->AppUserService = $AppUserService;
        $this->passwordGenerator = $passwordGenerator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param LoginWithGoogleRequest $loginWithGoogleRequest
     * @return UserToken
     * @throws AuthenticationException
     * @throws ErrorException
     * @throws UserBlockedException
     * @throws ValidationException
     */
    public function loginWithGoogle(LoginWithGoogleRequest $loginWithGoogleRequest): UserToken
    {
        /** Create Google Client and get access token by auth code */
        $googleClient = $this->googleCalendarSyncService->createClient();
        $googleClient->setRedirectUri($this->frontendDomain . '/auth/login');
        $accessToken = $this->googleCalendarSyncService->getAccessToken($loginWithGoogleRequest->getAuthorizationCode());
        $this->googleCalendarSyncService->createClient($accessToken);
        /** @var Google_Service_Oauth2_Userinfoplus $googleUserAccount */
        $googleUserAccount = $this->googleCalendarSyncService->getProfile();
        $this->usernameLoginAttemptService->validateLoginAttempt($loginWithGoogleRequest);
        try {
            $user = $this->userEmailService->findOneByEmail($googleUserAccount->getEmail())->getUser();
        } catch (NotFoundException $exception) {
            /** @var string $password */
            $password = $this->passwordGenerator->generatePassword(16);
            $user = $this->AppUserService->findOrCreateUserWithAutogeneratedPassword(
                $googleUserAccount->getEmail(),
                $googleUserAccount->getEmail(),
                $password
            );
            $this->eventDispatcher->dispatch(
                NotificationLifecycleEvent::NOTIFICATION_SEND,
                new UserWithAutogeneratedPasswordCreatedEvent($googleUserAccount->getEmail(), $password)
            );
//            throw new UserBlockedException;
        } catch (Exception $exception) {
            $this->passwordEncoder->isPasswordValid(
                self::ENCODED_PASSWORD,
                self::INVALID_PASSWORD
            );

            throw new AuthenticationException;
        }

        return $this->loginUserFromThirdParty($user, $loginWithGoogleRequest);
    }

    /**
     * @param User $user
     * @param LoginAttemptEntityInterface $loginAttempt
     * @return UserToken
     */
    protected function loginUserFromThirdParty(User $user, LoginAttemptEntityInterface $loginAttempt): UserToken
    {
        $userToken = $this->userTokenManager->generateFreshForUser(
            $user,
            $loginAttempt->getIpAddress(),
            $loginAttempt->getDeviceName()
        );

        $this->authEventService
            ->dispatchAuthenticationSuccess(
                $loginAttempt,
                $user,
                $userToken
            );

        return $userToken;
    }
}