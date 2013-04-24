<?php
/**
 * Class Aoe_Eav_Model_Config
 *
 * @author Dmytro Zavalkin <dmytro.zavalkin@aoemedia.de>
 */
class Aoe_Eav_Model_Config extends Mage_Eav_Model_Config
{
    /**
     * EAV data cache tag, used in backend cache management
     */
    const CACHE_TAG = 'EAV';

    /**
     * Entity types cache
     *
     * @var Mage_Eav_Model_Entity_Type[]
     */
    protected $_entityTypes = null;

    /**
     * Attribute sets cache
     *
     * @var Mage_Eav_Model_Entity_Attribute_Set[]
     */
    protected $_attributeSets = null;

    /**
     * Attributes cache
     *
     * @var Mage_Eav_Model_Entity_Attribute_Abstract[]
     */
    protected $_attributes = null;

    /**
     * Load data from cache
     *
     * @param string $cacheId
     * @return bool|string
     */
    protected function _loadDataFromCache($cacheId)
    {
        if ($this->_isCacheEnabled()) {
            return Mage::app()->loadCache($cacheId);
        }

        return false;
    }

    /**
     * Load data from cache
     *
     * @param string $cacheId
     * @param string $data
     */
    protected function _saveDataToCache($cacheId, $data)
    {
        if ($this->_isCacheEnabled()) {
            Mage::app()->saveCache($data, $cacheId, array(self::CACHE_TAG, Mage_Eav_Model_Entity_Attribute::CACHE_TAG));
        }
    }

    /**
     * Initialize all entity types data
     *
     * @return $this
     */
    protected function _initEntityTypes()
    {
        if (is_array($this->_entityTypes)) {
            return $this;
        }

        Varien_Profiler::start('EAV: ' . __METHOD__);
        /**
         * try load information about entity types from cache
         */
        $cache = $this->_loadDataFromCache(self::ENTITIES_CACHE_ID);
        if ($cache) {
            list($this->_entityTypes, $this->_references['entity']) = unserialize($cache);
            Varien_Profiler::stop('EAV: ' . __METHOD__);

            return $this;
        }

        $this->_entityTypes = array();
        $entityTypeCollection = Mage::getResourceModel('eav/entity_type_collection');
        if ($entityTypeCollection->count() > 0) {
            /** @var $entityType Mage_Eav_Model_Entity_Type */
            foreach ($entityTypeCollection as $entityType) {
                $entityTypeCode                      = $entityType->getData('entity_type_code');
                $this->_entityTypes[$entityTypeCode] = $entityType;
                $this->_addEntityTypeReference($entityType->getData('entity_type_id'), $entityTypeCode);
            }
        }

        // we don't save entity types to cache here because entity type attribute codes aren't set at this point yet
        Varien_Profiler::stop('EAV: ' . __METHOD__);

        return $this;
    }

    /**
     * Initialize all attribute sets
     *
     * @return $this
     */
    protected function _initAllAttributeSets()
    {
        if (is_array($this->_attributeSets)) {
            return $this;
        }
        Varien_Profiler::start('EAV: ' . __METHOD__);

        $this->_attributeSets = array();
        $attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_set_collection');
        if ($attributeSetCollection->count() > 0) {
            /** @var $attributeSet Mage_Eav_Model_Entity_Attribute_Set */
            foreach ($attributeSetCollection as $attributeSet) {
                $this->_attributeSets[$attributeSet->getData('attribute_set_id')] = $attributeSet;
            }
        }
        Varien_Profiler::stop('EAV: ' . __METHOD__);

        return $this;
    }

