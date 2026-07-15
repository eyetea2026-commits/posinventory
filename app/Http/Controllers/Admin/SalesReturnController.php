<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! auth()->user() || ! auth()->user()->isAdmin()) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $returnType = $request->query('return_type');

        $statusCounts = [
            'pending' => SalesReturn::where('Status', SalesReturn::STATUS_PENDING)->count(),
            'approved' => SalesReturn::where('Status', SalesReturn::STATUS_APPROVED)->count(),
            'declined' => SalesReturn::where('Status', SalesReturn::STATUS_DECLINED)->count(),
            'processed' => SalesReturn::where('Status', SalesReturn::STATUS_PROCESSED)->count(),
        ];

        $returns = SalesReturn::with([
                'transaction.billing.payment',
                'transaction.staff.user',
                'product.category',
                'staff.user',
                'approvedByUser',
                'processedByUser',
                'replacement.product',
            ])
            ->when($search, function ($query, $search) {
                $query->where('Status', 'like', "%{$search}%")
                    ->orWhere('Reason', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($product) use ($search) {
                        $product->where('ProductName', 'like', "%{$search}%");
                    });
            })
            ->when($status, function ($query, $status) {
                $query->where('Status', $status);
            })
            ->when($returnType, function ($query, $returnType) {
                $query->where('ReturnType', $returnType);
            })
            ->orderByDesc('ReturnDate')
            ->paginate(15)
            ->withQueryString();

        return view('admin.sales-returns.index', [
            'returns' => $returns,
            'search' => $search,
            'status' => $status,
            'returnType' => $returnType,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function show(SalesReturn $salesReturn)
    {
        $salesReturn->load([
            'transaction.billing.payment',
            'transaction.staff.user',
            'product.category',
            'staff.user',
            'approvedByUser',
            'processedByUser',
            'replacement.product',
        ]);

        $transaction = $salesReturn->transaction;
        $receiptNumber = $transaction ? 'RCT-' . str_pad($transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT) : null;

        return response()->json([
            'transaction' => [
                'SalesTransactionID' => $transaction?->SalesTransactionID,
                'ReceiptNumber' => $receiptNumber,
                'InvoiceNumber' => $receiptNumber,
                'TransactionDate' => optional($transaction?->SalesTransactionDate)->format('Y-m-d H:i'),
                'CustomerName' => $salesReturn->CustomerName ?? $transaction?->CustomerName,
                'OriginalCashier' => $transaction?->staff?->user?->name,
                'PaymentMethod' => $transaction?->billing?->payment?->PaymentMethod,
            ],
            'product' => [
                'ProductName' => $salesReturn->product?->ProductName,
                'Barcode' => $salesReturn->product?->Barcode,
                'SKU' => $salesReturn->product?->SKU,
                'Category' => $salesReturn->product?->category?->CategoryName,
                'SellingPrice' => $salesReturn->product?->Price,
            ],
            'return' => [
                'SalesReturnID' => $salesReturn->SalesReturnID,
                'Quantity' => $salesReturn->Quantity,
                'ReturnType' => $salesReturn->ReturnType,
                'Reason' => $salesReturn->Reason,
                'ReturnDate' => $salesReturn->ReturnDate ? \Carbon\Carbon::parse($salesReturn->ReturnDate)->format('Y-m-d') : null,
                'Status' => $salesReturn->Status,
                'DeclineReason' => $salesReturn->DeclineReason,
                'ApprovedBy' => $salesReturn->approvedByUser?->name,
                'ProcessedBy' => $salesReturn->processedByUser?->name,
                'RefundMethod' => $salesReturn->RefundMethod,
                'RefundAmount' => $salesReturn->RefundAmount,
                'DaysSincePurchase' => $salesReturn->days_since_purchase,
                'ReturnWindowDays' => SalesReturn::RETURN_WINDOW_DAYS,
                'EligibleForReturn' => $salesReturn->is_within_return_window,
                'Replacement' => $salesReturn->replacement ? [
                    'ProductName' => $salesReturn->replacement->product?->ProductName,
                    'Quantity' => $salesReturn->replacement->Quantity,
                    'SlipNumber' => $salesReturn->replacement->SlipNumber,
                ] : null,
            ],
        ]);
    }

    public function create()
    {
        return view('admin.sales-returns.create', [
            'transactions' => SalesTransaction::orderByDesc('SalesTransactionDate')->take(50)->get(),
            'products' => Product::orderBy('ProductName')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'SalesTransactionID' => ['required', 'integer', 'exists:SalesTransaction,SalesTransactionID'],
            'ProductID' => ['required', 'integer', 'exists:Product,ProductID'],
            'Quantity' => ['required', 'integer', 'min:1'],
            'Reason' => ['required', 'string', 'max:255'],
            'ReturnType' => ['nullable', 'in:refund,replacement'],
            'ReturnDate' => ['required', 'date'],
        ]);

        SalesReturn::create([
            'SalesTransactionID' => $data['SalesTransactionID'],
            'ProductID' => $data['ProductID'],
            'Quantity' => $data['Quantity'],
            'Reason' => $data['Reason'],
            'ReturnType' => $data['ReturnType'] ?? SalesReturn::TYPE_REFUND,
            'ReturnDate' => $data['ReturnDate'],
            'Status' => SalesReturn::STATUS_PENDING,
        ]);

        return redirect()->route('admin.sales-returns.index')->with('status', 'Return request submitted successfully.');
    }

    public function approve(SalesReturn $salesReturn)
    {
        if ($salesReturn->Status !== SalesReturn::STATUS_PENDING) {
            return back()->with('status', 'Only pending returns can be approved.');
        }

        DB::transaction(function () use ($salesReturn) {
            $salesReturn->update([
                'Status' => SalesReturn::STATUS_APPROVED,
                'ApprovedBy' => auth()->id(),
            ]);
        });

        ActivityLog::record(
            'return.approved',
            "Approved {$salesReturn->ReturnType} #{$salesReturn->SalesReturnID} for {$salesReturn->Quantity} x \"{$salesReturn->product?->ProductName}\" (Txn #{$salesReturn->SalesTransactionID})"
        );

        return back()->with('status', 'Return approved. The cashier can now process it.');
    }

    public function decline(Request $request, SalesReturn $salesReturn)
    {
        if ($salesReturn->Status !== SalesReturn::STATUS_PENDING) {
            return back()->with('status', 'Only pending returns can be declined.');
        }

        $data = $request->validate([
            'DeclineReason' => ['required', 'string', 'max:255'],
        ]);

        $salesReturn->update([
            'Status' => SalesReturn::STATUS_DECLINED,
            'ApprovedBy' => auth()->id(),
            'DeclineReason' => $data['DeclineReason'],
        ]);

        ActivityLog::record('return.declined', "Declined return #{$salesReturn->SalesReturnID}: {$data['DeclineReason']}");

        return back()->with('status', 'Return request declined.');
    }
}
