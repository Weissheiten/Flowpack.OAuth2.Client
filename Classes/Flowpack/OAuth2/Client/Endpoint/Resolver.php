<?php
namespace Flowpack\OAuth2\Client\Endpoint;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Flowpack.OAuth2.Client".*
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

/**
 * @Flow\Scope("singleton")
 */
class Resolver
{

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
     * @param string $providerName The provider name as given in Settings.yaml
     * @throws \InvalidArgumentException
     * @return TokenEndpointInterface
     */
    public function getTokenEndpointForProvider($providerName)
    {
        $tokenEndpointClassName = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, sprintf('Neos.Flow.security.authentication.providers.%s.providerOptions.tokenEndpointClassName', $providerName));
        if ($tokenEndpointClassName === null) {
            throw new \InvalidArgumentException(sprintf('In Settings.yaml, there was no "tokenEndpointClassName" option given for the provider "%s".', $providerName), 1383743372);
        }
        return $this->objectManager->get($tokenEndpointClassName);
    }
}
