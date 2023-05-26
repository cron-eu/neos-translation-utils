<?php

namespace CRON\NeosTranslationUtils\Service;


use CRON\NeosTranslationUtils\Service\Model\XliffTranslation;
use CRON\NeosTranslationUtils\Service\Model\TransUnit;
use CRON\NeosTranslationUtils\Utils\FileUtils;
use /** @noinspection PhpUnusedAliasInspection */
    Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\ConsoleOutput;
use Neos\FluidAdaptor\View\StandaloneView;
use \SimpleXMLElement;

/**
 * Class XliffTranslationService
 *
 * @Flow\Scope("singleton")
 */
class XliffTranslationService
{
    /**
     * @Flow\Inject
     * @var FileUtils
     */
    protected $fileUtils;

    /**
     * @Flow\InjectConfiguration(path="translations.fileExtension")
     * @var string
     */
    protected $translationFileExtension;

    /**
     * @Flow\InjectConfiguration(path="translations.path")
     * @var string
     */
    protected $translationsPath;

    /**
     * @Flow\InjectConfiguration(path="translations.templateFile")
     * @var string
     */
    protected $translationsTemplateFile;

    /**
     * @Flow\Inject
     * @var NodeTypeService
     */
    protected $nodeTypeService;

    /**
     * @Flow\Inject
     * @var ConsoleOutput
     */
    protected $output;

    /**
     * This method generates an XLIFF translation file representation.
     * If an XLIFF file already exists at the given file path, the instance will be populated with the <trans-unit>'s of that file.
     * In any case this method returns an instance of XliffTranslation with all trans-units from the given translation IDs.
     *
     * @param string $basePath
     * @param string $packageKey
     * @param string $sourceLanguage
     * @param string|null $targetLanguage
     * @param array $pathParts
     * @param array $translationIds
     * @param array $updatedTranslations
     *
     * @return XliffTranslation
     */
    protected function generateXliffTranslation($basePath, $packageKey, $sourceLanguage, $targetLanguage, $pathParts, $translationIds, &$updatedTranslations)
    {
        $productName = $packageKey;
        $transUnits = [];

        // map for the trans-units that were able to be parsed from the file
        $fileTransUnits = [];

        $locale = $targetLanguage ?: $sourceLanguage;

        $xliffFilePath = $this->buildXliffTranslationFilePath($basePath, $locale, $pathParts);
        $localeFilePath = sprintf('%s/%s', $locale, implode('/', $pathParts));

        $fileContents = @file_get_contents($xliffFilePath);

        if ($fileContents) {
            // without replacing the xmlns attribute no nodes will be found using xpath
            $fileContents = str_replace('xmlns=', 'ns=', $fileContents);

            try {
                $xmlEl = new SimpleXMLElement($fileContents);

                $bodyEls = $xmlEl->xpath('/xliff/file/body');
                if (count($bodyEls) > 0) {
                    $bodyNode = $bodyEls[0];

                    foreach ($translationIds as $translationId) {
                        $transUnitEls = $bodyNode->xpath('trans-unit[@id="' . $translationId . '"]');
                        $source = false;
                        $target = false;

                        if (count($transUnitEls) > 0) {
                            $transUnitNode = $transUnitEls[0];
                            $sourceNode = $transUnitNode->xpath('source');
                            $targetNode = $transUnitNode->xpath('target');
                            if ((count($sourceNode) > 0)) {
                                $source = strval($sourceNode[0]);
                            }
                            if ((count($targetNode) > 0)) {
                                $target = strval($targetNode[0]);
                            }
                        }

                        $fileTransUnits[$translationId] = [
                            'source' => $source,
                            'target' => $target
                        ];
                    }
                } else {
                    // <body> node doesn't exist!
                    $this->output->outputLine(sprintf('ERROR: XLIFF file \'%s\' is missing a body tag.', $xliffFilePath));
                }
            } catch (\Exception $e) {
                // error parsing the XLIFf file - show a warning but continue
                $this->output->outputLine(sprintf('ERROR: Error parsing XLIFF file \'%s\', continuing without parsed values.', $xliffFilePath));
            }
        }

        foreach ($translationIds as $translationId) {
            $transUnit = new TransUnit($translationId, '', '');
            $fileTransUnit = null;
            if (isset($fileTransUnits[$translationId])) {
                $fileTransUnit = $fileTransUnits[$translationId];
            }

            if ($fileTransUnit && isset($fileTransUnit['source']) && $fileTransUnit['source'] !== false) {
                $transUnit->setSource($fileTransUnit['source']);
            } else {
                // set a placeholder that identifies the file and translation
                $transUnit->setSource(sprintf('#%s:%s', $localeFilePath, $translationId));

                $updatedTranslations[] = sprintf('%s:%s', $xliffFilePath, $translationId);
            }

            if ($fileTransUnit && isset($fileTransUnit['target']) && $fileTransUnit['target'] !== false) {
                $transUnit->setSource($fileTransUnit['target']);
            } else if ($targetLanguage != null) {
                // set a placeholder that identifies the file and translation
                $transUnit->setSource(sprintf('#%s:%s', $localeFilePath, $translationId));

                $updatedTranslations[] = sprintf('%s:%s', $xliffFilePath, $translationId);
            }

            $transUnits[] = $transUnit;
        }

        return new XliffTranslation($productName, $sourceLanguage, $targetLanguage, $transUnits);
    }

