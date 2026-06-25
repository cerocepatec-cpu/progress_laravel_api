<?php

namespace App\Http\Controllers\Api\V1;

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

    public function productCategories()
    {
        return $this->ok(DB::table('categoriesproducts')->orderBy('id')->get());
    }

    public function uoms()
    {
        return $this->ok(DB::table('uoms')->orderBy('id')->get());
    }

    public function products()
    {
        return $this->ok(
            DB::table('products as p')
                ->leftJoin('categoriesproducts as c', 'p.category_id', '=', 'c.id')
                ->leftJoin('uoms as u', 'p.uom_id', '=', 'u.id')
                ->where('p.status', '<>', 'deleted')
                ->select('p.*', 'c.name as category_name', 'u.name as uom_name')
                ->orderBy('p.id')
                ->get()
        );
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

            return $this->ok(['id' => (int) $data['id']], 'Categorie produit mise a jour.');
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

        return $this->ok(['id' => $id], 'Categorie produit creee.', 201);
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
            DB::table('uoms')->where('id', (int) $data['id'])->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'updatedAt' => now(),
            ]);

            return $this->ok(['id' => (int) $data['id']], 'Unite mise a jour.');
        }

        $id = (int) ((DB::table('uoms')->max('id') ?? 0) + 1);
        DB::table('uoms')->insert([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'createdAt' => now(),
            'updatedAt' => now(),
            'added_by' => auth()->user()?->member_code,
        ]);

        return $this->ok(['id' => $id], 'Unite creee.', 201);
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

        $id = (int) ((DB::table('products')->max('id') ?? 0) + 1);
        DB::table('products')->insert([
            'id' => $id,
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
