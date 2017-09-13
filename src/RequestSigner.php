<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-09-08
 */

namespace fk\http;

use fk\http\exceptions\FieldsExceededException;

class RequestSigner
{
    protected $key;
    protected $algorithm;

    protected $maxFieldsAllowed = 100;

    /**
     * @var bool Whether to blur the sign, to mix it up
     */
    protected $blur = false;

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
            $sign = $this->signWithMD5($data);
        }

        if ($this->blur) {
            $this->blur = false;
            return $this->uglify($sign);
        }
        return $sign;
    }

    public function asUglify($blur = true)
    {
        // uglify
        $this->blur = $blur;
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

    public function purify($sign)
    {
        return strtoupper(substr($sign, 9, 32));
    }

    protected function signWithMD5($data)
    {
        $sorted = $this->sort($data);
        $queryString = $this->buildQuery($sorted);
        $result = md5(md5($queryString) . $this->key);

        return strtoupper($result);
    }

    protected function randomString($count)
    {
        $string = '';
        for ($i = 0; $i < $count; $i++) {
            $rand = rand(0, 10);
            if ($rand < 3) {
                $ascii = rand(0, 9);
            } else if ($rand < 6) {
                $ascii = rand(65, 90);
            } else {
                $ascii = rand(97, 122);
            }
            $string .= $rand < 3 ? $ascii : chr($ascii); // ASCII for capital is from 65-90, z=97-122
        }
        return $string;
    }

    /**
     * @param string|array $data
     * @param string $sign
     * @return bool Whether it's a validate sign
     */
    public function validate($data, $sign): bool
    {
        if (!$sign || !$data) return false;

        if (substr($sign, -1) !== '=') return false;

        return $this->sign($data) === $this->purify($sign);
    }

    protected function sort($data)
    {
        krsort($data);
        $sorted = [];
        /**
         * a b c | d e f | g
         * c b a | f e d | g
         */

        $step = 3;
        do {
            $chunk = [];
            for ($i = 0; $i < $step; $i++) {
                if (null === $key = key($data)) {
                    break;
                } else {
                    $chunk[] = [$key, current($data)];
                    next($data);
                }
            }
            unset ($key);

            for ($i = count($chunk); $i > 0; $i--) {
                list($key, $value) = $chunk[$i - 1];
                $sorted[$key] = $value;
            }

        } while (key($data));

        return $sorted;
    }

    protected function buildQuery($data)
    {
//        return http_build_query($data);
        return str_replace('+', '%20', http_build_query($data));
    }
}