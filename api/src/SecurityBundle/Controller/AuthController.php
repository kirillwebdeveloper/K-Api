<?php

namespace App\SecurityBundle\Controller;

use ErrorException;
use App\SecurityBundle\Request\LoginWithGoogleRequest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use AppZ\CoreBundle\Response\ApiResponse;
use AppZ\RestBundle\Controller\CoreRestControllerTrait;
use AppZ\RestBundle\Exception\ValidationException;
use AppZ\RestExceptionBundle\Exception\ApiException;
use AppZ\RestExceptionBundle\Exception\MalformedJsonException;
use AppZ\SecurityBundle\Controller\AuthController as AppZAuthController;
use AppZ\SecurityBundle\Exception\AuthenticationException;
use AppZ\SecurityBundle\Exception\UserBlockedException;

/**
 * Class AuthController
 * @package App\SecurityBundle\Controller
 * @Route("/auth")
 */
class AuthController extends AppZAuthController
{
    use CoreRestControllerTrait;

    /**
     * Perform a login attempt via username and password into the system.
     *
     * @param Request $request
     *
     * @return ApiResponse
     *
     * @throws ApiException
     * @throws MalformedJsonException
     * @throws UserBlockedException
     * @throws ErrorException
     * @throws ValidationException
     * @throws AuthenticationException
     * @Route("/login/google", name="auth_login_with_google")
     * @Method({"POST"})
     */
    public function loginWithGoogleAction(Request $request): ApiResponse
    {
        /** @var LoginWithGoogleRequest $loginAttempt */
        $loginAttempt = $this->deserialize(
            (string)$request->getContent(false),
            ['create'],
            LoginWithGoogleRequest::class
        );
        $loginAttempt->setIpAddress($request->getClientIp());
        $userToken = $this->get('App_authentication_service')->loginWithGoogle($loginAttempt);

        return $this->serialize($userToken, ApiResponse::HTTP_OK, ['get']);
    }
}
