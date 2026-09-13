<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class AdminActivityController extends Controller {
 public function index(Request $request): JsonResponse {
  $q=AdminActivityLog::query()->with('admin:id,name,email')->latest();
  if($request->filled('module')) $q->where('module',$request->string('module')->toString());
  if($request->filled('search')){$s=$request->string('search')->toString();$q->where(fn($x)=>$x->where('description','like',"%{$s}%")->orWhereHas('admin',fn($u)=>$u->where('name','like',"%{$s}%")));}
  return response()->json(['success'=>true,'data'=>$q->paginate(min((int)$request->input('per_page',30),100))]);
 }
}
