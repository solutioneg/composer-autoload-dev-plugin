<?php

namespace Solutioneg\AutoloadDevPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class AutoloadDevPlugin implements PluginInterface, EventSubscriberInterface
{
    private $composer;
    private $io;

    const DIR = 'modules-dir';

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallOrUpdate',
            ScriptEvents::POST_UPDATE_CMD => 'onPostInstallOrUpdate'
        ];
    }

    public function onPostInstallOrUpdate(Event $event)
    {
        $noDev = $event->isDevMode() === false;

        // Load the root composer.json
        $rootPackage = $this->composer->getPackage();
        $extra = $rootPackage->getExtra();
        if (!isset($extra[self::DIR])) {
            $this->io->writeError('No "dev-dependencies-dir" defined in root composer.json');
            return;
        }

        $devDependenciesDir = $extra[self::DIR];
        if (!is_dir($devDependenciesDir)) {
            $this->io->writeError('The specified "dev-dependencies-dir" does not exist.');
            return;
        }

        // Read composer.json files in the specified directory
        $packageDirs = array_diff(scandir($devDependenciesDir), ['..', '.']);
        $autoloadDev = [];

        foreach ($packageDirs as $packageDir) {
            $packagePath = $devDependenciesDir . '/' . $packageDir . '/composer.json';
            if (file_exists($packagePath)) {
                $packageComposerData = json_decode(file_get_contents($packagePath), true);

                if (isset($packageComposerData['autoload-dev']) && !$noDev) {
                    foreach ($packageComposerData['autoload-dev'] as $type => $paths) {
                        if (!isset($autoloadDev[$type])) {
                            $autoloadDev[$type] = [];
                        }
                        foreach ($paths as $namespace => $path) {
                            $autoloadDev[$type][$namespace] = $devDependenciesDir . '/' . $packageDir . '/' . $path;
                        }
                    }
                }
            }
        }

        // Update the root composer.json autoload-dev
        $rootComposerFile = 'composer.json';
        $rootComposerData = json_decode(file_get_contents($rootComposerFile), true);
        if (!isset($rootComposerData['autoload-dev'])) {
            $rootComposerData['autoload-dev'] = [];
        }

        foreach ($autoloadDev as $type => $paths) {
            if (!isset($rootComposerData['autoload-dev'][$type])) {
                $rootComposerData['autoload-dev'][$type] = [];
            }
            foreach ($paths as $namespace => $path) {
                $rootComposerData['autoload-dev'][$type][$namespace] = $path;
            }
        }

        file_put_contents($rootComposerFile, json_encode($rootComposerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->io->write('Autoload-dev paths from dev dependencies mapped successfully.');
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }
}
