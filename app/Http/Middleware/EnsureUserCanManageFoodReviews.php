<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageFoodReviews
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || (! $user->is_admin && ! $user->canManageFoodReviews())) {
            abort(403, 'Bạn không có quyền truy cập quản lý đánh giá.');
        }

        $path = $request->path();
        if (! $user->canAccessFoodReviewsPath($path, $request->method()) && ! $user->canAccessFoodReviewsSubpages()) {
            return redirect()->route('food.reviews.index');
        }

        return $next($request);
    }
}