    /**
     * Initialize all attributes for all entity types
     *
     * @return $this
     */
    protected function _initAllAttributes()
    {
        if (is_array($this->_attributes)) {
            return $this;
        }
        Varien_Profiler::start('EAV: '.__METHOD__);

        $this->_initEntityTypes();

        /**
         * try load information about attributes and attribute sets from cache
         */
        $cache = $this->_loadDataFromCache(self::ATTRIBUTES_CACHE_ID);
        if ($cache) {
            list($this->_attributeSets, $this->_attributes, $this->_references['attribute']) = unserialize($cache);
            Varien_Profiler::stop('EAV: ' . __METHOD__);

            return $this;
        }

        $this->_initAllAttributeSets();
        if (is_array($this->_entityTypes) && count($this->_entityTypes) > 0) {
            foreach ($this->_entityTypes as $entityType) {
                $this->_initAttributes($entityType);
            }
        }

        $this->_saveDataToCache(self::ATTRIBUTES_CACHE_ID,
            serialize(array($this->_attributeSets, $this->_attributes, $this->_references['attribute']))
        );
        // save entities types to cache because they are fully set now
        // (we've just added attribute_codes to each entity type object)
        $this->_saveDataToCache(self::ENTITIES_CACHE_ID,
            serialize(array($this->_entityTypes, $this->_references['entity']))
        );

        Varien_Profiler::stop('EAV: ' . __METHOD__);

        return $this;
    }

    /**
     * Initialize all attributes for given entity type
     *
     * @param  Mage_Eav_Model_Entity_Type $entityType
     * @return $this
     */
    protected function _initAttributes($entityType)
    {
        Varien_Profiler::start('EAV: '.__METHOD__);

        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $attributesCollection */
        $attributesCollection = Mage::getResourceModel($entityType->getEntityAttributeCollection());

        if ($attributesCollection) {
            $attributesData = $attributesCollection->setEntityTypeFilter($entityType)
                ->addSetInfo()
                ->getData();

            $entityTypeAttributeCodes    = array();
            $attributeSetsAttributeCodes = array();
            foreach ($attributesData as $attributeData) {
                $this->_initAttribute($entityType, $attributeData);
                $entityTypeAttributeCodes[] = $attributeData['attribute_code'];
                $attributeSetIds            = array_keys($attributeData['attribute_set_info']);
                unset($attributeData['attribute_set_info']);

                foreach ($attributeSetIds as $attributeSetId) {
                    if (!isset($attributeSetsAttributeCodes[$attributeSetId])) {
                        $attributeSetsAttributeCodes[$attributeSetId] = array();
                    }
                    $attributeSetsAttributeCodes[$attributeSetId][] = $attributeData['attribute_code'];
                }
            }

            $entityType->setData('attribute_codes', $entityTypeAttributeCodes);
            if (count($attributeSetsAttributeCodes) > 0) {
                foreach ($attributeSetsAttributeCodes as $attributeSetId => $attributeCodes) {
                    if (isset($this->_attributeSets[$attributeSetId])) {
                        $this->_attributeSets[$attributeSetId]->setData('attribute_codes', $attributeCodes);
                    }
                }
            }
        }

        Varien_Profiler::stop('EAV: '.__METHOD__);
        return $this;
    }

