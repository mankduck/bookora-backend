<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->where('status', 'published')
            ->where(fn($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->with('author:id,name')
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(min(max((int)$request->integer('per_page', 9), 1), 30));
        return response()->json(['success' => true, 'data' => $posts]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::query()->where('slug', $slug)->where('status', 'published')
            ->where(fn($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->with('author:id,name')->firstOrFail();
        return response()->json(['success' => true, 'data' => ['post' => $post]]);
    }
}
