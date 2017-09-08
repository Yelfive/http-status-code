<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-09-08
 */

namespace fk\http;

class RequestSigner
{
    protected $key;
    protected $algorithm;

    /**
     * @param string $key
     * @param callable $algorithm
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
     * @return string Signature
     */
    public function sign($data): string
    {
        $sorted = $this->sort($data);
        $queryString = $this->buildQuery($sorted);
        $result = md5(md5($queryString) . $this->key);

        return strtoupper($result);
    }

    public function blur($sign)
    {
        for ($i = 0; $i < 32; $i++) {
            if (rand(0, 10) < 6) {
                $sign[$i] = strtolower($sign[$i]);
            }
        }
        $result = $this->randomString(9) . $sign . $this->randomString(rand(1, 10)) . str_repeat('=', rand(1, 2));
        return $result;
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
        $sign = strtoupper(substr($sign, 9, 32));
        return $this->sign($data) === $sign;
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