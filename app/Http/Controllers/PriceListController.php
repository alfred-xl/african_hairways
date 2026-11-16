<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceListController extends Controller
{
    /**
     * Show every active service and its price, grouped by category.
     */
    public function __invoke(): View
    {
        $categories = ServiceCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with(['services' => fn (HasMany $query) => $query->active()->ordered()])
            ->get()
            ->filter(fn (ServiceCategory $category): bool => $category->services->isNotEmpty());

        return view('pages.prices', ['categories' => $categories]);
    }
}
