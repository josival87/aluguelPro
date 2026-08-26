<?php

namespace Database\Seeders;

use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\CondominiumRule;
use App\Models\Contract;
use App\Models\Feature;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\SolarReading;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Company::firstOrCreate(['singleton'=>true],['cnpj'=>'12.345.678/0001-90','name'=>'AlugaPro Imóveis','phone'=>'(81) 3333-0101','email'=>'contato@alugapro.local','pix_key'=>'financeiro@alugapro.local']);
        $group=PropertyGroup::firstOrCreate(['name'=>'Residencial Piedade'],['responsible_name'=>'Marina Andrade','phone'=>'(81) 99999-1020','pix_key'=>'81999991020']);
        CondominiumRule::firstOrCreate(['group_id'=>$group->id,'title'=>'Silêncio e convivência'],['content'=>'Respeite o horário de silêncio entre 22h e 8h e mantenha as áreas comuns organizadas.']);
        $features=collect(['Varanda','Próximo ao mar','Portaria 24h','Aceita pet'])->map(fn($name)=>Feature::firstOrCreate(['name'=>$name]));
        $residentialContract=Contract::where('title','Contrato residencial')->firstOrFail();
        $available=Property::firstOrCreate(['slug'=>'apartamento-203-piedade'],[
            'group_id'=>$group->id,'contract_id'=>$residentialContract->id,'title'=>'Apartamento 203 · Piedade','description'=>'Apartamento iluminado, ventilado e próximo a serviços essenciais.','type'=>'residential','usable_area'=>64,'bedrooms'=>2,'bathrooms'=>2,'parking_spaces'=>1,'street'=>'Rua das Palmeiras','number'=>'203','neighborhood'=>'Piedade','city'=>'Jaboatão dos Guararapes','state'=>'PE','postal_code'=>'54400-000','rent_amount'=>1850,'status'=>'available','has_solar_energy'=>true,
        ]);
        $available->features()->syncWithoutDetaching($features->pluck('id'));
        if(!$available->media()->exists())$available->media()->create(['mime_type'=>'image/svg+xml','media_base64'=>base64_encode($this->placeholder('Apartamento 203')),'sort_order'=>0]);
        $rented=Property::firstOrCreate(['slug'=>'apartamento-102-piedade'],[
            'group_id'=>$group->id,'contract_id'=>$residentialContract->id,'title'=>'Apartamento 102 · Piedade','description'=>'Unidade residencial com medição individual de energia solar.','type'=>'residential','usable_area'=>58,'bedrooms'=>2,'bathrooms'=>1,'parking_spaces'=>1,'street'=>'Avenida Bernardo Vieira','number'=>'102','neighborhood'=>'Piedade','city'=>'Jaboatão dos Guararapes','state'=>'PE','rent_amount'=>1600,'status'=>'rented','has_solar_energy'=>true,
        ]);
        $rented->features()->syncWithoutDetaching($features->pluck('id'));
        $user=User::firstOrCreate(['login'=>'cliente@alugapro.local'],['name'=>'Ana Souza','email'=>'cliente@alugapro.local','cpf'=>'12345678900','phone'=>'(81) 98888-0102','role'=>'client','active'=>true,'password'=>'Cliente@2026']);
        $client=Client::firstOrCreate(['cpf'=>'12345678900'],['user_id'=>$user->id,'name'=>$user->name,'phone'=>$user->phone,'email'=>$user->email,'family_income'=>6500,'status'=>'active']);
        $lease=Lease::firstOrCreate(['property_id'=>$rented->id,'client_id'=>$client->id],['start_date'=>now()->startOfYear(),'end_date'=>now()->startOfYear()->addYear(),'contract_months'=>12,'due_day'=>10,'rent_amount'=>1600,'status'=>'active','has_solar_energy'=>true,'utility_number'=>'NEO-APT102']);
        $solar=$lease->solarConfig()->firstOrCreate([],['initial_reading'=>400,'price_per_kwh'=>0.95]);
        $rent=Charge::firstOrCreate(['lease_id'=>$lease->id,'generation_key'=>'rent:'.now()->format('Y-m')],['client_id'=>$client->id,'type'=>'rent','reference_month'=>now()->startOfMonth(),'due_date'=>now()->startOfMonth()->day(10),'amount'=>1600,'status'=>now()->day>10?'paid':'open','paid_at'=>now()->day>10?now()->startOfMonth()->day(10):null,'description'=>'Aluguel do mês']);
        $solarCharge=Charge::firstOrCreate(['lease_id'=>$lease->id,'generation_key'=>'solar:'.now()->format('Y-m')],['client_id'=>$client->id,'type'=>'solar','reference_month'=>now()->startOfMonth(),'due_date'=>now()->startOfMonth()->day(10),'amount'=>111.15,'status'=>'open','description'=>'Energia solar: 117 kWh']);
        $photoPath=public_path('images/meter-reference-517.jpeg');
        SolarReading::updateOrCreate(['solar_config_id'=>$solar->id,'reference_month'=>now()->startOfMonth()],['charge_id'=>$solarCharge->id,'previous_reading'=>400,'meter_reading'=>517,'consumption_kwh'=>117,'amount'=>111.15,'photo_base64'=>is_file($photoPath)?base64_encode(file_get_contents($photoPath)):null,'photo_mime_type'=>'image/jpeg','ocr_reading'=>null,'ocr_confidence'=>0,'ocr_status'=>'manual_reference','confirmed_by'=>User::where('role','admin')->value('id')]);
    }

    private function placeholder(string $title): string
    {
        $safe=htmlspecialchars($title,ENT_QUOTES,'UTF-8');
        return '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="700"><defs><linearGradient id="g"><stop stop-color="#0b5cff"/><stop offset="1" stop-color="#071a3a"/></linearGradient></defs><rect width="100%" height="100%" fill="url(#g)"/><path d="M300 520V250l300-170 300 170v270H700V350H500v170z" fill="white" opacity=".9"/><text x="600" y="640" font-size="48" text-anchor="middle" fill="white" font-family="Arial">'.$safe.'</text></svg>';
    }
}
