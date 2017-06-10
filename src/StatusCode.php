<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-05-04
 */

namespace fk\http;

/**
 * Class StatusCode
 * Status code defined by HTTP Specification
 */
class StatusCode
{
    /*
    |----------------------
    |      Message
    |----------------------
    */
    const MESSAGE_CONTINUE = 100;
    const MESSAGE_SWITCH_PROTOCOL = 101;

    /*
    |----------------------
    |        Success
    |----------------------
    */
    const SUCCESS_OK = 200;
    const SUCCESS_CREATED = 201;
    const SUCCESS_ACCEPTED = 202;
    const SUCCESS_NO_CONTENT = 204;
    const SUCCESS_RESET_CONTENT = 205;
    /* Request range header */
    const SUCCESS_PARTIAL_CONTENT = 206;

    /*
    |----------------------
    |      Redirection
    |----------------------
    */
    const REDIRECT_MULTIPLE_CHOICE = 300;
    const REDIRECT_PERMANENTLY = 301;
    const REDIRECT_FOUND = 302;
    const REDIRECT_SEE_OTHER = 303;
    const REDIRECT_NOT_MODIFIED = 304;
    const REDIRECT_USER_PROXY = 305;
    const REDIRECT_REMAINED = 306; // Not used now
    const REDIRECT_TEMPORARY = 307;

    /*
    |----------------------
    |     Client Error
    |----------------------
    */
    const CLIENT_BAD_REQUEST = 400;
    const CLIENT_UNAUTHORIZED = 401;
    const CLIENT_PAYMENT_REQUIRED = 402;
    const CLIENT_FORBIDDEN = 403;
    const CLIENT_NOT_FOUND = 404;
    const CLIENT_METHOD_NOT_ALLOWED = 405;
    const CLIENT_NOT_ACCEPTABLE = 406;
    const CLIENT_PROXY_AUTHENTICATE_REQUIRED = 407;
    const CLIENT_REQUEST_TIMEOUT = 408;
    const CLIENT_CONFLICT = 409;
    const CLIENT_GONE = 410;
    const CLIENT_LENGTH_REQUIRED = 411;
    const CLIENT_PRECONDITION_FAILED = 412;
    const CLIENT_REQUEST_ENTITY_TOO_LARGE = 413;
    const CLIENT_REQUEST_URI_TOO_LONG = 414;
    const CLIENT_UNSUPPORTED_MEDIA_TYPE = 415;
    const CLIENT_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    const CLIENT_EXPECTATION_FAILED = 417;

    /*
    |----------------------
    |     Server Error
    |----------------------
    */
    const SERVER_INTERNAL_ERROR = 500;
    const SERVER_NOT_IMPLEMENTED = 501;
    const SERVER_BAD_REQUEST = 502;
    const SERVER_SERVICE_UNAVAILABLE = 503;
    const SERVER_GATEWAY_TIMEOUT = 504;
    const SERVER_HTTP_VERSION_NOT_SUPPORTED = 505;

    const ALWAYS_EXPECTS_OK = false;

    /**
     * Provides a uniform entry for modifying status code
     * @param int $code
     * @return int
     */
    public static function getStatusCode($code)
    {
        return static::ALWAYS_EXPECTS_OK ? static::SUCCESS_OK : $code;
    }
}