<?php

declare(strict_types=1);

namespace Sockudo\Laravel\Tests\Unit;

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Sockudo\ApiErrorException;
use Sockudo\Laravel\SockudoBroadcaster;
use Sockudo\Sockudo;

class SockudoBroadcasterTest extends TestCase
{
    public function testItBroadcastsLaravelChannelsAndExcludesTheOriginatingSocket(): void
    {
        $client = $this->createMock(Sockudo::class);
        $client->expects(self::once())
            ->method('trigger')
            ->with(
                ['orders', 'private-users.42'],
                'order.updated',
                ['order_id' => 'ord-123'],
                ['socket_id' => '123.456'],
            )
            ->willReturn((object) ['ok' => true]);

        $broadcaster = new SockudoBroadcaster($client);
        $broadcaster->broadcast(
            [new Channel('orders'), new PrivateChannel('users.42')],
            'order.updated',
            ['order_id' => 'ord-123', 'socket' => '123.456'],
        );
    }

    public function testItChunksBroadcastsAtTheSockudoChannelLimit(): void
    {
        $channels = array_map(static fn(int $index): string => "channel-{$index}", range(1, 101));
        $calls = 0;
        $client = $this->createMock(Sockudo::class);
        $client->expects(self::exactly(2))
            ->method('trigger')
            ->willReturnCallback(function (array $chunk) use (&$calls): object {
                ++$calls;
                self::assertCount($calls === 1 ? 100 : 1, $chunk);

                return (object) ['ok' => true];
            });

        (new SockudoBroadcaster($client))->broadcast($channels, 'test', []);
    }

    public function testItReturnsPrivateChannelAuthorization(): void
    {
        $client = $this->createMock(Sockudo::class);
        $client->expects(self::once())
            ->method('authorizeChannel')
            ->with('private-orders', '123.456')
            ->willReturn('{"auth":"test-key:signature"}');

        $broadcaster = new SockudoBroadcaster($client);
        $broadcaster->channel('orders', static fn(): bool => true);

        $request = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-orders',
            'socket_id' => '123.456',
        ]);
        $request->setUserResolver(static fn(): object => (object) ['id' => 42]);

        self::assertSame(['auth' => 'test-key:signature'], $broadcaster->auth($request));
    }

    public function testItReturnsPresenceChannelAuthorization(): void
    {
        $user = new class {
            public function getAuthIdentifier(): int
            {
                return 42;
            }
        };
        $client = $this->createMock(Sockudo::class);
        $client->expects(self::once())
            ->method('authorizePresenceChannel')
            ->with('presence-room', '123.456', '42', ['name' => 'Ada'])
            ->willReturn('{"auth":"test-key:signature","channel_data":"data"}');

        $broadcaster = new SockudoBroadcaster($client);
        $broadcaster->channel('room', static fn(): array => ['name' => 'Ada']);

        $request = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'presence-room',
            'socket_id' => '123.456',
        ]);
        $request->setUserResolver(static fn() => $user);

        self::assertSame(
            ['auth' => 'test-key:signature', 'channel_data' => 'data'],
            $broadcaster->auth($request),
        );
    }

    public function testItReturnsDecodedUserAuthentication(): void
    {
        $client = $this->createMock(Sockudo::class);
        $client->expects(self::once())
            ->method('authenticateUser')
            ->with('123.456', ['id' => '42', 'name' => 'Ada'])
            ->willReturn('{"auth":"test-key:signature","user_data":"{\\"id\\":\\"42\\"}"}');

        $broadcaster = new SockudoBroadcaster($client);
        $broadcaster->resolveAuthenticatedUserUsing(
            static fn(): array => ['id' => '42', 'name' => 'Ada'],
        );
        $request = Request::create('/broadcasting/user-auth', 'POST', ['socket_id' => '123.456']);

        self::assertSame(
            ['auth' => 'test-key:signature', 'user_data' => '{"id":"42"}'],
            $broadcaster->resolveAuthenticatedUser($request),
        );
    }

    public function testItWrapsPublishErrorsWithoutCopyingTheResponseBody(): void
    {
        $error = new ApiErrorException('response body that must not be surfaced', 500);
        $client = $this->createMock(Sockudo::class);
        $client->expects(self::once())->method('trigger')->willThrowException($error);

        try {
            (new SockudoBroadcaster($client))->broadcast(['orders'], 'test', []);
            self::fail('Expected a BroadcastException.');
        } catch (BroadcastException $exception) {
            self::assertSame('Sockudo publish failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('response body', $exception->getMessage());
        }
    }
}
