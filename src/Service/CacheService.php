<?php

namespace Bash\Bundle\CacheBundle\Service;

use function implode;
use function json_encode;
use function md5;
use Predis\Client as RedisClient;

class CacheService
{
    private RedisClient $cacheData;
    private RedisClient $cacheCounter;
    private string $expireType = 'EX';

    public function __construct(RedisClient $cacheData, RedisClient $cacheCounter)
    {
        $this->cacheData = $cacheData;
        $this->cacheCounter = $cacheCounter;
    }

    public function getAndSetData($key, ?callable $callback = null, ?array $paramArr = null, ?int $expirationTime = null, array $tags = [])
    {
        $data = $this->getData($key, $tags);

        if (false === $data && null !== $callback) {
            $data = \call_user_func_array($callback, $paramArr);
            $status = $this->setData($key, $data, $expirationTime, $tags);
            //TODO: log on dev or not?
        }

        return $data;
    }

    public function getData($key, array $tags = [])
    {
        $cacheKey = $this->generateCacheKey($key, $tags);
        $data = $this->cacheData->get($cacheKey);

        //TODO: use Symfony Serialize @ver:1
        return unserialize($data);
    }

    public function setData($key, $data, ?int $expirationTime = null, array $tags = [])
    {
        $cacheKey = $this->generateCacheKey($key, $tags);

        $moreParams = [];
        if (null !== $expirationTime) {
            $moreParams = [
                $this->expireType,
                $expirationTime,
            ];
        }

        //TODO: use Symfony Serialize @ver:1
        $data = serialize($data);

        return $this->cacheData->set($cacheKey, $data, ...$moreParams);
    }

    public function delData($key, array $tags = []): void
    {
        $cacheKey = $this->generateCacheKey($key, $tags);
        $this->cacheData->del($cacheKey);
    }

    public function delDataByPatterns(array $patterns): void
    {
        if (empty($patterns)) {
            return;
        }

        foreach ($patterns as $pattern) {
            $keys = $this->cacheData->keys($pattern);
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    $this->cacheData->del($key);
                }
            }
        }
    }

    public function getAndSetCounter($key, array $tags, $count): int
    {
        $currentCount = $this->getCounter($key, $tags);

        if (false === $currentCount && null !== $count) {
            $this->setCounter($key, $count, null, $tags);
            $currentCount = $count;
        }

        return (int) $currentCount;
    }

    public function getCounter($key, array $tags = [])
    {
        $cacheKey = $this->generateCacheKey($key, $tags);
        $data = $this->cacheCounter->get($cacheKey);

        //TODO: use Symfony Serialize @ver:1
        return unserialize($data);
    }

    public function setCounter($key, $data, ?int $expirationTime = null, array $tags = [])
    {
        $cacheKey = $this->generateCacheKey($key, $tags);

        $moreParams = [];
        if (null !== $expirationTime) {
            $moreParams = [
                $this->expireType,
                $expirationTime,
            ];
        }
        //TODO: use Symfony Serialize @ver:1
        $data = serialize($data);

        return $this->cacheCounter->set($cacheKey, $data, ...$moreParams);
    }

    public function delCounter($key, array $tags): void
    {
        $cacheKey = $this->generateCacheKey($key, $tags);
        $this->cacheCounter->del($cacheKey);
    }

    public function incrementCounter($key, array $tags = []): void
    {
        $cacheKey = $this->generateCacheKey($key, $tags);
        $this->cacheCounter->incr($cacheKey);
    }

    private function generateCacheKey($value, array $tags): string
    {
        $cacheKey = $value;
        if (\is_array($value)) {
            if (isset($value['base'])) {
                $base = $value['base'];
                unset($value['base']);

                return $base . '_' . md5(json_encode($value));
            }

            return md5(json_encode($value));
        }

        return $cacheKey . implode(':', $tags);
    }
}
