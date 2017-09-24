<?php

namespace PHPixie\FrameworkBundle;

class Console extends \PHPixie\Console\Registry\Provider\Implementation
{
    
    protected $frameworkBuilder;
    
    public function __construct($frameworkBuilder)
    {
        $this->frameworkBuilder = $frameworkBuilder;
    }
    
    public function commandNames()
    {
        return array('installWebAssets', 'generateBundle', 'database', 'migrate', 'seed', 'generateORM');
    }
    
    protected function buildInstallWebAssetsCommand($commandConfig)
    {
        return new Console\InstallWebAssets(
            $commandConfig,
            $this->frameworkBuilder->assets(),
            $this->frameworkBuilder->components()->bundles(),
            $this->frameworkBuilder->components()->filesystem()
        );
    }
    
    protected function buildGenerateBundleCommand($commandConfig)
    {
        return new Console\GenerateBundle(
            $commandConfig,
            $this->frameworkBuilder
        );
    }
    
    protected function buildDatabaseCommand($commandConfig)
    {
        $migrate = $this->frameworkBuilder->components()->migrate();
        return $migrate->consoleCommands()->buildCommand('database', $commandConfig);
    }
    
    protected function buildMigrateCommand($commandConfig)
    {
        $migrate = $this->frameworkBuilder->components()->migrate();
        return $migrate->consoleCommands()->buildCommand('migrate', $commandConfig);
    }
    
    protected function buildSeedCommand($commandConfig)
    {
        $migrate = $this->frameworkBuilder->components()->migrate();
        return $migrate->consoleCommands()->buildCommand('seed', $commandConfig);
    }
    
    protected function buildGenerateORMCommand($commandConfig)
    {
        return new Console\GenerateORM(
            $commandConfig,
            $this->frameworkBuilder
        );
    }
}