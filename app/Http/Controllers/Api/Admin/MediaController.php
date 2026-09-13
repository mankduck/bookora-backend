<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class MediaController extends Controller {
 public function image(Request $request): JsonResponse {$d=$request->validate(['image'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120']]);$path=$d['image']->store('media','public');return response()->json(['success'=>true,'data'=>['url'=>url(Storage::url($path))]],201);}
}
