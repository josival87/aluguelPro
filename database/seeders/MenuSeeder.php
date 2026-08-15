<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['label'=>'Dashboard','route'=>'admin.dashboard','icon'=>'dashboard','sort_order'=>1],
            ['label'=>'Empresa','route'=>'admin.company.edit','icon'=>'settings','sort_order'=>2],
            ['label'=>'Usuários','route'=>'admin.users.index','icon'=>'user','sort_order'=>3],
            ['label'=>'Grupos','route'=>'admin.groups.index','icon'=>'building','sort_order'=>4],
            ['label'=>'Clientes','route'=>'admin.clients.index','icon'=>'users','sort_order'=>5],
            ['label'=>'Imóveis','route'=>'admin.properties.index','icon'=>'home','sort_order'=>6],
            ['label'=>'Contratos','route'=>'admin.contracts.index','icon'=>'file','sort_order'=>7],
            ['label'=>'Aluguéis','route'=>'admin.leases.index','icon'=>'key','sort_order'=>8],
            ['label'=>'Cobranças','route'=>'admin.charges.index','icon'=>'calendar','sort_order'=>9],
            ['label'=>'Características','route'=>'admin.features.index','icon'=>'tag','sort_order'=>10],
            ['label'=>'Medição de energia','route'=>'admin.solar.create','icon'=>'sun','sort_order'=>11],
        ];
        foreach ($items as $item) Menu::updateOrCreate(['label'=>$item['label']],$item);
    }
}
