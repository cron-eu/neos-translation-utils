<?php

namespace CRON\NeosTranslationUtils\Service;

use CRON\NeosTranslationUtils\Service\Model\NodeType;
use CRON\NeosTranslationUtils\Utils\FileUtils;
use Neos\Flow\Annotations as Flow;

use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * Class NodeTypeService
 *
 * @Flow\Scope("singleton")
 */
class NodeTypeService
{
    /**
     * @Flow\InjectConfiguration(path="nodeTypes.includePatterns")
     * @var array
     */
    protected $includePatterns;

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
     * @return array
     */
    protected function getNodeTypeFilePaths($basePath)
    {
        $nodeTypeFilePaths = [];

        foreach ($this->includePatterns as $includePattern) {
            $nodeTypeFilePaths = array_merge($nodeTypeFilePaths, $this->fileUtils->globFiles($basePath, $includePattern));
        }

        return $nodeTypeFilePaths;
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

        // determine the path/filename parts of the translation file to be created for this NodeType.
        // A NodeType like 'Vendor.Package:Content.Division.ComponentName' should yield a translation file
        // Content/Division/ComponentName.xlf in the NodeTypes directory of the locale.
        $nodeTypeFullNames = array_keys($yamlValues);

        if (count($nodeTypeFullNames) > 0) {
            $nodeTypeFullName = $nodeTypeFullNames[0];
        } else {
            return null;
        }

        // before ':' is the package key, if existent
        // after ':' is the NodeType name
        $nodeTypeFullNameSplit = explode(':', $nodeTypeFullName);

        $fileNameParts = explode('.', $nodeTypeFullNameSplit[array_key_last($nodeTypeFullNameSplit)]);

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

            if (preg_match('/^ui\\.inspector\\.(tabs\\.[^\\.]+)\\.label$/', $translationId, $matches)) {
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
