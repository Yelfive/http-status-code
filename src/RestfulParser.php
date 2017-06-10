<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 */

namespace fk\http;

/**
 * Request parser to parse restful request
 */
class RestfulParser
{

    /**
     * Parses a HTTP request body with `Content-Type: form-data` into `key=>value` formation
     * @param string $rawBody the raw HTTP request body.
     * @return array parameters parsed from the request body
     */
    public static function parseFormData($rawBody): array
    {
        if (!static::needParse()) return $_POST;

        $params = [];
        $rawArray = explode("\n", $rawBody);
        $count = count($rawArray);
        if ($count < 1) { // In case it's malformed
            return [];
        }
        $startPos = strpos($rawArray[1], 'name="') + 6;
        $length = -2;   // The last two characters are `"\n`
        for ($i = 0; $i + 4 < $count; $i += 4) {
            $name = substr($rawArray[$i + 1], $startPos, $length);
            $value = substr($rawArray[$i + 3], 0, -1);  // The last character is a `\n`
            $params[$name] = $value;
        }
        return $params;
    }

    public static function needParse()
    {
        return isset($_SERVER['HTTP_CONTENT_TYPE'])
        && 0 === strncmp('multipart/form-data', $_SERVER['HTTP_CONTENT_TYPE'], 19)
        && $_SERVER['REQUEST_METHOD'] !== 'POST';
    }
}