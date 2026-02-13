<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function stats()
    {
        try {
            // Total unique clients
            $totalClients = Customer::count();

            // Clients created this month
            $clientsThisMonth = Customer::whereYear("created_at", now()->year)
                ->whereMonth("created_at", now()->month)
                ->count();

            // Top client (most invoices)
            $topClient = Customer::withCount("invoices")
                ->orderByDesc("invoices_count")
                ->first();


            $totalInvoices = Invoice::count();

            $paidInvoices = Invoice::where("status", "paid")
                ->count();

            $pendingInvoices = Invoice::where("status", "pending")
                ->count();

            $overdueInvoices = Invoice::where("status", "overdue")
                ->count();

            $totalRevenue = Invoice::sum("total");

            $paidRevenue = Invoice::where("status", "paid")
                ->sum("total");

            $pendingRevenue = Invoice::where("status", "pending")
                ->sum("total");

            $overdueRevenue = Invoice::where("status", "overdue")
                ->sum("total");

            return response()->json([
                "total_revenue" => $totalRevenue,
                "paid_revenue" => $paidRevenue,
                "pending_revenue" => $pendingRevenue,
                "overdue_revenue" => $overdueRevenue,
                "total_invoices" => $totalInvoices,
                "overdue_invoices" => $overdueInvoices,
                "paid_invoices" => $paidInvoices,
                "pending_invoices" => $pendingInvoices,
                "total_clients" => $totalClients,
                "clients_this_month" => $clientsThisMonth,
                "top_client" => $topClient ? [
                    "id" => $topClient->id,
                    "name" => $topClient->name,
                    "invoices_count" => $topClient->invoices_count
                ] : null
            ], 200);
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json([
                "message" => "Failed to fetch clients stats",
            ], 500);
        }
    }

    public function recentInvoices()
    {
        try {
            $invoices = Invoice::with('customer')
                ->latest()
                ->take(5)
                ->get();

            return response()->json(["invoices" => $invoices], 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }
}
