<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CategoryProduct;
use App\Models\City;
use App\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends ApiController
{
    public function memberCategories()
    {
        return $this->ok(DB::table('categories')->orderBy('categorie_id')->get());
    }

    public function storeMemberCategory(Request $request)
    {
        $data = $request->validate([
            'categorie_name' => ['required', 'string'],
        ]);

        $id = (int) ((DB::table('categories')->max('categorie_id') ?? 0) + 1);

        DB::table('categories')->insert([
            'categorie_id' => $id,
            'categorie_name' => $data['categorie_name'],
        ]);

        return $this->ok(['categorie_id' => $id], 'Categorie membre creee.', 201);
    }

    public function updateMemberCategory(Request $request, int $category)
    {
        $data = $request->validate([
            'categorie_name' => ['required', 'string'],
        ]);

        DB::table('categories')->where('categorie_id', $category)->update($data);

        return $this->ok(null, 'Categorie membre mise a jour.');
    }

    public function deleteMemberCategory(int $category)
    {
        DB::table('categories')->where('categorie_id', $category)->delete();

        return $this->ok(null, 'Categorie membre supprimee.');
    }

    public function countries()
    {
        return $this->ok(DB::table('countries')->where('status', 'available')->orderBy('name')->get());
    }

    public function cities(Request $request)
    {
        $query = DB::table('cities')->orderBy('name');

        if ($request->filled('country_id')) {
            $query->where('country_id', (int) $request->country_id);
        }

        return $this->ok($query->get());
    }

    public function storeCity(Request $request)
{
    $data = $request->validate([
        'country_id' => ['required', 'integer', 'exists:countries,id'],
        'name' => ['required', 'string', 'max:255'],
    ]);

    $exists = City::query()
        ->where('country_id', $data['country_id'])
        ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])
        ->exists();

    if ($exists) {
        return response()->json([
            'message' => 'Cette ville existe déjà pour ce pays.',
        ], 422);
    }

    $city = City::create([
        'country_id' => $data['country_id'],
        'name' => trim($data['name']),
    ]);

    return response()->json([
        'message' => 'Ville ajoutée avec succès.',
        'data' => $city,
    ], 201);
}

    public function productCategories()
    {
        return $this->ok(DB::table('categoriesproducts')->orderBy('id')->get());
    }

    public function uoms()
    {
        return $this->ok(DB::table('uoms')->orderBy('id')->get());
    }


    public function products(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $search = trim((string) $request->input('q', ''));
        $categoryId = $request->input('category_id');
        $uomId = $request->input('uom_id');
        $status = $request->input('status');

        $query = DB::table('products as p')
            ->leftJoin('categoriesproducts as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('uoms as u', 'p.uom_id', '=', 'u.id')
            ->select(
                'p.id',
                'p.name',
                'p.description',
                'p.category_id',
                'p.uom_id',
                'p.created_at',
                'p.updated_at',
                'p.added_by',
                'p.status',
                'c.name as category_name',
                'u.name as uom_name'
            );

        if ($search !== '') {
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);

            $query->where(function ($main) use ($terms): void {
                foreach ($terms as $term) {
                    $main->orWhere(function ($sub) use ($term): void {
                        $sub->where('p.name', 'like', "%{$term}%")
                            ->orWhere('p.description', 'like', "%{$term}%")
                            ->orWhere('c.name', 'like', "%{$term}%")
                            ->orWhere('u.name', 'like', "%{$term}%")
                            ->orWhere('p.status', 'like', "%{$term}%");
                    });
                }
            });
        }

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('p.category_id', (int) $categoryId);
        }

        if ($uomId !== null && $uomId !== '') {
            $query->where('p.uom_id', (int) $uomId);
        }

        if ($status !== null && $status !== '') {
            $query->where('p.status', $status);
        }

        $products = $query
            ->orderByDesc('p.id')
            ->paginate($perPage)
            ->appends($request->query());

        return $this->ok([
            'items' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function storeCountry(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['required', 'string', 'max:5'],
        ]);

        $id = (int) ((DB::table('countries')->max('id') ?? 0) + 1);
        DB::table('countries')->insert([
            'id' => $id,
            'name' => $data['name'],
            'code' => $data['code'],
            'status' => 'available',
        ]);

        return $this->ok(['id' => $id], 'Pays cree.', 201);
    }

    public function updateCountry(Request $request, int $country)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['required', 'string', 'max:5'],
        ]);

        DB::table('countries')->where('id', $country)->update($data);

        return $this->ok(null, 'Pays mis a jour.');
    }

    public function deleteCountry(int $country)
    {
        DB::table('countries')->where('id', $country)->update(['status' => 'deleted']);

        return $this->ok(null, 'Pays retire de la liste active.');
    }

    public function storeProductCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'id' => ['nullable', 'integer'],
        ]);

        if (! empty($data['id'])) {
            DB::table('categoriesproducts')->where('id', (int) $data['id'])->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'updatedAt' => now(),
            ]);

            return $this->ok(CategoryProduct::find($data['id']), 'Categorie produit mise a jour.');
        }

        $id = (int) ((DB::table('categoriesproducts')->max('id') ?? 0) + 1);
        DB::table('categoriesproducts')->insert([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'createdAt' => now(),
            'updatedAt' => now(),
            'added_by' => auth()->user()?->member_id,
        ]);

        return $this->ok(CategoryProduct::find($id), 'Categorie produit creee.', 201);
    }

    public function deleteProductCategory(int $category)
    {
        DB::table('categoriesproducts')->where('id', $category)->delete();

        return $this->ok(null, 'Categorie produit supprimee.');
    }

    public function storeUom(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'id' => ['nullable', 'integer'],
        ]);

        if (! empty($data['id'])) {
            $updated=DB::table('uoms')->where('id', (int) $data['id'])->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'updatedAt' => now(),
            ]);

            return $this->ok(Uom::find($data['id']), 'Unite mise a jour.');
        }

        $id = (int) ((DB::table('uoms')->max('id') ?? 0) + 1);
        $new=DB::table('uoms')->insert([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'createdAt' => now(),
            'updatedAt' => now(),
            'added_by' => auth()->user()?->member_code,
        ]);

        return $this->ok(Uom::find($id), 'Unite creee.', 201);
    }

    public function deleteUom(int $uom)
    {
        DB::table('uoms')->where('id', $uom)->delete();

        return $this->ok(null, 'Unite supprimee.');
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'uom_id' => ['nullable', 'integer'],
        ]);

        $id = DB::table('products')->insertGetId([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'uom_id' => $data['uom_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
            'added_by' => auth()->user()?->member_id,
            'status' => 'available',
        ]);

        return $this->ok(['id' => $id], 'Produit cree.', 201);
    }

    public function updateProduct(Request $request, int $product)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'uom_id' => ['nullable', 'integer'],
        ]);

        DB::table('products')->where('id', $product)->update([
            ...$data,
            'updated_at' => now(),
        ]);

        return $this->ok(null, 'Produit mis a jour.');
    }

    public function deleteProduct(int $product)
    {
        DB::table('products')->where('id', $product)->update(['status' => 'deleted']);

        return $this->ok(null, 'Produit supprime logiquement.');
    }
}
