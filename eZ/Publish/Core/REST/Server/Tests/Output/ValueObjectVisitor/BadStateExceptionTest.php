<?php
/**
 * File containing a test class
 *
 * @copyright Copyright (C) 1999-2013 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\REST\Server\Tests\Output\ValueObjectVisitor;

use eZ\Publish\Core\REST\Server\Output\ValueObjectVisitor;
use eZ\Publish\API\Repository\Tests\Stubs\Exceptions;
use eZ\Publish\Core\REST\Common;

class BadStateExceptionTest extends ExceptionTest
{
    /**
     * Get expected status code
     *
     * @return int
     */
    protected function getExpectedStatusCode()
    {
        return 409;
    }

    /**
     * Get expected message
     *
     * @return string
     */
    protected function getExpectedMessage()
    {
        return "Conflict";
    }

    /**
     * Gets the exception
     *
     * @return \Exception
     */
    protected function getException()
    {
        return new Exceptions\BadStateExceptionStub( "Test" );
    }

    /**
     * Gets the exception visitor
     *
     * @return \eZ\Publish\Core\REST\Server\Output\ValueObjectVisitor\BadStateException
     */
    protected function internalGetVisitor()
    {
        return new ValueObjectVisitor\BadStateException;
    }
}
