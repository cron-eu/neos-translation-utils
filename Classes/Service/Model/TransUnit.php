<?php

namespace CRON\NeosTranslationUtils\Service\Model;

/**
 * Class TransUnit
 * This class represents a <trans-unit> tag in an XLIFF file.
 * @package CRON\NeosTranslationUtils\Service\Model
 */
class TransUnit
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $source;

    /**
     * @var string
     */
    protected $target;

    /**
     * @param string $id
     * @param string $source
     * @param string $target
     */
    public function __construct($id, $source, $target)
    {
        $this->id = $id;
        $this->source = $source;
        $this->target = $target;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param string $source
     */
    public function setSource($source)
    {
        $this->source = $source;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param string $target
     */
    public function setTarget($target)
    {
        $this->target = $target;
    }
}
