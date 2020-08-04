<?php

namespace CRON\NeosTranslationUtils\Command;

use CRON\NeosTranslationUtils\Service\XliffTranslationService;
use CRON\NeosTranslationUtils\Utils\FileUtils;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Package\Exception\UnknownPackageException;
use Neos\Flow\Package\PackageManager;
use \Neos\Flow\Cli\CommandController;

class TranslationUtilsCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @var XliffTranslationService
     */
    protected $translationService;

    /**
     * @Flow\Inject
     * @var FileUtils
     */
    protected $fileUtils;

    /**
     * Return the path of the package for the given package key.
     * This method also checks if the package directory exists.
     *
     * @param string $packageKey
     * @return string|null
     * @throws UnknownPackageException
     */
    protected function getPackageDirectoryPath($packageKey)
    {
        if (!$this->packageManager->isPackageKeyValid($packageKey)) {
            $this->outputLine('Package key not valid!');
            return null;
        }

        if (!$this->packageManager->isPackageAvailable($packageKey)) {
            $this->outputLine('Package is not available!');
            return null;
        }

        $package = $this->packageManager->getPackage($packageKey);
        $packageDirectoryPath = $package->getPackagePath();

        if (!is_dir($packageDirectoryPath)) {
            $this->outputLine('Package path is not a directory!');
            return null;
        }

        return $packageDirectoryPath;
    }

    /**
     * This command updates the translations for the given source and target language from the NodeType files
     * in the given package. The NodeType files will be scanned for the magic value 'i18n'.
     * The source translations will be used in the source-tags in the target language XLIFF file.
     * Missing translations will be labeled "#<package>:<translation-id>".
     *
     * @param string $packageKey
     * @param string $sourceLanguage
     * @param string $targetLanguage
     *
     * @throws UnknownPackageException
     */
    public function updateCommand($packageKey, $sourceLanguage, $targetLanguage)
    {
        $packageDirectoryPath = $this->getPackageDirectoryPath($packageKey);

        if (!$packageDirectoryPath) {
            return;
        }

        $this->translationService->updateXliffTranslationFiles($packageKey, $packageDirectoryPath, $sourceLanguage, $targetLanguage);
    }

    /**
     * This command updates the translations for the given source language from the NodeType files
     * in the given package. The NodeType files will be scanned for the magic value 'i18n'.
     * Missing translations will be labeled "#<package>:<translation-id>".
     *
     * @param string $packageKey
     * @param string $sourceLanguage
     *
     * @throws UnknownPackageException
     */
    public function updateSourceCommand($packageKey, $sourceLanguage)
    {
        $packageDirectoryPath = $this->getPackageDirectoryPath($packageKey);

        if (!$packageDirectoryPath) {
            return;
        }

        $this->translationService->updateXliffTranslationFiles($packageKey, $packageDirectoryPath, $sourceLanguage, null);
    }
}
