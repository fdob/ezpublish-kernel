<?php
/**
 * File containing the XmlText Value class
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\FieldType\XmlText;

use eZ\Publish\Core\FieldType\Value as BaseValue;
use DOMDocument;

/**
 * Basic for TextLine field type
 */
class Value extends BaseValue
{
    const EMPTY_VALUE = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" />
EOT;

    /**
     * XML content as DOMDocument
     *
     * @var \DOMDocument
     */
    public $xml;

    /**
     * Initializes a new XmlText Value object with $xmlDoc in
     *
     * @param \DOMDocument|string $xmlDoc
     */
    public function __construct( $xmlDoc = null )
    {
        if ( $xmlDoc instanceof DOMDocument )
        {
            $this->xml = $xmlDoc;
        }
        else
        {
            $this->xml = new DOMDocument;
            $this->xml->loadXML( $xmlDoc === null ? self::EMPTY_VALUE : $xmlDoc );
        }
    }

    /**
     * @see \eZ\Publish\Core\FieldType\Value
     */
    public function __toString()
    {
        return isset( $this->xml ) ? $this->xml->saveXML() : self::EMPTY_VALUE;
    }
}
