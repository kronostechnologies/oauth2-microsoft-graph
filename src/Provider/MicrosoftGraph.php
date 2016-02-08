<?php

namespace EightyOneSquare\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * Provider for Microsoft Graph API
 */
class MicrosoftGraph extends AbstractProvider
{

    use BearerAuthorizationTrait;

    const ACCESS_TOKEN_RESOURCE_OWNER_ID = 'id';
    const ACCESS_TOKEN_RESOURCE = 'https://graph.microsoft.com/';
    
    /**
     * Base URL for Microsoft Graph API requests
     * 
     * @var string URL
     */
    protected $apiUrlBase = 'https://graph.microsoft.com';

    /**
     * API Version string. Used for building api url.
     * 
     * @var string API version like 'v1.0' or 'beta'.
     */
    protected $apiVersion = 'v1.0';

    /**
     * Base URL for Authorization (OAuth2 endpoint)
     * 
     * @var string URL
     * @link http://graph.microsoft.io/en-us/docs/authorization/app_authorization Graph Authorization
     */
    protected $loginUrlBase = 'https://login.microsoftonline.com/common/oauth2';

    /**
     * Gets base URL
     * 
     * @return string Base URL
     */
    public function getApiUrlBase()
    {
        return $this->apiUrlBase;
    }

    /**
     * Sets API version string.
     * 
     * @param string $apiUrlBase Base URL
     * @return self
     */
    public function setApiUrlBase($apiUrlBase)
    {
        // Remove trailing slash
        $this->apiUrlBase = rtrim($apiUrlBase, '/');
        return $this;
    }

    /**
     * Gets API version string.
     * 
     * @return string API version like 'v1.0' or 'beta'.
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * Sets API version string.
     * 
     * @param string $apiVersion API version like 'v1.0' or 'beta'.
     * @return self
     * @throws \InvalidArgumentException On non-string value
     */
    public function setApiVersion($apiVersion)
    {
        // Ensure string.
        if (!is_string($apiVersion)) {
            throw new \InvalidArgumentException('apiVersion should be string value!');
        }

        $this->apiVersion = $apiVersion;
        return $this;
    }

    /**
     * Gets base URL for login (OAuth2 endpoint)
     * 
     * @return string Base URL for login
     */
    public function getLoginUrlBase()
    {
        return $this->loginUrlBase;
    }

    /**
     * Sets base URL for login (OAuth2 endpoint)
     * 
     * You may want to change this if you want to authenticate against another
     * tenant. Examples:
     * - https://login.microsoftonline.com/common/oauth2 (default)
     * - https://login.microsoftonline.com/mycompany/oauth2 (your tentantid)
     * - https://login.microsoftonline.com/common/oauth2/v2.0 (v2.0 model preview)
     * 
     * @param string $loginUrlBase Base URL
     * @return self
     */
    public function setLoginUrlBase($loginUrlBase)
    {
        // Remove trailing slash
        $this->loginUrlBase = rtrim($loginUrlBase, '/');
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBaseAuthorizationUrl()
    {
        // examples: https://login.microsoftonline.com/common/oauth2/authorize
        return $this->loginUrlBase . '/authorize';
    }

    /**
     * @inheritdoc
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        // example: https://login.microsoftonline.com/common/oauth2/token
        return $this->loginUrlBase . '/token';
    }

    /**
     * @inheritdoc
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        // example: https://graph.microsoft.com/v1.0/me
        return $this->apiUrlBase . '/' . $this->apiVersion . '/me';
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultScopes()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAccessToken($grant = 'authorization_code', array $params = [])
    {
        if (!isset($params['resource'])) {
            // Set to default Access Token Resource
            $params['resource'] = self::ACCESS_TOKEN_RESOURCE;
        }
        
        return parent::getAccessToken($grant, $params);
    }

    /**
     * @inheritdoc
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (isset($data['odata.error'])) {
            // OData error?
            $this->handleODataError($response, $data);
            return;
        }

        if (isset($data['error']) && !empty($data['error'])) {
            $this->handleOAuth2Error($response, $data);
            return;
        }

        // No errors
    }

    /**
     * @inheritdoc
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new MicrosoftGraphUser($response);
    }

    /**
     * Handle oauth2 error
     * 
     * @param ResponseInterface $response Response
     * @param array $data Error response data
     * @throws IdentityProviderException
     */
    protected function handleOAuth2Error(ResponseInterface $response, $data)
    {
        /*
         * Example error:
         *  array(
         *      'error' => 'invalid_grant', 
         *      'error_description' => 'AADSTS65001: The user or administrator has not consented to use the application with ID 'xxxxxxxxx77'. Send an interactive authorization request for this user and resource. ', 
         *      'error_codes' => array('65001'), 
         *      'timestamp' => '2016-02-08 17:12:11Z', 
         *      'trace_id' => 'xxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxx', 
         *      'correlation_id' => 'xxx...', 
         *      'resource_owner_id' => null
         * )
         */

        if (!empty($data['error'])) {
            $message = $data['error'] . ': ' . $data['error_description'];
            throw new IdentityProviderException($message, $data['error_codes'][0], $data);
        }
    }

    /**
     * Handle OData error
     * 
     * @param ResponseInterface $response Response
     * @param array $data Error response data
     * @throws IdentityProviderException
     */
    protected function handleODataError(ResponseInterface $response, $data)
    {
        if (isset($data['odata.error']['message'])) {
            $message = $data['odata.error']['message']['value'];
        } else {
            $message = $response->getReasonPhrase();
        }

        // Throw
        throw new IdentityProviderException($message, $response->getStatusCode(), $response);
    }
}
