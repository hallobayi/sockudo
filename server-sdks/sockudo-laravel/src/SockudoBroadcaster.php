<?php

declare(strict_types=1);

namespace Sockudo\Laravel;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use JsonException;
use Sockudo\SockudoInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class SockudoBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    public function __construct(
        private SockudoInterface $sockudo,
        private readonly bool $allowJsonp = false,
    ) {}

    public function resolveAuthenticatedUser($request)
    {
        $user = parent::resolveAuthenticatedUser($request);

        if (!$user) {
            return null;
        }

        if ($user instanceof Arrayable) {
            $user = $user->toArray();
        }

        if (!is_array($user)) {
            throw new BroadcastException('Sockudo user authentication data must be an array.');
        }

        return $this->decodeResponse($request, $this->sockudo->authenticateUser($request->socket_id, $user));
    }

    public function auth($request)
    {
        $channelName = $this->normalizeChannelName($request->channel_name);

        if (empty($request->channel_name)
            || ($this->isGuardedChannel($request->channel_name)
                && !$this->retrieveUser($request, $channelName))) {
            throw new AccessDeniedHttpException();
        }

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }

    public function validAuthenticationResponse($request, $result)
    {
        if (str_starts_with($request->channel_name, 'private')) {
            return $this->decodeResponse(
                $request,
                $this->sockudo->authorizeChannel($request->channel_name, $request->socket_id),
            );
        }

        $channelName = $this->normalizeChannelName($request->channel_name);
        $user = $this->retrieveUser($request, $channelName);
        $broadcastIdentifier = method_exists($user, 'getAuthIdentifierForBroadcasting')
            ? $user->getAuthIdentifierForBroadcasting()
            : $user->getAuthIdentifier();

        return $this->decodeResponse(
            $request,
            $this->sockudo->authorizePresenceChannel(
                $request->channel_name,
                $request->socket_id,
                (string) $broadcastIdentifier,
                $result,
            ),
        );
    }

    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $socket = Arr::pull($payload, 'socket');
        $parameters = $socket !== null ? ['socket_id' => $socket] : [];
        $channels = $this->formatChannels($channels);

        try {
            foreach (array_chunk($channels, 100) as $chunk) {
                $this->sockudo->trigger($chunk, $event, $payload, $parameters);
            }
        } catch (Throwable) {
            // Transport errors may contain signed URLs or response bodies. Do
            // not copy or chain them into Laravel's rendered exception.
            throw new BroadcastException('Sockudo publish failed.');
        }
    }

    public function getSockudo(): SockudoInterface
    {
        return $this->sockudo;
    }

    public function setSockudo(SockudoInterface $sockudo): void
    {
        $this->sockudo = $sockudo;
    }

    /**
     * @return array<string, mixed>|mixed
     */
    private function decodeResponse($request, string $response): mixed
    {
        try {
            $decoded = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new BroadcastException('Sockudo returned an invalid authentication response.', previous: $error);
        }

        if (!$request->input('callback', false) || !$this->allowJsonp) {
            return $decoded;
        }

        return response()->json($decoded)->withCallback($request->callback);
    }
}
