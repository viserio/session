<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark Framework.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Viserio\Component\Session\Tests\Handler;

use Mockery;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use SessionHandlerInterface;
use Viserio\Component\Session\Handler\MigratingSessionHandler;

/**
 * @internal
 *
 * @small
 */
final class MigratingSessionHandlerTest extends MockeryTestCase
{
    /** @var \Viserio\Component\Session\Handler\MigratingSessionHandler */
    private $dualHandler;

    /** @var \Mockery\MockInterface|SessionHandlerInterface */
    private $currentHandler;

    /** @var \Mockery\MockInterface|SessionHandlerInterface */
    private $writeOnlyHandler;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->currentHandler = Mockery::mock(SessionHandlerInterface::class);
        $this->writeOnlyHandler = Mockery::mock(SessionHandlerInterface::class);
        $this->dualHandler = new MigratingSessionHandler($this->currentHandler, $this->writeOnlyHandler);
    }

    public function testClose(): void
    {
        $this->allowMockingNonExistentMethods(true);

        $this->currentHandler->shouldReceive('close')
            ->once()
            ->andReturn(true);
        $this->writeOnlyHandler->shouldReceive('close')
            ->once()
            ->andReturn(false);

        $result = $this->dualHandler->close();

        self::assertTrue($result);

        $this->allowMockingNonExistentMethods();
    }

    public function testDestroy(): void
    {
        $sessionId = 'xyz';

        $this->currentHandler->shouldReceive('destroy')
            ->once()
            ->with($sessionId)
            ->andReturn(true);
        $this->writeOnlyHandler->shouldReceive('destroy')
            ->once()
            ->with($sessionId)
            ->andReturn(false);

        $result = $this->dualHandler->destroy($sessionId);

        self::assertTrue($result);
    }

    public function testGc(): void
    {
        $maxlifetime = 357;

        $this->currentHandler->shouldReceive('gc')
            ->once()
            ->with($maxlifetime)
            ->andReturn(true);
        $this->writeOnlyHandler->shouldReceive('gc')
            ->once()
            ->with($maxlifetime)
            ->andReturn(false);

        $result = $this->dualHandler->gc($maxlifetime);

        self::assertTrue($result);
    }

    public function testOpen(): void
    {
        $savePath = '/path/to/save/location';
        $sessionName = 'xyz';

        $this->currentHandler->shouldReceive('open')
            ->once()
            ->with($savePath, $sessionName)
            ->andReturn(true);
        $this->writeOnlyHandler->shouldReceive('open')
            ->once()
            ->with($savePath, $sessionName)
            ->andReturn(false);

        $result = $this->dualHandler->open($savePath, $sessionName);

        self::assertTrue($result);
    }

    public function testRead(): void
    {
        $sessionId = 'xyz';
        $readValue = 'something';

        $this->currentHandler->shouldReceive('read')
            ->once()
            ->with($sessionId)
            ->andReturn($readValue);
        $this->writeOnlyHandler->shouldReceive('read')
            ->never()
            ->with(Mockery::any());

        $result = $this->dualHandler->read($sessionId);

        self::assertSame($readValue, $result);
    }

    public function testWrite(): void
    {
        $sessionId = 'xyz';
        $data = 'my-serialized-data';

        $this->currentHandler->shouldReceive('write')
            ->once()
            ->with($sessionId, $data)
            ->andReturn(true);
        $this->writeOnlyHandler->shouldReceive('write')
            ->once()
            ->with($sessionId, $data)
            ->andReturn(false);

        $result = $this->dualHandler->write($sessionId, $data);

        self::assertTrue($result);
    }

    public function testValidateId(): void
    {
        $sessionId = 'xyz';
        $readValue = 'something';

        $this->currentHandler->shouldReceive('read')
            ->once()
            ->with($sessionId)
            ->andReturn($readValue);

        $this->writeOnlyHandler->shouldReceive('read')
            ->never()
            ->with(Mockery::any());

        $result = $this->dualHandler->validateId($sessionId);

        self::assertTrue($result);
    }

    public function testUpdateTimestamp(): void
    {
        $sessionId = 'xyz';
        $data = 'my-serialized-data';

        $this->currentHandler->shouldReceive('write')
            ->once()
            ->with($sessionId, $data)
            ->andReturn(true);

        $this->writeOnlyHandler->shouldReceive('write')
            ->once()
            ->with($sessionId, $data)
            ->andReturn(false);

        $result = $this->dualHandler->updateTimestamp($sessionId, $data);

        self::assertTrue($result);
    }
}
