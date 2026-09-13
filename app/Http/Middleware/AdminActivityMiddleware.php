<?php
namespace App\Http\Middleware;
use App\Models\AdminActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class AdminActivityMiddleware {
 public function handle(Request $request, Closure $next): Response {
  $response=$next($request);
  if(in_array($request->method(),['POST','PUT','PATCH','DELETE'],true) && $response->getStatusCode()<400 && $request->user()){
   [$module,$description]=$this->describe($request);
   try{AdminActivityLog::create(['admin_id'=>$request->user()->id,'action'=>$this->action($request->method()),'module'=>$module,'description'=>$description,'method'=>$request->method(),'path'=>$request->path(),'ip_address'=>$request->ip(),'meta'=>['route'=>$request->route()?->getName()]]);}catch(\Throwable $e){report($e);}
  }
  return $response;
 }
 private function action(string $method): string{return match($method){'POST'=>'create_or_action','PUT','PATCH'=>'update','DELETE'=>'delete',default=>'action'};}
 private function describe(Request $r): array {
  $p=$r->path();$name=(string)($r->input('name')??$r->input('title')??'');
  if(str_contains($p,'homepage-modules/reorder')) return ['Giao diện','Sắp xếp lại các module trang chủ'];
  if(str_contains($p,'homepage-modules')) return ['Giao diện',($r->isMethod('post')?'Thêm mới':'Cập nhật').' module giao diện'.($name?" {$name}":'')];
  if(str_contains($p,'settings')) return ['Cài đặt','Cập nhật thông tin/cấu hình website'];
  if(str_contains($p,'posts')) return ['Bài viết',($r->isMethod('post')?'Thêm mới':($r->isMethod('delete')?'Xóa':'Cập nhật')).' bài viết'.($name?" {$name}":'')];
  if(str_contains($p,'staff')) return ['Nhân viên',($r->isMethod('post')?'Thêm mới':($r->isMethod('delete')?'Xóa':'Cập nhật')).' nhân viên'.($name?" {$name}":'')];
  if(str_contains($p,'services')) return ['Dịch vụ',($r->isMethod('post')?'Thêm mới':($r->isMethod('delete')?'Xóa':'Cập nhật')).' dịch vụ/gói'.($name?" {$name}":'')];
  if(str_contains($p,'bookings')) { $code=(string)($r->route('booking')?->booking_code ?? $r->route('booking') ?? '');$status=(string)$r->input('status','');return ['Lịch đặt','Cập nhật đơn '.($code?:'booking').($status?" → {$status}":'')]; }
  if(str_contains($p,'coupons')) return ['Mã giảm giá',($r->isMethod('post')?'Thêm mới':($r->isMethod('delete')?'Xóa':'Cập nhật')).' mã giảm giá'.($name?" {$name}":'')];
  return ['Quản trị','Thực hiện thao tác quản trị: '.$p];
 }
}
