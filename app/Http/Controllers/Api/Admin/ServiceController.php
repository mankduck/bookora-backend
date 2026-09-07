<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Service\StoreServiceRequest;
use App\Http\Requests\Admin\Service\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Service::query()
            ->with([
                'category:id,name',
                'variants',
            ])
            ->withCount('variants');

        if ($request->filled('search')) {
            $search = $request
                ->string('search')
                ->toString();

            $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where(
                'category_id',
                $request->integer('category_id')
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        $services = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(
                min(
                    (int) $request->input(
                        'per_page',
                        15
                    ),
                    100
                )
            );

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    public function store(
        StoreServiceRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $data['slug'] = $this->makeUniqueSlug(
            $data['slug'] ?? $data['name']
        );

        $data['sort_order'] =
            $data['sort_order'] ?? 0;

        $data['is_featured'] =
            $data['is_featured'] ?? false;

        $service = Service::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tạo dịch vụ thành công.',
            'data' => [
                'service' => $service->load([
                    'category:id,name',
                    'variants',
                ]),
            ],
        ], 201);
    }

    public function show(
        Service $service
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => [
                'service' => $service->load([
                    'category:id,name',
                    'variants',
                ]),
            ],
        ]);
    }

    public function update(
        UpdateServiceRequest $request,
        Service $service
    ): JsonResponse {
        $data = $request->validated();

        $data['slug'] = $this->makeUniqueSlug(
            $data['slug'] ?? $data['name'],
            $service->id
        );

        $data['sort_order'] =
            $data['sort_order'] ?? 0;

        $data['is_featured'] =
            $data['is_featured'] ?? false;

        $service->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật dịch vụ thành công.',
            'data' => [
                'service' => $service
                    ->fresh()
                    ->load([
                        'category:id,name',
                        'variants',
                    ]),
            ],
        ]);
    }

    public function destroy(
        Service $service
    ): JsonResponse {
        if ($service->variants()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Dịch vụ đang có gói dịch vụ. Hãy xóa các gói trước.',
            ], 422);
        }

        if ($service->staff()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Dịch vụ đang được gán cho nhân viên.',
            ], 422);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa dịch vụ thành công.',
        ]);
    }

    private function makeUniqueSlug(
        string $value,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($value);

        if ($baseSlug === '') {
            $baseSlug = 'service';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Service::query()
                ->when(
                    $ignoreId,
                    fn ($query) =>
                        $query->where(
                            'id',
                            '!=',
                            $ignoreId
                        )
                )
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}