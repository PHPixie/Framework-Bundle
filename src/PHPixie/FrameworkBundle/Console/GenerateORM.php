<?php

namespace PHPixie\FrameworkBundle\Console;

use PHPixie\Console\Command\Config;
use \PHPixie\Console\Exception\CommandException;

/**
 * Description of GenerateORM
 * Console command to generate and register ORM  classes 
 * @author sobolevna
 */
class GenerateORM extends \PHPixie\Console\Command\Implementation {

    /**
     *
     * @var boolean
     */
    protected $overwrite;

    /**
     *
     * @var string
     */
    protected $bundle;

    /**
     *
     * @var \Project\Framework\Builder
     */
    protected $frameworkBuilder;

    /**
     *
     * @var \PHPixie\DefaultBundle\Builder 
     */
    protected $builder;

    /**
     * 
     * @param Config $config
     * @param \Project\Framework\Builder $frameworkBuilder
     */
    public function __construct($config, $frameworkBuilder) {
        $this->frameworkBuilder = $frameworkBuilder;

        $config->description('Generate new ORM files and settings.');
        $config->argument('name')->description('Your model name with bundle prefix (e.g. app:customers). With -a flag this argument is regarded as bundle name.');
        $config->argument('type')->description('Either "database"(default) or "embedded".');
        $config->argument('connection')->description('Connection name (if not mentioned, default value is used.');
        $config->option('a')->flag()->description('Mark this if you want to generate and register all models from your ORM config (the only other argument is to specify bundle name).');
        $config->option('o')->flag()->description('Overwrite existing files.');

        parent::__construct($config);
    }

    /**
     * 
     * @param \PHPixie\Slice\Date $argumentData
     * @param \PHPixie\Slice\Date $optionData
     * @throws CommandException;
     */
    public function run($argumentData, $optionData) {
        $this->overwrite = $optionData->get('o');
        $name = explode(':', $argumentData->get('name'));
        $this->bundle = $name[0];
        $this->builder = $this->frameworkBuilder->bundles()->get($this->bundle)->builder();
        if ((empty($name[1]) && !$optionData->get('a')) || (!empty($name[1]) && $optionData->get('a'))) {
            throw new CommandException('You should either set a model name or raise an "-a" flag.');
        } elseif (!empty($name[1])) {
            $this->make($name[1], $argumentData->get('type'), $argumentData->get('connection'));
        } elseif ($optionData->get('a')) {
            $models = $this->builder->ormConfig()->getData('models');
            foreach ($models as $key => $value) {
                $this->make($key, $value['type'], $value['connection']);
            }
        } else {
            throw new CommandException('Something went wrong');
        }
        $this->writeLine('Task completed.');
    }

    /**
     * 
     * @param string $name
     * @param string $type
     * @param string $connection
     * @throws CommandException
     */
    protected function make($name, $type = 'database', $connection = 'default') {
        if (!$name) {
            throw new CommandException('Invalid model name.');
        }
        if (!$this->builder->ormConfig()->get('models.' . strtolower($name))) {
            $this->registerModel($name, $type, $connection);
            $this->writeLine("Model '$name' has been registerd.");
        }
        $this->generateClasses(ucfirst($name));
        $this->writeLine('All ORM classes have been generated.');
        $this->writeLine();
        $this->registerClasses($name);
        $this->writeLine('ORM classes have been registered.');
        $this->writeLine();
    }

    /**
     * 
     * @param string $name
     */
    protected function generateClasses($name) {
        $model = ucfirst($name);
        $bundle = ucfirst($this->bundle);

        $actions = $this->builder->components()->filesystem()->actions();
        $destination = $this->builder->filesystemRoot()->path('src/ORM/' . $model);

        if (is_dir($destination) && $this->overwrite) {
            $actions->remove($destination);
            $this->writeLine("Directory '$destination' removed.");
        }
        elseif (is_dir($destination) && !$this->overwrite) {
            throw new CommandException("Directory '$destination' already exisits.");
        }
        $actions->createDirectory($destination);

        foreach (array('Repository', 'Entity', 'Query') as $wrapper) {
            $this->makeClassFile($bundle, $model, $wrapper);
        }
    }

    /**
     * 
     * @param string $bundle
     * @param string $model
     * @param string $wrapper
     * @throws CommandException
     */
    protected function makeClassFile($bundle, $model, $wrapper) {
        $src = $this->getTemplateDirectory() . "{$wrapper}.php";
        if (!file_exists($src)) {
            throw new CommandException("A template for {$wrapper} doesn't exists.");
        }
        $dst = $this->builder->filesystemRoot()->path("src/ORM/$model/{$wrapper}.php");

        if (file_exists($dst) && !$this->overwrite) {
            throw new CommandException("Class \Project\\$bundle\\$model\\$wrapper already exisits.");
        }

        $txt = str_replace('BUNDLE', $bundle, str_replace('NS', $model, file_get_contents($src)));
        file_put_contents($dst, $txt);
        $this->writeLine("Class '$wrapper' for model '$model' has been generated");
    }

    /**
     * 
     * @param string $name
     */
    protected function registerClasses($name) {
        $className = ucfirst($name);
        $modelName = lcfirst($name);
        $pathORM = $this->builder->filesystemRoot()->path('src/ORM.php');
        $bundle = ucfirst($this->bundle);

        $ns = "\Project\\$bundle\ORM\\" . $className;
        $txt = file_get_contents($pathORM);

        if (!strpos($txt, "$ns\Entity")) {
            $txt = str_replace("/*entityGeneratorPlaceholder*/", ",\n\t\t'$modelName' => '$ns\Entity'\n\t\t/*entityGeneratorPlaceholder*/", $txt);
        }
        if (!strpos($txt, "$ns\Repository")) {
            $txt = str_replace("/*repositoryGeneratorPlaceholder*/", ",\n\t\t'$modelName' => '$ns\Repository'\n\t\t/*repositoryGeneratorPlaceholder*/", $txt);
        }
        if (!strpos($txt, "$ns\Query")) {
            $txt = str_replace("/*queryGeneratorPlaceholder*/", ",\n\t\t'$modelName' => '$ns\Query'\n\t\t/*repositoryGeneratorPlaceholder*/", $txt);
        }

        
        file_put_contents($pathORM, $this->prettifyText($txt));
    }
    
    /**
     * 
     * @param string $name
     * @param string $type
     * @param string $connection
     */
    protected function registerModel($name, $type, $connection) {
        $ormPath = $this->builder->assetsRoot()->path('config').'/orm.php';
        $this->writeLine($ormPath);
        if (!file_exists($ormPath)) {
            copy($this->getTemplateDirectory().'../bundleTemplate/assets/config/orm.php', $ormPath);
        }
        $txt = file_get_contents($ormPath);
        if (!strpos($txt, "'$name'") && !strpos($txt, "\"$name\"")) {
            $replace = ",
        '$name' => array(
            'type' => '$type',
            'connection' => '$connection',
            'id' => 'id'    
        )
        /*modelGeneratorPlaceholder*/";
            $txt = str_replace("/*modelGeneratorPlaceholder*/", $replace, $txt);
            file_put_contents($ormPath, $this->prettifyText($txt));
        }
    }
    
    /**
     * 
     * @param string $txt
     * @return string
     */
    protected function prettifyText($txt) {
        return str_replace('(,', "(", preg_replace('/(\n|\s|\t)*,/u', ',', $txt));
    }

    /**
     * 
     * @return string
     */
    protected function getTemplateDirectory() {
        return __DIR__ . '/../../../../assets/ormTemplate/';
    }

}
