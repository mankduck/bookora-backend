<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceCategory\StoreServiceCategoryRequest;
use App\Http\Requests\Admin\ServiceCategory\UpdateServiceCategoryRequest;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ServiceCategory::query()
            ->withCount('services')
            ->with([
                'parent:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        $categories = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(
                perPage: min(
                    (int) $request->input('per_page', 15),
                    100
                )
            );

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function store(
        StoreServiceCategoryRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $data['slug'] = $this->makeUniqueSlug(
            $data['slug'] ?? $data['name']
        );

        $data['sort_order'] = $data['sort_order'] ?? 0;

        $category = ServiceCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tạo danh mục thành công.',
            'data' => [
                'category' => $category->load('parent:id,name'),
            ],
        ], 201);
    }

    public function show(
        ServiceCategory $serviceCategory
    ): JsonResponse {
        $serviceCategory->load([
            'parent:id,name',
            'children:id,parent_id,name,slug,status',
        ]);

        $serviceCategory->loadCount('services');

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $serviceCategory,
            ],
        ]);
    }

    public function update(
        UpdateServiceCategoryRequest $request,
        ServiceCategory $serviceCategory
    ): JsonResponse {
        $data = $request->validated();

        if (
            isset($data['parent_id']) &&
            $this->wouldCreateCycle(
                $serviceCategory,
                $data['parent_id']
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể chọn danh mục con làm danh mục cha.',
            ], 422);
        }

        $slugSource = $data['slug'] ?? $data['name'];

        $data['slug'] = $this->makeUniqueSlug(
            $slugSource,
            $serviceCategory->id
        );

        $data['sort_order'] = $data['sort_order'] ?? 0;

        $serviceCategory->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật danh mục thành công.',
            'data' => [
                'category' => $serviceCategory
                    ->fresh()
                    ->load('parent:id,name'),
            ],
        ]);
    }

    public function destroy(
        ServiceCategory $serviceCategory
    ): JsonResponse {
        if ($serviceCategory->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Danh mục đang có danh mục con nên chưa thể xóa.',
            ], 422);
        }

        if ($serviceCategory->services()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Danh mục đang chứa dịch vụ nên chưa thể xóa.',
            ], 422);
        }

        $serviceCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa danh mục thành công.',
        ]);
    }

    private function makeUniqueSlug(
        string $value,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($value);

        if ($baseSlug === '') {
            $baseSlug = 'category';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            ServiceCategory::query()
                ->when(
                    $ignoreId,
                    fn ($query) =>
                        $query->where('id', '!=', $ignoreId)
                )
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function wouldCreateCycle(
        ServiceCategory $category,
        int $parentId
    ): bool {
        $parent = ServiceCategory::find($parentId);

        while ($parent) {
            if ($parent->id === $category->id) {
                return true;
            }

            $parent = $parent->parent;
        }

        return false;
    }
}