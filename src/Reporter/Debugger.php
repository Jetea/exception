<?php

namespace Jetea\Exception\Reporter;

/**
 * 异常展示
 */
class Debugger
{
    protected $collapseDir;

    protected $version;

    /**
     * @param string $collapseDir 错误显示页面中需要折叠的代码目录
     * @param string $version 错误显示页面的框架标识
     */
    public function __construct($collapseDir = '', $version = 'Jetea/1.0')
    {
        $vendorDir = __DIR__ . '/../../../../../../';   //vendor 目录
        $collapseDir = empty($collapseDir) ? $vendorDir : $collapseDir;
        $this->collapseDir = realpath($collapseDir);
        $this->version = ' php/' . PHP_VERSION . ' ' . $version;
    }

    /**
     * 展示错误调试页面
     */
    public function displayException($exception)
    {
        /** @var \Throwable $exception */
        if (($trace = $this->getExactTrace($exception)) === null) {
            $fileName = $exception->getFile();
            $errorLine = $exception->getLine();
        } else {
            $fileName = $trace['file'];
            $errorLine = $trace['line'];
        }

        $trace = $exception->getTrace();

        foreach ($trace as $i => $t) {
            if (!isset($t['file'])) {
                $trace[$i]['file'] = 'unknown';
            }

            if (!isset($t['line'])) {
                $trace[$i]['line'] = 0;
            }

            if (!isset($t['function'])) {
                $trace[$i]['function'] = 'unknown';
            }

            unset($trace[$i]['object']);
        }

        $data = array(
            'code'      => 500,
            'type'      => get_class($exception),
            'errorCode' => $exception->getCode(),
            'message'   => $exception->getMessage(),
            'file'      => $fileName,
            'line'      => $errorLine,
            'trace'     => $exception->getTraceAsString(),
            'traces'    => $trace,
            'time'      => time(),
            'version'   => (! empty($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '') . $this->version,
        );

        if (!headers_sent()) {
            // header("HTTP/1.0 {$data['code']} " . get_class($exception));
            header("HTTP/1.0 {$data['code']} " . 'Internal Server Error');
        }

        include dirname(__FILE__) . '/view/exception.blade.php';
    }

    /**
     * Returns the exact trace where the problem occurs.
     * @param \Throwable $exception the uncaught exception
     * @return array the exact trace where the problem occurs
     */
    private function getExactTrace($exception)
    {
        $traces=$exception->getTrace();

        foreach ($traces as $trace) {
            // property access exception
            if (isset($trace['function']) && ($trace['function']==='__get' || $trace['function']==='__set')) {
                return $trace;
            }
        }
        return null;
    }

    //---错误页面依赖方法---

    /**
     * Renders the source code around the error line.
     * @param string $file source file path
     * @param integer $errorLine the error line number
     * @param integer $maxLines maximum number of lines to display
     * @return string the rendering result
     */
    public function renderSourceCode($file, $errorLine, $maxLines)
    {
        $errorLine--;   // adjust line number to 0-based from 1-based
        if ($errorLine < 0 || ($lines = @file($file)) === false || ($lineCount = count($lines)) <= $errorLine) {
            return '';
        }

        $halfLines = (int)($maxLines/2);
        $beginLine = $errorLine-$halfLines>0 ? $errorLine-$halfLines:0;
        $endLine = $errorLine+$halfLines<$lineCount?$errorLine+$halfLines:$lineCount-1;
        $lineNumberWidth = strlen($endLine+1);

        $output = '';
        for ($i = $beginLine; $i <= $endLine; ++$i) {
            $isErrorLine = $i === $errorLine;
            $code = sprintf(
                "<span class=\"ln". ($isErrorLine?' error-ln':'') . "\">%0{$lineNumberWidth}d</span> %s",
                $i + 1,
                htmlspecialchars(str_replace("\t", '    ', $lines[$i]))
            );
            if (!$isErrorLine) {
                $output .= $code;
            } else {
                $output .= '<span class="error">'.$code.'</span>';
            }
        }
        return '<div class="code"><pre>'.$output.'</pre></div>';
    }

    /**
     * Returns a value indicating whether the call stack is from application code.
     * @param array $trace the trace data
     * @return boolean whether the call stack is from application code.
     */
    public function isCoreCode($trace)
    {
        if (isset($trace['file'])) {
            return $trace['file'] === 'unknown' || strpos(realpath($trace['file']), $this->collapseDir . DIRECTORY_SEPARATOR) === 0;
        }
        return false;
    }

    /**
     * Converts arguments array to its string representation
     *
     * @param array $args arguments array to be converted
     * @return string string representation of the arguments array
     */
    public function argumentsToString($args)
    {
        $count=0;

        $isAssoc = $args !== array_values($args);

        foreach ($args as $key => $value) {
            $count++;
            if ($count >= 5) {
                if ($count > 5) {
                    unset($args[$key]);
                } else {
                    $args[$key] = '...';
                }
                continue;
            }

            if (is_object($value)) {
                $args[$key] = get_class($value);
            } elseif (is_bool($value)) {
                $args[$key] = $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                if (strlen($value)>64) {
                    $args[$key] = '"'.substr($value, 0, 64).'..."';
                } else {
                    $args[$key] = '"'.$value.'"';
                }
            } elseif (is_array($value)) {
                $args[$key] = 'array(' . $this->argumentsToString($value) . ')';
            } elseif ($value == null) {
                $args[$key] = 'null';
            } elseif (is_resource($value)) {
                $args[$key] = 'resource';
            }

            if (is_string($key)) {
                $args[$key] = '"'.$key.'" => '.$args[$key];
            } elseif ($isAssoc) {
                $args[$key] = $key.' => '.$args[$key];
            }
        }
        $out = implode(", ", $args);

        return $out;
    }
    //---错误页面依赖end--
}
