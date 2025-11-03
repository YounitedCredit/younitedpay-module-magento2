<?php

namespace YounitedCredit\YounitedPay\Helper;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\ScopeInterface;
use YounitedCredit\YounitedPay\Model\YounitedLogger;
use YounitedPaySDK\Cache\Registry;
use YounitedPaySDK\Cache\RegistryItem;
use YounitedPaySDK\Client;
use YounitedPaySDK\Exception\RequestException;
use YounitedPaySDK\Request\AbstractRequest;
use YounitedPaySDK\Response\ErrorResponse;
use YounitedPaySDK\Response\ResponseBuilder;

class YounitedClient extends Client
{
    /**
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * @var string
     */
    private $cacheKey;

    /**
     * @var YounitedLogger
     */
    private $logger;

    /**
     * cURL handler
     *
     * @var mixed
     */
    protected $ch;

    /**
     * cURL options array
     *
     * @var array<mixed>
     */
    protected $options;

    /**
     * Maximum request body size
     *
     * @var int
     */
    protected static $MAX_BODY_SIZE;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var bool
     */
    protected $debugAPI;

    /** @var ModuleListInterface */
    protected $moduleList;

    /**
     * Create new cURL http client object
     */
    public function __construct() {
        self::$MAX_BODY_SIZE = 1024 * 1024;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
        $this->productMetadata = $objectManager->get(ProductMetadata::class);
        $this->moduleList = $objectManager->get(ModuleList::class);
        $this->scopeConfig = $objectManager->get(ScopeConfigInterface::class);;
        $this->debugAPI = (bool) $this->scopeConfig->getValue(Config::XML_PATH_API_DEBUG, ScopeInterface::SCOPE_STORE);
        $this->logger = new YounitedLogger();
    }

    /**
     * Set credentials
     *
     * @param string $clientId api client key
     * @param string $clientSecret api client secret
     *
     * @return self
     */
    public function setCredential($clientId, $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;

        return $this;
    }

    /**
     * Get Oauth token
     * @param string $tenantId tenantId
     *
     * @return false|string
     */
    private function getToken($tenantId)
    {
        $cache = Registry::getInstance();
        $this->cacheKey = 'token-' . base64_encode($this->clientId . $this->clientSecret);
        if ($cache->hasItem($this->cacheKey)) {
            /** @var RegistryItem */
            $tokenCache = $cache->getItem($this->cacheKey);
            if ($tokenCache->isExpired() === false) {
                return $tokenCache->get();
            }
        }

        $data['grant_type'] = 'client_credentials';
        $data['client_id'] = $this->clientId;
        $data['client_secret'] = $this->clientSecret;
        $data['scope'] = 'api://younited-pay/.default';

        $headers[] = 'Content-Type: application/x-www-form-urlencoded';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);

        $result = curl_exec($ch);
        if (curl_errno($ch) !== 0) {
            $info = curl_getinfo($ch);
            return false;
        }
        curl_close($ch);
        $output = json_decode((string) $result, true);

        try {
            $this->log('[younited pay] request token to : ' . 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token');
            $this->log('[younited pay] response : ' . $result);
        } catch (\Exception $e) {
            $this->log('[younited pay] exception response : ' . $e->getTraceAsString());   
        }

        if (empty($output['access_token']) === true) {
            return false;
        }

        $this->setTokenCache(
            $output['access_token'],
            (int) $output['expires_in'] + (new \DateTime())->getTimestamp()
        );

        return $output['access_token'];
    }

    /**
     * @param string $token
     * @param int $expiration
     *
     * @return void
     */
    public function setTokenCache($token, $expiration)
    {
        $expiration = (new \DateTime())->setTimestamp((int) $expiration);
        $cache = Registry::getInstance();
        $cache
            ->getItem($this->cacheKey)
            ->set($token)
            ->expiresAt($expiration);
    }

