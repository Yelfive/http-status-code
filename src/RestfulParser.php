<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 */

namespace fk\http;

/**
 * Request parser to parse restful request
 *
 * multipart/form-data
 * ```HTTP Header
 *  Content-Type: multipart/form-data; boundary={$boundary}
 * ```
 * ```HTTP Body
 *  --{$boundary}
 *  Content-Disposition: form-data; name="title"
 *
 *  HTTP learning material
 *  --{$boundary}
 *  Content-Disposition: form-data; name="material"; filename="HTTP Specification.pdf"
 *  Content-Type: application/octet-stream
 *
 *  <file in binary>
 *  --{$boundary}--
 * ```
 */
class RestfulParser
{
    const CRLF = "\r\n";

    /**
     * @var array Contains all text fields, Similar to PHP $_POST
     */
    protected $formData = [];

    /**
     * @var array Containing all files, similar to PHP $_FILES
     */
    protected $files = [];

    /**
     * @var array Array of pure simple file paths, to `unlink` when shutting down
     */
    private $_filePaths = [];

    const FIELD_TYPE_TEXT = 1;
    const FIELD_TYPE_BINARY = 2;

    /**
     * Parses a HTTP request body with `Content-Type: form-data` into `key=>value` formation
     * @return array parameters parsed from the request body
     */
    public static function parseFormData(): array
    {
        return (new static)->parse();
    }

    public function parse()
    {
        if (false === $boundary = static::getBoundary()) return [];

        // Do not read all at once
        // in case memory exhausted
        $handler = fopen('php://input', 'r');
        $value = '';
        $info = [];
        $CRLF = static::CRLF;
        while ($line = fgets($handler)) {
            if ($line === "--$boundary{$CRLF}") { // START of a field
                $this->set($info, $value);

                // start line might not be a line,
                // can be multiple lines.
                // So, as of HTTP Specification, take a CRLF as the delimiter
                $startLine = '';
                while ($CRLF !== $more = fgets($handler)) {
                    // Try retrieving mime type from the line
                    // Empty string indicates no mime found
                    // If there are more than one mime-alike lines, the last mime will be taken
                    // e.g. MIME=image/jpeg, instead of `hello/world`
                    /*
                     * 1. --$boundary
                     * 2. Content-Disposition: form-data; name="avatar"
                     * 3. Content-Type: hello/world
                     * 4.
                     * 5. "; filename="avatar.jpeg"
                     * 6. Content-Type: image/jpeg
                     * 7.
                     * 8. <binary>
                     * 9. --$boundary--
                     *
                     * NOTICE: Normally, from line 2-5, each line ends with a `CR`,
                     *          not a `CRLF` as HTTP Specification specifies
                     */
                    if ('' == $mime = $this->getMimeFromLine($more)) {
                        $startLine .= $more;
                    }
                }

                $value = '';
                $info = $this->parseStartLine($startLine);
                if (!empty($info['filename']) && isset($mime)) $info['mime'] = $mime;

            } else if ($line === "--$boundary--$CRLF") {
                // END of the form-data
                $this->set($info, $value);
                break;
            } else {
                $value .= $line;
            }
        }
        fclose($handler);

        $this->registerShutDown();
        $_FILES = &$this->files;
        return $this->formData;
    }

    protected function set($info, $value)
    {
        if (empty($info['type']) || empty($info['parts'])) return;

        extract($info);
        /**
         * @var array $parts
         * @var int $type
         */
        $value = substr($value, 0, -2); // Remove the trailing CR-LF

        switch ($type) {
            case static::FIELD_TYPE_TEXT:
                $this->parseText($parts, $value);
                break;
            case static::FIELD_TYPE_BINARY:
                if (isset($filename, $mime)) {
                    $this->parseFile($filename, $mime, $parts, $value);
                }
                break;
        }
    }

    protected function parseText($parts, $value)
    {
        $last = array_pop($parts);

        $array = &$this->formData;
        foreach ($parts as $part) {
            // In case it's array[] -> [array, ''], which should auto increase
            $index = $part;
            if ($part === '') {
                $array[] = [];
                end($array);
                $index = key($array);
            } else if (!isset($array[$part]) || !is_array($array[$part])) {
                $array[$part] = [];
            }
            $array = &$array[$index];
        }
        if ($last === '') {
            $array[] = $value;
        } else {
            $array[$last] = $value;
        }
    }

