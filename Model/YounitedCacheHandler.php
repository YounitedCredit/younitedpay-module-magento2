<?php
/**
 * Copyright since 2022 Younited Credit
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to tech@202-ecommerce.com so we can send you a copy immediately.
 *
 * @author     202 ecommerce <tech@202-ecommerce.com>
 * @copyright 2022 Younited Credit
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License (AFL 3.0)
 */

namespace YounitedCredit\YounitedPay\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use YounitedCredit\YounitedPay\Model\Cache\YounitedCache;

class YounitedCacheHandler
{
    /** @var \Magento\Framework\App\CacheInterface */
    private $cache;

    /** @var \Magento\Framework\Serialize\SerializerInterface */
    private $serializer;

    public function __construct(SerializerInterface $serializer, CacheInterface $cache)
    {
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    /**
     * getCache
     *
     * @param  string $key
     * @param  string $type
     * @return mixed
     */
    public function getCache($key, $type)
    {
        $cacheKey  = YounitedCache::TYPE_IDENTIFIER . '_' . $type . '_' . $key;
        $cached = $this->cache->load($cacheKey);
        if (empty($cached) === true) {
            return false;
        }

        return json_decode($cached, true);
    }

    /**
     * setCache
     *
     * @param  string $key
     * @param  string $type
     * @return mixed
     */
    public function setCache($key, $type, $cacheData, $cacheTime = 3600)
    {
        $cacheKey  = YounitedCache::TYPE_IDENTIFIER . '_' . $type . '_' . $key;
        $cacheTag  = YounitedCache::CACHE_TAG;
        
        $storeData = $this->cache->save(
            $this->serializer->serialize($cacheData),
            $cacheKey,
            [$cacheTag],
            $cacheTime
        );

        return $storeData;
    }

}