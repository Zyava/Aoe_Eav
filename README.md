Aoe_Eav
=======

Good explanation about what is EAV and how it is implemented in magento can be read 
[here](http://www.solvingmagento.com/magento-eav-system/).

Magento EAV cache is expected to cache product, category, other types of EAV attributes to cache.

Since **Magento CE 1.4** release EAV cache in Magento **is broken**. It means that 
**descriptions of EAV attributes** (product, category, etc.) are
loaded from eav_attribute and other related tables on **each page request** for **each new model/collection type** creation.
