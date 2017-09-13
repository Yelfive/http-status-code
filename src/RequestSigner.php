<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-09-08
 */

namespace fk\http;

use fk\http\exceptions\FieldsExceededException;

class RequestSigner extends RequestSignerUtility
{
    protected $key;
    protected $algorithm;

    protected $maxFieldsAllowed = 100;

    /**
     * @var bool Whether to blur the sign, to mix it up
     */
    protected $ugly = false;

    /**
     * @param string $key
     * @param callable $algorithm function with one parameter to accept `$data` to be signed
     * @return static
     */
    public function __construct($key, $algorithm = null)
    {
        $this->key = $key;
        $this->algorithm = $algorithm;

        return $this;
    }

    public static function instance($key, $algorithm = null)
    {
        return new static($key, $algorithm);
    }

    /**
     * @param array|string $data
     * @return string Signature string, empty string when data is empty
     * @throws FieldsExceededException
     */
    public function sign($data): string
    {
        if (!$data) return '';

        if (count($data) > $this->maxFieldsAllowed) {
            throw new FieldsExceededException("Maximum allowed fields exceeded, over $this->maxFieldsAllowed");
        }

        if (is_callable($this->algorithm)) {
            $sign = call_user_func($this->algorithm, $data);
        } else {
            $sign = $this->signWithMD5($data, $this->key);
        }

        if ($this->ugly) {
            $this->ugly = false;
            return $this->uglify($sign);
        }
        return $sign;
    }

    public function asUgly($ugly = true)
    {
        // uglify
        $this->ugly = $ugly;
        return $this;
    }

    public function uglify($sign)
    {
        for ($i = 0; $i < 32; $i++) {
            if (rand(0, 10) < 6) {
                $sign[$i] = strtolower($sign[$i]);
            }
        }
        $result = $this->randomString(9) . $sign . $this->randomString(rand(1, 10)) . str_repeat('=', rand(1, 2));
        return $result;
    }

    public function purify($sign, $prefixLength = 9)
    {
        return strtoupper(substr($sign, $prefixLength, 32));
    }

    /**
     * @param string|array $data
     * @param string $sign
     * @param int $prefixLength The prefix of the sign
     * @return bool Whether it's a validate sign
     */
    public function validate($data, $sign, $prefixLength = 9): bool
    {
        if (!$sign || !$data) return false;

        if (substr($sign, -1) !== '=') return false;

        return $this->sign($data) === $this->purify($sign, $prefixLength);
    }
}