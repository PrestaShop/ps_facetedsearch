<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\FacetedSearch\Tests\Indexation;

use Db;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\Indexation\DeferredQueue;
use Ps_Facetedsearch;

class DeferredQueueTest extends MockeryTestCase
{
    /**
     * @var Db|Mockery\MockInterface
     */
    private $dbMock;

    /**
     * @var DeferredQueue
     */
    private $queue;

    protected function setUp()
    {
        $this->dbMock = Mockery::mock(Db::class);
        $this->queue = new DeferredQueue($this->dbMock);
    }

    public function testInstallTable()
    {
        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::pattern('/CREATE TABLE IF NOT EXISTS.*faceted_deferred_queue/i'))
            ->andReturn(true);

        $this->assertTrue($this->queue->install());
    }

    public function testUninstallTable()
    {
        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::pattern('/DROP TABLE IF EXISTS.*faceted_deferred_queue/i'))
            ->andReturn(true);

        $this->assertTrue($this->queue->uninstall());
    }

    public function testEnqueueInvalidProduct()
    {
        $this->assertFalse($this->queue->enqueue(0));
        $this->assertFalse($this->queue->enqueue(-5));
    }

    public function testEnqueueValidProduct()
    {
        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::pattern('/INSERT INTO.*faceted_deferred_queue.*VALUES.*\(42,\s*0,/i'))
            ->andReturn(true);

        $this->assertTrue($this->queue->enqueue(42));
    }

    public function testEnqueueMultipleProducts()
    {
        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::pattern('/INSERT INTO.*faceted_deferred_queue.*\(10,\s*1,.*\(20,\s*1,/i'))
            ->andReturn(true);

        $this->assertTrue($this->queue->enqueueMultiple([10, 20, 10], 1));
    }

    public function testReleaseStaleLocks()
    {
        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::pattern('/UPDATE.*faceted_deferred_queue.*SET `locked_at` = NULL.*DATE_SUB\(NOW\(\),\s*INTERVAL 15 MINUTE\)/i'))
            ->andReturn(true);

        $this->assertTrue($this->queue->releaseStaleLocks(15));
    }

    public function testProcessEmptyQueue()
    {
        $moduleMock = Mockery::mock(Ps_Facetedsearch::class);

        $this->dbMock->shouldReceive('execute')
            ->once()
            ->andReturn(true); // releaseStaleLocks

        $this->dbMock->shouldReceive('executeS')
            ->once()
            ->andReturn([]);

        $this->assertEquals(0, $this->queue->process($moduleMock, 50));
    }

    public function testProcessQueueItems()
    {
        $moduleMock = Mockery::mock(Ps_Facetedsearch::class);
        $moduleMock->shouldReceive('indexProductPrices')->once()->with(100);
        $moduleMock->shouldReceive('indexAttributes')->once()->with(100);
        $moduleMock->shouldReceive('invalidateLayeredFilterBlockCache')->once();

        $this->dbMock->shouldReceive('execute')
            ->once()
            ->andReturn(true); // releaseStaleLocks

        $this->dbMock->shouldReceive('executeS')
            ->once()
            ->andReturn([
                ['id_product' => 100, 'id_shop' => 1],
            ]);

        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::pattern('/UPDATE.*faceted_deferred_queue.*SET `locked_at` = NOW\(\)/i'))
            ->andReturn(true);

        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::pattern('/DELETE FROM.*faceted_deferred_queue.*id_product = 100/i'))
            ->andReturn(true);

        $this->assertEquals(1, $this->queue->process($moduleMock, 50));
    }
}
