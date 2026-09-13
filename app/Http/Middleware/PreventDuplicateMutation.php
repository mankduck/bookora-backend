<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PreventDuplicateMutation
{
    private const RESULT_TTL_SECONDS = 8;
    private const LOCK_SECONDS = 15;
    private const WAIT_SECONDS = 12;

    public function handle(Request $request, Closure $next): Response
    {
        if (
            $this->isSafeMethod($request)
            || $request->is('api/broadcasting/auth')
            || $request->is('api/v1/auth/*')
        ) {
            return $next($request);
        }

        $fingerprint = $this->fingerprint($request);
        $resultKey = 'bookora:idempotency:result:' . $fingerprint;
        $lockKey = 'bookora:idempotency:lock:' . $fingerprint;

        if ($cached = Cache::get($resultKey)) {
            return $this->restoreResponse($cached);
        }

        $lock = Cache::lock($lockKey, self::LOCK_SECONDS);

        try {
            $lock->block(self::WAIT_SECONDS);

            if ($cached = Cache::get($resultKey)) {
                return $this->restoreResponse($cached);
            }

            /** @var Response $response */
            $response = $next($request);

            if ($response->getStatusCode() < 500) {
                Cache::put(
                    $resultKey,
                    $this->serializeResponse($response),
                    now()->addSeconds(self::RESULT_TTL_SECONDS),
                );
            }

            return $response;
        } catch (LockTimeoutException) {
            return response()->json([
                'success' => false,
                'message' => 'Thao tác này đang được xử lý. Vui lòng chờ hoàn tất.',
            ], 409);
        } finally {
            optional($lock)->release();
        }
    }

    private function isSafeMethod(Request $request): bool
    {
        return in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function fingerprint(Request $request): string
    {
        $identity = $request->user()
            ? 'user:' . $request->user()->getAuthIdentifier()
            : 'guest:' . $request->ip();

        $payload = [
            'identity' => $identity,
            'method' => strtoupper($request->method()),
            'path' => '/' . ltrim($request->path(), '/'),
            'query' => $this->normalize($request->query()),
            'input' => $this->normalize($request->except([])),
            'files' => $this->normalizeFiles($request->allFiles()),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }

    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $normalized[$key] = $this->normalizeFiles($file);
                continue;
            }

            if ($file instanceof UploadedFile) {
                $realPath = $file->getRealPath();
                $normalized[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                    'sha1' => $realPath && is_file($realPath) ? sha1_file($realPath) : null,
                ];
            }
        }

        ksort($normalized);

        return $normalized;
    }

    private function serializeResponse(Response $response): array
    {
        return [
            'content' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
        ];
    }

    private function restoreResponse(array $cached): Response
    {
        return response(
            $cached['content'] ?? '',
            (int) ($cached['status'] ?? 200),
            $cached['headers'] ?? [],
        );
    }
}
