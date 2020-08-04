<?php

namespace CRON\NeosTranslationUtils\Service\Model;

/**
 * Class NodeType
 * This class represents a single NodeType file for which translations should be generated.
 * @package CRON\NeosTranslationUtils\Service\Model
 */
class NodeType
{
    /**
     * @var string
     */
    private $filePath;

    /**
     * @var array
     */
    private $fileNameParts;

    /**
     * @var array
     */
    private $translationIds;

    /**
     * NodeType constructor.
     * @param string $filePath
     * @param array $filenameParts
     * @param array $translationIds
     */
    public function __construct($filePath, $filenameParts, $translationIds)
    {
        $this->filePath = $filePath;
        $this->fileNameParts = $filenameParts;
        $this->translationIds = $translationIds;
    }

    /**
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return array
     */
    public function getFileNameParts(): array
    {
        return $this->fileNameParts;
    }

    /**
     * @return array
     */
    public function getTranslationIds(): array
    {
        return $this->translationIds;
    }

    public function jsonSerialize()
    {
        return [
            'filePath' => $this->getFilePath(),
            'fileNameParts' => $this->getFileNameParts(),
            'translationIds' => $this->getTranslationIds()
        ];
    }
}
