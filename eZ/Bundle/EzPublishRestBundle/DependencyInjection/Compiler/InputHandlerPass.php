<?php
/**
 * File containing the InputParser CompilerPass class.
 *
 * @copyright Copyright (C) 2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Bundle\EzPublishRestBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Container processor for the ezpublish_rest.input.handler service tag.
 * Maps input formats (json, xml) to handlers.
 *
 * Tag attributes: format. Ex: json
 */
class InputHandlerPass implements CompilerPassInterface
{
    public function process( ContainerBuilder $container )
    {
        if ( !$container->hasDefinition( 'ezpublish_rest.input.dispatcher' ) )
        {
            return;
        }

        $definition = $container->getDefinition( 'ezpublish_rest.input.dispatcher' );

        // @todo rethink the relationships between registries. Rename if required.
        foreach ( $container->findTaggedServiceIds( 'ezpublish_rest.input.handler' ) as $id => $attributes )
        {
            if ( !isset( $attributes[0]['format'] ) )
                throw new \LogicException( 'ezpublish_rest.input.handler service tag needs a "format" attribute to identify the input handler. None given.' );

            $definition->addMethodCall(
                'addHandler',
                array( $attributes[0]["format"], new Reference( $id ) )
            );
        }

    }
}
