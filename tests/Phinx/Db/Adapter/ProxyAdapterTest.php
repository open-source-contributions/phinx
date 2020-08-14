<?php

namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\ProxyAdapter;
use Phinx\Migration\IrreversibleMigrationException;
use PHPUnit\Framework\TestCase;

class ProxyAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\ProxyAdapter
     */
    private $adapter;

    protected function setUp(): void
    {
        $stub = $this->getMockBuilder('\Phinx\Db\Adapter\PdoAdapter')
            ->setConstructorArgs([[]])
            ->setMethods([])
            ->getMock();

        $stub->expects($this->any())
            ->method('isValidColumnType')
            ->will($this->returnValue(true));

        $this->adapter = new ProxyAdapter($stub);
    }

    protected function tearDown(): void
    {
        unset($this->adapter);
    }

    public function testProxyAdapterCanInvertCreateTable()
    {
        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->addColumn('column1', 'string')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\DropTable', $commands[0]);
        $this->assertSame('atable', $commands[0]->getTable()->getName());
    }

    public function testProxyAdapterCanInvertRenameTable()
    {
        $table = new \Phinx\Db\Table('oldname', [], $this->adapter);
        $table->rename('newname')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\RenameTable', $commands[0]);
        $this->assertSame('newname', $commands[0]->getTable()->getName());
        $this->assertSame('oldname', $commands[0]->getNewName());
    }

    public function testProxyAdapterCanInvertAddColumn()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));
        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->addColumn('acolumn', 'string')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\RemoveColumn', $commands[0]);
        $this->assertSame('atable', $commands[0]->getTable()->getName());
        $this->assertSame('acolumn', $commands[0]->getColumn()->getName());
    }

    public function testProxyAdapterCanInvertRenameColumn()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->renameColumn('oldname', 'newname')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\RenameColumn', $commands[0]);
        $this->assertSame('newname', $commands[0]->getColumn()->getName());
        $this->assertSame('oldname', $commands[0]->getNewName());
    }

    public function testProxyAdapterCanInvertAddIndex()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->addIndex(['email'])
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\DropIndex', $commands[0]);
        $this->assertSame('atable', $commands[0]->getTable()->getName());
        $this->assertSame(['email'], $commands[0]->getIndex()->getColumns());
    }

    public function testProxyAdapterCanInvertAddForeignKey()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->addForeignKey(['ref_table_id'], 'refTable')
              ->save();

        $commands = $this->adapter->getInvertedCommands()->getActions();
        $this->assertInstanceOf('Phinx\Db\Action\DropForeignKey', $commands[0]);
        $this->assertSame('atable', $commands[0]->getTable()->getName());
        $this->assertSame(['ref_table_id'], $commands[0]->getForeignKey()->getColumns());
    }

    public function testGetInvertedCommandsThrowsExceptionForIrreversibleCommand()
    {
        $this->adapter
            ->getAdapter()
            ->expects($this->any())
            ->method('hasTable')
            ->will($this->returnValue(true));

        $table = new \Phinx\Db\Table('atable', [], $this->adapter);
        $table->removeColumn('thing')
              ->save();

        $this->expectException(IrreversibleMigrationException::class);
        $this->expectExceptionMessage('Cannot reverse a "Phinx\Db\Action\RemoveColumn" command');

        $this->adapter->getInvertedCommands();
    }
}