    /**
     * Write an XLIFF file from the given XliffTranslation instance.
     * The arguments $basePath, $locale and $pathParts are used to create the directory and file name.
     *
     * @param string $basePath
     * @param string $locale
     * @param array $pathParts
     * @param XliffTranslation $xliffTranslation
     *
     * @return boolean True if the XLIFF file was successfully written, false otherwise.
     */
    protected function writeXliffTranslationFile($basePath, $locale, $pathParts, $xliffTranslation)
    {
        $directoryPath = $this->buildXliffTranslationFileDirectoryPath($basePath, $locale, $pathParts);

        if (!$this->fileUtils->createDirectoryIfNotExists($directoryPath)) {
            // failed to create XLIFF translation file directory
            $this->output->outputLine(sprintf('ERROR: Failed to create directory \'%s\'.', $directoryPath));
            return false;
        }

        $filePath = $this->buildXliffTranslationFilePath($basePath, $locale, $pathParts);

        if (!$this->fileUtils->createFileIfNotExists($filePath)) {
            // failed to create XLIFF translation file
            $this->output->outputLine(sprintf('ERROR: Failed to create XLIFF file \'%s\'.', $filePath));
            return false;
        }

        $contextVariables = [];
        $contextVariables['xliffTranslation'] = $xliffTranslation;
        $templatePathAndFilename = $this->translationsTemplateFile;
        $fileContent = $this->renderTemplate($templatePathAndFilename, $contextVariables);

        $writeSuccess = $this->fileUtils->writeFile($filePath, $fileContent);

        if (!$writeSuccess) {
            $this->output->outputLine(sprintf('ERROR: Failed to write XLIFF file \'%s\'.', $filePath));
            return false;
        }

        //$this->output->outputLine(sprintf('INFO: Updated XLIFF file \'%s\'.', $filePath));
        return true;
    }

    /**
     * Render the given template file with the given variables
     *
     * Taken from the Neos.Kickstarterer package.
     *
     * @param string $templatePathAndFilename
     * @param array $contextVariables
     * @return string
     */
    protected function renderTemplate($templatePathAndFilename, $contextVariables)
    {
        $standaloneView = new StandaloneView();
        $standaloneView->setTemplatePathAndFilename($templatePathAndFilename);
        $standaloneView->assignMultiple($contextVariables);
        return $standaloneView->render();
    }

    /**
     * Build the path of the XLIFF translation file from the parameters.
     * The last entry in $pathParts represents the file name.
     *
     * @param string $basePath
     * @param string $locale
     * @param array $pathParts
     * @return string
     */
    protected function buildXliffTranslationFilePath($basePath, $locale, $pathParts)
    {
        return implode('/', [rtrim($basePath, '/'), trim($this->translationsPath, '/'), $locale, 'NodeTypes', implode('/', $pathParts)]) . '.' . $this->translationFileExtension;
    }

    /**
     * Build the directory path of the XLIFF translation file from the parameters.
     * The last entry in $pathParts would represent the file name but is not used for the directory.
     *
     * @param string $basePath
     * @param string $locale
     * @param array $pathParts
     * @return string
     */
    protected function buildXliffTranslationFileDirectoryPath($basePath, $locale, $pathParts)
    {
        $directoryPathParts = array_slice($pathParts, 0, count($pathParts) - 1);

        return implode('/', [rtrim($basePath, '/'), trim($this->translationsPath, '/'), $locale, implode('/', $directoryPathParts)]);
    }

    /**
     * @param array $updatedTranslations
     */
    protected function logUpdatedTranslations($updatedTranslations)
    {
        if (count($updatedTranslations) > 0) {
            foreach ($updatedTranslations as $updatedTranslation) {
                $this->output->outputLine(sprintf('Updated: %s', $updatedTranslation));
            }
        }
    }

    /**
     * Update or create the XLIFF translation files for the given source language and target language (optional)
     * using the NodeType files in the given package.
     * The source translations will be used in the source-tags in the target language XLIFF file.
     *
     * @param string $packageKey
     * @param string $packagePath
     * @param string $sourceLanguage
     * @param string|null $targetLanguage
     * @return int the number of updated translations
     */
    public function updateXliffTranslationFiles($packageKey, $packagePath, $sourceLanguage, $targetLanguage = null)
    {
        $nodeTypes = $this->nodeTypeService->getNodeTypes($packagePath);
        $numUpdatedTranslations = 0;

        foreach ($nodeTypes as $nodeType) {
            $updatedTranslations = [];
            $sourceXliffTranslation = $this->generateXliffTranslation($packagePath, $packageKey, $sourceLanguage, null, $nodeType->getFileNameParts(), $nodeType->getTranslationIds(), $updatedTranslations);

            $this->writeXliffTranslationFile($packagePath, $sourceLanguage, $nodeType->getFileNameParts(), $sourceXliffTranslation);
            $this->logUpdatedTranslations($updatedTranslations);
            $numUpdatedTranslations += count($updatedTranslations);

            if ($targetLanguage) {
                $updatedTranslations = [];
                $targetXliffTranslation = $this->generateXliffTranslation($packagePath, $packageKey, $sourceLanguage, $targetLanguage, $nodeType->getFileNameParts(), $nodeType->getTranslationIds(), $updatedTranslations);

                $targetXliffTranslation->updateTransUnitsFromSourceXliff($sourceXliffTranslation);

                $this->writeXliffTranslationFile($packagePath, $targetLanguage, $nodeType->getFileNameParts(), $targetXliffTranslation);
                $this->logUpdatedTranslations($updatedTranslations);
                $numUpdatedTranslations += count($updatedTranslations);
            }
        }

        return $numUpdatedTranslations;
    }
}
