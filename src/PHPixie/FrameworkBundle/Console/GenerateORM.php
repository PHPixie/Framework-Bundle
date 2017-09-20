<?php

/*
 * Copyright (C) 2017 sobolevna
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace PHPixie\FrameworkBundle\Console;

use PHPixie\Console\Command\Config;
use \PHPixie\Console\Exception\CommandException;

/**
 * Description of GenerateORM
 *
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
            $this->writeLine('Preparing to make');
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
        $this->writeLine("Generating model $name");
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
            $this->writeLine("Preparing wrapper $wrapper");
            $this->makeClassFile($bundle, $model, $wrapper);
        }
    }

    protected function makeClassFile($bundle, $model, $wrapper) {
        $this->writeLine("Generating class \Project\\$bundle\\$model\\$wrapper");
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
    
    protected function registerModel($name, $type, $connection) {
        $this->writeLine("Registering model $name");
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
    
    protected function prettifyText($txt) {
        return str_replace('(,', "(", preg_replace('/(\n|\s|\t)*,/u', ',', $txt));
    }

    protected function getTemplateDirectory() {
        return __DIR__ . '/../../../../assets/ormTemplate/';
    }

}
