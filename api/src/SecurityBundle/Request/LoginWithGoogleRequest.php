<?php

namespace App\SecurityBundle\Request;

use ErrorException;
use JMS\Serializer\Annotation as JMS;
use App\OrganisationBundle\Enum\AppRoleEnum;
use Symfony\Component\Validator\Constraints as Assert;
use AppZ\SecurityBundle\Entity\AbstractLoginAttempt;

/**
 * Defines the modelling of a provider google access token request.
 *
 * @package App\OrganisationBundle\Request
 *
 */
class LoginWithGoogleRequest extends AbstractLoginAttempt
{
    /**
     * @var string
     * @Assert\NotBlank()
     * @Assert\NotNull()
     * @Assert\Type("string")
     * @JMS\Type("string")
     * @JMS\Groups({"create"})
     */
    protected $authorizationCode;

    /**
     * @var string
     * @Assert\Type("string")
     * @Assert\Choice({"ROLE_PROVIDER", "ROLE_CUSTOMER"})
     * @JMS\Type("string")
     * @JMS\Groups({"create"})
     */
    protected $loginRole;

    /**
     * @return string
     */
    public function getAuthorizationCode(): string
    {
        return $this->authorizationCode;
    }

    /**
     * @param string $authorizationCode
     * @return LoginWithGoogleRequest
     */
    public function setAuthorizationCode(string $authorizationCode): LoginWithGoogleRequest
    {
        $this->authorizationCode = $authorizationCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getLoginRole(): string
    {
        return $this->loginRole;
    }

    /**
     * @param string $loginRole
     * @return LoginWithGoogleRequest
     * @throws ErrorException
     */
    public function setLoginRole(string $loginRole): LoginWithGoogleRequest
    {
        if (!in_array($loginRole, [AppRoleEnum::ROLE_PROVIDER, AppRoleEnum::ROLE_CUSTOMER])) {
            throw new ErrorException('Wrong login type');
        }
        $this->loginRole = $loginRole;
        return $this;
    }


}