    protected function getUploadMaxFileSizeAllowed():int
    {
        $config = ini_get('upload_max_filesize');
        if (is_numeric($config)) {
            return $config;
        }
        $unit = substr($config, -1);
        $size = substr($config, 0, -1);

        switch (strtoupper($unit)) {
            case 'G':
                $size *= 1024;
            case 'M':
                $size *= 1024;
            case 'K':
                $size *= 1024;
        }
        return $size;
    }

    protected function saveFileToTmp($fileContent)
    {
        $dir = sys_get_temp_dir();
        if (!is_dir($dir)) {
            return [UPLOAD_ERR_NO_TMP_DIR];
        } else if (!is_writable($dir)) {
            return [UPLOAD_ERR_CANT_WRITE];
        } else if (strlen($fileContent) > $this->getUploadMaxFileSizeAllowed()) {
            return [UPLOAD_ERR_INI_SIZE];
        }
        $filename = $dir . '/' . uniqid('file_');
        $i = 10;
        // If file exists already,
        // try 10 time at most to get a new, unoccupied filename
        while (file_exists($filename) && $i > 0) {
            $filename = $dir . '/' . uniqid('file_');
        }
        $handler = fopen($filename, 'w');
        fwrite($handler, $fileContent);
        fclose($handler);
        $this->_filePaths[] = $filename;
        return [UPLOAD_ERR_OK, $filename];
    }

    protected function registerShutDown()
    {
        // Remove tmp files after when shutting down
        if ($this->_filePaths) register_shutdown_function(function () {
            foreach ($this->_filePaths as $file) {
                @unlink($file);
            }
        });
    }

    /**
     * @param $filename
     * @param $mime
     * @param $parts
     * @param $value
     */
    protected function parseFile($filename, $mime, $parts, $value)
    {
        $fileSize = strlen($value);
        $first = array_shift($parts);
        $uploaded = $this->saveFileToTmp($value);
        $errorCode = $uploaded[0];
        $fileInfo = [
            'name' => $filename,
            'type' => $mime,
            'tmp_name' => $errorCode === UPLOAD_ERR_OK ? $uploaded[1] : '',
            'error' => $errorCode,
            'size' => $fileSize,
        ];
        if (empty($parts)) {
            $this->files[$first] = $fileInfo;
            return;
        }

        if (!isset($this->files[$first]) || !is_array($this->files[$first])) {
            $this->files[$first] = array_combine(['name', 'type', 'tmp_name', 'error', 'size'], array_fill(0, 5, []));
        }

        $last = array_pop($parts);
        $name = &$this->files[$first]['name'];
        $type = &$this->files[$first]['type'];
        $tmp_name = &$this->files[$first]['tmp_name'];
        $error = &$this->files[$first]['error'];
        $size = &$this->files[$first]['size'];

        foreach ($parts as $part) {
            // Using reference method
            // to bind the return's point to the variable `$new`
            $new = &$this->fileInfoLogger([
                'name' => &$name,
                'type' => &$type,
                'tmp_name' => &$tmp_name,
                'error' => &$error,
                'size' => &$size,
            ], $part);
            // Bind each reference in `$new` to the current scope
            foreach ($new as $k => &$v) {
                $$k = &$v;
            }
            // Unset the `$new`
            unset($new);
        }

        $this->fileInfoWriter([
            'name' => &$name,
            'type' => &$type,
            'tmp_name' => &$tmp_name,
            'error' => &$error,
            'size' => &$size,
        ], $last, $fileInfo);
    }

    protected $fileKeys = ['name', 'type', 'tmp_name', 'error', 'size'];

    protected function &fileInfoLogger($references, $part)
    {
        $new = [];
        if ($part === '') {
            foreach ($references as $k => &$reference) {
                $reference[] = [];
                end($reference);
                $index = key($reference);
                $new[$k] = &$reference[$index];
                unset($reference);
            }
        } else {
            foreach ($references as $k => &$reference) {
                if (!isset($reference[$part]) || !is_array($reference[$part])) {
                    $reference[$part] = [];
                }
                $new[$k] = &$reference[$part];
                unset($reference);
            }
        }

        return $new;
    }

