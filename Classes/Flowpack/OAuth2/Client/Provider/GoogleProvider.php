<?php
namespace Flowpack\OAuth2\Client\Provider;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Flowpack.OAuth2.Client".*
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Flowpack\OAuth2\Client\Token\AbstractClientToken;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\SecurityLoggerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Authentication\TokenInterface;
use Neos\Flow\Security\Exception\UnsupportedAuthenticationTokenException;
use Neos\Flow\Security\Policy\PolicyService;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

/**
 */
class GoogleProvider extends AbstractClientProvider
{

    /**
     * @Flow\Inject
     * @var SecurityLoggerInterface
     */
    protected $securityLogger;


    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var PolicyService
     */
    protected $policyService;

    /**
     * @Flow\Inject
     * @var \Neos\Flow\Security\AccountRepository
     */
    protected $accountRepository;

    /**
     * @Flow\Inject
     * @var \Neos\Flow\Security\Context
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var \Flowpack\OAuth2\Client\Endpoint\GoogleTokenEndpoint
     */
    protected $googleTokenEndpoint;

    /**
     * @Flow\Inject
     * @var \Flowpack\OAuth2\Client\Flow\GoogleFlow
     */
    protected $googleFlow;

    /**
     * @Flow\Inject
     * @var \Neos\Flow\Persistence\PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * Tries to authenticate the given token. Sets isAuthenticated to TRUE if authentication succeeded.
     *
     * @param TokenInterface $authenticationToken The token to be authenticated
     * @throws \Neos\Flow\Security\Exception\UnsupportedAuthenticationTokenException
     * @return void
     */
    public function authenticate(TokenInterface $authenticationToken)
    {
        if (!($authenticationToken instanceof AbstractClientToken)) {
            throw new UnsupportedAuthenticationTokenException('This provider cannot authenticate the given token.', 1383754993);
        }

        $credentials = $authenticationToken->getCredentials();
        $scope = $this->buildScopeParameter();
        $tokenInformation = $this->googleTokenEndpoint->requestValidatedTokenInformation($credentials, $scope);

        if ($tokenInformation === false) {
            $authenticationToken->setAuthenticationStatus(TokenInterface::WRONG_CREDENTIALS);
            return;
        }

        // From here, we surely know the user is considered authenticated against the remote service,
        // yet to check if there is an immanent account present.
        $authenticationToken->setAuthenticationStatus(TokenInterface::AUTHENTICATION_SUCCESSFUL);
        /** @var $account \Neos\Flow\Security\Account */
        $account = null;
        $isNewCreatedAccount = false;
        $providerName = $this->name;
        $accountRepository = $this->accountRepository;
        $this->securityContext->withoutAuthorizationChecks(function () use ($tokenInformation, $providerName, $accountRepository, &$account) {
            $account = $accountRepository->findByAccountIdentifierAndAuthenticationProviderName($tokenInformation['sub'], $providerName);
        });

        if ($account === null) {
            $account = new Account();
            $isNewCreatedAccount = true;
            $account->setAccountIdentifier($tokenInformation['sub']);
            $account->setAuthenticationProviderName($providerName);

            // adding in Settings.yaml specified roles to the account
            // so the account can be authenticate against a role in the frontend for example
            $roles = array();
            foreach ($this->options['authenticateRoles'] as $roleIdentifier) {
                $roles[] = $this->policyService->getRole($roleIdentifier);
            }
            $account->setRoles($roles);
            $this->accountRepository->add($account);
        }

        $authenticationToken->setAccount($account);

        // request long-live token and attach that to the account
        $longLivedToken = $this->googleTokenEndpoint->requestLongLivedToken($credentials['access_token']);
        $account->setCredentialsSource($longLivedToken['access_token']);
        $account->authenticationAttempted(TokenInterface::AUTHENTICATION_SUCCESSFUL);

        $this->accountRepository->update($account);
        $this->persistenceManager->persistAll();

        // Only if defined a Party for the account is created
        if ($this->options['partyCreation'] && $isNewCreatedAccount) {
            $this->googleFlow->createPartyAndAttachToAccountFor($authenticationToken);
        }
    }

    /**
     * Returns the class names of the tokens this provider is responsible for.
     *
     * @return array The class name of the token this provider is responsible for
     */
    public function getTokenClassNames()
    {
        return array('Flowpack\OAuth2\Client\Token\GoogleToken');
    }

    /**
     * Returns the scopes
     *
     * @return array
     */
    protected function buildScopeParameter()
    {
        $scopes = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.Flow.security.authentication.providers.GoogleOAuth2Provider.providerOptions.scopes');
        $scope = implode(' ', $scopes);
        $scopes = array('scope' => $scope);

        return $scopes;
    }
}
