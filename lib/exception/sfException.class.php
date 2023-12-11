<?php

use Rentpost\Sprocket\Log\Dispatcher as LogDispatcher;

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) 2004-2006 Sean Kerr <sean@code-box.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfException is the base class for all symfony related throwables and
 * provides an additional method for printing up a detailed view of an
 * throwable.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Sean Kerr <sean@code-box.org>
 *
 * @version    SVN: $Id$
 */
class sfException extends Exception
{
    /** @var Exception|Throwable|null */
    protected $wrappedException;

    protected static $lastException;

    /**
     * Wraps an Throwable.
     *
     * @param Exception|Throwable $e An Throwable instance
     *
     * @return sfException An sfException instance that wraps the given Throwable object
     */
    public static function createFromException($e)
    {
        $exception = new sfException(sprintf('Wrapped %s: %s', get_class($e), $e->getMessage()));
        $exception->setWrappedException($e);
        self::$lastException = $e;

        return $exception;
    }

    /**
     * Sets the wrapped exception.
     *
     * @param Exception|Throwable $e A Throwable instance
     */
    public function setWrappedException($e)
    {
        $this->wrappedException = $e;

        self::$lastException = $e;
    }

    /**
     * Gets the last wrapped throwable.
     *
     * @return Exception|Throwable An Throwable instance
     */
    public static function getLastException()
    {
        return self::$lastException;
    }

    /**
     * Clears the $lastException property (added for #6342).
     */
    public static function clearLastException()
    {
        self::$lastException = null;
    }

    /**
     * Prints the stack trace for this exception.
     */
    public function printStackTrace()
    {
        if (null === $this->wrappedException) {
            $this->setWrappedException($this);
        }

        $exception = $this->wrappedException;

        if (!sfConfig::get('sf_test')) {
            LogDispatcher::getInstance()->logException($exception);

            // clean current output buffer
            while (ob_get_level()) {
                if (!ob_end_clean()) {
                    break;
                }
            }

            if (sfConfig::get('sf_compressed')) {
                ob_start('ob_gzhandler');
            }

            header('HTTP/1.0 500 Internal Server Error');
        }

        if (!sfConfig::get('sf_test')) {
            exit(1);
        }
    }

    /**
     * Returns the path for the template error message.
     *
     * @param string $format The request format
     * @param bool   $debug  Whether to return a template for the debug mode or not
     *
     * @return bool|string false if the template cannot be found for the given format,
     *                     the absolute path to the template otherwise
     */
    public static function getTemplatePathForError($format, $debug)
    {
        $templatePaths = array(
            sfConfig::get('sf_app_config_dir').'/error',
            sfConfig::get('sf_config_dir').'/error',
            __DIR__.'/data',
        );

        $template = sprintf('%s.%s.php', $debug ? 'exception' : 'error', $format);
        foreach ($templatePaths as $path) {
            if (null !== $path && is_readable($file = $path.'/'.$template)) {
                return $file;
            }
        }

        return false;
    }


    /**
     * Returns an array of exception traces.
     *
     * @param Exception|Throwable $exception An Throwable implementation instance
     * @param string              $format    The trace format (txt or html)
     *
     * @return array An array of traces
     */
    protected static function getTraces($exception, $format = 'txt')
    {
        $traceData = $exception->getTrace();
        array_unshift($traceData, array(
            'function' => '',
            'file' => null != $exception->getFile() ? $exception->getFile() : null,
            'line' => null != $exception->getLine() ? $exception->getLine() : null,
            'args' => array(),
        ));

        $traces = array();
        if ('html' == $format) {
            $lineFormat = 'at <strong>%s%s%s</strong>(%s)<br />in <em>%s</em> line %s <a href="#" onclick="toggle(\'%s\'); return false;">...</a><br /><ul class="code" id="%s" style="display: %s">%s</ul>';
        } else {
            $lineFormat = 'at %s%s%s(%s) in %s line %s';
        }

        for ($i = 0, $count = count($traceData); $i < $count; ++$i) {
            $line = isset($traceData[$i]['line']) ? $traceData[$i]['line'] : null;
            $file = isset($traceData[$i]['file']) ? $traceData[$i]['file'] : null;
            $args = isset($traceData[$i]['args']) ? $traceData[$i]['args'] : array();
            $traces[] = sprintf(
                $lineFormat,
                isset($traceData[$i]['class']) ? $traceData[$i]['class'] : '',
                isset($traceData[$i]['type']) ? $traceData[$i]['type'] : '',
                $traceData[$i]['function'],
                self::formatArgs($args, false, $format),
                self::formatFile($file, $line, $format, null === $file ? 'n/a' : sfDebug::shortenFilePath($file)),
                null === $line ? 'n/a' : $line,
                'trace_'.$i,
                'trace_'.$i,
                0 == $i ? 'block' : 'none',
                self::fileExcerpt($file, $line)
            );
        }

        return $traces;
    }

