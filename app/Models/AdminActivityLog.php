<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AdminActivityLog extends Model {
 protected $fillable=['admin_id','action','module','description','method','path','ip_address','meta'];
 protected $casts=['meta'=>'array'];
 public function admin(): BelongsTo { return $this->belongsTo(User::class,'admin_id'); }
}
