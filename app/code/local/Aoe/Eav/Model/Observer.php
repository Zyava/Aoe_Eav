<?php
/**
 * Class Aoe_Eav_Model_Observer
 *
 * @author Dmytro Zavalkin <dmytro.zavalkin@aoemedia.de>
 */
class Aoe_Eav_Model_Observer
{
    /**
     * Clean eav cache on attribute set save/delete
     */
    public function cleanEavCache()
    {
        Mage::getSingleton('eav/config')->clear();
        Mage::app()->cleanCache(array(Mage_Eav_Model_Entity_Attribute::CACHE_TAG));
    }
}
