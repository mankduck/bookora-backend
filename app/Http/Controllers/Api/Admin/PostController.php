<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()->with('author:id,name');
        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(fn($q) => $q->where('title', 'like', "%{$search}%")->orWhere('excerpt', 'like', "%{$search}%"));
        }
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        return response()->json(['success' => true, 'data' => $query->orderByDesc('id')->paginate(15)]);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['post' => $post->load('author:id,name')]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title']);
        if (($data['status'] ?? 'draft') === 'published' && empty($data['published_at'])) $data['published_at'] = now();
        $post = Post::query()->create($data);
        return response()->json(['success' => true, 'message' => 'Đã tạo bài viết.', 'data' => ['post' => $post]], 201);
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        $data = $this->validated($request, $post);
        if (isset($data['slug'])) $data['slug'] = $this->uniqueSlug($data['slug'], $post->id);
        elseif (isset($data['title']) && $data['title'] !== $post->title) $data['slug'] = $this->uniqueSlug($data['title'], $post->id);
        if (($data['status'] ?? $post->status) === 'published' && !$post->published_at && empty($data['published_at'])) $data['published_at'] = now();
        $post->update($data);
        return response()->json(['success' => true, 'message' => 'Đã cập nhật bài viết.', 'data' => ['post' => $post->fresh()]]);
    }

    public function destroy(Post $post): JsonResponse
    {
        $post->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa bài viết.']);
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'featured_image' => ['nullable', 'string', 'max:1500'],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'bai-viet';
        $slug = $base; $i = 2;
        while (Post::query()->where('slug', $slug)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists()) $slug = $base . '-' . $i++;
        return $slug;
    }
}
