<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companySetting = $request->user()->companySetting;

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