    /**
     * Send a Request
     *
     * @param AbstractRequest $request
     * @param mixed $additionnalHeaders
     *
     * @return Response\AbstractResponse
     *
     * @throws RequestException Invalid request
     * @throws InvalidArgumentException Invalid header names and/or values
     * @throws RuntimeException Failure to create stream
     */
    public function sendRequest(AbstractRequest $request, $additionnalHeaders = [])
    {
        $tenantId = $request->getTenantId();
        $token = $this->getToken($tenantId);
        if ($token === false) {
            return new ErrorResponse(401);
        }

        $headers = array_merge($additionnalHeaders, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'X-Api-Version' => '2025-01-01',
        ]);
        $request->setHeaders($headers);
        
        $version = explode('.', $this->productMetadata->getVersion());
        $headers[] = [
            'cms_version' => 'Magento2 v' . ($version[1] ?? 'None')
        ];
        $headers[] = [
            'cms_version_module' => $this->moduleList->getOne('YounitedCredit_YounitedPay')['setup_version'] ?? 'None'
        ];

        $response = $this->createResponse($request);
        $options = $this->createOptions($request, $response);
        $this->ch = curl_init();

        // Setup the cURL request
        curl_setopt_array($this->ch, $options);

        // Execute the request
        $result = curl_exec($this->ch);
        $infos = curl_getinfo($this->ch);
        // Check for any request errors
        switch (curl_errno($this->ch)) {
            case CURLE_OK:
                break;
            case CURLE_COULDNT_RESOLVE_PROXY:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_COULDNT_CONNECT:
            case CURLE_OPERATION_TIMEOUTED:
            case CURLE_SSL_CONNECT_ERROR:
                throw new RequestException('curl error ' . curl_error($this->ch), $request);
            default:
                throw new RequestException('curl error: network error', $request);
        }
        curl_close($this->ch);

        // Get the response
        return $response->getResponse();
    }

    /**
     * Create cURL request options
     *
     * @param AbstractRequest $request
     * @param ResponseBuilder $response
     *
     * @return array<mixed> cURL options
     *
     * @throws RequestException Invalid request
     * @throws InvalidArgumentException Invalid header names and/or values
     * @throws RuntimeException Unable to read request body
     */
    protected function createOptions(AbstractRequest $request, ResponseBuilder $response)
    {
        $options = $this->options;

        // These options default to false and cannot be changed on set up.
        // The options should be provided with the request instead.
        $options[CURLOPT_FOLLOWLOCATION] = false;
        $options[CURLOPT_HEADER]         = false;
        $options[CURLOPT_RETURNTRANSFER] = false;
        $options[CURLOPT_SSLVERSION]     = CURL_SSLVERSION_TLSv1_2;
        $options[CURLOPT_TIMEOUT]        = 20;
        $options[CURLOPT_CONNECTTIMEOUT] = 8;

        $this->log('[younited pay] request to : ' . (string) $request->getUri());

        try {
            $options[CURLOPT_HTTP_VERSION] = $this->getProtocolVersion($request->getProtocolVersion());
        } catch (\UnexpectedValueException $e) {
            throw new RequestException($e->getMessage(), $request);
        }
        $options[CURLOPT_URL] = (string) $request->getUri();

        $options = $this->addRequestBodyOptions($request, $options);

        $options[CURLOPT_HTTPHEADER] = $this->createHeaders($request, $options);

        if ($request->getUri()->getUserInfo()) {
            $options[CURLOPT_USERPWD] = $request->getUri()->getUserInfo();
        }

        $options[CURLOPT_HEADERFUNCTION] = function ($ch, $data) use ($response) {
            $clean_data = trim($data);

            if ($clean_data !== '') {
                if (strpos(strtoupper($clean_data), 'HTTP/') === 0) {
                    $response->setStatus($clean_data)->getResponse();
                } else {
                    $response->addHeader($clean_data);
                }
            }

            return strlen($data);
        };

        $options[CURLOPT_WRITEFUNCTION] = function ($ch, $data) use ($response, $options) {
            if (empty($response->getResponse()->getBody()) === false) {
                try {
                    $this->log('[younited pay] response : ' . $data);
                } catch (\Exception $e) {
                    $this->log('[younited pay] exception response : ' . $e->getTraceAsString());   
                }

                return $response->getResponse()->getBody()->write($data);
            }
            return 0;
        };

        return $options;
    }

    private function log($message)
    {
        if ($this->debugAPI === true) {
            $this->logger->info($message);
        }
    }
}