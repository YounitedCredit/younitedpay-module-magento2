<?php

namespace YounitedCredit\YounitedPay\Helper;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Module\ModuleListInterface;
use YounitedCredit\YounitedPay\Model\YounitedLogger;
use YounitedCredit\YounitedPay\Model\YounitedCacheHandler;
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
     * @var YounitedLogger
     */
    private $logger;

    /**
     * @var YounitedCacheHandler
     */
    private $cacheHandler;

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
        $this->logger = new YounitedLogger();
        $this->cacheHandler = $objectManager->get(YounitedCacheHandler::class);
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
        $cacheKey = base64_encode($this->clientId . $this->clientSecret);
        $cacheToken = $this->cacheHandler->getCache($cacheKey, 'token');
        if ($cacheToken !== false) {
             return $cacheToken;
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
            $this->logger->log('[younited pay] request token to : ' . 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token');
            $this->logger->log('[younited pay] response : ' . ( empty($output['access_token']) === false ? substr($output['access_token'], 0,5) . '*****' : 'error'));
        } catch (\Exception $e) {
            $this->logger->log('[younited pay] exception response : ' . $e->getTraceAsString());   
        }

        if (empty($output['access_token']) === true) {
            return false;
        }

        $this->cacheHandler->setCache($cacheKey, 'token', $output['access_token'], (int) $output['expires_in']);
        $this->setTokenCache(
            $output['access_token'],
            (int) $output['expires_in'] + (new \DateTime())->getTimestamp()
        );

        return $output['access_token'];
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

        try {
            $this->logger->log('[younited pay] response : ' . json_encode($response->getResponse()->getModel()));
        } catch (\Exception $e) {
            $this->logger->log('[younited pay] exception response : ' . $e->getTraceAsString());   
        }

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
        $options = parent::createOptions($request, $response);
        
        $options[CURLOPT_TIMEOUT]        = 20;
        $options[CURLOPT_CONNECTTIMEOUT] = 8;

        $this->logger->log('[younited pay] request to : ' . (string) $request->getUri());

        $options[CURLOPT_WRITEFUNCTION] = function ($ch, $data) use ($response, $options) {
            if (empty($response->getResponse()->getBody()) === false) {
                return $response->getResponse()->getBody()->write($data);
            }
            return 0;
        };

        return $options;
    }
}