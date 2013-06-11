<?php
/**
 * File containing the BlockMatcherFactory class.
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\MVC\Symfony\Matcher;

use eZ\Publish\API\Repository\Values\ValueObject;
use eZ\Publish\Core\FieldType\Page\Parts\Block;
use eZ\Publish\Core\MVC\Symfony\Matcher\Block\MatcherInterface;
use eZ\Publish\Core\MVC\Symfony\Matcher\MatcherInterface as BaseMatcherInterface;

class BlockMatcherFactory extends AbstractMatcherFactory
{
    const MATCHER_RELATIVE_NAMESPACE = 'eZ\\Publish\\Core\\MVC\\Symfony\\Matcher\\Block';

    protected function getMatcher( $matcherIdentifier )
    {
        $matcher = parent::getMatcher( $matcherIdentifier );
        if ( !$matcher instanceof MatcherInterface )
        {
            throw new InvalidArgumentException(
                'Matcher for Blocks must implement eZ\\Publish\\Core\\MVC\\Symfony\\Matcher\\Block\\MatcherInterface.'
            );
        }

        return $matcher;
    }

    /**
     * Checks if $valueObject matches $matcher rules.
     *
     * @param \eZ\Publish\Core\MVC\Symfony\Matcher\Block\MatcherInterface $matcher
     * @param ValueObject $valueObject
     *
     * @return bool
     */
    protected function doMatch( BaseMatcherInterface $matcher, ValueObject $valueObject )
    {
        if ( !$valueObject instanceof Block )
            throw new InvalidArgumentException( 'Value object must be a valid Block instance' );

        return $matcher->matchBlock( $valueObject );
    }
}