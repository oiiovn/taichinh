<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }

        $products = FoodProduct::query()
            ->with('recipeTemplate:id,name')
            ->where('user_id', $user->id)
            ->orderBy('ten_hang')
            ->orderBy('ma_hang')
            ->get();

        return view('pages.food.mon', [
            'title' => 'Món',
            'products' => $products,
        ]);
    }
}
