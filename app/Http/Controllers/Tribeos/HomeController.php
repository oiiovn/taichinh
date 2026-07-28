<?php

namespace App\Http\Controllers\Tribeos;

use App\Http\Controllers\Controller;
use App\Services\Tribeos\TribeosHomeDataService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(
        private readonly TribeosHomeDataService $homeData
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filter = $request->input('filter', 'all');
        $hashtag = $request->input('tag');
        $search = $request->input('q');

        $data = $this->homeData->forUser($user, $filter, $hashtag, $search);

        if ($request->ajax() && $request->get('partial') === 'feed') {
            return response()->view('pages.tribeos.partials.home-feed', array_merge($data, [
                'currentFilter' => $filter,
                'currentTag' => $hashtag,
                'currentSearch' => $search,
            ]));
        }

        return view('pages.tribeos.index', array_merge($data, [
            'title' => 'Trang chủ',
            'currentFilter' => $filter,
            'currentTag' => $hashtag,
            'currentSearch' => $search,
        ]));
    }
}
