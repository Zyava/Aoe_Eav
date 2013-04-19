<?php
/**
 * Class Aoe_Eav_Model_Config
 *
 * @author Dmytro Zavalkin <dmytro.zavalkin@aoemedia.de>
 */
class Aoe_Eav_Model_Config extends Mage_Eav_Model_Config
{
    /**
     * Entity types cache
     *
     * @var Mage_Eav_Model_Entity_Type[]
     */
    protected $_entityTypes = null;

    /**
     * Initialize all entity types data
     *
     * @return Mage_Eav_Model_Config
     */
    protected function _initEntityTypes()
    {
        Varien_Profiler::start('EAV: ' . __METHOD__);
        /**
         * try load information about entity types from cache
         */
        if ($this->_isCacheEnabled()
            && ($cache = Mage::app()->loadCache(self::ENTITIES_CACHE_ID))
        ) {
            list($this->_entityTypes, $this->_references['entity']) = unserialize($cache);
            Varien_Profiler::stop('EAV: ' . __METHOD__);

            return $this;
        }

        $entityTypeCollection = Mage::getResourceModel('eav/entity_type_collection');
        if ($entityTypeCollection->count() > 0) {
            $entityTypes     = array();
            $references = array();
            /** @var $entityType Mage_Eav_Model_Entity_Type */
            foreach ($entityTypeCollection as $entityType) {
                $entityTypes[$entityType->getData('entity_type_code')] = $entityType;
                $references[$entityType->getData('entity_type_id')]    = $entityType->getData('entity_type_code');
            }

            $this->_entityTypes          = $entityTypes;
            $this->_references['entity'] = $references;
        }

        if ($this->_isCacheEnabled()) {
            Mage::app()->saveCache(serialize(array($this->_entityTypes, $this->_references['entity'])),
                self::ENTITIES_CACHE_ID,
                array('eav', Mage_Eav_Model_Entity_Attribute::CACHE_TAG)
            );
        }
        Varien_Profiler::stop('EAV: ' . __METHOD__);

        return $this;
    }

    /**
     * Get entity type object by entity type code/identifier
     *
     * @param  Mage_Eav_Model_Entity_Type|string|int $code
     * @return Mage_Eav_Model_Entity_Type
     */
    public function getEntityType($code)
    {
        if ($code instanceof Mage_Eav_Model_Entity_Type) {
            return $code;
        }

        Varien_Profiler::start('EAV: ' . __METHOD__);
        if ($this->_entityTypes === null) {
            $this->_initEntityTypes();
        }

        if (is_numeric($code)) {
            $entityCode = $this->_getEntityTypeReference($code);
            if ($entityCode !== null) {
                $code = $entityCode;
            }
        }

        if (!isset($this->_entityTypes[$code])) {
            Mage::throwException(Mage::helper('eav')->__('Invalid entity_type specified: %s', $code));
        }
        Varien_Profiler::stop('EAV: ' . __METHOD__);

        return $this->_entityTypes[$code];
    }
}
