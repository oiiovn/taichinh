<div class="space-y-2 bg-gray-50/90 px-2 py-2.5 dark:bg-gray-900/30">
    @foreach($day['items'] as $p)
        @include('pages.food.partials.thong-ke-buff-payment-item', ['p' => $p])
    @endforeach
</div>
