<?php
namespace CarterZenk\EloquentIdeHelper\Config;

/**
 * Class Config
 * @package CarterZenk\EloquentIdeHelper\Config
 */
class Config implements ConfigInterface
{
    /**
     * @var array
     */
    private $values = array();

    /**
     * @var string
     */
    protected $configFilePath;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $configArray, $configFilePath = null)
    {
        $this->configFilePath = $configFilePath;
        $this->values = $this->replaceTokens($configArray);
    }

    /**
     * Replace tokens in the specified array.
     *
     * @param array $arr Array to replace
     * @return array
     */
    protected function replaceTokens(array $arr)
    {
        // Get environment variables
        // $_ENV is empty because variables_order does not include it normally
        $tokens = array();
        foreach ($_SERVER as $varname => $varvalue) {
            if (0 === strpos($varname, 'IDE_HELPER_')) {
                $tokens['%%' . $varname . '%%'] = $varvalue;
            }
        }

        // IDE helper defined tokens (override env tokens)
        $tokens['%%IDE_HELPER_CONFIG_PATH%%'] = $this->configFilePath;
        $tokens['%%IDE_HELPER_CONFIG_DIR%%'] = dirname($this->configFilePath);

        // Recurse the array and replace tokens
        return $this->recurseArrayForTokens($arr, $tokens);
    }

    /**
     * Recurse an array for the specified tokens and replace them.
     *
     * @param array $arr Array to recurse
     * @param array $tokens Array of tokens to search for
     * @return array
     */
    protected function recurseArrayForTokens($arr, $tokens)
    {
        $out = array();
        foreach ($arr as $name => $value) {
            if (is_array($value)) {
                $out[$name] = $this->recurseArrayForTokens($value, $tokens);
                continue;
            }
            if (is_string($value)) {
                foreach ($tokens as $token => $tval) {
                    $value = str_replace($token, $tval, $value);
                }
                $out[$name] = $value;
                continue;
            }
            $out[$name] = $value;
        }
        return $out;
    }

    /**
     * @inheritdoc
     */
    public function getConnection()
    {
        if (!isset($this->values['connection'])) {
            throw new \UnexpectedValueException('Connection info missing from config file.');
        }

        return $this->values['connection'];
    }

    /**
     * @inheritdoc
     */
    public function getModelDirectories()
    {
        if (!isset($this->values['models']['directories'])) {
            throw new \UnexpectedValueException("Model directories missing from config file.");
        }

        return $this->values['models']['directories'];
    }

    /**
     * @inheritdoc
     */
    public function getFacadeOutputFile()
    {
        if (isset($this->values['facades']['output'])) {
            return $this->values['facades']['output'];
        }

        return dirname($this->configFilePath).'/_ide_helper_facades.php';
    }

    /**
     * @inheritdoc
     */
    public function getFacadeOutputFormat()
    {
        return (isset($this->values['facades']['format']))
            ? $this->values['facades']['format']
            : 'php';
    }

    public function getModelOutputFile()
    {
        if (isset($this->values['models']['output'])) {
            return $this->values['models']['output'];
        }

        return dirname($this->configFilePath).'/_ide_helper_models.php';
    }

    /**
     * @inheritdoc
     */
    public function getIgnoredModels()
    {
        return isset($this->values['models']['ignored'])
            ? $this->values['models']['ignored']
            : [];
    }

    /**
     * @return bool
     */
    public function getModelWrite()
    {
        return isset($this->values['models']['write'])
            ? $this->values['models']['write']
            : false;
    }

    /**
     * @inheritdoc
     */
    public function getModelReset()
    {
        return isset($this->values['models']['reset'])
            ? $this->values['models']['reset']
            : true;
    }

    /**
     * @inheritdoc
     */
    public function getModelCamelCasedProperties()
    {
        return isset($this->values['models']['camel_cased_properties'])
            ? $this->values['models']['camel_cased_properties']
            : false;
    }

    /**
     * @inheritdoc
     */
    public function getModelTypeOverrides()
    {
        return isset($this->values['models']['type_overrides'])
            ? $this->values['models']['type_overrides']
            : [];
    }

    /**
     * @inheritdoc
     */
    public function getModelCustomDbTypes()
    {
        return isset($this->values['models']['custom_db_types'])
            ? $this->values['models']['custom_db_types']
            : [];
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->values[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function offsetGet($offset)
    {
        if (!array_key_exists($offset, $this->values)) {
            throw new \InvalidArgumentException('Identifier '.$offset.' is not defined.');
        }

        return $this->values[$offset] instanceof \Closure
            ? $this->values[$offset]($this)
            : $this->values[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->values[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->values[$offset]);
    }
}