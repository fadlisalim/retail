<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(): View
    {
        return view('account.quotations', [
            'quotations' => auth()->user()->quotations()->with('items')->latest()->paginate(10),
        ]);
    }
}