    /**
     * Init attribute from attribute data array
     *
     * @param Mage_Eav_Model_Entity_Type $entityType
     * @param array $attributeData
     */
    protected function _initAttribute($entityType, $attributeData)
    {
        $entityTypeCode = $entityType->getEntityTypeCode();
        if (!empty($attributeData['attribute_model'])) {
            $model = $attributeData['attribute_model'];
        } else {
            $model = $entityType->getAttributeModel();
        }

        $attributeCode = $attributeData['attribute_code'];
        $attribute     = Mage::getModel($model)->setData($attributeData);
        $entity        = $entityType->getEntity();
        if ($entity && in_array($attributeCode, $entity->getDefaultAttributes())) {
            $attribute->setBackendType(Mage_Eav_Model_Entity_Attribute_Abstract::TYPE_STATIC)
                ->setIsGlobal(1);
        }

        $this->_attributes[$entityTypeCode][$attributeCode] = $attribute;
        $this->_addAttributeReference($attributeData['attribute_id'], $attributeCode, $entityTypeCode);
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
        $this->_initEntityTypes();

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

    /**
     * Get attribute by code for entity type
     *
     * @param   Mage_Eav_Model_Entity_Type|string|int $entityType
     * @param   Mage_Eav_Model_Entity_Attribute_Abstract|string|int $code
     * @return  Mage_Eav_Model_Entity_Attribute_Abstract|false
     */
    public function getAttribute($entityType, $code)
    {
        if ($code instanceof Mage_Eav_Model_Entity_Attribute_Interface) {
            return $code;
        }

        Varien_Profiler::start('EAV: '.__METHOD__);
        $this->_initAllAttributes();

        $entityType     = $this->getEntityType($entityType);
        $entityTypeCode = $entityType->getEntityTypeCode();

        /**
         * Validate attribute code
         */
        if (is_numeric($code)) {
            $attributeCode = $this->_getAttributeReference($code, $entityTypeCode);
            if ($attributeCode) {
                $code = $attributeCode;
            }
        }

        if (!isset($this->_attributes[$entityTypeCode][$code])) {
            // backward compatibility with attributes which are absent in db but present in xml config for some reason
            // for example type_id attribute in app/code/core/Mage/Sales/etc/config.xml
            $attribute = Mage::getModel($entityType->getAttributeModel())
                ->setAttributeCode($code);
        } else {
            $attribute = $this->_attributes[$entityTypeCode][$code];
            $attribute->setEntityType($entityType);
        }
        Varien_Profiler::stop('EAV: ' . __METHOD__);

        return $attribute;
    }

    /**
     * Get attribute object for collection usage
     *
     * @deprecated use getAttribute() instead
     * @param   Mage_Eav_Model_Entity_Type|string|int $entityType
     * @param   Mage_Eav_Model_Entity_Attribute_Abstract|string|int $attribute
     * @return  Mage_Eav_Model_Entity_Attribute_Abstract|false
     */
    public function getCollectionAttribute($entityType, $attribute)
    {
        return $this->getAttribute($entityType, $attribute);
    }

    /**
     * Get codes of all entity type attributes
     *
     * @param  Mage_Eav_Model_Entity_Type|string|int $entityType
     * @param  Varien_Object $object
     * @return array
     */
    public function getEntityAttributeCodes($entityType, $object = null)
    {
        Varien_Profiler::start('EAV: ' . __METHOD__);
        $this->_initAllAttributes();

        $attributeSetId = 0;
        if (($object instanceof Varien_Object) && $object->getAttributeSetId()) {
            $attributeSetId = $object->getAttributeSetId();
        }

        if ($attributeSetId && isset($this->_attributeSets[$attributeSetId])) {
            $attributes = $this->_attributeSets[$attributeSetId]->getData('attribute_codes');
        } else {
            $entityType = $this->getEntityType($entityType);
            $attributes = $entityType->getAttributeCodes();
        }
        Varien_Profiler::stop('EAV: ' . __METHOD__);

        return $attributes;
    }

    /**
     * Reset object state
     *
     * @return $this
     */
    public function clear()
    {
        $this->_references    = null;
        $this->_entityTypes   = null;
        $this->_attributeSets = null;
        $this->_attributes    = null;

        return $this;
    }

    /**
     * Import attributes data from external source
     *
     * @deprecated
     * @param string|Mage_Eav_Model_Entity_Type $entityType
     * @param array $attributes
     * @return $this
     */
    public function importAttributesData($entityType, array $attributes)
    {
        // with cached eav attributes we don't need this method

        return $this;
    }

    /**
     * Prepare attributes for usage in EAV collection
     *
     * @deprecated
     * @param   mixed $entityType
     * @param   array $attributes
     * @return  $this
     */
    public function loadCollectionAttributes($entityType, $attributes)
    {
        // with cached eav attributes we don't need this method

        return $this;
    }

    /**
     * Preload entity type attributes for performance optimization
     *
     * @deprecated
     * @param   mixed $entityType
     * @param   mixed $attributes
     * @return  Mage_Eav_Model_Config
     */
    public function preloadAttributes($entityType, $attributes)
    {
        // with fixed cached eav attributes this method is obsolete

        return $this;
    }
}
