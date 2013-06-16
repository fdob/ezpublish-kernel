<?php
/**
 * File containing the Configurator class.
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Bundle\EzPublishLegacyBundle\Configurator;

use Sensio\Bundle\DistributionBundle\Configurator\Configurator as DistributionBundleConfigurator;

/**
 * Configurator for installation steps
 *
 * Extends DistributionBundleConfigurator to make the service available
 * even if DistributionBundle is not activated.
 */
class Configurator extends DistributionBundleConfigurator
{

}
