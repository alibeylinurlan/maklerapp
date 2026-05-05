<?php

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/listings/recent', function (Request $request) {
    if ($request->header('X-Api-Key') !== config('services.teleskop.api_key')) {
        abort(401);
    }

    $query = Property::where('is_owner', true)->orderByDesc('bumped_at');

    if ($since = $request->query('since')) {
        $query->where('bumped_at', '>', $since);
    } else {
        $query->limit(1);
    }

    $items = $query->get([
        'bina_id', 'category_id', 'title', 'path',
        'price', 'currency', 'rooms', 'area',
        'floor', 'floor_total', 'location_id', 'location_full_name',
        'photos', 'bumped_at', 'is_leased',
        'has_mortgage', 'has_bill_of_sale',
    ]);

    return response()->json($items);
});
