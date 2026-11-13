<?php
namespace App\Http\Middleware;
use App\Models\InstallationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
final class AdminAccess {
 public function handle(Request $request,Closure $next): Response {
  $user=$request->user();$ownerId=InstallationSettings::query()->whereKey(1)->value('owner_id');
  $owner=$user&&$user->isOwner()&&$ownerId&&(int)$user->id===(int)$ownerId;
  $employee=$user&&$user->isEmployee()&&$user->is_active;
  abort_unless($owner||$employee,403);return $next($request);
 }
}