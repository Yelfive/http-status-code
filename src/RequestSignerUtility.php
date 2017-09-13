<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-09-13
 */

namespace fk\http;

class RequestSignerUtility
{


    public function sort($data, $step = 3)
    {
        krsort($data);
        $sorted = [];
        /**
         * a b c | d e f | g
         * c b a | f e d | g
         */

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

    public function buildQuery($data)
    {
        return str_replace('+', '%20', http_build_query($data));
    }


    protected function signWithMD5($data, $key)
    {
        $sorted = $this->sort($data);
        $queryString = $this->buildQuery($sorted);
        $result = md5(md5($queryString) . $key);

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

}