    /**
     * Returns an HTML version of an array as YAML.
     *
     * @param array $values The values array
     *
     * @return string An HTML string
     */
    protected static function formatArrayAsHtml($values)
    {
        return '<pre>'.self::escape(@sfYaml::dump($values)).'</pre>';
    }

    /**
     * Returns an excerpt of a code file around the given line number.
     *
     * @param string $file A file path
     * @param int    $line The selected line number
     *
     * @return string An HTML string
     */
    protected static function fileExcerpt($file, $line)
    {
        // $file can be null for RuntimeException
        if (null === $file) {
            return '';
        }

        if (is_readable($file)) {
            $content = preg_split('#<br />#', preg_replace('/^<code>(.*)<\/code>$/s', '$1', highlight_file($file, true)));

            $lines = array();
            for ($i = max($line - 3, 1), $max = min($line + 3, count($content)); $i <= $max; ++$i) {
                $lines[] = '<li'.($i == $line ? ' class="selected"' : '').'>'.$content[$i - 1].'</li>';
            }

            return '<ol start="'.max($line - 3, 1).'">'.implode("\n", $lines).'</ol>';
        }
    }

    /**
     * Formats an array as a string.
     *
     * @param array  $args   The argument array
     * @param bool   $single
     * @param string $format The format string (html or txt)
     *
     * @return string
     */
    protected static function formatArgs($args, $single = false, $format = 'html')
    {
        $result = array();

        $single and $args = array($args);

        foreach ($args as $key => $value) {
            if (is_object($value)) {
                $formattedValue = ('html' == $format ? '<em>object</em>' : 'object').sprintf("('%s')", get_class($value));
            } elseif (is_array($value)) {
                $formattedValue = ('html' == $format ? '<em>array</em>' : 'array').sprintf('(%s)', self::formatArgs($value));
            } elseif (is_string($value)) {
                $formattedValue = ('html' == $format ? sprintf("'%s'", self::escape($value)) : "'{$value}'");
            } elseif (null === $value) {
                $formattedValue = ('html' == $format ? '<em>null</em>' : 'null');
            } else {
                $formattedValue = $value;
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", self::escape($key), $formattedValue);
        }

        return implode(', ', $result);
    }

    /**
     * Formats a file path.
     *
     * @param string $file   An absolute file path
     * @param int    $line   The line number
     * @param string $format The output format (txt or html)
     * @param string $text   Use this text for the link rather than the file path
     *
     * @return string
     */
    protected static function formatFile($file, $line, $format = 'html', $text = null)
    {
        if (null === $text) {
            $text = $file;
        }

        if ('html' == $format && $file && $line && $linkFormat = sfConfig::get('sf_file_link_format', ini_get('xdebug.file_link_format'))) {
            $link = strtr($linkFormat, array('%f' => $file, '%l' => $line));
            $text = sprintf('<a href="%s" title="Click to open this file" class="file_link">%s</a>', $link, $text);
        }

        return $text;
    }

    /**
     * Escapes a string value with html entities.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function escape($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        return htmlspecialchars($value, ENT_QUOTES, sfConfig::get('sf_charset', 'UTF-8'));
    }
}
