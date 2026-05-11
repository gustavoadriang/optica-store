<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Facades\Pricing;

class CatalogController extends Controller
{
    /**
     * Serialize a product to a plain array safe for Inertia props.
     *
     * @return array{id: int, name: string, slug: string|null, thumbnail_url: string|null, price_formatted: string|null, base_price_formatted: string|null, discount_percentage: int}
     */
    private function serializeProduct(Product $product): array
    {
        $variant = $product->variants->first();
        $priceFormatted = null;
        $basePriceFormatted = null;
        $discountPercentage = 0;

        if ($variant) {
            $pricing = Pricing::for($variant)->get();

            if ($pricing->matched) {
                $priceFormatted = $pricing->matched->price->formatted();
            }

            if ($pricing->base && $pricing->base->price->value > ($pricing->matched?->price->value ?? 0)) {
                $basePriceFormatted = $pricing->base->price->formatted();
                $discountPercentage = (int) round(
                    (($pricing->base->price->value - $pricing->matched->price->value) / $pricing->base->price->value) * 100,
                );
            }
        }

        $colors = $product->variants
            ->flatMap(fn ($v) => $v->values)
            ->unique('id')
            ->map(fn ($v) => $v->translate('name'))
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $product->id,
            'name' => $product->translateAttribute('name'),
            'slug' => $product->defaultUrl?->slug,
            'thumbnail_url' => $product->thumbnail?->getUrl('medium'),
            'price_formatted' => $priceFormatted,
            'base_price_formatted' => $basePriceFormatted,
            'discount_percentage' => $discountPercentage,
            'in_stock' => $variant ? $variant->canBeFulfilledAtQuantity(1) : false,
            'colors' => $colors,
        ];
    }

    public function __invoke(Request $request): Response
    {
        $query = Product::browsable()
            ->with(['variants.basePrices', 'variants.values', 'defaultUrl', 'thumbnail']);
        $sort = $request->input('sort', 'newest');

        if ($sort === 'price_asc') {
            $query->withMin('variants', 'price')->orderBy('variants_min_price', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->withMin('variants', 'price')->orderBy('variants_min_price', 'desc');
        } else {
            // newest: order by created_at desc
            $query->orderByDesc('created_at');
        }

        $paginator = $query->paginate(12);

        $products = $paginator->getCollection()
            ->map(fn (Product $product) => $this->serializeProduct($product))
            ->values()
            ->all();

        return Inertia::render('Catalog/Index', [
            'products' => $products,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'sort' => $request->input('sort', 'newest'),
            ],
        ]);
    }
}
