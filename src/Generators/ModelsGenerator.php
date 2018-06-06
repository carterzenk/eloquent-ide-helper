<?php
namespace CarterZenk\EloquentIdeHelper\Generators;

use Barryvdh\Reflection\DocBlock;
use Barryvdh\Reflection\DocBlock\Context;
use Barryvdh\Reflection\DocBlock\Tag;
use Barryvdh\Reflection\DocBlock\Serializer as DocBlockSerializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class ModelsGenerator
{
    /**
     * @var DocBlock|null
     */
    protected $phpDoc;

    /**
     * @var \ReflectionClass
     */
    protected $reflectionClass;

    /**
     * @var array
     */
    protected $typeOverrides;

    /**
     * @var array
     */
    protected $customDbTypes;

    /**
     * @var array
     */
    protected $properties;

    /**
     * @var array
     */
    protected $methods;

    /**
     * @var bool
     */
    protected $reset;

    /**
     * @var bool
     */
    protected $camelCasedProperties;

    /**
     * ModelsGenerator constructor.
     * @param \ReflectionClass $reflectionClass
     * @param array $typeOverrides
     * @param array $customDbTypes
     * @param bool $camelCasedProperties
     * @param bool $reset
     */
    public function __construct(
        \ReflectionClass $reflectionClass,
        $typeOverrides = [],
        $customDbTypes = [],
        $camelCasedProperties = false,
        $reset = false
    ){
        $this->reflectionClass = $reflectionClass;
        $this->typeOverrides = $typeOverrides;
        $this->customDbTypes = $customDbTypes;
        $this->reset = $reset;
        $this->camelCasedProperties = $camelCasedProperties;
    }

    /**
     * @throws \Exception
     */
    public function writeToModel()
    {
        $filename = $this->reflectionClass->getFileName();

        if (is_file($filename)) {
            $contents = file_get_contents($filename);
        } else {
            throw new \Exception("File does not exist at path {$filename}");
        }

        if (!$this->phpDoc->getTagsByName('mixin')) {
            $this->phpDoc->appendTag(
                Tag::createInstance("@mixin \\Eloquent", $this->phpDoc)
            );
        }

        $docComment = $this->serialize();
        $originalDoc = $this->reflectionClass->getDocComment();
        $classname = $this->reflectionClass->getShortName();

        if ($originalDoc) {
            $contents = str_replace($originalDoc, $docComment, $contents);
        } else {
            $needle = "class {$classname}";
            $replace = "{$docComment}\nclass {$classname}";
            $pos = strpos($contents, $needle);
            if ($pos !== false) {
                $contents = substr_replace($contents, $replace, $pos, strlen($needle));
            }
        }

        if (file_put_contents($filename, $contents, 0) === false) {
            throw new \Exception("Could not write phpDocBlock to {$filename}");
        }
    }

    /**
     * @return string
     */
    public function serialize()
    {
        $serializer = new DocBlockSerializer();

        return $serializer->getDocComment($this->phpDoc);
    }

    /**
     * @param bool $parseSchema
     * @throws \Doctrine\DBAL\DBALException
     */
    public function generate($parseSchema = false)
    {
        $this->properties = [];
        $this->methods = [];

        /** @var Model $model */
        $model = $this->reflectionClass->newInstanceWithoutConstructor();

        if ($parseSchema) {
            $this->getPropertiesFromTable($model);
        }

        if (method_exists($model, 'getCasts')) {
            $this->castPropertiesType($model);
        }

        $this->getPropertiesFromMethods($model);

        $this->phpDoc = $this->getDocBlock();
    }

    /**
     * Load the properties from the database table.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getPropertiesFromTable(Model $model)
    {
        $table = $model->getConnection()->getTablePrefix() . $model->getTable();
        $schema = $model->getConnection()->getDoctrineSchemaManager();
        $databasePlatform = $schema->getDatabasePlatform();
        $databasePlatform->registerDoctrineTypeMapping('enum', 'string');

        $platformName = $databasePlatform->getName();

        $customTypes = isset($this->customDbTypes[$platformName])
            ? $this->customDbTypes[$platformName]
            : [];

        foreach ($customTypes as $yourTypeName => $doctrineTypeName) {
            $databasePlatform->registerDoctrineTypeMapping($yourTypeName, $doctrineTypeName);
        }

        $database = null;
        if (strpos($table, '.')) {
            list($database, $table) = explode('.', $table);
        }

        $columns = $schema->listTableColumns($table, $database);

        if ($columns) {
            foreach ($columns as $column) {
                $name = $column->getName();
                if (in_array($name, $model->getDates())) {
                    $type = '\Carbon\Carbon';
                } else {
                    $type = $column->getType()->getName();
                    switch ($type) {
                        case 'string':
                        case 'text':
                        case 'date':
                        case 'time':
                        case 'guid':
                        case 'datetimetz':
                        case 'datetime':
                            $type = 'string';
                            break;
                        case 'integer':
                        case 'bigint':
                        case 'smallint':
                            $type = 'integer';
                            break;
                        case 'decimal':
                        case 'float':
                            $type = 'float';
                            break;
                        case 'boolean':
                            $type = 'boolean';
                            break;
                        default:
                            $type = 'mixed';
                            break;
                    }
                }

                $comment = $column->getComment();
                $this->setProperty($name, $type, true, true, $comment);
                $this->setMethod(
                    Str::camel("where_" . $name),
                    '\Illuminate\Database\Query\Builder|\\' . get_class($model),
                    array('$value')
                );
            }
        }
    }

    /**
     * Cast the properties's type from $casts.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function castPropertiesType($model)
    {
        $casts = $model->getCasts();
        foreach ($casts as $name => $type) {
            switch ($type) {
                case 'boolean':
                case 'bool':
                    $realType = 'boolean';
                    break;
                case 'string':
                    $realType = 'string';
                    break;
                case 'array':
                case 'json':
                    $realType = 'array';
                    break;
                case 'object':
                    $realType = 'object';
                    break;
                case 'int':
                case 'integer':
                case 'timestamp':
                    $realType = 'integer';
                    break;
                case 'real':
                case 'double':
                case 'float':
                    $realType = 'float';
                    break;
                case 'date':
                case 'datetime':
                    $realType = '\Carbon\Carbon';
                    break;
                case 'collection':
                    $realType = '\Illuminate\Support\Collection';
                    break;
                default:
                    $realType = 'mixed';
                    break;
            }

            if (!isset($this->properties[$name])) {
                continue;
            } else {
                $this->properties[$name]['type'] = $this->getTypeOverride($realType);
            }
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function getPropertiesFromMethods($model)
    {
        foreach ($this->reflectionClass->getMethods() as $reflection) {
            $method = $reflection->getName();

            if (Str::startsWith($method, 'get') && Str::endsWith(
                    $method,
                    'Attribute'
                ) && $method !== 'getAttribute'
            ) {
                //Magic get<name>Attribute
                $name = Str::snake(substr($method, 3, -9));
                if (!empty($name)) {
                    $type = $this->getReturnTypeFromDocBlock($reflection);
                    $this->setProperty($name, $type, true, null);
                }
            } elseif (Str::startsWith($method, 'set') && Str::endsWith(
                    $method,
                    'Attribute'
                ) && $method !== 'setAttribute'
            ) {
                //Magic set<name>Attribute
                $name = Str::snake(substr($method, 3, -9));
                if (!empty($name)) {
                    $this->setProperty($name, null, null, true);
                }
            } elseif (Str::startsWith($method, 'scope') && $method !== 'scopeQuery') {
                //Magic set<name>Attribute
                $name = Str::camel(substr($method, 5));
                if (!empty($name)) {
                    $args = $this->getParameters($reflection);
                    //Remove the first ($query) argument
                    array_shift($args);
                    $this->setMethod($name, '\Illuminate\Database\Query\Builder|\\' . $reflection->class, $args);
                }
            } elseif (!method_exists('Illuminate\Database\Eloquent\Model', $method)
                && !Str::startsWith($method, 'get')
            ) {
                //Use reflection to inspect the code, based on Illuminate/Support/SerializableClosure.php
                $file = new \SplFileObject($reflection->getFileName());
                $file->seek($reflection->getStartLine() - 1);

                $code = '';
                while ($file->key() < $reflection->getEndLine()) {
                    $code .= $file->current();
                    $file->next();
                }
                $code = trim(preg_replace('/\s\s+/', '', $code));
                $begin = strpos($code, 'function(');
                $code = substr($code, $begin, strrpos($code, '}') - $begin + 1);

                foreach (array(
                             'hasMany',
                             'hasManyThrough',
                             'belongsToMany',
                             'hasOne',
                             'belongsTo',
                             'morphOne',
                             'morphTo',
                             'morphMany',
                             'morphToMany'
                         ) as $relation) {
                    $search = '$this->' . $relation . '(';
                    if ($pos = stripos($code, $search)) {
                        //Resolve the relation's model to a Relation object.
                        $relationObj = $model->$method();

                        if ($relationObj instanceof Relation) {
                            $relatedModel = '\\' . get_class($relationObj->getRelated());

                            $relations = ['hasManyThrough', 'belongsToMany', 'hasMany', 'morphMany', 'morphToMany'];
                            if (in_array($relation, $relations)) {
                                //Collection or array of models (because Collection is Arrayable)
                                $this->setProperty(
                                    $method,
                                    $this->getCollectionClass($relatedModel) . '|' . $relatedModel . '[]',
                                    true,
                                    null
                                );
                            } elseif ($relation === "morphTo") {
                                // Model isn't specified because relation is polymorphic
                                $this->setProperty(
                                    $method,
                                    '\Illuminate\Database\Eloquent\Model|\Eloquent',
                                    true,
                                    null
                                );
                            } else {
                                //Single model is returned
                                $this->setProperty($method, $relatedModel, true, null);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the parameters and format them correctly
     *
     * @param \ReflectionMethod $method
     * @return array
     */
    public function getParameters(\ReflectionMethod $method)
    {
        //Loop through the default values for paremeters, and make the correct output string
        $params = array();
        $paramsWithDefault = array();
        /** @var \ReflectionParameter $param */
        foreach ($method->getParameters() as $param) {
            $paramClass = $param->getClass();
            $paramStr = (!is_null($paramClass) ? '\\' . $paramClass->getName() . ' ' : '') . '$' . $param->getName();
            $params[] = $paramStr;
            if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                $default = $param->getDefaultValue();
                if (is_bool($default)) {
                    $default = $default ? 'true' : 'false';
                } elseif (is_array($default)) {
                    $default = 'array()';
                } elseif (is_null($default)) {
                    $default = 'null';
                } elseif (is_int($default)) {
                    //$default = $default;
                } else {
                    $default = "'" . trim($default) . "'";
                }
                $paramStr .= " = $default";
            }
            $paramsWithDefault[] = $paramStr;
        }
        return $paramsWithDefault;
    }

    /**
     * Get method return type based on it DocBlock comment
     *
     * @param \ReflectionMethod $reflection
     *
     * @return null|string
     */
    protected function getReturnTypeFromDocBlock(\ReflectionMethod $reflection)
    {
        $type = null;
        $phpdoc = new DocBlock($reflection);

        if ($phpdoc->hasTag('return')) {
            $type = $phpdoc->getTagsByName('return')[0]->getContent();
        }

        return $type;
    }

    /**
     * Determine a model classes' collection type.
     *
     * @see http://laravel.com/docs/eloquent-collections#custom-collections
     * @param string $className
     * @return string
     */
    private function getCollectionClass($className)
    {
        // Return something in the very very unlikely scenario the model doesn't
        // have a newCollection() method.
        if (!method_exists($className, 'newCollection')) {
            return '\Illuminate\Database\Eloquent\Collection';
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $className;
        return '\\' . get_class($model->newCollection());
    }

    /**
     * Returns the overide type for the give type.
     *
     * @param string $type
     * @return string
     */
    protected function getTypeOverride($type)
    {
        return isset($this->typeOverrides[$type])
            ? $this->typeOverrides[$type]
            : $type;
    }

    /**
     * @param string $name
     * @param string|null $type
     * @param bool|null $read
     * @param bool|null $write
     * @param string|null $comment
     */
    protected function setProperty($name, $type = null, $read = null, $write = null, $comment = '')
    {
        if (!isset($this->properties[$name])) {
            $this->properties[$name] = array();
            $this->properties[$name]['type'] = 'mixed';
            $this->properties[$name]['read'] = false;
            $this->properties[$name]['write'] = false;
            $this->properties[$name]['comment'] = (string) $comment;
        }
        if ($type !== null) {
            $this->properties[$name]['type'] = $this->getTypeOverride($type);
        }
        if ($read !== null) {
            $this->properties[$name]['read'] = $read;
        }
        if ($write !== null) {
            $this->properties[$name]['write'] = $write;
        }
    }

    /**
     * @param $name
     * @param string $type
     * @param array $arguments
     */
    protected function setMethod($name, $type = '', $arguments = array())
    {
        $methods = array_change_key_case($this->methods, CASE_LOWER);

        if (!isset($methods[strtolower($name)])) {
            $this->methods[$name] = array();
            $this->methods[$name]['type'] = $type;
            $this->methods[$name]['arguments'] = $arguments;
        }
    }

    /**
     * @return DocBlock
     */
    protected function getDocBlock()
    {
        $phpDoc = new DocBlock(
            $this->reset ? '' : $this->reflectionClass,
            new Context($this->reflectionClass->getNamespaceName())
        );

        if (!$phpDoc->getText()) {
            $phpDoc->setText($this->reflectionClass->getName());
        }

        // Override computed doc blocks if an existing doc block is present in the model.
        $propertyOverrides = [];
        $methodOverrides = [];

        foreach ($phpDoc->getTags() as $tag) {
            $name = $tag->getName();
            if ($name == "property" || $name == "property-read" || $name == "property-write") {
                $propertyOverrides[] = $tag->getVariableName();
            } elseif ($name == "method") {
                $methodOverrides[] = $tag->getMethodName();
            }
        }

        // Append computed property tags to doc block.
        $this->appendPropertyTags($phpDoc, $propertyOverrides);

        // Append computed method tags to doc block.
        $this->appendMethodTags($phpDoc, $methodOverrides);

        return $phpDoc;
    }

    protected function appendPropertyTags(DocBlock $phpDoc, array $overrides)
    {
        foreach ($this->properties as $name => $property) {
            $name = "\$$name";
            if (in_array($name, $overrides)) {
                continue;
            }
            if ($property['read'] && $property['write']) {
                $attr = 'property';
            } elseif ($property['write']) {
                $attr = 'property-write';
            } else {
                $attr = 'property-read';
            }

            if ($this->camelCasedProperties) {
                $name = Str::camel($name);
            }

            $tagLine = trim("@{$attr} {$property['type']} {$name} {$property['comment']}");
            $tag = Tag::createInstance($tagLine, $phpDoc);
            $phpDoc->appendTag($tag);
        }
    }

    /**
     * @param $phpDoc
     * @param array $overrides
     */
    protected function appendMethodTags(DocBlock $phpDoc, array $overrides)
    {
        foreach ($this->methods as $name => $method) {
            if (in_array($name, $overrides)) {
                continue;
            }
            $arguments = implode(', ', $method['arguments']);
            $tag = Tag::createInstance("@method static {$method['type']} {$name}({$arguments})", $phpDoc);
            $phpDoc->appendTag($tag);
        }
    }
}