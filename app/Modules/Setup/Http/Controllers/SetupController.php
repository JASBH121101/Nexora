<?php
namespace App\Modules\Setup\Http\Controllers;
use App\Core\Installation\InstallationState;
use App\Core\Installation\ServerRequirements;
use App\Models\BusinessContact;
use App\Models\BusinessCurrency;
use App\Models\InstallationSettings;
use App\Models\User;
use App\Modules\Setup\Services\TemplateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;
final class SetupController {
 private const CURRENCIES=['USD','NIO','EUR','GTQ','HNL','CRC','PAB','COP','MXN','CAD','GBP'];
 public function __invoke(InstallationState $installation,ServerRequirements $requirements,TemplateCatalog $templates): View|RedirectResponse {
  if($installation->isCompleted())return redirect()->route(Auth::check()?'admin.home':'admin.login');
  $checks=$requirements->checks();return view('setup.index',['checks'=>$checks,'requirementsPassed'=>collect($checks)->every(static fn(array $check):bool=>$check['passed']),'schemaReady'=>$installation->hasSchema(),'templates'=>$templates->all(),'currencies'=>self::CURRENCIES]);
 }
 public function store(Request $request,InstallationState $installation,ServerRequirements $requirements,TemplateCatalog $templates): RedirectResponse {
  if($installation->isCompleted())return redirect()->route('admin.login');
  $checks=$requirements->checks();
  if(!$installation->hasSchema()||!collect($checks)->every(static fn(array $check):bool=>$check['passed']))throw ValidationException::withMessages(['setup'=>'El entorno todavía no cumple todos los requisitos de instalación.']);
  $phones=array_values(array_filter((array)$request->input('phones',[]),static fn($phone)=>is_array($phone)&&trim((string)($phone['number']??''))!==''));$request->merge(['phones'=>$phones]);
  $v=$request->validate([
   'business_name'=>['required','string','max:120'],'business_type'=>['required','string',Rule::in(array_keys($templates->all()))],
   'owner_name'=>['required','string','max:100'],'owner_username'=>['required','string','min:3','max:32','regex:/^[A-Za-z0-9._-]+$/','unique:users,username'],
   'owner_email'=>['required','email','max:255','unique:users,email'],'owner_password'=>['required','string','min:12','confirmed'],
   'description'=>['nullable','string','max:700'],'primary_color'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],
   'primary_currency'=>['required','string',Rule::in(self::CURRENCIES)],'additional_currencies'=>['sometimes','array','max:10'],
   'additional_currencies.*'=>['string','distinct',Rule::in(self::CURRENCIES)],'phones'=>['sometimes','array','max:5'],
   'phones.*.number'=>['required','string','max:20','regex:/^[0-9+()\-\s]{7,20}$/'],'phones.*.show_public'=>['nullable','boolean'],
   'primary_phone'=>['nullable','integer','min:0','max:4'],'photos_enabled'=>['sometimes','boolean'],
   'logo'=>['nullable','prohibited_if:photos_enabled,0','image','mimes:jpg,jpeg,png,webp','max:2048','dimensions:max_width=3000,max_height=3000'],
   'cover'=>['nullable','prohibited_if:photos_enabled,0','image','mimes:jpg,jpeg,png,webp','max:5120','dimensions:max_width=5000,max_height=3000'],
  ]);
  $currencies=array_values(array_unique(array_merge([$v['primary_currency']],array_filter($v['additional_currencies']??[],static fn(string $c):bool=>$c!==$v['primary_currency']))));
  $photos=(bool)($v['photos_enabled']??true);$stored=[];
  try {
   $owner=DB::transaction(function()use($request,$v,$currencies,$photos,$templates,&$stored):User{
    if(User::query()->exists()||InstallationSettings::query()->exists())throw ValidationException::withMessages(['setup'=>'Esta instalación ya tiene un propietario o un proceso iniciado.']);
    $owner=User::query()->create(['name'=>$v['owner_name'],'username'=>strtolower(trim($v['owner_username'])),'email'=>strtolower(trim($v['owner_email'])),'password'=>$v['owner_password'],'role'=>'owner','is_active'=>true,'must_change_password'=>false]);
    $logo=null;$cover=null;if($photos&&$request->hasFile('logo')){$logo=$request->file('logo')->store('identity','public');$stored[]=$logo;}if($photos&&$request->hasFile('cover')){$cover=$request->file('cover')->store('identity','public');$stored[]=$cover;}
    InstallationSettings::query()->create(['id'=>1,'business_name'=>$v['business_name'],'business_type'=>$v['business_type'],'owner_id'=>$owner->id,'configuration'=>['identity'=>['description'=>$v['description']??'','primaryColor'=>$v['primary_color'],'logoPath'=>$logo,'coverPath'=>$cover,'photosEnabled'=>$photos],'pricing'=>['primaryCurrency'=>$v['primary_currency'],'enabledCurrencies'=>$currencies],'template'=>$v['business_type'],'website'=>['editorMode'=>'basic','draft'=>true]],'setup_completed_at'=>now()]);
    foreach($currencies as $i=>$code)BusinessCurrency::query()->create(['code'=>$code,'is_primary'=>$code===$v['primary_currency'],'is_enabled'=>true,'sort_order'=>$i]);
    $phoneRows=array_values($v['phones']??[]);$main=isset($v['primary_phone'])?(int)$v['primary_phone']:0;
    foreach($phoneRows as $i=>$phone)BusinessContact::query()->create(['phone'=>trim($phone['number']),'is_primary'=>$i===$main,'show_public'=>(bool)($phone['show_public']??false),'sort_order'=>$i]);
    $templates->seed($v['business_type']);return $owner;
   });
  }catch(Throwable $exception){foreach($stored as $path)Storage::disk('public')->delete($path);throw $exception;}
  Auth::login($owner);$request->session()->regenerate();
  $message='Configura SMTP para recibir el enlace de verificación del correo.';
  if(config('mail.default')==='smtp'&&filled(config('mail.mailers.smtp.host'))){try{$owner->sendEmailVerificationNotification();$message='Se envió un enlace de verificación al correo del propietario.';}catch(Throwable){$message='El catálogo se creó, pero falló el envío. Revisa SMTP y solicita un nuevo enlace desde el panel.';}}
  return redirect()->route('admin.home')->with('status','NEXORA quedó configurado y la cuenta propietaria fue creada.')->with('verification_message',$message);
 }
}