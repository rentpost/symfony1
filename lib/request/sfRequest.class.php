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
 * sfRequest provides methods for manipulating client request information such
 * as attributes, and parameters. It is also possible to manipulate the
 * request method originally sent by the user.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Sean Kerr <sean@code-box.org>
 */
abstract class sfRequest implements ArrayAccess
{
    public const GET = 'GET';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const PATCH = 'PATCH';
    public const DELETE = 'DELETE';
    public const HEAD = 'HEAD';
    public const OPTIONS = 'OPTIONS';

    /** @var sfEventDispatcher */
    protected $dispatcher;

    /** @var string|null */
    protected $content;

    /** @var string */
    protected $method;
    protected $options = [];

    /** @var sfParameterHolder */
    protected $parameterHolder;

    /** @var sfParameterHolder */
    protected $attributeHolder;

    /**
     * Class constructor.
     *
     * @see initialize()
     *
     * @param array $parameters
     * @param array $attributes
     * @param array $options
     */
    public function __construct(sfEventDispatcher $dispatcher, $parameters = [], $attributes = [], $options = [])
    {
        $this->initialize($dispatcher, $parameters, $attributes, $options);
    }

    /**
     * Calls methods defined via sfEventDispatcher.
     *
     * @param string $method    The method name
     * @param array  $arguments The method arguments
     *
     * @return mixed The returned value of the called method
     *
     * @throws sfException if call fails
     */
    public function __call($method, $arguments)
    {
        $event = $this->dispatcher->notifyUntil(new sfEvent($this, 'request.method_not_found', ['method' => $method, 'arguments' => $arguments]));
        if (!$event->isProcessed()) {
            throw new sfException(sprintf('Call to undefined method %s::%s.', get_class($this), $method));
        }

        return $event->getReturnValue();
    }

    public function __clone()
    {
        $this->parameterHolder = clone $this->parameterHolder;
        $this->attributeHolder = clone $this->attributeHolder;
    }

    /**
     * Initializes this sfRequest.
     *
     * Available options:
     *
     *  * logging: Whether to enable logging or not (false by default)
     *
     * @param sfEventDispatcher $dispatcher An sfEventDispatcher instance
     * @param array             $parameters An associative array of initialization parameters
     * @param array             $attributes An associative array of initialization attributes
     * @param array             $options    An associative array of options
     *
     * @throws sfInitializationException If an error occurs while initializing this sfRequest
     */
    public function initialize(sfEventDispatcher $dispatcher, $parameters = [], $attributes = [], $options = [])
    {
        $this->dispatcher = $dispatcher;

        $this->options = $options;

        if (!isset($this->options['logging'])) {
            $this->options['logging'] = false;
        }

        // initialize parameter and attribute holders
        $this->parameterHolder = new sfParameterHolder();
        $this->attributeHolder = new sfParameterHolder();

        $this->parameterHolder->add($parameters);
        $this->attributeHolder->add($attributes);
    }

    /**
     * Return an option value or null if option does not exists.
     *
     * @param string $name the option name
     *
     * @return mixed The option value
     */
    public function getOption($name)
    {
        return isset($this->options[$name]) ? $this->options[$name] : null;
    }

    /**
     * Returns the options.
     *
     * @return array the options
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Extracts parameter values from the request.
     *
     * @param array $names An indexed array of parameter names to extract
     *
     * @return array An associative array of parameters and their values. If
     *               a specified parameter doesn't exist an empty string will
     *               be returned for its value
     */
    public function extractParameters($names)
    {
        $array = [];

        $parameters = $this->parameterHolder->getAll();
        foreach ($parameters as $key => $value) {
            if (in_array($key, $names)) {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * Gets the request method.
     *
     * @return string The request method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Sets the request method.
     *
     * @param string $method The request method
     *
     * @throws sfException - If the specified request method is invalid
     */
    public function setMethod($method)
    {
        if (!in_array(strtoupper($method), [self::GET, self::POST, self::PUT, self::PATCH, self::DELETE, self::HEAD, self::OPTIONS])) {
            throw new sfException(sprintf('Invalid request method: %s.', $method));
        }

        $this->method = strtoupper($method);
    }

    /**
     * Returns true if the request parameter exists (implements the ArrayAccess interface).
     *
     * @param string $name The name of the request parameter
     *
     * @return bool true if the request parameter exists, false otherwise
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($name)
    {
        return $this->hasParameter($name);
    }

    /**
     * Returns the request parameter associated with the name (implements the ArrayAccess interface).
     *
     * @param string $name The offset of the value to get
     *
     * @return mixed The request parameter if exists, null otherwise
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($name)
    {
        return $this->getParameter($name, false);
    }

    /**
     * Sets the request parameter associated with the offset (implements the ArrayAccess interface).
     *
     * @param string $offset The parameter name
     * @param string $value  The parameter value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->setParameter($offset, $value);
    }

    /**
     * Removes a request parameter.
     *
     * @param string $offset The parameter name
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->getParameterHolder()->remove($offset);
    }

    /**
     * Retrieves the parameters for the current request.
     *
     * @return sfParameterHolder The parameter holder
     */
    public function getParameterHolder()
    {
        return $this->parameterHolder;
    }

    /**
     * Retrieves the attributes holder.
     *
     * @return sfParameterHolder The attribute holder
     */
    public function getAttributeHolder()
    {
        return $this->attributeHolder;
    }

    /**
     * Retrieves an attribute from the current request.
     *
     * @param string $name    Attribute name
     * @param string $default Default attribute value
     *
     * @return mixed An attribute value
     */
    public function getAttribute($name, $default = null)
    {
        return $this->attributeHolder->get($name, $default);
    }

    /**
     * Indicates whether or not an attribute exist for the current request.
     *
     * @param string $name Attribute name
     *
     * @return bool true, if the attribute exists otherwise false
     */
    public function hasAttribute($name)
    {
        return $this->attributeHolder->has($name);
    }

    /**
     * Sets an attribute for the request.
     *
     * @param string $name  Attribute name
     * @param string $value Value for the attribute
     */
    public function setAttribute($name, $value)
    {
        $this->attributeHolder->set($name, $value);
    }

    /**
     * Retrieves a parameter for the current request.
     *
     * @param string $name    Parameter name
     * @param mixed $default Parameter default value
     */
    public function getParameter(string $name, mixed $default = null): mixed
    {
        return $this->parameterHolder->get($name, $default);
    }

    /**
     * Indicates whether or not a parameter exist for the current request.
     *
     * @param string $name Parameter name
     *
     * @return bool true, if the parameter exists otherwise false
     */
    public function hasParameter($name)
    {
        return $this->parameterHolder->has($name);
    }

    /**
     * Sets a parameter for the current request.
     *
     * @param string $name  Parameter name
     * @param string $value Parameter value
     */
    public function setParameter($name, $value)
    {
        LogDispatcher::getInstance()->notice('stupid sf1 method `sfRequest::setParameter()` been called, avoid calling this if possible');

        $this->parameterHolder->set($name, $value);
    }

    /**
     * Returns the content of the current request.
     *
     * @return false|string The content or false if none is available
     */
    public function getContent()
    {
        if (null === $this->content && '' === trim($this->content = file_get_contents('php://input'))) {
            $this->content = false;
        }

        return $this->content;
    }
}