    protected function fileInfoWriter($references, $last, $values)
    {
        if ($last === '') {
            foreach ($references as $k => &$reference) {
                $reference[] = $values[$k];//$value;
            }
        } else {
            foreach ($references as $k => &$reference) {
                $reference[$last] = $values[$k];// $value;
            }
        }
    }

    protected function getMimeFromLine($line)
    {
        if (strncmp('content-type:', strtolower($line), 13) === 0) {
            return trim(substr($line, 13));
        } else {
            return '';
        }
    }

    /**
     * @param string $line
     * @return bool|array Returns field name, false on failure
     * [field name, filename]
     */
    protected function getFieldName(string $line)
    {
        if (preg_match('#\bname="([^"]+)"\B#', $line, $match)) {
            // As of PHP, strip all `\r\n`s in the field name
            $info[] = str_replace(["\r", "\n"], '', $match[1]);

            if (preg_match('#\bfilename="([^"]+)"#', $line, $match)) $info[] = $match[1];

            return $info;
        } else {
            return false;
        }
    }

    protected function parseStartLine($line)
    {
        /**
         * - field start with `[`  will be discarded
         * - translate `[` to `_`
         * - field name after the last pair of brackets will be discarded.
         *      e.g. `hello[]world`, `world` will be discarded
         * - pared brackets will be considered as array
         * - if a filed name contains double quotes, anything after the quote will be discarded
         *      single quote won't matter
         * - brackets start with first [ found and end will next ]
         *      e.g. hello[[world] =>
         *          hello => [
         *              [world
         *          ]
         */
        if (!$line || $line[0] === '[') return false;

        $info = $this->getFieldName($line);
        if (!$info) return false;

        $name = $info[0];

        $parts = $this->parseFieldName($name);
        list($type, $filename) = $this->getFieldInfo($info);
        return compact('type', 'filename', 'parts');
    }

    protected function getFieldInfo($name)
    {
        if (count($name) === 2) {
            return [static::FIELD_TYPE_BINARY, $name[1]];
        } else {
            return [static::FIELD_TYPE_TEXT, ''];
        }
    }

    /**
     * @param $name
     * @return array [hello, world, to, you]
     * [hello, world, to, you]
     * represents $_PUT['hello']['world']['to']['you']
     *
     * [hello]
     * represents $_PUT['hello']
     */
    protected function parseFieldName($name)
    {
        $start = strpos($name, '[', 0);
        if ($start === false) { // No `[` found, $_PUT['hello']
            return [$name];
        } else if ($start === 0) { // Discard field name start with `[`
            return false;
        }

        $end = strpos($name, ']', $start);
        if ($end === false) { // `[` found, but not the `]`
            return [str_replace('[', '_', $name)];
        }

        // Definitely paired brackets found
        $first = substr($name, 0, $start);
        $name = substr($name, $start);
        // Strip the well-formed part of the name, such as `hello[world]xxx` -> `hello[world]`
        if (preg_match('#^(?:\[[^\]]*\])+#', $name, $wellFormedMatch)) {
            // Get parts for the array
            if (preg_match_all('#\[([^\]]*)\]#', $wellFormedMatch[0], $match)) {
                array_unshift($match[1], $first);
                return $match[1];
            }
        }

        return false;
    }

    public static function needParse()
    {
        return false !== static::getBoundary();
    }

    protected static $boundary;

    protected static function getBoundary()
    {
        if (static::$boundary !== null) return static::$boundary;

        if (empty($_SERVER['HTTP_CONTENT_TYPE']) || empty($_SERVER['REQUEST_METHOD'])) return static::$boundary = false;

        if (
            !in_array(strtoupper($_SERVER['REQUEST_METHOD']), ['POST', 'GET'])
            && preg_match('#^multipart/form-data; boundary=([\w\-]+)$#', $_SERVER['HTTP_CONTENT_TYPE'], $match)
        ) {
            return $match[1];
        }
        return false;
    }
}