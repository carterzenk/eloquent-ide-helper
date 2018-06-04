<?php
namespace CarterZenk\EloquentIdeHelper\Config;

/**
 * Interface ConfigInterface
 * @package CarterZenk\EloquentIdeHelper\Config
 */
interface ConfigInterface extends \ArrayAccess
{
    /**
     * The connection data passed to Eloquent.
     * 
     * @return array
     */
    public function getConnection();

    /**
     * The file path to output the facades helper.
     * 
     * @return string
     */
    public function getFacadeOutputFile();

    /**
     * The output format for the facades helper.
     *
     * @return string
     */
    public function getFacadeOutputFormat();

    /**
     * The model directories to search in.
     *
     * @return array
     */
    public function getModelDirectories();

    /**
     * The file path to output the models helper.
     * 
     * @return string
     */
    public function getModelOutputFile();

    /**
     * The models to ignore when generating the models file.
     * 
     * @return array
     */
    public function getIgnoredModels();

    /**
     * A boolean indicating whether or not to write to the model file.
     *
     * @return boolean
     */
    public function getModelWrite();

    /**
     * A boolean indicating whether or not to remove the original docs instead of appending.
     *
     * @return bool
     */
    public function getModelReset();

    /**
     * A boolean indicating whether or not to look for camel cased properties on a model.
     *
     * @return bool
     */
    public function getModelCamelCasedProperties();

    /**
     * An array containing type overrides for models.
     *
     * @return array
     */
    public function getModelTypeOverrides();

    /**
     * An array containing custom db types used in the database.
     *
     * @return array
     */
    public function getModelCustomDbTypes();
}