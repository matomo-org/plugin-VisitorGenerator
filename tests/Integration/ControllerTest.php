<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\tests\Integration;

use Piwik\Http\BadRequestException;
use Piwik\Plugins\VisitorGenerator\Controller;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group VisitorGenerator
 * @group ControllerTest
 */
class ControllerTest extends IntegrationTestCase
{
    public function tearDown(): void
    {
        unset($_GET['daysToCompute'], $_REQUEST['daysToCompute']);
        parent::tearDown();
    }

    public function testGeneratingLessThanOneDayAnswers400(): void
    {
        $_GET['daysToCompute'] = $_REQUEST['daysToCompute'] = '0';

        $this->expectException(BadRequestException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Days to compute must be greater or equal to 1.');

        $method = new \ReflectionMethod(Controller::class, 'checkDays');
        $method->setAccessible(true);
        $method->invoke(new Controller());
    }
}
