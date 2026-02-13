<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companySetting = CompanySetting::first(); 
        
        if (!$companySetting) {
            return response()->json(['message' => 'Company settings not found'], 404);
        }

        return response()->json([
            'company_name' => $companySetting->company_name,
            'company_email' => $companySetting->company_email,
            'company_phone' => $companySetting->company_phone,
            'company_address' => $companySetting->company_address,
            'logo' => $companySetting->logo,
            'primary_color' => $companySetting->primary_color,
            'secondary_color' => $companySetting->secondary_color,
            'invoice_prefix' => $companySetting->invoice_prefix,
            'invoice_footer' => $companySetting->invoice_footer,
            'tin' => $companySetting->tin,
            'currency' => $companySetting->currency,
            'currency_symbol' => $companySetting->currency_symbol
        ], 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'company_email' => 'nullable|email|max:255',
            'company_phone' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^\+?[0-9\s\-\(\)]{7,20}$/'
            ],
            'company_address' => 'nullable|string',
            'invoice_prefix' => 'required|string|max:50',
            'invoice_footer' => 'nullable|string',
            'tin' => 'nullable|string|max:255',
            'currency' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:5',
            'primary_color' => 'required|string|max:50',
            'secondary_color' => 'required|string|max:50',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'company_name.required' => 'Company name is required.',
            'company_email.email' => 'Company email must be a valid email.',
            'company_phone.regex' => 'Company phone must be a valid local or international phone number.',
            'invoice_prefix.required' => 'Invoice prefix is required.',
            'currency.required' => 'Currency is required.',
            'currency_symbol.required' => 'Currency symbol is required.',
            'logo.image' => 'Logo must be an image.',
            'logo.mimes' => 'Logo must be a jpg, jpeg, or png file.',
            'logo.max' => 'Logo must not exceed 2MB.',
        ]);

        $newLogoPath = null;

        try {
            DB::beginTransaction();

            // get old settings
            $settings = CompanySetting::first();
            $oldLogoPath = $settings ? $settings->logo : null;

            // upload new logo if provided
            if ($request->hasFile('logo')) {

                if (!Storage::disk('public')->exists('public_company_logos')) {
                    Storage::disk('public')->makeDirectory('public_company_logos');
                }

                $newLogoPath = $request->file('logo')->store('public_company_logos', 'public');
            }

            // update or create
            $companySetting = CompanySetting::updateOrCreate(
                [
                    'company_name' => $request->company_name,
                    'company_email' => $request->company_email,
                    'company_phone' => $request->company_phone,
                    'company_address' => $request->company_address,
                    'invoice_prefix' => $request->invoice_prefix,
                    'invoice_footer' => $request->invoice_footer,
                    'tin' => $request->tin,
                    'currency' => $request->currency,
                    'currency_symbol' => $request->currency_symbol,
                    'primary_color' => $request->primary_color,
                    'secondary_color' => $request->secondary_color,
                    'logo' => $newLogoPath ?? $oldLogoPath,
                ]
            );

            DB::commit();

            // delete old logo after successful update
            if ($newLogoPath && $oldLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
                Storage::disk('public')->delete($oldLogoPath);
            }

            return response()->json([
                'message' => 'Company settings updated successfully',
                'settings' => $companySetting
            ], 200);
        } catch (Exception $ex) {

            DB::rollBack();

            // delete new uploaded logo if transaction fails
            if ($newLogoPath && Storage::disk('public')->exists($newLogoPath)) {
                Storage::disk('public')->delete($newLogoPath);
            }

            Log::error($ex->getMessage());

            return response()->json([
                'message' => $ex->getMessage()
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'company_email' => 'required|email|max:255',
            'company_phone' => 'nullable|string|max:20',
            'company_address' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|max:7', // e.g. #ff0000
            'secondary_color' => 'nullable|string|max:7', // e.g. #00ff00
            'invoice_prefix' => 'nullable|string|max:20',
            'invoice_footer' => 'nullable|string|max:1000',
        ], [
            'company_name.required' => 'Company name is required.',
            'company_email.required' => 'Company email is required.',
            'company_email.email' => 'Company email must be a valid email address.',

        ]);

        $companySetting = $request->user()->companySetting;

        if (!$companySetting) {
            // Create new settings if they don't exist
            $companySetting = $request->user()->companySetting()->create($request->all());
        } else {
            // Update existing settings
            $companySetting->update($request->all());
        }

        return response()->json($companySetting);
    }
}
