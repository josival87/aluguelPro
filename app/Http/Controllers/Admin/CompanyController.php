<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function edit()
    {
        return view('admin.company.edit', [
            'company' => Company::query()->firstOrNew(['singleton' => true]),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'cnpj' => ['required', 'string', 'max:18'],
            'phone' => ['required', 'string', 'max:20'], 'email' => ['required', 'email'],
            'pix_key' => ['nullable', 'string', 'max:255'], 'logo' => ['nullable', 'image', 'max:4096'],
        ]);
        $company = Company::query()->firstOrNew(['singleton' => true]);
        $created = ! $company->exists;
        $company->fill($data);
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $company->logo_base64 = 'data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath()));
        }
        $company->save();

        return back()->with(
            'success',
            $created ? 'Dados da empresa salvos.' : 'Dados da empresa atualizados.',
        );
    }
}
