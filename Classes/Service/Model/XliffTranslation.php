<?php

namespace CRON\NeosTranslationUtils\Service\Model;

/**
 * Class XliffTranslation
 * This class represents an XLIFF translation file and holds all its important values.
 *
 * @package CRON\NeosTranslationUtils\Service\Model
 */
class XliffTranslation
{
    /**
     * aka package key
     * @var string
     */
    protected $productName;

    /**
     * @var string
     */
    protected $sourceLanguage;

    /**
     * @var string|null
     */
    protected $targetLanguage;

    /**
     * @var TransUnit[]
     */
    protected $transUnits;

    /**
     * @param string $productName
     * @param string $sourceLanguage
     * @param string|null $targetLanguage
     * @param TransUnit[] $transUnits
     */
    public function __construct($productName, $sourceLanguage, $targetLanguage, $transUnits)
    {
        $this->productName = $productName;
        $this->sourceLanguage = $sourceLanguage;
        $this->targetLanguage = $targetLanguage;
        $this->transUnits = $transUnits;
    }

    /**
     * @return TransUnit[]
     */
    public function getTransUnits()
    {
        return $this->transUnits;
    }

    /**
     * @return string
     */
    public function getProductName()
    {
        return $this->productName;
    }

    /**
     * @return string
     */
    public function getSourceLanguage()
    {
        return $this->sourceLanguage;
    }

    /**
     * @return string|null
     */
    public function getTargetLanguage()
    {
        return $this->targetLanguage;
    }

    /**
     * @param string $productName
     */
    public function setProductName($productName)
    {
        $this->productName = $productName;
    }

    /**
     * @param string $sourceLanguage
     */
    public function setSourceLanguage($sourceLanguage)
    {
        $this->sourceLanguage = $sourceLanguage;
    }

    /**
     * @param string|null $targetLanguage
     */
    public function setTargetLanguage($targetLanguage)
    {
        $this->targetLanguage = $targetLanguage;
    }

    /**
     * @param TransUnit[] $transUnits
     */
    public function setTransUnits($transUnits)
    {
        $this->transUnits = $transUnits;
    }

    /**
     * Update the <trans-unit> tags for this XLIFF file with the source translations from the given XLIFF file.
     *
     * @param XliffTranslation $sourceXliff
     */
    public function updateTransUnitsFromSourceXliff($sourceXliff)
    {
        $transUnits = [];

        $sourceTransUnits = $sourceXliff->getTransUnits();

        // build map for better access, use id as key
        /** @var string[] $targetMap */
        $targetMap = [];
        foreach ($this->getTransUnits() as $transUnit) {
            if ($transUnit->getId()) {
                $targetMap[$transUnit->getId()] = $transUnit->getTarget();
            }
        }

        foreach ($sourceTransUnits as $sourceTransUnit) {
            $target = null;
            if (isset($targetMap[$sourceTransUnit->getId()])) {
                $target = $targetMap[$sourceTransUnit->getId()];
            }
            $transUnits[] = new TransUnit($sourceTransUnit->getId(), $sourceTransUnit->getSource(), $target);
        }

        $this->setTransUnits($transUnits);
    }

    /**
     * Method to be invoked when json_encode is called on an instance of this class.
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'productName' => $this->getProductName(),
            'sourceLanguage' => $this->getSourceLanguage(),
            'targetLanguage' => $this->getTargetLanguage(),
            'transUnits' => $this->getTransUnits()
        ];
    }
}
