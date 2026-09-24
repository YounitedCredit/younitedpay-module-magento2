<?php

namespace YounitedCredit\YounitedPay\Helper;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use YounitedCredit\YounitedPay\Model\Logger\YounitedLogger;
use YounitedCredit\YounitedPay\Model\YounitedCacheHandler;
use YounitedPaySDK\Client;
use YounitedPaySDK\Exception\RequestException;
use YounitedPaySDK\Request\AbstractRequest;
use YounitedPaySDK\Response\AbstractResponse;
use YounitedPaySDK\Response\CallbackResponse;
use YounitedPaySDK\Response\DefaultResponse;
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
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var \YounitedSDK\Stream
     */
    private $stream;

    /**
     * Create new cURL http client object
     */
    public function __construct(
        YounitedLogger $logger,
        YounitedCacheHandler $cacheHandler,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleListInterface,
        EncryptorInterface $encryptor
    ) 
    {
        self::$MAX_BODY_SIZE = 1024 * 1024;
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleListInterface;
        $this->logger = $logger;
        $this->cacheHandler = $cacheHandler;
        $this->encryptor = $encryptor;
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
        $this->clientSecret = $this->encryptor->decrypt($clientSecret);

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
        $cacheKey = hash('sha256', $this->clientId . $this->clientSecret);
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

    /**
     * Retrieve a callback request from API
     *
     * @param bool $isLegacy - Change behaviour depending old / new API
     *
     * @return AbstractResponse
     *
     * @throws RuntimeException Failure to create stream
     */
    public function retrieveCallbackResponse($isLegacy = true)
    {
        try {
            $this->stream = new \YounitedPaySDK\Stream();
            $content = fopen('php://temp', 'w+b');
            if ($content === false) {
                $body = $this->stream->create();
                $this->logger->debug('[younited pay] No stream content on webhook - created.');
            } else {
                $body = $this->stream->create($content);
                $this->logger->debug('[younited pay] Stream with content: ' . stream_get_contents($content));
            }
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('Unable to create stream "php://temp"');
        }

        $message = DefaultResponse::getInstance(CallbackResponse::class)->withBody($body);

        $response = (new ResponseBuilder($message))->getResponse();
        $headers = $this->get_apache_nginx_headers();

        $headerSignatureRequest = '';
        $headerDatetimeRequest = '';
        if ($isLegacy === true) {
            if (isset($headers['X-YC-SIGNATURE-256']) === false || isset($headers['X-YC-DATETIME']) === false) {
                $this->logger->debug('[younited pay] No signature or datetime header');
                return $response->withStatus(401, 'No Signature or Datetime header');
            }

            $headerSignatureRequest = $headers['X-YC-SIGNATURE-256'];
            $headerDatetimeRequest = $headers['X-YC-DATETIME'];
        } else {
            if (isset($headers['X-YOUNITED-HMACSHA256-SIGNATURE']) === false || isset($headers['X-YOUNITED-DATETIME']) === false) {
                return $response->withStatus(401, 'No Signature or Datetime header');
            }

            $headerSignatureRequest = $headers['X-YOUNITED-HMACSHA256-SIGNATURE'];
            $headerDatetimeRequest = $headers['X-YOUNITED-DATETIME'];
        }

        if (empty($headerSignatureRequest) === true || empty($headerDatetimeRequest) === true) {
            $this->logger->debug('[younited pay] Hash not accepted - timestamp or signature empty');
            $this->logger->debug('[younited pay] Header Signature: ' . $headerSignatureRequest . ', Header Datetime: ' . $headerDatetimeRequest);
            return $response->withStatus(401, 'Signature or Datetime header empty');
        }

        $currentWebhookUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $payload = file_get_contents('php://input');

        $hashData = implode('|', [
            $currentWebhookUrl,
            $payload,
            $headerDatetimeRequest,
        ]);

        $expectedSignature = hash_hmac('sha256', $hashData, $this->clientSecret);

        if ($headerSignatureRequest !== $expectedSignature) {
            $this->logger->log('[younited pay] Hash not accepted - signature mismatch');
            $this->logger->log('[younited pay] Payload: ' . $payload);
            $this->logger->log('[younited pay] Current Webhook URL: ' . $currentWebhookUrl);
            $this->logger->log('[younited pay] Expected Signature: ' . $expectedSignature);
            $this->logger->log('[younited pay] Hash Data: ' . $hashData);
            return $response->withStatus(401, 'Hash not accepted.');
        }

        if (null !== $response->getBody()) {
            $response->getBody()->write($payload !== false ? $payload : '');
        }

        return $response;
    }

    /**
     * Function to get apache / ngynx headers
     *
     * @return string[] $headers
     */
    private function get_apache_nginx_headers()
    {
        $headers = [];

        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $name = substr($name, 5);
                $name = str_replace('_', ' ', $name);
                $name = ucwords($name);
                $name = str_replace(' ', '-', $name);
                $name = strtoupper($name);
                $headers[$name] = $value;
            } elseif (strpos($name, 'X-YC-') !== false) {
                $name = strtoupper($name);
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}