<?php

namespace CRON\NeosTranslationUtils\Service;

use CRON\NeosTranslationUtils\Service\Model\NodeType;
use CRON\NeosTranslationUtils\Utils\FileUtils;
use /** @noinspection PhpUnusedAliasInspection */
    Neos\Flow\Annotations as Flow;

use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * Class NodeTypeService
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeService
{
    /**
     * @Flow\InjectConfiguration(path="nodeTypes.includePattern")
     * @var string
     */
    protected $includePattern;

    /**
     * @Flow\InjectConfiguration(path="nodeTypes.translationMagicValue")
     * @var string
     */
    protected $translationMagicValue;

    /**
     * @Flow\Inject
     * @var FileUtils
     */
    protected $fileUtils;


    /**
     * @var YamlParser
     */
    protected $yamlParser;

    public function __construct()
    {
        $this->yamlParser = new YamlParser();
    }

    /**
     * Return the absolute paths of the included NodeType files.
     *
     * @param string $basePath
     * @return array|null
     */
    protected function getNodeTypeFilePaths($basePath)
    {
        return $this->fileUtils->globFiles($basePath, $this->includePattern);
    }

    /**
     * Recursive function for extracting the translation keys for which the value is the magic translation value.
     *
     * @param array|mixed $yamlObj
     * @param string $keyPath
     * @param int $ignoreKeyDepth recursive depth for which the first key(s) should be ignored
     * @return array
     */
    private function doExtractTranslationKeys($yamlObj, $keyPath, $ignoreKeyDepth) {
        $translationKeys = [];

        if ($keyPath) {
            if ($ignoreKeyDepth > 0) {
                $keyPath = '';
                $ignoreKeyDepth--;
            }
        }

        if (is_string($yamlObj)) {
            if ($yamlObj == $this->translationMagicValue) {
                $translationKeys[] = $keyPath;
            }
        } else if (is_array($yamlObj)) {
            foreach ($yamlObj as $key => $value) {
                if ($keyPath) {
                    $newKeyPath = $keyPath . '.' . $key;
                } else {
                    $newKeyPath = $key;
                }
                $translationKeys = array_merge($translationKeys, $this->doExtractTranslationKeys($value, $newKeyPath, $ignoreKeyDepth));
            }
        }

        return $translationKeys;
    }

    /**
     * Extract the translation keys for the entries that have the magic translation value.
     *
     * @param array|mixed $yamlValues
     * @return array
     */
    protected function extractTranslationKeys($yamlValues)
    {
        return $this->doExtractTranslationKeys($yamlValues, '', 1);
    }

    /**
     * Populate and return an instance of NodeType that holds the necessary values for updating translations.
     *
     * @param string $filePath
     * @return NodeType|null
     */
    protected function parseNodeTypeFile($filePath)
    {
        // parse yaml file as php array
        $yamlValues = $this->yamlParser->parseFile($filePath);

        if (!$yamlValues) {
            return null;
        }

        $translationKeys = $this->processTranslationIdExceptions($this->extractTranslationKeys($yamlValues));

        // split filename into parts by '.' and remove the .yaml-ending
        $filePathParts = explode('/', $filePath);
        $fileName = $filePathParts[count($filePathParts) - 1];
        $fileNameParts = explode('.', preg_replace('/\.yaml$/', '', $fileName));

        return new NodeType($filePath, $fileNameParts, $translationKeys);
    }

    /**
     * Retrieve all included and relevant NodeType files.
     * A NodeType file is not relevant if it contains no entries that need to be translated.
     *
     * @param string $basePath
     *
     * @return NodeType[]|null
     */
    public function getNodeTypes($basePath)
    {
        $nodeTypeFilePaths = $this->getNodeTypeFilePaths($basePath);

        if ($nodeTypeFilePaths == null) {
            return null;
        }

        $nodeTypeFiles = [];

        foreach ($nodeTypeFilePaths as $nodeTypeFilePath) {
            $nodeTypeFile = $this->parseNodeTypeFile($nodeTypeFilePath);

            // filter out NodeType files that contain no translation ids
            if ($nodeTypeFile && count($nodeTypeFile->getTranslationIds()) > 0) {
                $nodeTypeFiles[] = $nodeTypeFile;
            }
        }

        return $nodeTypeFiles;
    }

    /**
     * This method handles various exceptional cases where the i18n translation ID is not strictly the NodeType's path.
     * See https://neos.github.io/neos/4.3/source-class-Neos.Neos.Aspects.NodeTypeConfigurationEnrichmentAspect.html
     *
     * @param array $translationIds
     * @return array
     */
    protected function processTranslationIdExceptions($translationIds) {
        if (!is_array($translationIds)) {
            return [];
        }

        return array_map(function($translationId) {
            $translationId = trim($translationId);

            if (preg_match('/^(properties\\.[^\\.]+)\\.ui\\.label$/', $translationId, $matches)) {
                return $matches[1];
            }

            if (preg_match('/^ui\\.inspector\\.(groups\\.[^\\.]+)\\.label$/', $translationId, $matches)) {
                return $matches[1];
            }

            if (preg_match('/^properties\\.([^\\.]+)\\.ui\\.inspector\\.editorOptions\\.values\\.([^\\.]+)\\.label$/', $translationId, $matches)) {
                return 'properties.' . $matches[1] .'.selectBoxEditor.values.' . $matches[2];
            }

            if (preg_match('/^properties\\.([^\\.]+)\\.ui\\.inspector\\.editorOptions\\.placeholder$/', $translationId, $matches)) {
                return 'properties.' . $matches[1] .'.selectBoxEditor.placeholder';
            }

            if (preg_match('/^ui\\.creationDialog\\.elements\\.([^\\.]+)\\.ui\\.label$/', $translationId, $matches)) {
                return 'creationDialog.' . $matches[1];
            }

            return $translationId;
        }, $translationIds);
    }
}
