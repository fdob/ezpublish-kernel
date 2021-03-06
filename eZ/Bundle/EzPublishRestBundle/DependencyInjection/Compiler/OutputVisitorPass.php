<?php
/**
 * File containing the OutputVisitorPass class.
 *
 * @copyright Copyright (C) 2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */
namespace eZ\Bundle\EzPublishRestBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Compiler pass for the ezpublish_rest.output.visitor tag.
 *
 * Maps an output visitor (json, xml...) to an accept-header
 * @todo The tag is much more limited in scope than the name shows. Refactor. More ways to map ?
 */
class OutputVisitorPass implements CompilerPassInterface
{
    public function process( ContainerBuilder $container )
    {
        if ( !$container->hasDefinition( 'ezpublish_rest.output.visitor.dispatcher' ) )
        {
            return;
        }

        $definition = $container->getDefinition( 'ezpublish_rest.output.visitor.dispatcher' );

        foreach ( $container->findTaggedServiceIds( 'ezpublish_rest.output.visitor' ) as $id => $attributes )
        {
            if ( !isset( $attributes[0]['regexps'] ) )
            {
                throw new \LogicException( 'ezpublish_rest.output.visitor service tag needs a "regexps" attribute to identify the Accept header. None given.' );
            }

            if ( is_array( $attributes[0]['regexps'] ) )
            {
                $regexps = $attributes[0]['regexps'];
            }
            else if ( is_string( $attributes[0]['regexps'] ) )
            {
                try
                {
                    $regexps = $container->getParameter( $attributes[0]['regexps'] );
                }
                catch ( InvalidArgumentException $e )
                {
                    throw new \LogicException( "The regexps attribute of the ezpublish_rest.output.visitor service tag can be a string matching a container parameter name. No parameter {$attributes[0]['regexps']} could be found." );
                }
            }
            else
            {
                throw new \LogicException( 'ezpublish_rest.output.visitor service tag needs a "regexps" attribute, either as an array or a string. Invalid value.' );
            }

            foreach ( $regexps as $regexp )
            {
                $definition->addMethodCall(
                    'addVisitor',
                    array( $regexp, new Reference( $id ) )
                );
            }
        }

    }
}
